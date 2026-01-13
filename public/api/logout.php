<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
include "../includes/common_api.php";
$NO_REDIRECT = $NO_PRELOAD = 1;
header ('Content-Type: application/json');
$token = isset($_POST['token']) ? db_input($_POST['token']) : '';
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
$user_id = DecodeParam($token);
sql_query("UPDATE users SET cActive='N' WHERE iUserID=$user_id");

session_destroy();
$response = array('statusCode' => 200, 'message' => "Logged out successfully");
http_response_code(200);
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
