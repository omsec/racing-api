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
$trackId = $data->trackId;

// not needed
// $username = '';

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

        // start with nested objects to assemble final response
        // terrain
        $sql = "CALL listMvcEntries(:tableRef, :recordId, :codeType)";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':tableRef', ApiService::tbl_RAT, PDO::PARAM_STR);
        $stmt->bindParam(':recordId', $trackId, PDO::PARAM_INT);                
        $stmt->bindValue(':codeType', ApiService::ct_Terrain, PDO::PARAM_STR);        

        $stmt->execute();
        $cnt = $stmt->rowCount();
        if ($cnt > 0) {
            // get values (codeDefinition array)
            $idx = 0;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $terrain[$idx]['code'] = $row['COD_Value'];
                $terrain[$idx]['text'] = $row['TXT_Value'];
                $idx++;
            }
            $stmt->closeCursor();
        }
        else {
            $terrain = null; // gemäss model required ;-)
        }
        
        // images (URLs)
        $sql = "CALL listMvfEntries(:tableRef, :recordId)";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':tableRef', ApiService::tbl_RAT, PDO::PARAM_STR);
        $stmt->bindParam(':recordId', $trackId, PDO::PARAM_INT);

        $stmt->execute();
        $cnt = $stmt->rowCount();

        if ($cnt > 0) {
            // get data & form URL (client must "prefix" domain name)
            $idx = 0;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $images[$idx]['createdById'] = $row['USR_CreatedBy'];
                $images[$idx]['userName'] = $imageUrl . $row['USR_LoginName'];
                $images[$idx]['xBoxName'] = $row['USR_XBoxTag'];
                $images[$idx]['discordName'] = $row['USR_DiscordName'];
                $images[$idx]['uploadedDT'] = $row['MVF_Uploaded'];                
                $images[$idx]['imageURL'] =  $apiService->getImageUrl() . basename($row['MVF_FileName']);
                $images[$idx]['originalFileName'] = $row['MVF_Original'];
                $images[$idx]['description'] = $row['MVF_Description'];
                $idx++;
            }
            $stmt->closeCursor();
        }
        else {
            $images = null;
        }

        // get main data
        $sql = "CALL readTrack(:trackId, :cdLanguage, :userId)";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':trackId', $trackId, PDO::PARAM_INT);
        $stmt->bindParam(':cdLanguage', $languageCode, PDO::PARAM_INT);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $cnt = $stmt->rowCount();        

        if ($cnt == 1) {
            // build object parts
            $metaInfo = array(
                "id" => $row['RAT_TrackId'],
                "createdDT" => $row['RAT_Created'],
                "createdId" => $row['USR_CreatedBy'],
                "createdName" => $row['USR_CreatedByName'],
                "modifiedDT" => $row['RAT_Modified'],
                "modifiedId" => $row['USR_ModifiedBy'],
                "modifiedName" => $row['USR_ModifiedByName'],
                "sharingModeCode" => $row['COD_Sharing'],
				"sharingModeText" => $row['TXT_Sharing']
            );

            $ratingInfo = array(
                "rating" => $row['RAV_Rating'],
                "upVotes" => $row['RAV_UpVotes'],
                "downVotes" => $row['RAV_DownVotes'],
                "userVote" => $row['RAV_UserVote']
            );

            // build response
            echo json_encode(array(
                "metaInfo" => $metaInfo,
                // std info
                "gameCode" => $row['COD_Game'],
                "gameText" => $row['TXT_Game'],
                "name" => $row['RAT_Name'],
                "typeCode" => $row['COD_Type'],
                "typeText" => $row['TXT_Type'],
                "seriesCode" => $row['COD_Series'],
                "seriesText" => $row['TXT_Series'],
                // cst info block I
                "designedBy" => $row['RAT_Designer'],
                "externalId" => $row['RAT_Reference'],
                // restrictions
                "carThemeCode" => $row['COD_CarTheme'],
                "carThemeText" => $row['TXT_CarTheme'],
                "carId" => $row['VEC_CarId'],
                "carName" => $row['VEC_CarName'],
                "carClassCode" => $row['COD_CarClass'],
                "carClassText" => $row['TXT_CarClass'],
                // routing
                "forzaRouteId" => $row['RAT_ForzaRouteId'],
                "forzaRouteName" => $row['RAT_ForzaRouteName'],
                "customRoute" => $row['RAT_CustomRoute'],
                // cst info block II
                "standardLaps" => $row['RAT_Laps'],
                "seasonCode" => $row['COD_Season'],
                "seasonText" => $row['TXT_Season'],
                "daytimeCode" => $row['COD_TimeOfDay'],
                "daytimeText" => $row['TXT_TimeOfDay'],
                "weatherCode" => $row['COD_Weather'],
                "weatherText" => $row['TXT_Weather'],
                "timeProgressionCode" => $row['COD_TimeProgression'],
                "timeProgressionText" => $row['TXT_TimeProgression'],
                // cst info block III
                "defaultLapTimeSec" => $row['RAT_DefaultLapTimeSec'],
                "distanceKM" => $row['RAT_DistanceKM'],
                "sharingCode" => $row['RAT_SharingCode'],
				"difficultyCode" => $row['COD_Difficulty'],
                "difficultyText" => $row['TXT_Difficulty'],
                "description" => $row['RAT_Description'],
                // additional
                "terrain" => $terrain,
                "ratingInfo" => $ratingInfo,
                "images" => $images
            ));

            $stmt->closeCursor();
            http_response_code(200);
        } else {
            // no or many records found (data error)            
            echo json_encode(array("message" => "No Data found."));            
            http_response_code(404); // no data found; oder 200??
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