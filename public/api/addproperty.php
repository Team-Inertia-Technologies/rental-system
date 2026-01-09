<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
$NO_REDIRECT = $NO_PRELOAD = 1;
include "../includes/common_api.php";

header('Content-Type: application/json');

$request = json_decode(file_get_contents("php://input"), true);
$_REQUEST = array_merge($_REQUEST, $request ?? []);

$token  = $_REQUEST['token'] ?? '';
$mode   = strtoupper(trim($_REQUEST['mode'] ?? ''));

$propId        = (int)($_REQUEST['propId'] ?? 0);
$name          = $_REQUEST['name'] ?? '';
$address       = $_REQUEST['address'] ?? '';
$category      = (int)($_REQUEST['category'] ?? 0);
$type          = (int)($_REQUEST['type'] ?? 0);
$carpetArea    = (float)($_REQUEST['carpetArea'] ?? 0);
$builtUpArea   = (float)($_REQUEST['builtUpArea'] ?? 0);
$tenantId      = (int)($_REQUEST['tenantId'] ?? 0);
$monthlyRent   = (float)($_REQUEST['monthlyRent'] ?? 0);
$leaseStart    = $_REQUEST['leaseStart'] ?? '';
$leaseEnd      = $_REQUEST['leaseEnd'] ?? '';
$agreementdoc  = $_REQUEST['agreementdoc'] ?? '';

if (!in_array($mode, ['INSERT', 'UPDATE'])) {
    http_response_code(400);
    echo json_encode(["statusCode" => 400, "message" => "Invalid mode"]);
    exit;
}

try {

    /* ===============================
       INSERT MODE
    =============================== */
    if ($mode === 'INSERT') {

        $propId = NextID("iPropertyID", "property");

        $insertProperty = "
            INSERT INTO property
            (iPropertyID, vName, vAddress, iCategoryID, iPropertyTypeID, fCarpetArea, fBuiltArea)
            VALUES (
                $propId,
                '".db_input($name)."',
                '".db_input($address)."',
                $category,
                $type,
                $carpetArea,
                $builtUpArea
            )
        ";
        sql_query($insertProperty);

        if ($tenantId > 0) {
            $agreementId = NextID("iAgreementID", "agreement");
            $now = NOW;

            $leaseStartDate = $leaseStart ? "'".db_input($leaseStart)."'" : "NULL";
            $leaseEndDate   = $leaseEnd ? "'".db_input($leaseEnd)."'" : "NULL";

            $insertAgreement = "
                INSERT INTO agreement
                (iAgreementID, dtAgreement, dAgreementDate, iPropertyID, iTenantID,
                 iPropertyTypeID, fAmount, dFrom, dTo, vAgreementDoc)
                VALUES (
                    $agreementId,
                    '$now',
                    '$now',
                    $propId,
                    $tenantId,
                    $type,
                    $monthlyRent,
                    $leaseStartDate,
                    $leaseEndDate,
                    '".db_input($agreementdoc)."'
                )
            ";
            sql_query($insertAgreement);
        }

        $message = "Property added successfully";
    }

    /* ===============================
       UPDATE MODE
    =============================== */
    if ($mode === 'UPDATE') {

        if ($propId <= 0) {
            throw new Exception("Invalid Property ID");
        }

        $updateProperty = "
            UPDATE property SET
                vName='".db_input($name)."',
                vAddress='".db_input($address)."',
                iCategoryID=$category,
                iPropertyTypeID=$type,
                fCarpetArea=$carpetArea,
                fBuiltArea=$builtUpArea
            WHERE iPropertyID=$propId
        ";
        sql_query($updateProperty);

        if ($tenantId > 0) {
            $leaseStartDate = $leaseStart ? "'".db_input($leaseStart)."'" : "NULL";
            $leaseEndDate   = $leaseEnd ? "'".db_input($leaseEnd)."'" : "NULL";

            $agreementExists = sql_num_rows(
                sql_query("SELECT iAgreementID FROM agreement WHERE iPropertyID=$propId")
            );

            if ($agreementExists) {
                $updateAgreement = "
                    UPDATE agreement SET
                        iTenantID=$tenantId,
                        iPropertyTypeID=$type,
                        fAmount=$monthlyRent,
                        dFrom=$leaseStartDate,
                        dTo=$leaseEndDate,
                        vAgreementDoc='".db_input($agreementdoc)."'
                    WHERE iPropertyID=$propId
                ";
                sql_query($updateAgreement);
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