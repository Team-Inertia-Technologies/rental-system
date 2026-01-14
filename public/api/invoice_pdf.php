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

if (!$token) {
    http_response_code(400);
    echo json_encode([
        "statusCode" => 400,
        "error" => ["message" => "Missing token"]
    ]);
    exit;
}

try {

    /* ---------------------------
       SAMPLE DATA (Replace with DB)
    ---------------------------- */
    $invoiceNo   = "INV-2024-881";
    $invoiceDate = date("d/m/Y");

    $billing = [
        "name"   => "Tech Solutions Pvt Ltd",
        "contact"=> "Rajesh Kumar",
        "phone"  => "+91 98765 43210",
        "email"  => "contact@techsolutions.com",
        "gst"    => "GST123456789"
    ];

    $shipping = [
        "name"   => "MG Road Commercial Shop",
        "addr"   => "Mumbai, Maharashtra",
        "area"   => "1200 sq.ft"
    ];

    $amount   = 150000;
    $cgst     = $amount * 0.09;
    $sgst     = $amount * 0.09;
    $total    = $amount + $cgst + $sgst;

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
            <td><div style="width:120px;height:60px;background:#eee;text-align:center;line-height:60px;">LOGO</div></td>
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
                <strong>Reverse Charge:</strong> No<br>
                <strong>State:</strong> Maharashtra
            </td>
        </tr>
    </table>

    <br>

    <table>
        <tr>
            <td class="box" width="50%">
                <strong style="color:#0077c8">Billing Address</strong><br><br>
                <strong>'.$billing['name'].'</strong><br>
                Contact: '.$billing['contact'].'<br>
                '.$billing['phone'].'<br>
                '.$billing['email'].'<br>
                GSTIN: '.$billing['gst'].'
            </td>
            <td class="box" width="50%">
                <strong style="color:#0077c8">Shipping Address</strong><br><br>
                <strong>'.$shipping['name'].'</strong><br>
                '.$shipping['addr'].'<br>
                Carpet Area: '.$shipping['area'].'
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
                <td>Rent for December 2024</td>
                <td>997212</td>
                <td>1</td>
                <td class="right">'.number_format($amount).'</td>
                <td class="right">'.number_format($amount).'</td>
            </tr>
        </tbody>
    </table>

    <br>

    <table>
        <tr>
            <td width="60%">
                <strong>Amount in Words:</strong><br>
                Fifty Nine Thousand Rupees Only
            </td>
            <td width="40%">
                <table>
                    <tr><td>Subtotal</td><td class="right">'.number_format($amount).'</td></tr>
                    <tr><td>CGST @9%</td><td class="right">'.number_format($cgst).'</td></tr>
                    <tr><td>SGST @9%</td><td class="right">'.number_format($sgst).'</td></tr>
                    <tr class="total"><td>Total</td><td class="right">'.number_format($total).'</td></tr>
                </table>
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
        For Rental System<br><br><br>
        Authorized Signatory
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
    $mpdf->Output("Invoice.pdf", "I"); // I = inline, D = download
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "statusCode" => 500,
        "error" => ["message" => $e->getMessage()]
    ]);
    exit;
}