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

// Extract user info from token
$userId = null;

$arr = explode(" ", $authHeader);
$jwt = $arr[1];

// Read Body
$data = json_decode(file_get_contents("php://input"));

// Extract Body
$itemType = $data->itemType;
$itemId = $data->itemId;

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, $secret_key, array('HS256'));
        // $username = $decoded->data->username;
        $userId = $decoded->data->userId;
        // Access is granted.

        // Database Connectivity
        $conn = null;
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        // form response (default)
        $ratingInfo = array(
            "rating" => 0,
            "upVotes" => 0,
            "downVotes" => 0,
            "userVote" => 0
        );

        // start with rating itself
        $sql = "CALL readRating(:tableRef, :recordId)";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':tableRef', $itemType, PDO::PARAM_STR);
        $stmt->bindParam(':recordId', $itemId, PDO::PARAM_INT);
        
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $cnt = $stmt->rowCount();
        
        if ($cnt == 1) {
            // patch empty array
            $ratingInfo['rating'] = $row['RAV_Rating'];
            $ratingInfo['upVotes'] = $row['RAV_UpVotes'];
            $ratingInfo['downVotes'] = $row['RAV_DownVotes'];
            // ommitted since not needed
            // $ratingInfo['totalVotes'] = $row['RAV_TotalVotes'];

            $stmt->closeCursor();
        }

        // get user's vote on requested object
        $sql = "CALL readVote(:tableRef, :recordId, :userId)";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':tableRef', $itemType, PDO::PARAM_STR);
        $stmt->bindParam(':recordId', $itemId, PDO::PARAM_INT);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $cnt = $stmt->rowCount();
        
        if ($cnt == 1) {
            // patch response
            $ratingInfo['userVote'] = $row['RAV_Vote'];

            $stmt->closeCursor();
        }

        // return response
        http_response_code(200);
        echo json_encode($ratingInfo);
        
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