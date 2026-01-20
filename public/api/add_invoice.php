<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
$NO_REDIRECT = $NO_PRELOAD = 1;
include "../includes/common_api.php";

header('Content-Type: application/json');

$postdata = file_get_contents("php://input");
$request = json_decode($postdata, true);
$_REQUEST = array_merge($_REQUEST, $request ?? []);

$token = $_REQUEST['token'] ?? '';
$invoiceNumber = $_REQUEST['invoiceNumber'] ?? '';
$agreementId = isset($_REQUEST['agreementId']) ? (int)$_REQUEST['agreementId'] : 0;
$date = $_REQUEST['date'] ?? '';
$rent = isset($_REQUEST['rent']) ? (float)$_REQUEST['rent'] : 0;
$CGST = isset($_REQUEST['CGST']) ? (float)$_REQUEST['CGST'] : 0;
$SGST = isset($_REQUEST['SGST']) ? (float)$_REQUEST['SGST'] : 0;
$IGST = isset($_REQUEST['IGST']) ? (float)$_REQUEST['IGST'] : 0;
$totalAmount = isset($_REQUEST['totalAmount']) ? (float)$_REQUEST['totalAmount'] : 0;
$propId = isset($_REQUEST['propId']) ? (int)$_REQUEST['propId'] : 0;
$propertyName = $_REQUEST['propertyName'] ?? '';
$tenantId = isset($_REQUEST['tenantId']) ? (int)$_REQUEST['tenantId'] : 0;
$tenantName = $_REQUEST['tenantName'] ?? '';

if (!$token) {
    http_response_code(400);
    echo json_encode([
        "statusCode" => 400,
        "error" => [
            "message" => "Missing token"
        ]
    ]);
    exit;
}

$userid = DecodeParam($token);

try {
	$iInvoiceID = NextID("iInvoiceID", "invoice");
	$now = NOW;
	$query = "INSERT INTO invoice (iInvoiceID, dtInvoice, dInvoiceDate, vInvoiceNo, iUID, iPropertyID, iTenantID, iAgreementID, fValue, fCGST, fSGST, fIGST, fTotal, vPayStatus) VALUES 
	($iInvoiceID, '$now', '$date', '$invoiceNumber', '$userid', $propId, $tenantId, $agreementId, $rent, $CGST, $SGST, $IGST, $totalAmount, 'DRAFT')";

	sql_query($query);

	http_response_code(200);
	echo json_encode([
		"data" => [
			"invoiceId" => $iInvoiceID,
			"message" => "Invoice added successfully"
		],
		"statusCode" => 200
	]);
	exit;

}  catch (Exception $e) {

    http_response_code(500);
    echo json_encode([
        "error" => [
            "message" => $e->getMessage()
        ],
        "statusCode" => 500
    ]);
    exit;
}
