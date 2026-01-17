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
$invoiceId = isset($_REQUEST['invoiceId']) ? (int)$_REQUEST['invoiceId'] : 0;
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

try {
	$query = " SELECT 
		i.iInvoiceID AS id,
		i.vInvoiceNo AS invoiceNo,
		i.dInvoiceDate AS invoiceDate,
		i.fValue,
		i.fCGST,
		i.fTotal AS totalAmount,
		i.vPayStatus AS status,
		p.vName AS propertyName,
		t.vParty AS tenantName
		t.vPartyContactNo,
		t.vPartyEmailID
		t.vGSTNo
	FROM invoice i
	JOIN property p ON i.iPropertyID = p.iPropertyID
	JOIN tenant t ON i.iTenantID = t.iTenantID
	WHERE i.iInvoiceID = $invoiceId
	";
	$res = sql_query($query);
	if ($data = sql_fetch_assoc($res)) {
		$invoice = array(
			"invoiceId" => (int)$data['id'],
			"invoiceNumber" => $data['invoiceNo'],
			"date" => date('M Y', strtotime($data['invoiceDate'])),
			"amount" => (float)$data['fValue'],
			"cgst" => (float)$data['fCGST'],
			"total" => (float)$data['totalAmount'],
			"status" => $data['status'],
			"statusText" => $data['status'],
			"propertyName" => $data['propertyName'],
			"tenantName" => $data['tenantName'],
			"tenantContactNo" => $data['vPartyContactNo'],
			"tenantEmail" => $data['vPartyEmailID'],
			"tenantGSTNo" => $data['vGSTNo'],
		);
	} else {
		throw new Exception("Invoice not found");
	}

	$response = array(
		"data" => array(
			"invoice" => $invoice,
		),
		"statusCode" => 200,
	);
	http_response_code(200);
	echo json_encode($response);
	exit;
} catch (Exception $e) {
	$response = array(
		"error" => array(
			"message" => $e->getMessage()
		),
		"statusCode" => 500,
	);
	http_response_code(500);
	echo json_encode($response);
	exit;
}
