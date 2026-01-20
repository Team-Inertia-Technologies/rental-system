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
$mode   = strtoupper(trim($_REQUEST['mode'] ?? ''));
$tenantId = isset($_REQUEST['tenantId']) ? (int)$_REQUEST['tenantId'] : 0;

if (empty($token)) {
    http_response_code(404);
    echo json_encode([
        "statusCode" => 404,
        "message" => "Token missing"
    ]);
    exit;
}

if (!in_array($mode, ['INSERT', 'UPDATE'])) {
    http_response_code(400);
    echo json_encode(["statusCode" => 400, "message" => "Invalid mode"]);
    exit;
}

try {
	if ($mode === 'INSERT') {
		$propertytypequery = "SELECT iPropertyTypeID, vName FROM property_type WHERE cStatus='A' ORDER BY vName ASC";
		$propertytyperes = sql_query($propertytypequery);
		$propertytypes = [];
		while ($data = sql_fetch_assoc($propertytyperes)) {
			$propertytypes[] = array(
				"id" => (int)$data['iPropertyTypeID'],
				"typeName" => $data['vName'],
			);
		}

		$response = array(
			"data" => array(
				"propertyTypeArr" => $propertytypes,
			),
			"statusCode" => 200,
		);
	
		http_response_code(200);
		echo json_encode($response);
		exit;
	} 

	if ($mode === 'UPDATE') {
		$propertytypequery = "SELECT iPropertyTypeID, vName FROM property_type WHERE cStatus='A' ORDER BY vName ASC";
		$propertytyperes = sql_query($propertytypequery);
		$propertytypes = [];
		while ($data = sql_fetch_assoc($propertytyperes)) {
			$propertytypes[] = array(
				"id" => (int)$data['iPropertyTypeID'],
				"typeName" => $data['vName'],
			);
		}

		if($tenantId <= 0){
			http_response_code(400);
			header('Content-Type: application/json');
			echo json_encode(["statusCode" => 400, "message" => "Invalid tenantId"]);
			exit;
		}

		$query = "SELECT t.iTenantID, t.vParty, t.vPartyContactNo, t.vPartyEmailID, t.vGSTNo, t.vPanNo, t.vContactPerson, t.iType,
					pt.iPropertyTypeID, pt.vName as propertyTypeName
					FROM tenant t
					LEFT JOIN property_type pt ON t.iType = pt.iPropertyTypeID
					WHERE t.iTenantID = $tenantId AND t.cStatus='A'";
		$res = sql_query($query);

		$tenantData = sql_fetch_assoc($res);

		$response = array(
			"data" => array(
				"tenantData" => array(
					"tenantId" => (int)$tenantData['iTenantID'],
					"name" => db_output2($tenantData['vParty']),
					"mobile" => $tenantData['vPartyContactNo'],
					"email" => $tenantData['vPartyEmailID'],
					"gstNumber" => $tenantData['vGSTNo'],
					"panNumber" => $tenantData['vPanNo'],
					"contactPerson" => db_output2($tenantData['vContactPerson']),
					"type" => (int)$tenantData['iType'],
					"propertyTypeName" => $tenantData['propertyTypeName'],
				),
				"propertyTypeArr" => $propertytypes,
			),
			"statusCode" => 200,
		);

		http_response_code(200);
		header('Content-Type: application/json');
		echo json_encode($response);
		exit;
	}


} catch (Exception $e) {
	http_response_code(500);
	header('Content-Type: application/json');
	echo json_encode([
		"statusCode" => 500,
		"message" => "An error occurred: " . $e->getMessage()
	]);
	exit;
}