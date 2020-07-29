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

        $sql = "CALL listChampionships(:userId)";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

        $stmt->execute();
        $cnt = $stmt->rowCount();

        $idx = 0;
        if ($cnt > 0) {
            // return data
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $items[$idx]['id'] = $row['CMP_ChampionshipId'];
                $items[$idx]['name'] = $row['CMP_Name'];
                $items[$idx]['rating'] = $row['RAV_Rating'];
                $items[$idx]['countRaces'] = $row['count_Races'];
                $items[$idx]['countSeries'] = $row['count_Series'];
                $items[$idx]['seriesCode'] = $row['COD_Series'];
                $items[$idx]['seriesText'] = $row['TXT_Series'];
                $items[$idx]['countCarClass'] = $row['count_CarClass'];
                $items[$idx]['carClassCode'] = $row['COD_CarClass'];
                $items[$idx]['carClassText'] = $row['TXT_CarClass'];
                $items[$idx]['countCarTheme'] = $row['count_CarTheme'];
                $items[$idx]['carThemeCode'] = $row['COD_CarTheme'];
                $items[$idx]['carThemeText'] = $row['TXT_CarTheme'];
                $items[$idx]['countCar'] = $row['count_Car'];
                $items[$idx]['carName'] = $row['VEC_CarName'];
                $items[$idx]['createdById'] = $row['USR_CreatedBy'];
                $items[$idx]['createdByName'] = $row['USR_CreatedByName'];
                $idx++;
            }            
        } else {
            // empty string is not valid JSON - hence add an "emtpy" item
            $items[$idx]['id'] = null;
            $items[$idx]['name'] = null;
            $items[$idx]['rating'] = null;
            $items[$idx]['countRaces'] = null;
            $items[$idx]['countSeries'] = null;
            $items[$idx]['seriesCode'] = null;
            $items[$idx]['seriesText'] = null;
            $items[$idx]['countCarClass'] = null;
            $items[$idx]['carClassCode'] = null;
            $items[$idx]['carClassText'] = null;
            $items[$idx]['countCarTheme'] = null;
            $items[$idx]['carThemeCode'] = null;
            $items[$idx]['carThemeText'] = null;
            $items[$idx]['countCar'] = null;
            $items[$idx]['carName'] = null;
            $items[$idx]['createdById'] = null;
            $items[$idx]['createdByName'] = null;
        }

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