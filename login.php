<?php
include_once './config/database.php';
require "./vendor/autoload.php";
use \Firebase\JWT\JWT;

// Header Variables
$username = '';
$password = '';

// Read Body
$data = json_decode(file_get_contents("php://input"));

// Extract Body
$username = $data->username;
$password = $data->password;

// Database Connectivity
$conn = null;
$databaseService = new DatabaseService();
$conn = $databaseService->getConnection();

$sql = "CALL loginUser(:username)";

try {
	$stmt = $conn->prepare($sql);
	
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);

    $stmt->execute();

    // get the actual result
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $cnt = $stmt->rowCount();

    if ($cnt == 1) {
        // get data
        // eigentlich nicht ideal, die ganzen user-infos an dieser stelle zu lesen (redundanz, getUserInfo)
        // künftig evtl. eine prozedur "doLogin" mit IN-Param IP-adresse (wäre auch nach register nützlich)
        // --> Rückgabe weiter unten nicht vergessen!
        $userId = $row['USR_UserId'];
        $created = $row['USR_Created'];
        $modified = $row['USR_Modified'];
        $loginActive = $row['USR_LoginActive'];
        $passwordDB = $row['USR_Password'];
        $roleCode = $row['COD_Role'];
        $roleText = $row['TXT_Role'];
        $languageCode = $row['COD_Language'];
        $languageText = $row['TXT_Language'];
        $xBox = $row['USR_XBoxTag'];
        $discord = $row['USR_DiscordName'];
        
        if (password_verify($password, $passwordDB)) {
    
            // define token
            // ToDo: schlauere werte überlegen
            $secret_key = "YOUR_SECRET_KEY"; // ToDo: irgendwas
            $issuer_claim = "EIERWERFER.COM"; // this can be the servername
            $audience_claim = "EW-RACING"; // client name (angular app)
            $issuedat_claim = time(); // issued at
            $notbefore_claim = $issuedat_claim; // activate immediately (client will navigate url)
            $expire_claim = $issuedat_claim + 3600; // expire time in seconds ToDo: 1 Stunde?
    
            // build token
            $token = array(
                "iss" => $issuer_claim,
                "aud" => $audience_claim,
                "iat" => $issuedat_claim,
                "nbf" => $notbefore_claim,
                "exp" => $expire_claim,
                // what's included inside ("core")
                "data" => array(
                    "userId" => $userId,
                    "username" => $username,
					// should not be used, subject to change (get from database)
                    "loginActive" => $loginActive,
                    "roleCode" => $roleCode
                )
            );
    
            $jwt = JWT::encode($token, $secret_key);
    
            // build final response (must match type-def in angular client)
            echo json_encode(
                array(
                    "userId" => $userId,
                    "created" => $created,
                    "modified" => $modified,
                    "username" => $username,
                    "password" => '',                
                    "loginActive" => $loginActive,
                    "roleCode" => $roleCode,
                    "roleText" => $roleText,
                    "languageCode" => $languageCode,
                    "languageText" => $languageText,
                    "xBox" => $xBox,
                    "discord" => $discord,
                    "token" => $jwt,
                    "profilePictureUrl" => ''
                    // evtl. ncoch => $exp mitgeben zur info (expireAt)
                )
            );                       
    
            http_response_code(200);
        }
        else {
        // Header gemäss Wikipedia ;-)     
        header('WWW-Authenticate: invalid user/password.');
        echo json_encode(array("message" => "Login failed."));        
        http_response_code(401);
        }
    
        // aufräumen unabhängig vom Result (pwd check)
        $stmt->closeCursor();        
    }
    else {
        // user not found or multiple records returned (internal/data error)
        if ($cnt > 1) {
            $stmt->closeCursor();
        }
        header('WWW-Authenticate: invalid user/password');
        echo json_encode(array("message" => "Login failed."));
        http_response_code(401);
    }

} catch (PDOException $ex) {
    // technical error
    http_response_code(400);
    die(json_encode(array("message" => $ex->getMessage())));
}
?>