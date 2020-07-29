<?php
include_once './config/api.php';
include_once './config/database.php';
require "./vendor/autoload.php";
use \Firebase\JWT\JWT;

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
$id = $data->id;

// Read Query Parameter (DELETE has no Body)
// $id = $_GET["id"];

$apiService = new ApiService();
$secret_key = null;
$secret_key = $apiService->getApiKey();

// no response given, just status (set client to text)

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, $secret_key, array('HS256'));        
        // Access is granted - get actual data        

        // Databae Connectivity
        $conn = null;
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        $sql = "CALL deleteMvfEntry(:fileId)";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':fileId', $id, PDO::PARAM_INT);

        $stmt->execute();
        
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