<?php
include_once './config/database.php';

// Body Variables
$username = '';
$password = '';
$xboxName = '';
$discordName = '';
$languageCode = 0;

$conn = null;

$databaseService = new DatabaseService();
$conn = $databaseService->getConnection();

// Read Body
$data = json_decode(file_get_contents("php://input"));

// Extract Body
$username = $data->username;
$password = $data->password;
$xboxName = $data->xboxName;
$discordName = $data->discordName;
$languageCode = $data->languageCode;

$passwordHash = password_hash($password, PASSWORD_BCRYPT);

$sql = "CALL createUser(:username, :password, :xboxName, :discordName, :cdLanguage)";

try {
    $stmt = $conn->prepare($sql);

    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->bindParam(':password', $passwordHash, PDO::PARAM_STR);
    $stmt->bindParam(':xboxName', $xboxName, PDO::PARAM_STR);
    $stmt->bindParam(':discordName', $discordName, PDO::PARAM_STR);
    $stmt->bindParam(':cdLanguage', $languageCode, PDO::PARAM_INT);

    $stmt->execute();
    $stmt->closeCursor(); // bei insert nötig?

    http_response_code(200);
    echo json_encode(array("message" => "User was successfully registered."));
    
} catch (PDOException $ex) {
    http_response_code(400);
    die(json_encode(array("message" => $ex->getMessage())));
}
?>