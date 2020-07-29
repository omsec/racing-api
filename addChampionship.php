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

// payload
$gameCode = $data->gameCode;
$blueprintName = $data->blueprintName;
$description = $data->description;

// tags or whatever to come (array if multi-value)
// $terrain = $data->terrain;

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, $secret_key, array('HS256'));
        // $username = $decoded->data->username;
        // Access is granted.        

        // Database Connectivity
        $conn = null;
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        // save championship
        $sql = "CALL createChampionship(
            :userId, :cdSharingMode,
            :cdGame, :blueprintName, :description,
            @championshipId)";
        
        $stmt = $conn->prepare($sql);
        
        $stmt->bindParam(':userId', $createdId, PDO::PARAM_INT);
        $stmt->bindParam(':cdSharingMode', $sharingModeCode, PDO::PARAM_INT);

        $stmt->bindParam(':cdGame', $gameCode, PDO::PARAM_INT);
        $stmt->bindParam(':blueprintName', $blueprintName, PDO::PARAM_STR);
        if (empty($description)) {
            $description = null;
        }
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);

        $stmt->execute();

        // get ID for later return (OUT parameter)
        $row = $conn->query("SELECT @championshipId AS id")->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $id = $row['id'];
        }        
/*
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
*/        

        http_response_code(200);
        echo json_encode(array("id" => $id));        

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