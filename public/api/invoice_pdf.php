<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
$NO_REDIRECT = $NO_PRELOAD = 1;

include "../includes/common_api.php";
require_once "../vendor/autoload.php"; // mPDF autoload

use Mpdf\Mpdf;

$postdata = file_get_contents("php://input");
$request  = json_decode($postdata, true);
$_REQUEST = array_merge($_REQUEST, $request ?? []);

$token = $_REQUEST['token'] ?? '';
$propId = isset($_REQUEST['propId']) ? (int)$_REQUEST['propId'] : 0;

if (!$token) {
    http_response_code(400);
    echo json_encode([
        "statusCode" => 400,
        "error" => ["message" => "Missing token"]
    ]);
    exit;
}

try {

    $q = "
        SELECT
            i.vInvoiceNo,
            i.dInvoiceDate,
            i.fValue,
            i.fCGST,
            i.fSGST,
            i.fIGST,
            i.fTotal,

            t.vParty,
            t.vContactPerson,
            t.vPartyContactNo,
            t.vPartyEmailID,
            t.vGSTNo,

            p.vName AS propertyName,
            p.vAddress,
            p.fCarpetArea,
            
            u.vName AS ownerName,
            u.vAccountName,
            u.vContactNo,
            u.vEmailID,
            u.vAddress as ownerAddress,
            u.vCompanyGST,
            u.iStateCode,
            u.vState,
            u.vBankName,
            u.vAccountNo,
            u.vIFSC,
            u.vBranch,
            u.vCompanyPan

        FROM invoice i
        JOIN tenant t   ON i.iTenantID   = t.iTenantID
        JOIN property p ON i.iPropertyID = p.iPropertyID
        LEFT JOIN user u ON u.iUID = i.iUID
        WHERE i.iPropertyID = $propId
        LIMIT 1
        ";

    $res = sql_query($q);
    $row = sql_fetch_object($res);

    if (!$row) {
        http_response_code(404);
        echo json_encode([
            "statusCode" => 404,
            "error" => ["message" => "Invoice not found"]
        ]);
        exit;
    }

   
    if (!empty($row) && isset($row->vInvoiceNo) && trim($row->vInvoiceNo) !== '') {
        $invoiceNo = $row->vInvoiceNo;
    } else {
        $invoiceNo = "INV-" . date('Y') . "-" . rand(100000, 999999);
    }
    
    $invoiceDate = $date = date("d-m-Y", strtotime($row->dInvoiceDate));
   
    $billing = [
        "name"   => db_output2($row->vParty),
        "contact"=> db_output2($row->vContactPerson),
        "phone"  => $row->vPartyContactNo,
        "email"  => $row->vPartyEmailID,
        "gst"    => $row->vGSTNo
    ];

    
    $bankDetails = [
        "ownerName" => db_output2($row->vAccountName ?? ''),
        "bankName"  => db_output2($row->vBankName ?? ''),
        "accountNo" => $row->vAccountNo ?? '',
        "ifsc"      => $row->vIFSC ?? '',
        "branch"    => db_output2($row->vBranch ?? ''),
        "pan"       => $row->vCompanyPan ?? ''
    ];

    $amount   = (float)$row->fValue;
    $cgst     = (float)$row->fCGST;
    $sgst     = (float)$row->fSGST;
    $igst     = (float)$row->fIGST;
    $total    = (float)$row->fTotal;

    // Determine if intrastate or interstate
    $isIntrastate = ($cgst > 0 && $sgst > 0);
    $isInterstate = ($igst > 0);

    // Convert amount to words (you can use a library or function)
    // For now, using a placeholder - you should implement proper number to words conversion
    function convertNumberToWords($number) {
        $ones = array(
            0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
            6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
            11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
            16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen'
        );
        $tens = array(
            0 => '', 2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
            6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety'
        );
        $hundreds = array('', 'Thousand', 'Lakh', 'Crore');
        
        $number = number_format($number, 2, '.', '');
        list($integer, $decimal) = explode('.', $number);
        $integer = (int)$integer;
        
        if ($integer == 0) return 'Zero Rupees Only';
        
        $words = array();
        $crore = floor($integer / 10000000);
        $integer %= 10000000;
        $lakh = floor($integer / 100000);
        $integer %= 100000;
        $thousand = floor($integer / 1000);
        $integer %= 1000;
        $hundred = floor($integer / 100);
        $integer %= 100;
        
        if ($crore) $words[] = convertNumberToWords($crore) . ' Crore';
        if ($lakh) $words[] = convertNumberToWords($lakh) . ' Lakh';
        if ($thousand) $words[] = convertNumberToWords($thousand) . ' Thousand';
        if ($hundred) $words[] = $ones[$hundred] . ' Hundred';
        
        if ($integer > 0) {
            if ($integer < 20) {
                $words[] = $ones[$integer];
            } else {
                $tensDigit = floor($integer / 10);
                $onesDigit = $integer % 10;
                $words[] = $tens[$tensDigit] . ($onesDigit ? ' ' . $ones[$onesDigit] : '');
            }
        }
        
        return implode(' ', $words) . ' Rupees Only';
    }
    
    $amountInWords = convertNumberToWords($total);

    /* ---------------------------
       HTML INVOICE
    ---------------------------- */
    $html = '
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        .header { width: 100%; }
        .title { text-align: right; font-size: 26px; color: #0077c8; font-weight: bold; }
        .box { border: 1px solid #ccc; padding: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #0077c8; color: #fff; padding: 8px; }
        td { padding: 8px; border-bottom: 1px solid #ccc; }
        .right { text-align: right; }
        .total { font-weight: bold; }
    </style>

    <table class="header">
        <tr>
            <td style="width:120px;">
                <img src="../uploads/ManMyRent.png" alt="Company Logo" style="width:100px; height:auto;">
            </td>
            <td class="title">TAX INVOICE</td>
        </tr>
    </table>

    <br>

    <table>
        <tr>
            <td width="60%"></td>
            <td>
                <strong>Invoice No:</strong> '.$invoiceNo.'<br>
                <strong>Dated:</strong> '.$invoiceDate.'<br>
            </td>
        </tr>
    </table>

    <br>

    <table>
        <tr>
            <td class="box" width="50%">
                <strong style="color:#0077c8">Party</strong><br><br>
                <strong>'.$billing['name'].'</strong><br>
                Contact: '.$billing['contact'].'<br>
                '.$billing['phone'].'<br>
                '.$billing['email'].'<br>
                GSTIN: '.$billing['gst'].'
            </td>
            <td class="box" width="50%">
                <strong style="color:#0077c8">Biller</strong><br><br>
                <strong>'.$bankDetails['ownerName'].'</strong><br>
                '.$bankDetails['ownerAddress'].'<br>
                Tel No: '.$bankDetails['vContactNo'].'<br>
                GSTIN/UIN: '.$bankDetails['vCompanyGST'].'<br>
                State Name : '.$bankDetails['vState'].', Code: '.$bankDetails['iStateCode'].'<br>
                E-Mail: '.$bankDetails['vEmailID'].'
            </td>
        </tr>
    </table>

    <br>

    <table>
        <thead>
            <tr>
                <th>S.No</th>
                <th>Description</th>
                <th>HSN/SAC</th>
                <th>Qty</th>
                <th>Rate</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>Rent for '. date("F Y", strtotime($invoiceDate)) .'</td>
                <td>997212</td>
                <td>1</td>
                <td class="right">'.number_format($amount, 2).'</td>
                <td class="right">'.number_format($amount, 2).'</td>
            </tr>
        </tbody>
    </table>

    <br>

    <table>
        <tr>
            <td width="60%" style="vertical-align: top;">
                <strong>Amount in Words:</strong><br>
                <strong style="color:#0077c8;">INR '.$amountInWords.'</strong>
                <br><br>
                <strong>Remarks:</strong><br>
                Rent for the month of '. date("F Y", strtotime($invoiceDate)) .'
            </td>
            <td width="40%">
                <table>
                    <tr><td>Subtotal</td><td class="right">'.number_format($amount, 2).'</td></tr>';
                    
    if ($isIntrastate) {
        $html .= '
                    <tr><td>CGST @9%</td><td class="right">'.number_format($cgst, 2).'</td></tr>
                    <tr><td>SGST @9%</td><td class="right">'.number_format($sgst, 2).'</td></tr>';
    } elseif ($isInterstate) {
        $html .= '
                    <tr><td>IGST @18%</td><td class="right">'.number_format($igst, 2).'</td></tr>';
    }
    
    $html .= '
                    <tr class="total"><td>Total</td><td class="right">₹ '.number_format($total, 2).'</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <br>

    <table>
        <tr>
            <td width="50%" style="vertical-align: top;">
                <strong>Tax Amount (in words):</strong><br>
                <strong style="color:#0077c8;">INR</strong>
                <br><br>
                <strong>Company\'s Bank Details</strong><br>
                A/c Holder\'s Name: <strong>'.$bankDetails['ownerName'].'</strong><br>
                Bank Name: '.$bankDetails['bankName'].'<br>
                A/c No.: '.$bankDetails['accountNo'].'<br>
                Branch & IFS Code: '.$bankDetails['branch'].' - '.$bankDetails['ifsc'].'
            </td>
            <td width="50%" style="vertical-align: top;">
                <strong>Company\'s PAN:</strong> '.$bankDetails['pan'].'
            </td>
        </tr>
    </table>

    <br>

    <strong>Terms & Conditions:</strong>
    <ol>
        <li>Payment is due within 5 days from the invoice date.</li>
        <li>Please include invoice number in payment reference.</li>
        <li>Late payment may attract penalty charges.</li>
    </ol>

    <br><br>

    <div style="text-align:right">
        <strong>Authorised Signatory</strong>
    </div>
    
    <div style="text-align:center; margin-top:20px; font-size:10px;">
        This is a Computer Generated Invoice
    </div>
    ';

    /* ---------------------------
       GENERATE PDF
    ---------------------------- */
    $mpdf = new Mpdf([
        'format' => 'A4',
        'margin_top' => 10,
        'margin_bottom' => 10
    ]);

    $mpdf->WriteHTML($html);
    $pdfContent = $mpdf->Output('', 'S'); // get PDF as string
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        "statusCode" => 200,
        "message" => "Invoice generated successfully",
        "fileName" => "Invoice_".$invoiceNo.".pdf",
        "pdf" => base64_encode($pdfContent)
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        "statusCode" => 500,
        "error" => ["message" => $e->getMessage()]
    ]);
    exit;
}
?>