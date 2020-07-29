<?php
include_once './config/database.php';
include_once './config/api.php';
require "./vendor/autoload.php";
use \Firebase\JWT\JWT;

$apiService = new ApiService();
$secret_key = null;
$secret_key = $apiService->getApiKey();
$imageUrl = null; // $apiService->getImageUrl();
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
// weil alle user angezeigt werden können sollen
// wird nicht der aktuelle aus dem Token gelesen
$userId = $data->profileId;

// embbed objects (by convention)
$metaInfo = null;
$ratingInfo = null;

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, $secret_key, array('HS256'));
        // $username = $decoded->data->username;
        $username = $decoded->data->username;
        // Access is granted.

        // Database Connectivity
        $conn = null;
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        // profile picture URL (using reduced std SP)
        $sql = "CALL listMvfEntries(:tableRef, :recordId)";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':tableRef', ApiService::tbl_USR, PDO::PARAM_STR);
        $stmt->bindParam(':recordId', $userId, PDO::PARAM_INT);

        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $cnt = $stmt->rowCount();

        // zero or one by convention
        $profilePictureUrl = '';
        if ($cnt == 1) {
            // URL does not contain host name/domain => must be added by the client
            $profilePictureUrl = $apiService->getImageUrl() . basename($row['MVF_FileName']);
            $stmt->closeCursor();
        }

        $sql = "CALL readUser(:userId, :cdLanguage)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':cdLanguage', $languageCode, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $cnt = $stmt->rowCount();

        if ($cnt == 1) {
            // build response
            // convention: pwd & jew is not returned
            echo json_encode(array(
                "userId" => $userId,
                "created" => $row['USR_Created'],
                "modified" => $row['USR_Modified'],
                "username" => $row['USR_LoginName'],
                "password" => null,
                "loginActive" => $row['USR_LoginActive'],
                "roleCode" => $row['COD_Role'],
                "roleText" => $row['TXT_Role'],
                "languageCode" => $row['COD_Language'],
                "languageText" => $row['TXT_Language'],
                "xBox" => $row['USR_XBoxTag'],
                "discord" => $row['USR_DiscordName'],
                "lastSeen" => $row['USR_LastSeen'],
                "token" => null,
                "profilePictureUrl" => $profilePictureUrl
            ));

            $stmt->closeCursor();
            http_response_code(200);            
        } else {
            // no or many records found (data error)
            echo json_encode(array("message" => "No Data found."));            
            http_response_code(404); // hier 404 & ungültige Struktur => Fehler im Client provizieren, denn hier ist was faul
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