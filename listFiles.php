<?php
include_once './config/api.php';
include_once './config/database.php';
require "./vendor/autoload.php";
use \Firebase\JWT\JWT;

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

// Extract Body
$objectType = $data->objectType;
$objectId = $data->objectId;

$apiService = new ApiService();
$secret_key = null;
$secret_key = $apiService->getApiKey();

// response data (content)
$images = [];

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, $secret_key, array('HS256'));        
        // Access is granted - get actual data        

        // Databae Connectivity
        $conn = null;
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        $sql = "CALL listMvfEntries(:tableRef, :recordId)";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':tableRef', $objectType, PDO::PARAM_STR);
        $stmt->bindParam(':recordId', $objectId, PDO::PARAM_INT);

        $stmt->execute();
        $cnt = $stmt->rowCount();

        if ($cnt > 0) {
            // return data
            $idx = 0;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $images[$idx]['id'] = $row['MVF_RowId'];
                $images[$idx]['createdById'] = $row['USR_CreatedBy'];
                $images[$idx]['userName'] = $row['USR_LoginName'];
                $images[$idx]['xBoxName'] = $row['USR_XBoxTag'];
                $images[$idx]['discordName'] = $row['USR_DiscordName'];
                $images[$idx]['uploadedDT'] = $row['MVF_Uploaded'];
                $images[$idx]['imageURL'] = $apiService->getImageUrl() . $row['MVF_FileName']; // client must complete URL with server name
                $images[$idx]['originalFileName'] = $row['MVF_Original'];
                $images[$idx]['description'] = $row['MVF_Description'];
                $idx++;
            }

            $stmt->closeCursor();            
            
            echo json_encode($images);
            http_response_code(200);
        } else {
            // empty result
            echo json_encode(array(null));            
            http_response_code(204); // no data found
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