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
$createdId = $data->metaInfo->createdId;
$sharingModeCode = $data->metaInfo->sharingModeCode;

// std info
$gameCode = $data->gameCode;
$name = $data->name;
$typeCode = $data->typeCode;
$seriesCode = $data->seriesCode;
// cst info block I
$designedBy = $data->designedBy;
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
$defaultLapTimeMin = $data->defaultLapTimeMin;
$distanceKM = $data->distanceKM;
$sharingCode = $data->sharingCode;
$difficultyCode = $data->difficultyCode;
$desciption = $data->description;
$trackId = 0; // returned from SP

// terrain
$terrain = $data->terrain;

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, $secret_key, array('HS256'));
        // $username = $decoded->data->username;
        // Access is granted.        

        // Database Connectivity
        $conn = null;
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        // 1) save course
        $sql = "CALL createTrack (:userId,
            :cdGame, :courseName, :cdType, :cdSeries,
            :cdSharing, :designer, :reference,
            :cdCarTheme, :carId, :cdCarClass,
            :forzaRouteId, :customRoute,
            :laps, :cdSeason, :cdTimeOfDay, :cdWeather, :cdTimeProgression,
            :defaultLapTimeMin, :distanceKM, :sharingCode, :cdDifficulty,
            :description,
            @trackId)";
        
        $stmt = $conn->prepare($sql);
        
        $stmt->bindParam(':userId', $createdId, PDO::PARAM_INT);

        $stmt->bindParam(':cdGame', $gameCode, PDO::PARAM_INT);
        $stmt->bindParam(':courseName', $name, PDO::PARAM_STR);
        $stmt->bindParam(':cdType', $typeCode, PDO::PARAM_INT);
        $stmt->bindParam(':cdSeries', $seriesCode, PDO::PARAM_INT);

        $stmt->bindParam(':cdSharing', $sharingModeCode, PDO::PARAM_INT);
        $stmt->bindParam(':designer', $designedBy, PDO::PARAM_STR);
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

        $stmt->bindParam(':defaultLapTimeMin', $defaultLapTimeMin, PDO::PARAM_INT);
        $stmt->bindParam(':distanceKM', $distanceKM, PDO::PARAM_INT);
        $stmt->bindParam(':sharingCode', $sharingCode, PDO::PARAM_INT);
        $stmt->bindParam(':cdDifficulty', $difficultyCode, PDO::PARAM_INT);

        $stmt->bindParam(':description', $description, PDO::PARAM_STR);

        $stmt->execute();

        // get trackId for later use (OUT parameter)
        $row = $conn->query("SELECT @trackId AS trackId")->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $trackId = $row['trackId'];
        }        

        // 2) save terrain selection
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
        echo json_encode(array("id" => $trackId));        

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