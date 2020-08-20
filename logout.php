<?php
include_once './config/database.php';
include_once './config/api.php';
require "./vendor/autoload.php";
use \Firebase\JWT\JWT;

$apiService = new ApiService();
$secret_key = null;
$secret_key = $apiService->getApiKey();
$jwt = null;

// Read Headers
$headers = getallheaders();
$authHeader = $headers["Authorization"];
// custom header - must be allowed in .htaccess
// $languageCode = $headers["Language"];

$arr = explode(" ", $authHeader);
$jwt = $arr[1];

// Read Body
$data = json_decode(file_get_contents("php://input"));

// Extract Body (none)

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, $secret_key, array('HS256'));
        $username = $decoded->data->username;
        // Access is granted.        

        // Database Connectivity
        $conn = null;
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        // internal logging
        $sql = "CALL createAuditAction(:cdAction, :originIP, :userName, :cdStatus)";
        $stmt = $conn->prepare($sql);

        $stmt->bindValue(':cdAction', 1, PDO::PARAM_INT);
        $stmt->binValue(':originIP', null, PDO::PARAM_STR);
        $stmt->bindParam(':userName', $username, PDO::PARAM_STR);
        $stmt->bindValue(':cdStatus', $null, PDO::PARAM_INT);

        $stmt->execute();

        http_response_code(200);
        // nothing returned
        // echo json_encode(array("id" => $trackId));

    } catch (Exception $ex) {
        http_response_code(401); // unauthorized (wohl token falsch/expired)
        die(json_encode(array("message" => $ex->getMessage())));
    }        
}
else {
    // Auth Header missing
    header('WWW-Authenticate: Access denied.');
    echo json_encode(array(
        "message" => "Access is denied."
    ));    
    http_response_code(401); // unauthorized
}


?>