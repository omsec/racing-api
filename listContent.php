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

$username = '';
$items = [];

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, $secret_key, array('HS256'));
        $username = $decoded->data->username;
        // Access is granted.

        // Database Connectivity
        $conn = null;
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        $sql = "CALL listContent(:username, :cdLanguage)";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':cdLanguage', $languageCode, PDO::PARAM_INT);

        $stmt->execute();
        $cnt = $stmt->rowCount();

        if ($cnt > 0) {
            // return data
            $idx = 0;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $items[$idx]['id'] = $row['itemId'];
                $items[$idx]['itemType'] = $row['itemType'];
                $items[$idx]['itemName'] = $row['itemName'];
                $items[$idx]['itemInfo1'] = $row['itemInfo1'];
                $items[$idx]['itemInfo2'] = $row['itemInfo2'];
                $items[$idx]['lastAccess'] = $row['sortField_Access'];
                $items[$idx]['rating'] = $row['rating'];
                // codes only to have clients pick the right image
                $items[$idx]['seriesCode'] = $row['COD_Series'];
                $items[$idx]['carClassCode'] = $row['COD_CarClass'];
                $idx++;
            }

            $stmt->closeCursor();            
            
            echo json_encode($items);
            http_response_code(200);
        } else {
            // no data found
            echo json_encode($items); // []/null ist kein gültiges JSON :-/
            http_response_code(204); // evtl. etwas anderes als Fehler (Error Interceptor triggered) []/null ist kein gültiges JSON :-/
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