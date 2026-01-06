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
	$query = " SELECT 
		t.iTenantID AS id,
		t.vParty AS tenantName,
		t.vPic AS tenantImg,
		t.vContactPerson,
		t.vContactNo,
		t.vEmailID,
		t.vGSTNo,
		t.vParty
	FROM tenant t
	WHERE t.cStatus = 'A'
	";
	$res = sql_query($query);
	$tenants = [];
	while ($data = sql_fetch_assoc($res)) {
		$tenants[] = array(
			"tenantId" => (int)$data['id'],
			"name" => $data['tenantName'],
			"mobile" => $data['vContactNo'],
			"email" => $data['vEmailID'],
			"gstNumber" => $data['vGSTNo'],
			"tenentImg" => $data['tenantImg'],
			"contactPerson" => $data['vParty'],
			"properties" => [],
		);
	}

	$response = array(
		"data" => array(
			"tenantList" => $tenants,
		),
		"statusCode" => 200,
	);
	http_response_code(200);
	header('Content-Type: application/json');
	echo json_encode($response);
	exit;

} catch (Exception $e) {
	$response = array(
		"error" => array(
			"message" => $e->getMessage(),
		),
		"statusCode" => 500,
	);
	http_response_code(500);
	header('Content-Type: application/json');
	echo json_encode($response);
	exit;
}