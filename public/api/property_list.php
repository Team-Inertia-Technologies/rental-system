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

	// Build property map
	$PROPERTY_ARR = [];
	$query = " SELECT p.iPropertyID AS id, p.vName AS propertyName, c.vName AS category, pt.vName AS propertyType, p.fCarpetArea AS carpetArea, fBaseAmount AS monthlyRent, p.vPic AS propertyImg
		FROM property AS p
		JOIN category AS c ON p.iCategoryID = c.iCategoryID
		JOIN property_type AS pt ON p.iPropertyTypeID = pt.iPropertyTypeID
		ORDER BY p.iPropertyID,
	";
	$res = sql_query($query);
	$properties = [];
	while ($data = sql_fetch_assoc($res)) {
		$properties[] = array(
			"id" => (int)$data['id'],
			"propertyName" => $data['propertyName'],
			"category" => $data['category'],
			"propertyType" => $data['propertyType'],
			"carpetArea" => (float)$data['carpetArea'],
			"monthlyRent" => (float)$data['monthlyRent'],
			"propertyImg" => $data['propertyImg'],
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