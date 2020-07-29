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
$searchTerm = $data->searchTerm;
$gameCode = $data->gameCode;

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

        $sql = "CALL searchCustomTracks(:userId, :cdGame, :searchTerm)";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':cdGame', $gameCode, PDO::PARAM_INT);
        $stmt->bindParam(':searchTerm', $searchTerm, PDO::PARAM_STR);

        $stmt->execute();
        $cnt = $stmt->rowCount();

        $idx = 0;
        if ($cnt > 0) {
            // return data
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $items[$idx]['id'] = $row['RAT_TrackId'];
                $items[$idx]['name'] = $row['RAT_Name'];
                $items[$idx]['forzaSharing'] = $row['RAT_SharingCode'];
                $items[$idx]['rating'] = $row['RAV_Rating'];
                $items[$idx]['typeCode'] = $row['COD_Type'];
                $items[$idx]['typeText'] = $row['TXT_Type'];
                $items[$idx]['designer'] = $row['RAT_Designer'];
                $items[$idx]['difficultyCode'] = $row['COD_Difficulty'];
                $items[$idx]['difficultyText'] = $row['TXT_Difficulty'];
                $items[$idx]['seriesCode'] = $row['COD_Series'];
                $items[$idx]['seriesText'] = $row['TXT_Series'];
                $items[$idx]['carClassCode'] = $row['COD_CarClass'];
                $items[$idx]['carClassText'] = $row['TXT_CarClass'];
                $items[$idx]['carThemeCode'] = $row['COD_CarTheme'];
                $items[$idx]['carThemeText'] = $row['TXT_CarTheme'];
                $items[$idx]['carId'] = $row['VEC_CarId'];
                $items[$idx]['carName'] = $row['VEC_CarName'];
                $items[$idx]['seasonCode'] = $row['COD_Season'];
                $items[$idx]['seasonText'] = $row['TXT_Season'];
                $items[$idx]['weatherCode'] = $row['COD_Weather'];
                $items[$idx]['weatherText'] = $row['TXT_Weather'];
                $items[$idx]['dayTimeCode'] = $row['COD_TimeOfDay'];
                $items[$idx]['dayTimeText'] = $row['TXT_TimeOfDay'];
                $items[$idx]['timeProgressionCode'] = $row['COD_TimeProgression'];
                $items[$idx]['timeProgressionText'] = $row['TXT_TimeProgression'];
                $idx++;
            }  
            // im else-fall (0 REcords) doch keine leere List schicken, das gibt eine Exception im Client         
        }/* else {
            // empty string is not valid JSON - hence add an "emtpy" item
            $items[$idx]['id'] = null;
            $items[$idx]['name'] = null;
            $items[$idx]['rating'] = null;
            $items[$idx]['typeCode'] = null;
            $items[$idx]['typeText'] = null;
            $items[$idx]['designer'] = null;
            $items[$idx]['difficultyCode'] = null;
            $items[$idx]['difficultyText'] = null;
            $items[$idx]['seriesCode'] = null;
            $items[$idx]['seriesText'] = null;
            $items[$idx]['carClassCode'] = null;
            $items[$idx]['carClassText'] = null;
            $items[$idx]['carThemeCode'] = null;
            $items[$idx]['carThemeText'] = null;
            $items[$idx]['carId'] = null;
            $items[$idx]['carName'] = null;
            $items[$idx]['seasonCode'] = null;
            $items[$idx]['seasonText'] = null;
            $items[$idx]['weatherCode'] = null;
            $items[$idx]['weatherText'] = null;
            $items[$idx]['dayTimeCode'] = null;
            $items[$idx]['dayTimeText'] = null;
            $items[$idx]['timeProgressionCode'] = null;
            $items[$idx]['timeProgressionText'] = null;
        }*/

        $stmt->closeCursor();            

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