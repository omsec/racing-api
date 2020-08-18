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

// Read Body
$data = json_decode(file_get_contents("php://input"));

// Extract Body (must contain required parts of "course"-object)

// metaInfo
$trackId = $data->metaInfo->id;
$modifiedId = $data->metaInfo->modifiedId;
$sharingModeCode = $data->metaInfo->sharingModeCode;

// std info
$gameCode = $data->gameCode;
$name = $data->name;
// cst info block I
$externalId = $data->externalId;
// restrictions
$carThemeCode = $data->carThemeCode;
$carId = $data->carId;
$carClassCode = $data->carClassCode;
// routing
$forzaRouteId = $data->forzaRouteId;
$customRoute = $data->customRoute;
// cst info block II
$standardLaps = $data->standardLaps;
$seasonCode = $data->seasonCode;
$daytimeCode = $data->daytimeCode;
$weatherCode = $data->weatherCode;
$timeProgressionCode = $data->timeProgressionCode;
// cst info block III
$defaultLapTimeSec = $data->defaultLapTimeSec;
$distanceKM = $data->distanceKM;
$sharingCode = $data->sharingCode;
$difficultyCode = $data->difficultyCode;
$description = $data->description;

// terrain
$terrain = $data->terrain;

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, $secret_key, array('HS256'));
        $userId = $decoded->data->userId;
        // Access is granted.        

        // Database Connectivity
        $conn = null;
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        // ToDO: Transactions in PHP/MySqL?

        // 1) update course
        $sql = "CALL updateTrack (:trackId, :userId,
            :cdGame, :courseName,
            :cdSharing, :reference,
            :cdCarTheme, :carId, :cdCarClass,
            :forzaRouteId, :customRoute,
            :laps, :cdSeason, :cdTimeOfDay, :cdWeather, :cdTimeProgression,
            :defaultLapTimeSec, :distanceKM, :sharingCode, :cdDifficulty,
            :description)";
        
        $stmt = $conn->prepare($sql);
        
        $stmt->bindParam(':trackId', $trackId, PDO::PARAM_INT);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

        $stmt->bindParam(':cdGame', $gameCode, PDO::PARAM_INT);
        $stmt->bindParam(':courseName', $name, PDO::PARAM_STR);

        $stmt->bindParam(':cdSharing', $sharingModeCode, PDO::PARAM_INT);
        $stmt->bindParam(':reference', $externalId, PDO::PARAM_STR);

        $stmt->bindParam(':cdCarTheme', $carThemeCode, PDO::PARAM_INT);
        $stmt->bindParam(':carId', $carId, PDO::PARAM_INT);
        $stmt->bindParam(':cdCarClass', $carClassCode, PDO::PARAM_INT);

        $stmt->bindParam(':forzaRouteId', $forzaRouteId, PDO::PARAM_INT);
        $stmt->bindParam(':customRoute', $customRoute, PDO::PARAM_STR);

        $stmt->bindParam(':laps', $standardLaps, PDO::PARAM_INT);
        $stmt->bindParam(':cdSeason', $seasonCode, PDO::PARAM_INT);
        $stmt->bindParam(':cdTimeOfDay', $daytimeCode, PDO::PARAM_INT);
        $stmt->bindParam(':cdWeather', $weatherCode, PDO::PARAM_INT);
        $stmt->bindParam(':cdTimeProgression', $timeProgressionCode, PDO::PARAM_INT);

        $stmt->bindParam(':defaultLapTimeSec', $defaultLapTimeSec, PDO::PARAM_INT);
        $stmt->bindParam(':distanceKM', $distanceKM, PDO::PARAM_INT);
        $stmt->bindParam(':sharingCode', $sharingCode, PDO::PARAM_INT);
        $stmt->bindParam(':cdDifficulty', $difficultyCode, PDO::PARAM_INT);

        $stmt->bindParam(':description', $description, PDO::PARAM_STR);

        $stmt->execute();

        // 2) update terrain tags (full replace: delete + create)
        $sql = "CALL deleteMvcEntries(:tableRef, :recordId, :codeType)";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':tableRef', ApiService::tbl_RAT, PDO::PARAM_STR);
        $stmt->bindParam(':recordId', $trackId, PDO::PARAM_INT);                
        $stmt->bindValue(':codeType', ApiService::ct_Terrain, PDO::PARAM_STR);        

        $stmt->execute();

        // 2) save terrain (new) selection
        if ($trackId > 0 && isset($terrain)) {
            $sql = "CALL createMvcEntry(:tableRef, :recordId, :codeType, :cdValue)";
            $stmt = $conn->prepare($sql);

            foreach ($terrain as $tag) {
                //echo $tag->valueCode;
                $stmt->bindValue(':tableRef', ApiService::tbl_RAT, PDO::PARAM_STR);
                $stmt->bindParam(':recordId', $trackId, PDO::PARAM_INT);                
                $stmt->bindValue(':codeType', ApiService::ct_Terrain, PDO::PARAM_STR);
                $stmt->bindParam(':cdValue', $tag->code, PDO::PARAM_INT);
                $stmt->execute();
            }
        }        

        http_response_code(200);
        // echo json_encode(array("id" => $trackId)); no response given for change methods if ok

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