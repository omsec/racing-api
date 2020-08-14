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

// Read Headers
$headers = getallheaders();
$authHeader = $headers["Authorization"];
// custom header - must be allowed in .htaccess
$languageCode = $headers["Language"];

$arr = explode(" ", $authHeader);
$jwt = $arr[1];

// Read Body
$data = json_decode(file_get_contents("php://input"));

// Extract Body
$championshipId = $data->championshipId;

// Read from Token
$userId = 0;

// embbed objects (by convention)
$metaInfo = null;
$ratingInfo = null;

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, $secret_key, array('HS256'));
        // $username = $decoded->data->username;
        $userId = $decoded->data->userId;
        // Access is granted.

        // Database Connectivity
        $conn = null;
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        // start with races to assemble final response
        $sql = "CALL listRaces(:championshipId, :cdLanguage)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':championshipId', $championshipId, PDO::PARAM_INT);
        $stmt->bindParam(':cdLanguage', $languageCode, PDO::PARAM_INT);        

        $stmt->execute();
        $cnt = $stmt->rowCount();

        $races = [];
        if ($cnt > 0) {
            // get values
            $idx = 0;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // start with nested objects to assemble response
                $metaInfo = array(
                    "id" => $row['RCE_RaceId'],
                    "createdDT" => $row['RCE_Created'],
                    "createdId" => $row['USR_CreatedBy'],
                    "createdName" => $row['USR_CreatedByName'],
                    "modifiedDT" => $row['RCE_Modified'],
                    "modifiedId" => $row['USR_ModifiedBy'],
                    "modifiedName" => $row['USR_ModifiedByName']
                    // no sharing mode for races
                );                
                $races[$idx]['metaInfo'] = $metaInfo;
                // std info
                $races[$idx]['championshipId'] = $championshipId;
                $races[$idx]['raceNo'] = $row['RCE_RaceNo'];
                $races[$idx]['trackId'] = $row['RAT_TrackId'];
                $races[$idx]['trackName'] = $row['RAT_TrackName'];
                // restrictions
                $races[$idx]['seriesCode'] = $row['COD_Series'];
                $races[$idx]['seriesText'] = $row['TXT_Series'];
                $races[$idx]['carThemeCode'] = $row['COD_CarTheme'];
                $races[$idx]['carThemeText'] = $row['TXT_CarTheme'];
                $races[$idx]['carId'] = $row['VEC_CarId'];
                $races[$idx]['carName'] = $row['VEC_CarName'];
                $races[$idx]['carClassCode'] = $row['COD_CarClass'];
                $races[$idx]['carClassText'] = $row['TXT_CarClass'];
                // conditions
                $races[$idx]['seasonCode'] = $row['COD_Season'];
                $races[$idx]['seasonText'] = $row['TXT_Season'];
                $races[$idx]['dayTimeCode'] = $row['COD_TimeOfDay'];
                $races[$idx]['dayTimeText'] = $row['TXT_TimeOfDay'];
                $races[$idx]['weatherCode'] = $row['COD_Weather'];
                $races[$idx]['weatherText'] = $row['TXT_Weather'];
                $races[$idx]['timeProgressionCode'] = $row['COD_TimeProgression'];
                $races[$idx]['timeProgressionText'] = $row['TXT_TimeProgression'];

                $idx++;                
            }
            $stmt->closeCursor();            
        }
        
        // get main data
        $sql = "CALL readChampionship(:championshipId, :cdLanguage, :userId)";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':championshipId', $championshipId, PDO::PARAM_INT);
        $stmt->bindParam(':cdLanguage', $languageCode, PDO::PARAM_INT);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $cnt = $stmt->rowCount();

        if ($cnt == 1) {
            // build object parts
            $metaInfo = array(
                "id" => $row['CMP_ChampionshipId'],
                "createdDT" => $row['CMP_Created'],
                "createdId" => $row['USR_CreatedBy'],
                "createdName" => $row['USR_CreatedByName'],
                "modifiedDT" => $row['CMP_Modified'],
                "modifiedId" => $row['USR_ModifiedBy'],
                "modifiedName" => $row['USR_ModifiedByName'],
                "sharingModeCode" => $row['COD_SharingMode'],
				"sharingModeText" => $row['TXT_SharingMode']
            );

            // build response
            echo json_encode(array(
                "metaInfo" => $metaInfo,
                // std info
                "gameCode" => $row['COD_Game'],
                "gameText" => $row['TXT_Game'],
                "blueprintName" => $row['CMP_Name'],
                "description" => $row['CMP_Description'],
                "races" => $races,
                "rating" => $row['RAV_Rating']
            ));

            $stmt->closeCursor();
            http_response_code(200);            
        } else {
            // no or many records found (data error)
            echo json_encode(array("message" => "No Data found."));            
            http_response_code(404); // no data found; oder 200?? vielleicht leeres objekt liefern?            
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