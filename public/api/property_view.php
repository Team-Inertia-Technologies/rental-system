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

try {

	// Fetch property details
	$query = " SELECT 
		p.iPropertyID AS id,
		p.vName AS propertyName,
		c.vName AS category,
		pt.vName AS propertyType,
		p.fCarpetArea AS carpetArea,
		p.fBaseAmount AS monthlyRent,
		p.vPic AS propertyImg,
		t.vParty AS tenantName,
		t.vPic AS tenantImg,
		t.vContactPerson,
		t.vContactNo,
		t.vEmailID,
		a.dFrom,
		a.dTo,
		a.fIncValue,
		a.cIncPeriod
	FROM property p
	JOIN category c ON p.iCategoryID = c.iCategoryID
	JOIN property_type pt ON p.iPropertyTypeID = pt.iPropertyTypeID
	LEFT JOIN agreement a ON a.iPropertyID = p.iPropertyID AND a.cStatus = 'A'
	LEFT JOIN tenant t ON t.iTenantID = a.iTenantID AND t.cStatus = 'A'
	WHERE p.iPropertyID = $propId
	";
	$res = sql_query($query);
	if ($data = sql_fetch_assoc($res)) {
		$propertyDetails = array(
			"propId" => (int)$data['id'],
			"propertyName" => $data['propertyName'],
			"category" => $data['category'],
			"type" => $data['propertyType'],
			"carpetArea" => (float)$data['carpetArea'],
			"tenantName" => $data['tenantName'],
			"tenantImg" => $data['tenantImg'],
			"conPersonName" => $data['vContactPerson'],
			"conPersonMob" => $data['vContactNo'],
			"conPersonEmail" => $data['vEmailID'],
			"LeasePeriod" => date('d/m/Y', strtotime($data['dFrom'])) . " - " . date('d/m/Y', strtotime($data['dTo'])),
			"monthlyRent" => (float)$data['monthlyRent'],
			"propertyImg" => $data['propertyImg'],
			"NextIncrement" => $data['fIncValue'],
			"IncrementFrom" => !empty($data['cIncPeriod']) ? date('M Y', strtotime($data['cIncPeriod'])) : ""		
		);

		$response = array(
			"data" => array(
				"propertyDetails" => $propertyDetails,
			),
			"statusCode" => 200,
		);

		http_response_code(200);
		echo json_encode($response);
		exit;
	} else {
		$response = array(
			"error" => array(
				"message" => "Property not found",
			),
			"statusCode" => 404,
		);
		http_response_code(404);
		echo json_encode($response);
		exit;
	}

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
