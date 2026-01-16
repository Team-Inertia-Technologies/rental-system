<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

$NO_REDIRECT = $NO_PRELOAD = 1;
include "../includes/common_api.php";

/* ===========================
   MinIO (S3) Configuration
=========================== */
require '../includes/vendor/autoload.php';

use Aws\S3\S3Client;

$s3 = new S3Client([
    'version' => 'latest',
    'region'  => 'us-east-1',
    'endpoint' => 'https://console-ti-stage-projects-minio.krjqe5.easypanel.host',
    'use_path_style_endpoint' => true,
    'credentials' => [
        'key'    => '2S34dCIyBH7Xndiu073H',
        'secret' => 'NIi1U3gBJ7D0DbSxTppOg1B5iDSwpCcEevw9dSbl',
    ],
]);

/* ===========================
   Support JSON OR multipart
=========================== */
if (empty($_FILES)) {
    $postdata = file_get_contents("php://input");
    $request = json_decode($postdata, true);
    if (is_array($request)) {
        $_REQUEST = array_merge($_REQUEST, $request);
    }
}

/* ===========================
   Inputs
=========================== */
$token         = $_REQUEST['token'] ?? '';
$mode          = strtoupper(trim($_REQUEST['mode'] ?? ''));
$tenantId      = isset($_REQUEST['tenantId']) ? (int)$_REQUEST['tenantId'] : 0;
$name          = $_REQUEST['name'] ?? '';
$mobile        = $_REQUEST['mobile'] ?? '';
$email         = $_REQUEST['email'] ?? '';
$gstNumber     = $_REQUEST['gstNumber'] ?? '';
$panNumber     = $_REQUEST['panNumber'] ?? '';
$contactPerson = $_REQUEST['contactPerson'] ?? '';
$type          = isset($_REQUEST['type']) ? (int)$_REQUEST['type'] : 0;

/* ===========================
   Validation
=========================== */
if (!in_array($mode, ['INSERT', 'UPDATE'])) {
    http_response_code(400);
    echo json_encode(["statusCode" => 400, "message" => "Invalid mode"]);
    exit;
}

if (!$token) {
    http_response_code(400);
    echo json_encode([
        "statusCode" => 400,
        "error" => ["message" => "Missing token"]
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
        throw new Exception("Invalid image type");
    }

    if ($_FILES['pic']['size'] > 2 * 1024 * 1024) {
        throw new Exception("Image size exceeds 2MB");
    }

    $tmpPath = $_FILES['pic']['tmp_name'];

    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['pic']['name']);
    $fileName = time() . '_' . $safeName;

    $objectKey = "tenant/" . $fileName;

    $result = $s3->putObject([
        'Bucket'      => 'rental',
        'Key'         => $objectKey,
        'SourceFile'  => $tmpPath,
        'ACL'         => 'public-read',
        'ContentType' => $_FILES['pic']['type']
    ]);

    $picUrl = $result['ObjectURL'];
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
                vGSTNo, vPanNo, vContactPerson, iType, vPic, cStatus
            ) VALUES (
                $Id,
                $userid,
                '".db_input($name)."',
                '".db_input($mobile)."',
                '".db_input($email)."',
                '".db_input($gstNumber)."',
                '".db_input($panNumber)."',
                '".db_input($contactPerson)."',
                $type,
                '".db_input($picUrl)."',
                'A'
            )
        ";

        sql_query($insertQuery);

        echo json_encode([
            "statusCode" => 200,
            "data" => [
                "tenantId" => $Id,
                "message" => "Tenant added successfully"
            ]
        ]);
        exit;
    }

    /* ---------- UPDATE ---------- */
    if ($mode === 'UPDATE') {

        if ($tenantId <= 0) {
            http_response_code(400);
            echo json_encode([
                "statusCode" => 400,
                "message" => "Invalid tenantId for update"
            ]);
            exit;
        }

        $picSql = $picUrl ? ", vPic = '".db_input($picUrl)."'" : '';

        $updateQuery = "
            UPDATE tenant SET
                vParty = '".db_input($name)."',
                vPartyContactNo = '".db_input($mobile)."',
                vPartyEmailID = '".db_input($email)."',
                vGSTNo = '".db_input($gstNumber)."',
                vPanNo = '".db_input($panNumber)."',
                vContactPerson = '".db_input($contactPerson)."'
                $picSql
            WHERE iTenantID = $tenantId
            AND cStatus = 'A'
        ";

        sql_query($updateQuery);

        echo json_encode([
            "statusCode" => 200,
            "data" => [
                "tenantId" => $tenantId,
                "message" => "Tenant details updated successfully"
            ]
        ]);
        exit;
    }

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
