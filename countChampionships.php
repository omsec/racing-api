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

// no body required

$arr = explode(" ", $authHeader);
$jwt = $arr[1];

$username = '';
$items = [];

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, $secret_key, array('HS256'));
        $userId = $decoded->data->userId;
        // Access is granted.

        // Database Connectivity
        $conn = null;
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        $sql = "CALL countChampionships(:userId, @cnt)";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

        $stmt->execute();

        $cnt = 0;        
        $row = $conn->query("SELECT @cnt AS cnt")->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $cnt = $row['cnt'];
        }

        $stmt->closeCursor();

        echo json_encode(array("count" => $cnt));
        http_response_code(200); // OK
       
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