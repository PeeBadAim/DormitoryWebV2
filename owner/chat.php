<?php
// owner/chat.php
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// ส่งข้อความ
if (isset($_POST['send_message'])) {
    $receiver_id = $_POST['receiver_id'];
    $message = sanitize_input($_POST['message']);
    
    $query = "INSERT INTO chat_messages (sender_id, receiver_id, message) 
              VALUES (:sender, :receiver, :message)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':sender', $user_id);
    $stmt->bindParam(':receiver', $receiver_id);
    $stmt->bindParam(':message', $message);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
    exit();
}

// ดึงรายชื่อผู้ติดต่อ
if ($_SESSION['role'] == 'owner') {
    $contacts_query = "SELECT DISTINCT u.user_id, u.full_name, u.role,
                      (SELECT message FROM chat_messages 
                       WHERE (sender_id = u.user_id AND receiver_id = :user_id) 
                       OR (sender_id = :user_id AND receiver_id = u.user_id)
                       ORDER BY created_at DESC LIMIT 1) as last_message,
                      (SELECT created_at FROM chat_messages 
                       WHERE (sender_id = u.user_id AND receiver_id = :user_id) 
                       OR (sender_id = :user_id AND receiver_id = u.user_id)
                       ORDER BY created_at DESC LIMIT 1) as last_time,
                      (SELECT COUNT(*) FROM chat_messages 
                       WHERE sender_id = u.user_id AND receiver_id = :user_id AND is_read = 0) as unread_count
                      FROM users u
                      WHERE u.user_id != :user_id AND u.status = 'active'
                      AND (u.role = 'tenant' OR u.role = 'technician')
                      ORDER BY last_time DESC";
} else {
    $contacts_query = "SELECT u.user_id, u.full_name, u.role,
                      (SELECT message FROM chat_messages 
                       WHERE (sender_id = u.user_id AND receiver_id = :user_id) 
                       OR (sender_id = :user_id AND receiver_id = u.user_id)
                       ORDER BY created_at DESC LIMIT 1) as last_message,
                      (SELECT created_at FROM chat_messages 
                       WHERE (sender_id = u.user_id AND receiver_id = :user_id) 
                       OR (sender_id = :user_id AND receiver_id = u.user_id)
                       ORDER BY created_at DESC LIMIT 1) as last_time
                      FROM users u
                      WHERE u.role = 'owner' AND u.status = 'active'
                      ORDER BY last_time DESC";
}

$contacts_stmt = $db->prepare($contacts_query);
$contacts_stmt->bindParam(':user_id', $user_id);
$contacts_stmt->execute();
$contacts = $contacts_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แชท</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .chat-container {
            display: grid;
            grid-template-columns: 350px 1fr;
            height: calc(100vh - 200px);
            gap: 0;
            background: var(--white);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .contacts-list {
            border-right: 2px solid var(--light-gray);
            overflow-y: auto;
        }
        
        .contact-item {
            padding: 15px 20px;
            border-bottom: 1px solid var(--light-gray);
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .contact-item:hover, .contact-item.active {
            background: var(--light-gray);
        }
        
        .contact-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .contact-last-message {
            font-size: 13px;
            color: var(--dark-gray);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .chat-area {
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            padding: 20px;
            border-bottom: 2px solid var(--light-gray);
            background: var(--white);
        }
        
        .messages-container {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f5f5f5;
        }
        
        .message {
            margin-bottom: 15px;
            display: flex;
        }
        
        .message.sent {
            justify-content: flex-end;
        }
        
        .message-bubble {
            max-width: 60%;
            padding: 12px 16px;
            border-radius: 18px;
            word-wrap: break-word;
        }
        
        .message.received .message-bubble {
            background: var(--white);
            border-bottom-left-radius: 4px;
        }
        
        .message.sent .message-bubble {
            background: var(--primary-color);
            color: var(--white);
            border-bottom-right-radius: 4px;
        }
        
        .message-time {
            font-size: 11px;
            color: var(--dark-gray);
            margin-top: 5px;
        }
        
        .message.sent .message-time {
            color: rgba(255,255,255,0.7);
        }
        
        .chat-input-area {
            padding: 20px;
            border-top: 2px solid var(--light-gray);
            background: var(--white);
        }
        
        .chat-input-form {
            display: flex;
            gap: 10px;
        }
        
        .chat-input {
            flex: 1;
        }
        
        .unread-badge {
            background: var(--danger-color);
            color: var(--white);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="#" class="logo">🏢 ระบบจัดการหอพัก</a>
            <ul class="nav-menu">
                <?php if($_SESSION['role'] == 'owner'): ?>
                    <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                    <li><a href="rooms.php" class="nav-link">จัดการห้องพัก</a></li>
                    <li><a href="bookings.php" class="nav-link">คำขอจอง</a></li>
                    <li><a href="tenants.php" class="nav-link">ผู้เช่า</a></li>
                    <li><a href="bills.php" class="nav-link">บิล/ชำระเงิน</a></li>
                    <li><a href="maintenance.php" class="nav-link">แจ้งซ่อม</a></li>
                <?php endif; ?>
                <li><a href="chat.php" class="nav-link">💬 แชท</a></li>
                <li><a href="../logout.php" class="nav-link">ออกจากระบบ</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <h1 style="margin: 30px 0;">💬 ข้อความ</h1>
        
        <div class="chat-container">
            <div class="contacts-list">
                <?php foreach($contacts as $contact): ?>
                    <div class="contact-item" onclick="loadChat(<?php echo $contact['user_id']; ?>, '<?php echo htmlspecialchars($contact['full_name']); ?>')">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div style="flex: 1;">
                                <div class="contact-name"><?php echo $contact['full_name']; ?></div>
                                <div class="contact-last-message"><?php echo $contact['last_message'] ?? 'ยังไม่มีข้อความ'; ?></div>
                            </div>
                            <?php if(isset($contact['unread_count']) && $contact['unread_count'] > 0): ?>
                                <span class="unread-badge"><?php echo $contact['unread_count']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="chat-area">
                <div class="chat-header">
                    <h3 id="chatHeaderName">เลือกผู้ติดต่อเพื่อเริ่มแชท</h3>
                </div>
                
                <div class="messages-container" id="messagesContainer">
                    <div style="text-align: center; padding: 50px; color: var(--dark-gray);">
                        เลือกผู้ติดต่อจากรายชื่อทางซ้าย
                    </div>
                </div>
                
                <div class="chat-input-area" id="chatInputArea" style="display: none;">
                    <form class="chat-input-form" onsubmit="sendMessage(event)">
                        <input type="hidden" id="receiverId">
                        <input type="text" id="messageInput" class="form-control chat-input" 
                               placeholder="พิมพ์ข้อความ..." required>
                        <button type="submit" class="btn btn-primary">ส่ง</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/chat.js"></script>
</body>
</html>