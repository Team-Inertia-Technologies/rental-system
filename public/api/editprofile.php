<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
$NO_REDIRECT = $NO_PRELOAD = 1;
include "../includes/common_api.php";

$S3OBJ = ObjectStorage();

/* ===========================
   Enforce multipart/form-data
=========================== */
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'multipart/form-data') === false) {
    http_response_code(415);
    echo json_encode([
        "statusCode" => 415,
        "message" => "Content-Type must be multipart/form-data"
    ]);
    exit;
}

$token = $_POST['token'] ?? '';
$name  = $_POST['name'] ?? '';
$mobile = $_POST['mobile'] ?? '';
$email = $_POST['email'] ?? '';
$address = $_POST['address'] ?? '';
$companyName = $_POST['companyName'] ?? '';
$companyPan = $_POST['companyPan'] ?? '';
$companyGST = $_POST['companyGST'] ?? '';


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

$picUrl = '';

if (!empty($_FILES['pic']) && $_FILES['pic']['error'] === UPLOAD_ERR_OK) {
	$allowedTypes = ['image/png', 'image/jpeg', 'image/webp'];

    if (!in_array($_FILES['pic']['type'], $allowedTypes)) {
        http_response_code(400);
        echo json_encode([
            "statusCode" => 400,
            "message" => "Invalid image type"
        ]);
        exit;
    }

    if ($_FILES['pic']['size'] > 2 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode([
            "statusCode" => 400,
            "message" => "Image size exceeds 2MB"
        ]);
        exit;
    }

    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['pic']['name']);
    $fileName = time() . '_' . $safeName;

    // Folder path
    $uploadPath = "profile/";

    $uploaded = ObjectStorageUpload(
        $uploadPath,
        $fileName,
        $_FILES['pic']['tmp_name']
    );

    if (!$uploaded) {
        http_response_code(500);
        echo json_encode([
            "statusCode" => 500,
            "message" => "Image upload failed"
        ]);
        exit;
    }

    // Store relative path or full URL (your choice)
    $picUrl = $uploadPath . $fileName;
}

try{

	$updateFields = [];
	if ($name !== '') {
		$updateFields[] = "vName = '" . db_input($name) . "'";
	}
	if ($mobile !== '') {
		$updateFields[] = "vContactNo = '" . db_input($mobile) . "'";
	}
	if ($email !== '') {
		$updateFields[] = "vEmailID = '" . db_input($email) . "'";
	}
	if ($address !== '') {
		$updateFields[] = "vAddress = '" . db_input($address) . "'";
	}
	if ($companyName !== '') {
		$updateFields[] = "vCompanyName = '" . db_input($companyName) . "'";
	}
	if ($companyPan !== '') {
		$updateFields[] = "vCompanyPan = '" . db_input($companyPan) . "'";
	}
	if ($companyGST !== '') {
		$updateFields[] = "vCompanyGST = '" . db_input($companyGST) . "'";
	}
	if ($picUrl !== '') {
		$updateFields[] = "vPic = '" . db_input($picUrl) . "'";
	}

	if (!empty($updateFields)) {
		$updateQuery = "UPDATE user SET " . implode(", ", $updateFields) . " WHERE iUID = '$userID'";
		sql_query($updateQuery);
	}

	http_response_code(200);
	header('Content-Type: application/json');
	echo json_encode([
		"statusCode" => 200,
		"message" => "Profile updated successfully"
	]);

} catch (Exception $e) {
	http_response_code(500);
	header('Content-Type: application/json');
	echo json_encode([
		"statusCode" => 500,
		"error" => [
			"message" => $e->getMessage()
		]
	]);
	exit;
}