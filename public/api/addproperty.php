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
   Inputs (FORM DATA)
=========================== */
$token  = $_POST['token'] ?? '';
$mode   = strtoupper(trim($_POST['mode'] ?? ''));

$propId        = (int)($_POST['propId'] ?? 0);
$name          = $_POST['name'] ?? '';
$address       = $_POST['address'] ?? '';
$category      = (int)($_POST['category'] ?? 0);
$type          = (int)($_POST['type'] ?? 0);
$carpetArea    = (float)($_POST['carpetArea'] ?? 0);
$builtUpArea   = (float)($_POST['builtUpArea'] ?? 0);
$tenantId      = (int)($_POST['tenantId'] ?? 0);
$monthlyRent   = (float)($_POST['monthlyRent'] ?? 0);
$leaseStart    = $_POST['leaseStart'] ?? '';
$leaseEnd      = $_POST['leaseEnd'] ?? '';

if (!in_array($mode, ['INSERT', 'UPDATE'])) {
    http_response_code(400);
    echo json_encode(["statusCode" => 400, "message" => "Invalid mode"]);
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
   Upload Property Image
=========================== */
$propertyPic = '';

if (!empty($_FILES['propertyPic']) && $_FILES['propertyPic']['error'] === UPLOAD_ERR_OK) {

    $allowedImg = ['image/png','image/jpeg','image/webp'];
    if (!in_array($_FILES['propertyPic']['type'], $allowedImg)) {
        http_response_code(400);
        echo json_encode([
            "statusCode" => 400,
            "message" => "Invalid image type"
        ]);
    }

    $imgName = time().'_'.preg_replace('/[^a-zA-Z0-9._-]/','',$_FILES['propertyPic']['name']);
    $imgKey  = time()."_".$imgName;

    $uploadPath = "property/";

    $result = ObjectStorageUpload($uploadPath, $imgKey, $_FILES['propertyPic']['tmp_name']);

    if (!$result) {
        http_response_code(500);
        echo json_encode([
            "statusCode" => 500,
            "message" => "Image upload failed"
        ]);
        exit;
    } 
    $propertyPic = $uploadPath . $imgKey;
}

/* ===========================
   Upload Agreement PDF
=========================== */
$agreementDoc = '';

if (!empty($_FILES['agreementDoc']) && $_FILES['agreementDoc']['error'] === UPLOAD_ERR_OK) {

    if ($_FILES['agreementDoc']['type'] !== 'application/pdf') {
        http_response_code(400);
        echo json_encode([
            "statusCode" => 400,
            "message" => "Invalid document type"
        ]);
        exit;
    }

    $pdfName = time().'_'.preg_replace('/[^a-zA-Z0-9._-]/','',$_FILES['agreementDoc']['name']);
    $pdfKey  = time()."_".$pdfName;

    $path = "agreement/";

    $result = ObjectStorageUpload($path, $pdfKey, $_FILES['agreementDoc']['tmp_name']);

    if (!$result) {
        http_response_code(500);
        echo json_encode([
            "statusCode" => 500,
            "message" => "Image upload failed"
        ]);
        exit;
    }


    $agreementDoc = $path . $pdfKey;
}

try {

    /* ===========================
       INSERT
    ============================ */
    if ($mode === 'INSERT') {

        $propId = NextID("iPropertyID", "property");

        sql_query("
            INSERT INTO property
            (iPropertyID, iUID, vName, vAddress, iCategoryID, iPropertyTypeID,
             fCarpetArea, fBuiltArea, vPic)
            VALUES (
                $propId,
                $userid,
                '".db_input($name)."',
                '".db_input($address)."',
                $category,
                $type,
                $carpetArea,
                $builtUpArea,
                '".db_input($propertyPic)."'
            )
        ");

        if ($tenantId > 0) {

            $agreementId = NextID("iAgreementID", "agreement");
            $now = NOW;

            $from = $leaseStart ? "'".db_input($leaseStart)."'" : "NULL";
            $to   = $leaseEnd   ? "'".db_input($leaseEnd)."'"   : "NULL";

            sql_query("
                INSERT INTO agreement
                (iAgreementID, dtAgreement, dAgreementDate, iPropertyID,
                 iUID, iTenantID, iPropertyTypeID, fAmount,
                 dFrom, dTo, vAgreementDoc)
                VALUES (
                    $agreementId,
                    '$now',
                    '$now',
                    $propId,
                    $userid,
                    $tenantId,
                    $type,
                    $monthlyRent,
                    $from,
                    $to,
                    '".db_input($agreementDoc)."'
                )
            ");
        }

        $message = "Property added successfully";
    }

    /* ===========================
       UPDATE
    ============================ */
    if ($mode === 'UPDATE') {

        if ($propId <= 0) {
            throw new Exception("Invalid Property ID");
        }
    
        /* ---------- PROPERTY UPDATE ---------- */
        $picSql = $propertyPic ? ", vPic='".db_input($propertyPic)."'" : '';
    
        sql_query("
            UPDATE property SET
                vName='".db_input($name)."',
                vAddress='".db_input($address)."',
                iCategoryID=$category,
                iPropertyTypeID=$type,
                fCarpetArea=$carpetArea,
                fBuiltArea=$builtUpArea
                $picSql
            WHERE iPropertyID=$propId
        ");
    
        /* ---------- AGREEMENT UPSERT ---------- */
        if ($tenantId > 0) {
    
            $from = $leaseStart ? "'".db_input($leaseStart)."'" : "NULL";
            $to   = $leaseEnd   ? "'".db_input($leaseEnd)."'"   : "NULL";

            $agreementExists = GetXFromYID("
                SELECT COUNT(*) 
                FROM agreement 
                WHERE iPropertyID = $propId
            ");
    
            if ($agreementExists > 0) {
                sql_query("
                    UPDATE agreement SET
                        iTenantID=$tenantId,
                        iPropertyTypeID=$type,
                        fAmount=$monthlyRent,
                        dFrom=$from,
                        dTo=$to,
                        vAgreementDoc='".db_input($agreementDoc)."'
                    WHERE iPropertyID=$propId
                ");
    
            } else {
                $agreementId = NextID('iAgreementID', 'agreement');
                $now = NOW;

    
                sql_query("
                INSERT INTO agreement
                (iAgreementID, dtAgreement, dAgreementDate, iPropertyID,
                 iUID, iTenantID, iPropertyTypeID, fAmount,
                 dFrom, dTo, vAgreementDoc)
                VALUES (
                    $agreementId,
                    '$now',
                    '$now',
                    $propId,
                    $userid,
                    $tenantId,
                    $type,
                    $monthlyRent,
                    $from,
                    $to,
                    '".db_input($agreementDoc)."'
                )
            ");
            }
        }
    
        $message = "Property updated successfully";
    }    

    echo json_encode([
        "statusCode" => 200,
        "data" => [
            "propertyId" => $propId,
            "message" => $message
        ]
    ]);
    exit;

} catch (Exception $e) {

    http_response_code(500);
    echo json_encode([
        "statusCode" => 500,
        "error" => ["message" => $e->getMessage()]
    ]);
    exit;
}
