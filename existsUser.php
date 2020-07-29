<?php
include_once './config/database.php';

// Body Variables
$username = '';

$conn = null;

$databaseService = new DatabaseService();
$conn = $databaseService->getConnection();

// Read Body
$data = json_decode(file_get_contents("php://input"));

// Extract Body
$username = $data->username;

$sql = "CALL existsUser(:username, @userExists)";

try {
    $stmt = $conn->prepare($sql);

    $stmt->bindParam(':username', $username, PDO::PARAM_STR);    

    $stmt->execute();
    $stmt->closeCursor();

    // get the actual result (OUT parameter)
    $row = $conn->query("SELECT @userExists AS userExists")->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo json_encode(array(
            "userExists" => $row['userExists']));
        http_response_code(200);        
    }

} catch (PDOException $ex) {
    http_response_code(400);
    // Fehlermeldung kann auch einbehalten werden (entspricht fachlich userExists = true/1)
    die(json_encode(array("message" => $ex->getMessage())));
}
?>