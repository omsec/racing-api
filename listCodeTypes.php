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
$languageCode = $headers["Language"];

$arr = explode(" ", $authHeader);
$jwt = $arr[1];

// hier bisher nicht benötigt (standard-feld)
// $username = '';

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, $secret_key, array('HS256'));
        // $username = $decoded->data->username;
        // Access is granted.

        // Database Connectivity
        $conn = null;
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        $sql = "CALL listCodeTypes(:cdLanguage)";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':cdLanguage', $languageCode, PDO::PARAM_INT);

        $stmt->execute();
        $cnt = $stmt->rowCount();

        if ($cnt > 0) {
            // return data
            $idx = 0;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $codeTypes[$idx]['type'] = $row['COD_Domain'];
                $codeTypes[$idx]['value'] = $row['COD_Value'];
                $codeTypes[$idx]['text'] = $row['COD_Text'];
                $idx++;
            }

            $stmt->closeCursor();            
            
            echo json_encode($codeTypes);
            http_response_code(200);
        } else {
            // no data found = internal error (data error)            
            echo json_encode(array("message" => "No Data found."));            
            http_response_code(404);
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