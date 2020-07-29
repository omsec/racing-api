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

// Extract Body
$itemType = $data->itemType;
$itemId = $data->itemId;
$userId = $data->userId;
$vote = $data->vote;

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, $secret_key, array('HS256'));
        // $username = $decoded->data->username;
        // Access is granted.        

        // Database Connectivity
        $conn = null;
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        $sql = "CALL updateVote (:tableRef, :recordId, :userId, :vote)";
        
        $stmt = $conn->prepare($sql);
        
        $stmt->bindParam(':tableRef', $itemType, PDO::PARAM_STR);
        $stmt->bindParam(':recordId', $itemId, PDO::PARAM_INT);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':vote', $vote, PDO::PARAM_INT);

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