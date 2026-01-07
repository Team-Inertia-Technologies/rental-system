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

try {
		$PROPERTY_ARR = [];
		$query = " SELECT 
		p.iPropertyID AS id,
		p.vName AS propertyName,
		c.vName AS category,
		pt.vName AS propertyType,
		p.fCarpetArea AS carpetArea,
		p.fBaseAmount AS monthlyRent,
		p.vPic AS propertyImg,
		t.vParty AS tenantName,
		t.vPic AS tenantImg
	FROM property p
	JOIN category c ON p.iCategoryID = c.iCategoryID
	JOIN property_type pt ON p.iPropertyTypeID = pt.iPropertyTypeID
	LEFT JOIN agreement a ON a.iPropertyID = p.iPropertyID AND a.cStatus = 'A'
	LEFT JOIN tenant t ON t.iTenantID = a.iTenantID AND t.cStatus = 'A'
	ORDER BY p.iPropertyID ASC
	";
	$res = sql_query($query);
	$properties = [];
	while ($data = sql_fetch_assoc($res)) {
		$properties[] = array(
			"propId" => (int)$data['id'],
			"propertyName" => $data['propertyName'],
			"category" => $data['category'],
			"propertyType" => $data['propertyType'],
			"carpetArea" => (float)$data['carpetArea'],
			"monthlyRent" => (float)$data['monthlyRent'],
			"propertyImg" => $data['propertyImg'],
			"tenantName" => $data['tenantName'],
			"tenantImg" => $data['tenantImg'],
		);
	}

	$response = array(
		"data" => array(
			"propertyList" => $properties,
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
?>