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

// search modes (also mapped in angular service)
define("SM_ALL", 0);
define("SM_STANDARD", 1);
define("SM_CUSTOM", 2);
// Read Headers
$headers = getallheaders();
$authHeader = $headers["Authorization"];
// custom header - must be allowed in .htaccess
// $languageCode = $headers["Language"]; // no need for language (template)

$arr = explode(" ", $authHeader);
$jwt = $arr[1];

// Read Body
$data = json_decode(file_get_contents("php://input"));

// Extract Body
$gameCode = $data->gameCode;
$searchTerm = $data->searchTerm;
$searchMode = $data->searchMode;

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, $secret_key, array('HS256'));
        // $username = $decoded->data->username;
        // Access is granted.

        // Database Connectivity
        $conn = null;
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();
        
        // Welche Strecken suchen? (Forza-Standard, Custom/Blueprints..)
        switch ($searchMode) {
            case SM_ALL:
                $sql = "CALL searchTrackNames(:cdGame, :searchTerm)";
                break;
            case SM_STANDARD:
                $sql = "CALL searchStandardTrackNames(:cdGame, :searchTerm)";
                break;
            case SM_CUSTOM:
                $sql = "CALL searchCustomTrackNames(:cdGame, :searchTerm)";
                break;                
        }                

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':cdGame', $gameCode, PDO::PARAM_INT);
        $stmt->bindParam(':searchTerm', $searchTerm, PDO::PARAM_STR);

        $stmt->execute();
        $cnt = $stmt->rowCount();

        if ($cnt > 0) {
            $idx = 0;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $tracks[$idx]['trackId'] = $row['RAT_TrackId'];
                $tracks[$idx]['typeCode'] = $row['COD_Type'];
                $tracks[$idx]['seriesCode'] = $row['COD_Series'];
                $tracks[$idx]['trackName'] = $row['RAT_Name'];                
                $idx++;
            }            
            $stmt->closeCursor();

            echo json_encode($tracks);
            http_response_code(200);
        } else {
            // no records found (empty list)
            echo json_encode(array(
                "trackId" => "",
                "typeCode" => "",
                "trackName" => ""
            ));            
            http_response_code(200);
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