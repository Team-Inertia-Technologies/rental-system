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
$properties = GetXFromYID("SELECT COUNT(*) AS propertyCount FROM property WHERE iUID = '$userID' AND cStatus = 'A' ");
$tenants = GetXFromYID("SELECT COUNT(*) AS tenantCount FROM tenant WHERE iUID = '$userID' AND cStatus = 'A' ");
$invoices = GetXFromYID("SELECT COUNT(*) AS invoiceCount FROM invoice WHERE iUID = '$userID' AND cStatus = 'A' ");



try {

	$query =  "SELECT vName, vEmailID, vContactNo, 	vAddress, vCompanyName, vCompanyPan, vCompanyGST, vPic FROM user WHERE iUID = '$userID' ";
	$res = sql_query($query);
	$profile = null;
	if ($row = sql_fetch_assoc($res)) {
		$profile = [
			"name"        => db_output2($row['vName'] ?? ''),
			"email"       => $row['vEmailID'] ?? '',
			"mobile"      => $row['vContactNo'] ?? '',
			"address"     => db_output2($row['vAddress'] ?? ''),
			"companyName" => db_output2($row['vCompanyName'] ?? ''),
			"companyPan"  => $row['vCompanyPan'] ?? '',
			"companyGST"  => $row['vCompanyGST'] ?? '',
			"properties"  => $properties,
			"tenants"     => $tenants,
			"invoices"    => $invoices,
			"pic"         => !empty($row['vPic']) ? 'https://ti-stage-projects-minio.krjqe5.easypanel.host/firstbucket/' . $row['vPic'] : ''
		];
	}

	http_response_code(200);
	header('Content-Type: application/json');
	echo json_encode([
		"statusCode" => 200,
		"data" => $profile
	]);

} catch (Exception $e) {
	http_response_code(500);
	echo json_encode([
		"statusCode" => 500,
		"error" => [
			"message" => $e->getMessage()
		]
	]);
	exit;
}