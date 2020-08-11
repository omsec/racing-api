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
$objectType = $data->objectType;
$objectId = $data->objectId;

$parentId = $data->parentId;
$statusCode = $data->statusCode;
$commentText = $data->commentText;

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, $secret_key, array('HS256'));
        $userId = $decoded->data->userId;
        // Access is granted.

        // Database Connectivity
        $conn = null;
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        // prepare SQL
        $sql = "CALL createComment(
            :userId,
            :tableRef, :recordId,
            :parentId, :cdStatus, :commentText,
            @id)";
        $stmt = $conn->prepare($sql);

        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':tableRef', $objectType, PDO::PARAM_STR);
        $stmt->bindParam(':recordId', $objectId, PDO::PARAM_INT);
        $stmt->bindParam(':parentId', $parentId, PDO::PARAM_INT);
        $stmt->bindParam(':cdStatus', $statusCode, PDO::PARAM_INT);
        $stmt->bindParam(':commentText', $commentText, PDO::PARAM_STR);

        $stmt->execute();

        // return ID
        $row = $conn->query("SELECT @id AS id")->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $id = $row['id'];
        }

        http_response_code(200);
        echo json_encode(array("ID" => $id));

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