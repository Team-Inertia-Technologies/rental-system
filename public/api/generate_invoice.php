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
$propId = isset($_REQUEST['propId']) ? (int)$_REQUEST['propId'] : 0;

if (!$token) {
    http_response_code(400);
	header('Content-Type: application/json');
    echo json_encode([
        "statusCode" => 400,
        "error" => [
            "message" => "Missing token"
        ]
    ]);
    exit;
}

$userID = DecodeParam($token);

try{

	// Simulate invoice generation logic
	if ($propId <= 0) {
		http_response_code(400);
		header('Content-Type: application/json');
		echo json_encode([
			"statusCode" => 400,
			"error" => [
				"message" => "Invalid property ID"
			]
		]);
		exit;
	}

	$query = "SELECT 
		a.iAgreementID,
		a.dFrom,
		a.dTo,
		a.dAgreementDate,
		a.fAmount,
		p.iPropertyID,
		p.vName AS propertyName,
		t.iTenantID,
		t.vParty AS tenantName,
		t.iStateCode AS tenantStateCode
	FROM agreement a
	JOIN property p ON a.iPropertyID = p.iPropertyID
	JOIN tenant t ON a.iTenantID = t.iTenantID
	WHERE a.iPropertyID = $propId AND a.cStatus = 'A'";

	$res = sql_query($query);
	$data = sql_fetch_assoc($res);
	if (!$data) {
		http_response_code(404);
		header('Content-Type: application/json');
		echo json_encode([
			"statusCode" => 404,
			"error" => [
				"message" => "No active agreement found for the property"
			]
		]);
		exit;
	}
	
	$tenantId = (int)$data['iTenantID'];
	$propertyId = (int)$data['iPropertyID'];
	$tenantStateCode = (int)$data['tenantStateCode'];

	// Fetch user's state code
	$userQuery = "SELECT iStateCode FROM user WHERE iUID = $userID LIMIT 1";
	$userRes = sql_query($userQuery);
	$userData = sql_fetch_assoc($userRes);
	$userStateCode = isset($userData['iStateCode']) ? (int)$userData['iStateCode'] : 0;

	// Calculate GST based on state code matching
	$rentAmount = (float)$data['fAmount'];
	$gstRate = 18; // 18% GST
	$totalGST = round(($rentAmount * $gstRate) / 100, 2);
	
	$gstBreakdown = [];
	
	if ($tenantStateCode === $userStateCode && $tenantStateCode > 0) {
		// Same state - Split into CGST and SGST (9% each)
		$cgst = round($totalGST / 2, 2);
		$sgst = round($totalGST / 2, 2);
		
		$gstBreakdown = [
			"type" => "intrastate",
			"labels" => ["CGST", "SGST"],
			"cgst" => $cgst,
			"sgst" => $sgst,
			"igst" => 0,
			"totalGst" => $cgst + $sgst
		];
		
	} else {
		// Different states - IGST (18%)
		$gstBreakdown = [
			"type" => "interstate",
			"labels" => ["IGST"],
			"cgst" => 0,
			"sgst" => 0,
			"igst" => $totalGST,
			"totalGst" => $totalGST
		];
		
	}

	$invoiceCheckQuery = "
		SELECT iInvoiceID
		FROM invoice
		WHERE iTenantID = $tenantId
		AND iPropertyID = $propertyId
		LIMIT 1
	";

	$invoiceCheckRes = sql_query($invoiceCheckQuery);
	$invoiceExists = (sql_num_rows($invoiceCheckRes) > 0);

	$invoiceNumber = "INV-" . date('Y') . "-" . rand(100000, 999999);
	$invoice = [
		"invoiceNumber" => $invoiceNumber,
		"agreementId" => (int)$data['iAgreementID'],
		"date" => $data['dAgreementDate'],
		"rent" => $rentAmount,
	
		"gstType" => $gstBreakdown['type'],
		"gstLabels" => $gstBreakdown['labels'],
	
		"CGST" => $gstBreakdown['cgst'],
		"SGST" => $gstBreakdown['sgst'],
		"IGST" => $gstBreakdown['igst'],
		"totalGst" => $gstBreakdown['totalGst'],
	
		"totalAmount" => round($rentAmount + $gstBreakdown['totalGst'], 2),
	
		"invoiceperiodFrom" => $data['dFrom'],
		"invoiceperiodTo" => $data['dTo'],
		"propId" => $propertyId,
		"propertyName" => db_output2($data['propertyName']),
		"tenantId" => $tenantId,
		"tenantName" => db_output2($data['tenantName']),
		"tenantStateCode" => $tenantStateCode,
		"userStateCode" => $userStateCode,
		"savedraft" => $invoiceExists
	];
	

	http_response_code(200);
	header('Content-Type: application/json');
	echo json_encode([
		"statusCode" => 200,
		"data" => [
			"invoice" => $invoice
		]
	]);

} catch (Exception $e) {

    http_response_code(500);
	header('Content-Type: application/json');
    echo json_encode([
        "error" => [
            "message" => $e->getMessage()
        ],
        "statusCode" => 500
    ]);
    exit;
}
?>