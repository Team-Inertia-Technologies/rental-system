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

/* ===========================
   Inputs (FORM-DATA ONLY)
=========================== */
$token         = $_POST['token'] ?? '';
$mode          = strtoupper(trim($_POST['mode'] ?? ''));
$tenantId      = isset($_POST['tenantId']) ? (int)$_POST['tenantId'] : 0;
$name          = $_POST['name'] ?? '';
$mobile        = $_POST['mobile'] ?? '';
$email         = $_POST['email'] ?? '';
$gstNumber     = $_POST['gstNumber'] ?? '';
$statecode    = $_POST['stateCode'] ?? '';
$panNumber     = $_POST['panNumber'] ?? '';
$contactPerson = $_POST['contactPerson'] ?? '';
$type          = isset($_POST['type']) ? (int)$_POST['type'] : 0;

/* ===========================
   Validation
=========================== */
if (!in_array($mode, ['INSERT', 'UPDATE'])) {
    http_response_code(400);
    echo json_encode([
        "statusCode" => 400,
        "message" => "Invalid mode"
    ]);
    exit;
}

if (!$token) {
    http_response_code(400);
    echo json_encode([
        "statusCode" => 400,
        "message" => "Missing token"
    ]);
    exit;
}

$userid = DecodeParam($token);

/* ===========================
   Image Upload to MinIO
=========================== */
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
    $uploadPath = "tenant/";

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


/* ===========================
   DB Operations
=========================== */
try {

    /* ---------- INSERT ---------- */
    if ($mode === 'INSERT') {

        $Id = NextID("iTenantID", "tenant");

        $insertQuery = "
            INSERT INTO tenant (
                iTenantID, iUID, vParty, vPartyContactNo, vPartyEmailID,
                vGSTNo, iStateCode, vPanNo, vContactPerson, iType, vPic, cStatus
            ) VALUES (
                $Id,
                $userid,
                '" . db_input($name) . "',
                '" . db_input($mobile) . "',
                '" . db_input($email) . "',
                '" . db_input($gstNumber) . "',
                '" . db_input($statecode) . "',
                '" . db_input($panNumber) . "',
                '" . db_input($contactPerson) . "',
                $type,
                '" . db_input($picUrl) . "',
                'A'
            )
        ";

        sql_query($insertQuery);

        echo json_encode([
            "statusCode" => 200,
            "message" => "Tenant added successfully",
            "tenantId" => $Id
        ]);
        exit;
    }

    /* ---------- UPDATE ---------- */
    if ($mode === 'UPDATE') {

        if ($tenantId <= 0) {
            http_response_code(400);
            echo json_encode([
                "statusCode" => 400,
                "message" => "Invalid tenantId"
            ]);
            exit;
        }

        $picSql = $picUrl ? ", vPic = '" . db_input($picUrl) . "'" : '';

        $updateQuery = "
            UPDATE tenant SET
                vParty = '" . db_input($name) . "',
                vPartyContactNo = '" . db_input($mobile) . "',
                vPartyEmailID = '" . db_input($email) . "',
                vGSTNo = '" . db_input($gstNumber) . "',
                iStateCode = '" . db_input($statecode) . "',
                vPanNo = '" . db_input($panNumber) . "',
                vContactPerson = '" . db_input($contactPerson) . "'
                $picSql
            WHERE iTenantID = $tenantId
            AND cStatus = 'A'
        ";

        sql_query($updateQuery);

        echo json_encode([
            "statusCode" => 200,
            "message" => "Tenant updated successfully",
            "tenantId" => $tenantId
        ]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "statusCode" => 500,
        "message" => $e->getMessage()
    ]);
    exit;
}
