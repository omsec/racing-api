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

// Body Variables
$password = '';

// Read Body
$data = json_decode(file_get_contents("php://input"));

// Extract Body
$password = $data->password;

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, $secret_key, array('HS256'));
        $userId = $decoded->data->userId;
        // Access is granted.        

        // Database Connectivity
        $conn = null;
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        $sql = "CALL readPassword (:userId)";        
        $stmt = $conn->prepare($sql);        

        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // get the actual result
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $cnt = $stmt->rowCount();

        if ($cnt == 1) {
            $passwordDB = $row['USR_Password'];

            // return some "secret" codes
            if (password_verify($password, $passwordDB)) {
                echo json_encode(array("status" => 5000));
            } else {
                echo json_encode(array("status" => 8000));
            }

            $stmt->closeCursor();
        }
        else {
            // user not found or multiple records returned (internal/data error)
            if ($cnt > 1) {
                $stmt->closeCursor();
            }
            header('WWW-Authenticate: invalid user/password');            
            http_response_code(401);
        }

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