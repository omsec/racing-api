<?php
include_once './config/variables.php';

// used to get mysql database connection
class DatabaseService{

    private $db_host = DB_hostName;
    private $db_name = DB_databaseName;
    private $db_user = DB_userName;
    private $db_password = DB_password;
    private $connection;

    public function getConnection(){

        $this->connection = null;
        // tell PDO to throw all exceptions from database
        try{
            $this->connection = new PDO("mysql:host=" . $this->db_host . ";dbname=" . $this->db_name,
                $this->db_user, $this->db_password, 
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );
        }catch(PDOException $exception){
            echo "Connection failed: " . $exception->getMessage();
        }

        return $this->connection;
    }

    // TEST (success): return object (requires json_encode/code)
    /*
    public function getCredentials($username){
        $sql = "CALL readCredentials(:username, @userId, @userRoleCode)";

        try {
            $stmt = $this->connection->prepare($sql);

            $stmt->bindParam(':username', $username, PDO::PARAM_STR);    

            $stmt->execute();
            $stmt->closeCursor();

            // get the actual result (OUT parameters)
            $row = $this->connection->query("SELECT @userId AS userId, @userRoleCode AS userRoleCode")->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return json_encode(array(
                    "userId"=> $row['userId'],
                    "userRoleCode"=> $row['userRoleCode']
                ));
            }

        } catch (Exception $ex) {
            echo "DB-Error: " . $ex->getMessage();
            return "error";
        }
    }
    */
}
?>