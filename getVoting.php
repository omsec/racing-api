<?php
include_once './config/database.php';
include_once './config/api.php';
require "./vendor/autoload.php";
use \Firebase\JWT\JWT;

$apiService = new ApiService();
$secret_key = null;
$secret_key = $apiService->getApiKey();
$imageUrl = $apiService->getImageUrl();
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

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, $secret_key, array('HS256'));
        $userId = $decoded->data->userId;
        // Access is granted.

        // Database Connectivity
        $conn = null;
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        // get main data
        $sql = "CALL readVoting(:userId, :tableRef, :recordId)";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':tableRef', $objectType, PDO::PARAM_STR);
        $stmt->bindParam(':recordId', $objectId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $cnt = $stmt->rowCount();        

        if ($cnt == 1) {
            // build response
            echo json_encode(array(
                "objectType" => $objectType,
                "objectId" => $objectId,
                "upVotes" => $row['RAV_UpVotes'],
                "downVotes" => $row['RAV_DownVotes'],
                "userVote" => $row['RAV_UserVote']
            ));

            $stmt->closeCursor();            
        } else {
            // no voting available yet
            echo json_encode(array(
                "objectType" => $objectType,
                "objectId" => $objectId,
                "upVotes" => 0,
                "downVotes" => 0,
                "userVote" => 0
            ));
            
        }
        // always return OK
        http_response_code(200);        
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