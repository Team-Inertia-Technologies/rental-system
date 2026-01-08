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

	$categoryquery = "SELECT iCategoryID, vName FROM category WHERE cStatus='A' ORDER BY vName ASC";
	$categoryres = sql_query($categoryquery);
	$categories = [];
	while ($data = sql_fetch_assoc($categoryres)) {
		$categories[] = array(
			"id" => (int)$data['iCategoryID'],
			"catName" => $data['vName'],
		);
	}

	$tenantquery = "SELECT iTenantID, vParty FROM tenant WHERE cStatus='A' ORDER BY vParty ASC";
	$tenantres = sql_query($tenantquery);
	$tenants = [];
	while ($data = sql_fetch_assoc($tenantres)) {
		$tenants[] = array(
			"id" => (int)$data['iTenantID'],
			"tenantName" => $data['vParty'],
		);
	}

	$response = array(
		"data" => array(
			"categoryArr" => $categories,
			"tenantArr" => $tenants,
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