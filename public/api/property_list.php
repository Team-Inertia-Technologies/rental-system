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

if (!$token) {
    http_response_code(400);
    echo json_encode([
        "statusCode" => 400,
        "error" => [
            "message" => "Missing token"
        ]
    ]);
    exit;
}

$userID = DecodeParam($token);

try {

    $query = "
        SELECT 
            p.iPropertyID AS id,
            p.vName AS propertyName,
            c.vName AS category,
            p.fCarpetArea AS carpetArea,
            p.fBaseAmount AS monthlyRent,
            a.fAmount AS agreedRent,
            p.vPic AS propertyImg,
            t.vParty AS tenantName,
            t.vPic AS tenantImg,
            inv.iInvoiceID,
            CASE 
                WHEN inv.iInvoiceID IS NULL THEN 0
                ELSE 1
            END AS invoiceStatus
        FROM property p
        JOIN category c ON p.iCategoryID = c.iCategoryID
        LEFT JOIN agreement a 
            ON a.iPropertyID = p.iPropertyID 
           AND a.cStatus = 'A'
        LEFT JOIN tenant t 
            ON t.iTenantID = a.iTenantID 
           AND t.cStatus = 'A'
        LEFT JOIN invoice inv
            ON inv.iPropertyID = p.iPropertyID
           AND inv.cStatus = 'A'
        WHERE p.iUID = '$userID'
        ORDER BY p.iPropertyID ASC
    ";

    $res = sql_query($query);
    $properties = [];

    while ($data = sql_fetch_assoc($res)) {
        $properties[] = [
            "propId"        => (int)$data['id'],
            "propertyName"  => db_output2($data['propertyName']),
            "category"      => $data['category'],
            "carpetArea"    => (float)$data['carpetArea'],
            "monthlyRent"   => (float)$data['monthlyRent'],
            "agreedRent"    => (float)$data['agreedRent'],
            "propertyImg"   => 'https://ti-stage-projects-minio.krjqe5.easypanel.host/firstbucket/'.$data['propertyImg'],
            "tenantName"    => db_output2($data['tenantName']),
            "tenantImg"     => 'https://ti-stage-projects-minio.krjqe5.easypanel.host/firstbucket/'.$data['tenantImg'],
            "invoiceStatus" => (bool)$data['invoiceStatus'],
            "invoiceID"     => isset($data['iInvoiceID']) ? (int)$data['iInvoiceID'] : 0
        ];
    }

    http_response_code(200);
    echo json_encode([
        "data" => [
            "propertyList" => $properties
        ],
        "statusCode" => 200
    ]);
    exit;

} catch (Exception $e) {

    http_response_code(500);
    echo json_encode([
        "error" => [
            "message" => $e->getMessage()
        ],
        "statusCode" => 500
    ]);
    exit;
}
?>
