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
$tenantId = isset($_REQUEST['tenantId']) ? (int)$_REQUEST['tenantId'] : 0;
$name = $_REQUEST['name'] ?? '';
$mobile = $_REQUEST['mobile'] ?? '';
$email = $_REQUEST['email'] ?? '';
$gstNumber = $_REQUEST['gstNumber'] ?? '';
$panNumber = $_REQUEST['panNumber'] ?? '';
$contactPerson = $_REQUEST['contactPerson'] ?? '';
$type = isset($_REQUEST['type']) ? (int)$_REQUEST['type'] : 0;
$agreement = isset($_REQUEST['agreement']) ? (int)$_REQUEST['agreement'] : 0;

try {
	$updateQuery = "UPDATE tenant SET 
		vParty = '" . db_input($name) . "',
		vPartyContactNo = '" . db_input($mobile) . "',
		vPartyEmailID = '" . db_input($email) . "',
		vGSTNo = '" . db_input($gstNumber) . "',
		vPanNo = '" . db_input($panNumber) . "',
		vContactPerson = '" . db_input($contactPerson) . "'
		WHERE iTenantID = $tenantId AND cStatus = 'A'";

	sql_query($updateQuery);
	if ($agreement > 0) {
		$agreementUpdateQuery = "UPDATE agreement SET 
			iTenantID = $tenantId
			WHERE iAgreementID = $agreement";

		sql_query($agreementUpdateQuery);
	}

	$response = array(
		"data" => array(
			"tenantId" => $tenantId,
			"message" => "Tenant details updated successfully"
		),
		"error" => null
	);

	echo json_encode($response);
} catch (Exception $e) {
	$response = array(
		"error" => array(
			"message" => "Internal Server Error",
		),
		"statusCode" => 500,
	);
	http_response_code(500);
	header('Content-Type: application/json');
	echo json_encode($response);
	exit;
}