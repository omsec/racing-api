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
// $languageCode = $headers["Language"]; - moved to SP in newer implementations

$arr = explode(" ", $authHeader);
$jwt = $arr[1];

// Read Body
$data = json_decode(file_get_contents("php://input"));

// Extract Body
$trackId = $data->trackId;

// Variables
$items = [];

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, $secret_key, array('HS256'));
        // $userId = $decoded->data->userId;
        // Access is granted.

        // Database Connectivity
        $conn = null;
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        $sql = "CALL listTrackUsage(:trackId)";
        $stmt = $conn->prepare($sql);

        $stmt->bindParam(':trackId', $trackId, PDO::PARAM_INT);

        $stmt->execute();
        $cnt = $stmt->rowCount();

        $idx = 0;
        if ($cnt > 0) {
            // return list
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $items[$idx]['championshipId'] = $row['CMP_ChampionshipId'];
                $items[$idx]['championshipName'] = $row['CMP_Name'];
                $items[$idx]['raceNumbers'] = $row['Races'];
                $idx++;
            }                        
            $stmt->closeCursor();
        }
        
        echo json_encode($items);
        http_response_code(200); // OK - regardless if there is data or not (list may be empty)
       
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