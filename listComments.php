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
$objectType = $data->objectType;
$objectId = $data->objectId;

// Variables
$items = [];

// https://stackoverflow.com/questions/29384548/php-how-to-build-tree-structure-list
function buildTree(array $elements, $commentId = 0) {
    $branch = array();

    foreach ($elements as $element) {
        if ($element['parentId'] == $commentId) {
            $answers = buildTree($elements, $element['id']);
            if ($answers) {
                $element['answers'] = $answers;
            }
            $branch[] = $element;
        }
    }

    return $branch;
}

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, $secret_key, array('HS256'));
        $userId = $decoded->data->userId;
        // Access is granted.

        // Database Connectivity
        $conn = null;
        $databaseService = new DatabaseService();
        $conn = $databaseService->getConnection();

        $sql = "CALL listComments(:userId, :tableRef, :recordId)";
        $stmt = $conn->prepare($sql);

        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':tableRef', $objectType, PDO::PARAM_STR);
        $stmt->bindParam(':recordId', $objectId, PDO::PARAM_INT);

        $stmt->execute();
        $cnt = $stmt->rowCount();

        $idx = 0;
        if ($cnt > 0) {
            // build flat list of comments
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $items[$idx]['id'] = $row['CMT_RowId'];
                $items[$idx]['objectType'] = $objectType;
                $items[$idx]['objectId'] = $objectId;
                $items[$idx]['createdDT'] = $row['CMT_Created'];
                $items[$idx]['createdId'] = $row['USR_CreatedBy'];
                $items[$idx]['createdName'] = $row['USR_CreatedByName'];                
                if (!is_null($row['MVF_ProfilePicture'])) {
                    $items[$idx]['createdPic'] =  $apiService->getImageUrl() . basename($row['MVF_ProfilePicture']);
                } else {
                    $items[$idx]['createdPic'] = null;
                }
                $items[$idx]['modifiedDT'] = $row['CMT_Modified'];
                $items[$idx]['modifiedId'] = $row['USR_ModifiedBy'];
                $items[$idx]['modifiedName'] = $row['USR_ModifiedByName'];
                $items[$idx]['parentId'] = $row['CMT_Parent'];                
                $items[$idx]['statusCode'] = $row['COD_Status'];
                $items[$idx]['statusText'] = $row['TXT_Status'];
                $items[$idx]['commentText'] = $row['CMT_Comment'];
                $items[$idx]['upVotes'] = $row['RAV_UpVotes'];
                $items[$idx]['downVotes'] = $row['RAV_DownVotes'];
                $items[$idx]['userVote'] = $row['RAV_UserVote'];
                $idx++;
            }                        

            $tree = buildTree($items);
            $stmt->closeCursor();
        } else {
            // return empty list
            $tree = $items;
        }
        
        echo json_encode($tree);
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