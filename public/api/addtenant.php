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
$mode   = strtoupper(trim($_REQUEST['mode'] ?? ''));
$tenantId = isset($_REQUEST['tenantId']) ? (int)$_REQUEST['tenantId'] : 0;
$name = $_REQUEST['name'] ?? '';
$pic = $_REQUEST['pic'] ?? '';
$mobile = $_REQUEST['mobile'] ?? '';
$email = $_REQUEST['email'] ?? '';
$gstNumber = $_REQUEST['gstNumber'] ?? '';
$panNumber = $_REQUEST['panNumber'] ?? '';
$contactPerson = $_REQUEST['contactPerson'] ?? '';
$type = isset($_REQUEST['type']) ? (int)$_REQUEST['type'] : 0;


if (!in_array($mode, ['INSERT', 'UPDATE'])) {
    http_response_code(400);
    echo json_encode(["statusCode" => 400, "message" => "Invalid mode"]);
    exit;
}

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

$userid = DecodeParam($token); 

try {

	if ($mode === 'INSERT') {
		$Id = NextID("iTenantID", "tenant");
		$insertQuery = "INSERT INTO tenant 
			(iTenantID, iUID, vParty, vPartyContactNo, vPartyEmailID, vGSTNo, vPanNo, vContactPerson, iType, cStatus) 
			VALUES (
				$Id,
				$userid,
				'" . db_input($name) . "',
				'" . db_input($mobile) . "',
				'" . db_input($email) . "',
				'" . db_input($gstNumber) . "',
				'" . db_input($panNumber) . "',
				'" . db_input($contactPerson) . "',
				$type,
				'A'
			)";

		sql_query($insertQuery);

		$response = array(
			"data" => array(
				"tenantId" => $Id,
				"message" => "Tenant added successfully"
			),
			"statusCode" => 200
		);

		echo json_encode($response);
		exit;
	}

	if ($mode === 'UPDATE') {
		if ($tenantId <= 0) {
			http_response_code(400);
			echo json_encode(["statusCode" => 400, "message" => "Invalid tenantId for update"]);
			exit;
		}

		$updateQuery = "UPDATE tenant SET 
		vParty = '" . db_input($name) . "',
		vPartyContactNo = '" . db_input($mobile) . "',
		vPartyEmailID = '" . db_input($email) . "',
		vGSTNo = '" . db_input($gstNumber) . "',
		vPanNo = '" . db_input($panNumber) . "',
		vContactPerson = '" . db_input($contactPerson) . "'
		WHERE iTenantID = $tenantId AND cStatus = 'A'";

		sql_query($updateQuery);
		$response = array(
			"data" => array(
				"tenantId" => $tenantId,
				"message" => "Tenant details updated successfully"
			),
			"statusCode" => 200
		);

		echo json_encode($response);
	}
} catch (Exception $e) {
	$response = array(
		"error" => array(
			"message" => $e->getMessage()
		),
		"statusCode" => 500,
	);
	http_response_code(500);
	header('Content-Type: application/json');
	echo json_encode($response);
	exit;
}