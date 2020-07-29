<?php
include_once './config/database.php';
require "./vendor/autoload.php";
use \Firebase\JWT\JWT;

$jwt = null;

// Read Headers
$headers = getallheaders();
$authHeader = $headers["Authorization"];
// custom header - must be allowed in .htaccess
$languageCode = $headers["Language"];

$arr = explode(" ", $authHeader);
$jwt = $arr[1];

$apiService = new ApiService();
$secret_key = null;
$secret_key = $apiService->getApiKey();

$users = [];

// Database Connectivity
$conn = null;
$databaseService = new DatabaseService();
$conn = $databaseService->getConnection();

$sql = "CALL listUsers(:cdLanguage)";

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, $secret_key, array('HS256'));
        
        // Access is granted - get actual data
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':cdLanguage', $languageCode, PDO::PARAM_INT);

        $stmt->execute();
        $cnt = $stmt->rowCount();

        if ($cnt > 0) {
            // return data
            $idx = 0;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $users[$idx]['created'] = $row['USR_Created'];
                $users[$idx]['modified'] = $row['USR_Modified'];
                $users[$idx]['username'] = $row['USR_LoginName'];
                //$users[$idx]['password'] = ''; pwd is never returned by services
                $users[$idx]['loginActive'] = $row['USR_LoginActive'];
                $users[$idx]['roleCode'] = $row['COD_Role'];
                $users[$idx]['roleText'] = $row['TXT_Role'];
                $users[$idx]['xBox'] = $row['USR_XBoxTag'];
                $users[$idx]['discord'] = $row['USR_DiscordName'];
                // token is not returned
                $idx++;
            }

            $stmt->closeCursor();            
            
            echo json_encode($users);
            http_response_code(200);
        } else {
            // no users found = internal error (data error)            
            echo json_encode(array("message" => "No Data found."));            
            http_response_code(204); // no data found
        }

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