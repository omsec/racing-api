<?php
// https://www.techiediaries.com/php-file-upload-tutorial/

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

// helpers
define("fileId", "fileName"); // form data field
$response = array();
$upload_dir = $apiService->getUploadDir();
$objType = "USR"; // static for this service

if ($jwt) {
    try {
        $decoded = JWT::decode($jwt, $secret_key, array('HS256'));
        $userId = $decoded->data->userId;
        // Access is granted.        

        // 1) upload file (format: rand_origName)
        if ($_FILES[fileId])
        {
            $file_name = $_FILES[fileId]["name"];
            $temp_name = $_FILES[fileId]["tmp_name"];
            $error = $_FILES[fileId]["error"];

            // https://www.php.net/manual/en/features.file-upload.errors.php
            if ($error == 0) {
                // $random_name = rand(1000, 1000000) . "-" . $file_name;
                // $ext = substr(strrchr($fileName, '.'), 1); removes extenstions (jpg)
                $random_name = $objType . "_" . $userId . "_" . rand(1000, 1000000) . strrchr($file_name, '.');
                $upload_name = $upload_dir.strtolower($random_name);

                $upload_name = preg_replace('/\s+/', '-', $upload_name);

                // move from tmp dir to final destination
                if (move_uploaded_file($temp_name, $upload_name)) {
                    $conn = null;
                    $databaseService = new DatabaseService();
                    $conn = $databaseService->getConnection();

                    // 2) remove previously uploaded profile picture
                    $sql = "CALL deleteMvfEntries(:tableRef, :recordId)";                    
                    $stmt = $conn->prepare($sql);

                    $stmt->bindValue(':tableRef', $objType, PDO::PARAM_STR); // table prefix
                    $stmt->bindParam(':recordId', $userId, PDO::PARAM_INT);

                    $stmt->execute();
                
                    // 3) register file with database repo
                    $sql = "CALL createMvfEntry(:userId,
                        :tableRef,
                        :recordId,
                        :fileName,
                        :original,
                        :description)";
                    
                    $stmt = $conn->prepare($sql);
                    
                    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
                    $stmt->bindValue(':tableRef', $objType, PDO::PARAM_STR); // table prefix
                    $stmt->bindParam(':recordId', $userId, PDO::PARAM_INT);
                    $stmt->bindValue(':fileName', basename($upload_name), PDO::PARAM_STR);
                    $stmt->bindParam(':original', $file_name, PDO::PARAM_STR);
                    $stmt->bindValue(':description', null, PDO::PARAM_STR);

                    $stmt->execute();
                    // errors raise exception                

                    $response = array(
                        "status" => "success",
                        "error" => false,
                        "message" => "File uploaded successfully",
                        "url" => $apiService->getImageUrl() . basename($upload_name) // client must complete URL with server name
                        );
                                            
                    http_response_code(200);
                }
                else {
                    $response = array(
                        "status" => "error",
                        "error" => true,
                        "message" => "Error uploading the file!"
                    );
                    http_response_code(500);
                }
            }
            else {
                $response = array(
                    "status" => "error",
                    "error" => true,
                    "message" => "Error uploading the file!"
                );
                http_response_code(500);
            }

        }
        else {
            $response = array(
                "status" => "error",
                "error" => true,
                "message" => "Error uploading the file!"
            );
            http_response_code(500);         
        }

        // http status set above
        echo json_encode($response);

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