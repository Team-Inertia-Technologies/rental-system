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
$name = $_REQUEST['name'] ?? '';
$Address = $_REQUEST['address'] ?? '';
$category = isset($_REQUEST['category']) ? (int)$_REQUEST['category'] : 0;
$type = isset($_REQUEST['type']) ? (int)$_REQUEST['type'] : 0;
$carpetArea = isset($_REQUEST['carpetArea']) ? (float)$_REQUEST['carpetArea'] : 0;
$builtUpArea = isset($_REQUEST['builtUpArea']) ? (float)$_REQUEST['builtUpArea'] : 0;
$tenantId = isset($_REQUEST['tenantId']) ? (int)$_REQUEST['tenantId'] : 0;
$monthlyRent = isset($_REQUEST['monthlyRent']) ? (float)$_REQUEST['monthlyRent'] : 0;
$leaseStart = $_REQUEST['leaseStart'] ?? '';
$leaseEnd = $_REQUEST['leaseEnd'] ?? '';
$agreementdoc = $_REQUEST['agreementdoc'] ?? '';

try{
	$updateQuery = "UPDATE property SET vName='" . db_input($name) . "', vAddress='" . db_input($Address) . "', iCategoryID=$category, iPropertyTypeID=$type, fCarpetArea=$carpetArea, fBuiltArea=$builtUpArea WHERE iPropertyID= $propId";
	sql_query($updateQuery);

	if (!empty($tenantId)) {
		$leaseStartDate = !empty($leaseStart) ? "'" . db_input($leaseStart) . "'" : "NULL";
		$leaseEndDate = !empty($leaseEnd) ? "'" . db_input($leaseEnd) . "'" : "NULL";
		$updateAgreementQuery = "UPDATE agreement SET iTenantID=$tenantId, iPropertyTypeID=$type, fAmount=$monthlyRent, dFrom=$leaseStartDate, dTo=$leaseEndDate, vAgreementDoc='" . db_input($agreementdoc) . "' WHERE iPropertyID= $propId";
		sql_query($updateAgreementQuery);
	}

	$response = array(
		"data" => array(
			"propertyId" => $propId,
			"message" => "Property updated successfully"
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