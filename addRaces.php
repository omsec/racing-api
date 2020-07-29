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

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, $secret_key, array('HS256'));
        // $username = $decoded->data->username;
        // Access is granted.

        // Database Connectivity
        $conn = null;
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        // prepare SQL
        $sql = "CALL createRace(
            :userId,
            :championship, :raceNo, :trackId,
            :cdSeries, :cdCarTheme, :carId, :cdCarClass,
            :cdSeason, :cdTimeOfDay, :cdWeather, :cdTimeProgression,
            @raceId)";

        $stmt = $conn->prepare($sql);

        foreach ($data as $race) {
            $createdId = $race->metaInfo->createdId;

            $championshipId = $race->championshipId;
            $raceNo = $race->raceNo;
            $trackId = $race->trackId;

            $seriesCode = $race->seriesCode;
            $carThemeCode = $race->carThemeCode;
            $carId = $race->carId;
            $carClassCode = $race->carClassCode;

            $seasonCode = $race->seasonCode;
            $daytimeCode = $race->dayTimeCode;
            $weatherCode = $race->weatherCode;
            $timeProgressionCode = $race->timeProgressionCode;

            $stmt->bindParam(':userId', $createdId, PDO::PARAM_INT);

            $stmt->bindParam(':championship', $championshipId, PDO::PARAM_INT);
            $stmt->bindParam(':raceNo', $raceNo, PDO::PARAM_INT);
            $stmt->bindParam(':trackId', $trackId, PDO::PARAM_INT);

            $stmt->bindParam(':cdSeries', $seriesCode, PDO::PARAM_INT);
            $stmt->bindParam(':cdCarTheme', $carThemeCode, PDO::PARAM_INT);
            $stmt->bindParam(':carId', $carId, PDO::PARAM_INT);
            $stmt->bindParam(':cdCarClass', $carClassCode, PDO::PARAM_INT);

            $stmt->bindParam(':cdSeason', $seasonCode, PDO::PARAM_INT);
            $stmt->bindParam(':cdTimeOfDay', $daytimeCode, PDO::PARAM_INT);
            $stmt->bindParam(':cdWeather', $weatherCode, PDO::PARAM_INT);
            $stmt->bindParam(':cdTimeProgression', $timeProgressionCode, PDO::PARAM_INT);

            $stmt->execute();

            // collect IDs of created Races in return-array (OUT parameter)
            $row = $conn->query("SELECT @raceId AS id")->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $raceIDs[] = $row['id'];
            }             
        }

        http_response_code(200);
        echo json_encode(array("IDs" => $raceIDs));

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