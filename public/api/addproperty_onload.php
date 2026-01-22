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
$propId = isset($_REQUEST['propId']) ? (int)$_REQUEST['propId'] : 0;

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
$userID = DecodeParam($token);
try{

	if ($mode === 'INSERT') {

		$categoryquery = "SELECT iCategoryID, vName FROM category WHERE cStatus='A' ORDER BY vName ASC";
		$categoryres = sql_query($categoryquery);
		$categories = [];
		while ($data = sql_fetch_assoc($categoryres)) {
			$categories[] = array(
				"id" => (int)$data['iCategoryID'],
				"catName" => $data['vName'],
			);
		}

		$tenantquery = "SELECT iTenantID, vParty FROM tenant WHERE cStatus='A' AND iUID = '$userID' ORDER BY vParty ASC";
		$tenantres = sql_query($tenantquery);
		$tenants = [];
		while ($data = sql_fetch_assoc($tenantres)) {
			$tenants[] = array(
				"id" => (int)$data['iTenantID'],
				"tenantName" => $data['vParty'],
			);
		}

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
				"categoryArr" => $categories,
				"tenantArr" => $tenants,
				"propertyTypeArr" => $propertytypes,
			),
			"statusCode" => 200,
		);
	
		http_response_code(200);
		echo json_encode($response);
		exit;
	}

	if ($mode === 'UPDATE') {

		$categoryquery = "SELECT iCategoryID, vName FROM category WHERE cStatus='A' ORDER BY vName ASC";
		$categoryres = sql_query($categoryquery);
		$categories = [];
		while ($data = sql_fetch_assoc($categoryres)) {
			$categories[] = array(
				"id" => (int)$data['iCategoryID'],
				"catName" => $data['vName'],
			);
		}

		$tenantquery = "SELECT iTenantID, vParty FROM tenant WHERE cStatus='A' AND iUID = '$userID' ORDER BY vParty ASC";
		$tenantres = sql_query($tenantquery);
		$tenants = [];
		while ($data = sql_fetch_assoc($tenantres)) {
			$tenants[] = array(
				"id" => (int)$data['iTenantID'],
				"tenantName" => $data['vParty'],
			);
		}

		$propertytypequery = "SELECT iPropertyTypeID, vName FROM property_type WHERE cStatus='A' ORDER BY vName ASC";
		$propertytyperes = sql_query($propertytypequery);
		$propertytypes = [];
		while ($data = sql_fetch_assoc($propertytyperes)) {
			$propertytypes[] = array(
				"id" => (int)$data['iPropertyTypeID'],
				"typeName" => $data['vName'],
			);
		}

		if ($propId <= 0) {
			http_response_code(400);
			echo json_encode([
				"statusCode" => 400,
				"message" => "Invalid property ID"
			]);
			exit;
		}

		$propertyQuery = " SELECT 
			p.iPropertyID AS id,
			p.vName AS propertyName,
			p.vAddress AS address,
			c.iCategoryID AS categoryId,
			p.fCarpetArea AS carpetArea,
			p.fBuiltArea AS builtUpArea,
			p.fBaseAmount AS baseAmount,
			a.dFrom,
			a.dTo,
			a.fAmount,
			a.vAgreementDoc,
			a.iTenantID,
			t.vParty
		FROM property p
		JOIN category c ON p.iCategoryID = c.iCategoryID
		LEFT JOIN agreement a ON a.iPropertyID = p.iPropertyID
		LEFT JOIN tenant t ON t.iTenantID = a.iTenantID
		WHERE p.iPropertyID = $propId
		";
		$propertyRes = sql_query($propertyQuery);
		$propertyData = sql_fetch_assoc($propertyRes);
		
		$response = array(
			"data" => array(
				"propertyDetails" => array(
					"propId" => (int)$propertyData['id'],
					"name" => db_output2($propertyData['propertyName']),
					"address" => $propertyData['address'],
					"category" => (int)$propertyData['categoryId'],
					"carpetArea" => (float)$propertyData['carpetArea'],
					"builtUpArea" => (float)$propertyData['builtUpArea'],
					"amount" => (float)$propertyData['baseAmount'],
					"tenantId" => (int)$propertyData['iTenantID'],
					"tenantName" => db_output2($propertyData['vParty']),
					"monthlyRent" => (float)$propertyData['fAmount'],
					"leaseStart" => $propertyData['dFrom'],
					"leaseEnd" => $propertyData['dTo'],
					"agreementdoc" => $propertyData['vAgreementDoc'],
				),
				"categoryArr" => $categories,
				"tenantArr" => $tenants,
				"propertyTypeArr" => $propertytypes,
			),
			"statusCode" => 200,
		);

		http_response_code(200);
		echo json_encode($response);
		exit;
	}

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