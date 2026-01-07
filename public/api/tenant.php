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
	$query = "SELECT 
		t.iTenantID AS tenantId,
		t.vParty AS tenantName,
		t.vPic AS tenantImg,
		t.vContactNo,
		t.vEmailID,
		t.vGSTNo,
		t.vPanNo,
		t.vParty,
		a.iAgreementID,
		a.iPropertyID,
		p.vName AS PropertyName
	FROM tenant t
	LEFT JOIN agreement a ON a.iTenantID = t.iTenantID
	LEFT JOIN property p ON p.iPropertyID = a.iPropertyID
	WHERE t.cStatus = 'A'
	";

	$res = sql_query($query);
	$tenants = [];

	while ($row = sql_fetch_assoc($res)) {
		$tenantId = (int)$row['tenantId'];
		if (!isset($tenants[$tenantId])) {
			$tenants[$tenantId] = [
				"tenantId"       => $tenantId,
				"name"           => $row['tenantName'],
				"mobile"         => $row['vContactNo'],
				"email"          => $row['vEmailID'],
				"gstNumber"      => $row['vGSTNo'],
				"panNumer"       => $row['vPanNo'],
				"tenentImg"      => $row['tenantImg'],
				"contactPerson"  => $row['vParty'],
				"properties"     => []
			];
		}

		if (!empty($row['iAgreementID'])) {
			$tenants[$tenantId]['properties'][] = [
				"propertyId"  => (int)$row['iPropertyID'],
				"propertyName"=> $row['PropertyName']
			];
		}
	}

	$tenants = array_values($tenants);
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