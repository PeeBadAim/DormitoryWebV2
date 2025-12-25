<?php
// api/get_messages.php
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_GET['receiver_id'])) {
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$receiver_id = $_GET['receiver_id'];

// ดึงข้อความ
$query = "SELECT *, 
          CASE WHEN sender_id = :user_id THEN 1 ELSE 0 END as is_sent
          FROM chat_messages 
          WHERE (sender_id = :user_id AND receiver_id = :receiver_id) 
          OR (sender_id = :receiver_id AND receiver_id = :user_id)
          ORDER BY created_at ASC";

$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->bindParam(':receiver_id', $receiver_id);
$stmt->execute();

$messages = $stmt->fetchAll();

// ทำเครื่องหมายว่าอ่านแล้ว
$update_query = "UPDATE chat_messages 
                SET is_read = 1 
                WHERE sender_id = :receiver_id 
                AND receiver_id = :user_id 
                AND is_read = 0";
$update_stmt = $db->prepare($update_query);
$update_stmt->bindParam(':receiver_id', $receiver_id);
$update_stmt->bindParam(':user_id', $user_id);
$update_stmt->execute();

echo json_encode(['messages' => $messages]);
?>