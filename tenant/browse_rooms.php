<?php
// tenant/browse_rooms.php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tenant') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

$message = '';

// จองห้อง
if (isset($_POST['book_room'])) {
    $room_id = $_POST['room_id'];
    $move_in_date = $_POST['move_in_date'];
    $notes = sanitize_input($_POST['notes']);
    
    // ตรวจสอบว่าห้องว่างหรือไม่
    $check_query = "SELECT status FROM rooms WHERE room_id = :room_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':room_id', $room_id);
    $check_stmt->execute();
    $room = $check_stmt->fetch();
    
    if ($room && $room['status'] == 'available') {
        $query = "INSERT INTO bookings (room_id, user_id, move_in_date, notes) 
                  VALUES (:room_id, :user_id, :move_in, :notes)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':room_id', $room_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':move_in', $move_in_date);
        $stmt->bindParam(':notes', $notes);
        
        if ($stmt->execute()) {
            // แจ้งเตือนเจ้าของหอ
            $owner_query = "SELECT user_id FROM users WHERE role = 'owner' LIMIT 1";
            $owner_stmt = $db->prepare($owner_query);
            $owner_stmt->execute();
            $owner = $owner_stmt->fetch();
            
            if ($owner) {
                send_notification($owner['user_id'], 'คำขอจองใหม่', 
                    $_SESSION['full_name'] . ' ขอจองห้องพัก', 'booking', $db->lastInsertId());
            }
            
            $message = '<div class="alert alert-success">ส่งคำขอจองห้องสำเร็จ! รอการอนุมัติจากเจ้าของหอพัก</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">ห้องนี้ไม่ว่างแล้ว</div>';
    }
}

// ดึงห้องที่ว่าง
$query = "SELECT * FROM rooms WHERE status = 'available' ORDER BY floor, room_number";
$stmt = $db->prepare($query);
$stmt->execute();
$rooms = $stmt->fetchAll();

// ดึงการจองของผู้ใช้
$bookings_query = "SELECT b.*, r.room_number, r.monthly_rent
                  FROM bookings b
                  JOIN rooms r ON b.room_id = r.room_id
                  WHERE b.user_id = :user_id
                  ORDER BY b.booking_date DESC";
$bookings_stmt = $db->prepare($bookings_query);
$bookings_stmt->bindParam(':user_id', $user_id);
$bookings_stmt->execute();
$my_bookings = $bookings_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จองห้องพัก</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .room-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .room-card {
            background: var(--white);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s;
        }
        
        .room-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        
        .room-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
        }
        
        .room-details {
            padding: 20px;
        }
        
        .room-number {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .room-price {
            font-size: 28px;
            font-weight: 700;
            color: var(--accent-color);
            margin: 15px 0;
        }
        
        .room-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin: 15px 0;
            color: var(--dark-gray);
            font-size: 14px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="#" class="logo">🏢 ระบบจัดการหอพัก</a>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="browse_rooms.php" class="nav-link">ดูห้องพัก</a></li>
                <li><a href="my_bills.php" class="nav-link">บิลของฉัน</a></li>
                <li><a href="maintenance.php" class="nav-link">แจ้งซ่อม</a></li>
                <li><a href="chat.php" class="nav-link">💬 แชท</a></li>
                <li><a href="../logout.php" class="nav-link">ออกจากระบบ</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <?php echo $message; ?>
        
        <h1 style="margin: 30px 0;">🏠 ห้องพักว่าง</h1>
        
        <div class="room-grid">
            <?php foreach($rooms as $room): ?>
                <div class="room-card">
                    <div class="room-image">🏠</div>
                    <div class="room-details">
                        <div class="room-number">ห้อง <?php echo $room['room_number']; ?></div>
                        <span class="badge badge-info"><?php echo $room['room_type']; ?></span>
                        
                        <div class="room-price">฿<?php echo number_format($room['monthly_rent'], 0); ?>/เดือน</div>
                        
                        <div class="room-info">
                            <div>📍 ชั้น <?php echo $room['floor']; ?></div>
                            <div>💧 ค่าน้ำ ฿<?php echo number_format($room['water_rate_per_unit'], 2); ?>/หน่วย</div>
                            <div>⚡ ค่าไฟ ฿<?php echo number_format($room['electric_rate_per_unit'], 2); ?>/หน่วย</div>
                        </div>
                        
                        <?php if($room['description']): ?>
                            <p style="color: var(--dark-gray); font-size: 14px; margin: 15px 0;">
                                <?php echo $room['description']; ?>
                            </p>
                        <?php endif; ?>
                        
                        <button class="btn btn-primary" style="width: 100%;" 
                                onclick='openBookingModal(<?php echo json_encode($room); ?>)'>
                            จองห้องนี้
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if(count($my_bookings) > 0): ?>
            <div class="card" style="margin-top: 40px;">
                <div class="card-header">📋 ประวัติการจองของฉัน</div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>เลขห้อง</th>
                                <th>ค่าเช่า</th>
                                <th>วันที่เข้าพัก</th>
                                <th>วันที่จอง</th>
                                <th>สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($my_bookings as $booking): ?>
                                <tr>
                                    <td><strong><?php echo $booking['room_number']; ?></strong></td>
                                    <td>฿<?php echo number_format($booking['monthly_rent'], 2); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($booking['move_in_date'])); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($booking['booking_date'])); ?></td>
                                    <td>
                                        <?php 
                                        $status_map = [
                                            'pending' => ['warning', 'รออนุมัติ'],
                                            'approved' => ['success', 'อนุมัติแล้ว'],
                                            'rejected' => ['danger', 'ปฏิเสธ'],
                                            'cancelled' => ['danger', 'ยกเลิก']
                                        ];
                                        $status = $status_map[$booking['status']];
                                        ?>
                                        <span class="badge badge-<?php echo $status[0]; ?>"><?php echo $status[1]; ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal จองห้อง -->
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">จองห้องพัก</h2>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="bookingForm">
                    <input type="hidden" name="room_id" id="roomId">
                    
                    <div id="roomInfo" style="background: var(--light-gray); padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <!-- Room info will be loaded here -->
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">วันที่ต้องการเข้าพัก *</label>
                        <input type="date" name="move_in_date" class="form-control" required 
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">หมายเหตุ</label>
                        <textarea name="notes" class="form-control" placeholder="ข้อความถึงเจ้าของหอ (ถ้ามี)"></textarea>
                    </div>
                    
                    <button type="submit" name="book_room" class="btn btn-primary" style="width: 100%;">
                        ยืนยันการจอง
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openBookingModal(room) {
            document.getElementById('roomId').value = room.room_id;
            document.getElementById('roomInfo').innerHTML = `
                <h3 style="margin-bottom: 15px;">ห้อง ${room.room_number}</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div><strong>ประเภท:</strong> ${room.room_type}</div>
                    <div><strong>ชั้น:</strong> ${room.floor}</div>
                    <div><strong>ค่าเช่า:</strong> ฿${parseFloat(room.monthly_rent).toLocaleString()}/เดือน</div>
                    <div><strong>ค่าน้ำ:</strong> ฿${parseFloat(room.water_rate_per_unit).toFixed(2)}/หน่วย</div>
                    <div><strong>ค่าไฟ:</strong> ฿${parseFloat(room.electric_rate_per_unit).toFixed(2)}/หน่วย</div>
                </div>
            `;
            document.getElementById('bookingModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('bookingModal').classList.remove('active');
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>