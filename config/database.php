<?php
// config/database.php
class Database {
    private $host = "localhost";
    private $db_name = "dorm_management";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        
        return $this->conn;
    }
}

// ฟังก์ชันช่วยเหลือ
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function generate_qr_code($data, $filename) {
    // ใช้ API สร้าง QR Code
    $qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($data);
    $qr_path = "../uploads/qrcodes/" . $filename;
    
    $qr_image = file_get_contents($qr_api);
    if ($qr_image !== false) {
        file_put_contents($qr_path, $qr_image);
        return "uploads/qrcodes/" . $filename;
    }
    return false;
}

function send_notification($user_id, $title, $message, $type, $related_id = null) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "INSERT INTO notifications (user_id, title, message, type, related_id) 
              VALUES (:user_id, :title, :message, :type, :related_id)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":title", $title);
    $stmt->bindParam(":message", $message);
    $stmt->bindParam(":type", $type);
    $stmt->bindParam(":related_id", $related_id);
    
    return $stmt->execute();
}

// เริ่ม Session
session_start();
?>