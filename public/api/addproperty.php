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

try {
	$iPropertyID = NextID("iPropertyID", "property");
	$insertQuery = "INSERT INTO property (iPropertyID, vName, vAddress iCategoryID, iPropertyTypeID, fCarpetArea, fBuiltArea) VALUES ($iPropertyID, '" . db_input($name) . "', '" . db_input($Address) . "', $category, $type, $carpetArea, $builtUpArea)";
	sql_query($insertQuery);
	if (!empty($tenantId)) {
		$iAgreementID = NextID("iAgreementID", "agreement");
		$now = NOW;
		$leaseStartDate = !empty($leaseStart) ? "'" . db_input($leaseStart) . "'" : "NULL";
		$leaseEndDate = !empty($leaseEnd) ? "'" . db_input($leaseEnd) . "'" : "NULL";
		$insertAgreementQuery = "INSERT INTO agreement (iAgreementID, dtAgreement, dAgreementDate, iPropertyID, iTenantID, iPropertyTypeID, fAmount, dFrom, dTo, vAgreementDoc) VALUES ($iAgreementID, '$now', '$now', $iPropertyID, $tenantId, $type, $monthlyRent, $leaseStartDate, $leaseEndDate, '" . db_input($agreementdoc) . "')";
		sql_query($insertAgreementQuery);
	}
	$response = array(
		"data" => array(
			"propertyId" => $propertyId,
			"message" => "Property added successfully"
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