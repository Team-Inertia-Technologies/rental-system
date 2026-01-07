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

try{
	$INVOICE_ARR = [];
	$query = " SELECT 
		i.iInvoiceID AS id,
		i.vInvoiceNo AS invoiceNo,
		i.dInvoiceDate AS invoiceDate,
		i.fTotal AS totalAmount,
		i.cStatus AS status,
		p.vName AS propertyName,
		t.vParty AS tenantName
	FROM invoice i
	JOIN property p ON i.iPropertyID = p.iPropertyID
	JOIN tenant t ON i.iTenantID = t.iTenantID
	ORDER BY i.iInvoiceID ASC
	";
	$res = sql_query($query);
	$invoices = [];
	while ($data = sql_fetch_assoc($res)) {
		$invoices[] = array(
			"invoiceId" => (int)$data['id'],
			"invoiceNumber" => $data['invoiceNo'],
			"date" => $data['invoiceDate'],
			"total" => (float)$data['totalAmount'],
			"status" => $data['status'],
			"statusText"=> 'Paid',
			"propertyName" => $data['propertyName'],
			"tenantName" => $data['tenantName'],
		);
	}

	$response = array(
		"data" => array(
			"invoices" => $invoices,
			"filterOptsArr" => [
					["val" => "A", "name" => "All"],
					["val" => "P", "name" => "Paid"],
					["val" => "S", "name" => "Sent"],
					["val" => "D", "name" => "Draft"],
					["val" => "O", "name" => "Overdue"],
			],
		),
		"statusCode" => 200,
	);
	http_response_code(200);
	echo json_encode($response);
	exit;

} catch (Exception $e) {
	$response = array(
		"error" => array(
			"message" => "Internal Server Error",
		),
		"statusCode" => 500,
	);
	http_response_code(500);
	echo json_encode($response);
	exit;
}