<?php
include_once './config/variables.php';
// used to get API/Token-Key
class ApiService{
    private $secret_key = API_KEY; 
    private $imageURL = "/images/uploads/";
    private $uploadDir = "../images/uploads/";

    // table prefixes (public)
    const tbl_RAT = "RAT";
    const tbl_USR = "USR";
    const tbl_CMT = "CMT";

    // Code Types
    const ct_Terrain = "Terrain";

    public function getApiKey() {
        return $this->secret_key;
    }

    public function getImageUrl() {
        return $this->imageURL;
    }

    public function getUploadDir() {
        return $this->uploadDir;
    }
}
?>