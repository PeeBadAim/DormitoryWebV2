<?php
// owner/rooms.php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'owner') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// จัดการ CRUD
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch($_POST['action']) {
            case 'add':
                $query = "INSERT INTO rooms (room_number, floor, room_type, monthly_rent, water_rate_per_unit, electric_rate_per_unit, description, status) 
                         VALUES (:room_number, :floor, :room_type, :monthly_rent, :water_rate, :electric_rate, :description, 'available')";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':room_number', $_POST['room_number']);
                $stmt->bindParam(':floor', $_POST['floor']);
                $stmt->bindParam(':room_type', $_POST['room_type']);
                $stmt->bindParam(':monthly_rent', $_POST['monthly_rent']);
                $stmt->bindParam(':water_rate', $_POST['water_rate']);
                $stmt->bindParam(':electric_rate', $_POST['electric_rate']);
                $stmt->bindParam(':description', $_POST['description']);
                $stmt->execute();
                break;
                
            case 'update':
                $query = "UPDATE rooms SET room_number = :room_number, floor = :floor, room_type = :room_type, 
                         monthly_rent = :monthly_rent, water_rate_per_unit = :water_rate, 
                         electric_rate_per_unit = :electric_rate, description = :description 
                         WHERE room_id = :room_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':room_number', $_POST['room_number']);
                $stmt->bindParam(':floor', $_POST['floor']);
                $stmt->bindParam(':room_type', $_POST['room_type']);
                $stmt->bindParam(':monthly_rent', $_POST['monthly_rent']);
                $stmt->bindParam(':water_rate', $_POST['water_rate']);
                $stmt->bindParam(':electric_rate', $_POST['electric_rate']);
                $stmt->bindParam(':description', $_POST['description']);
                $stmt->bindParam(':room_id', $_POST['room_id']);
                $stmt->execute();
                break;
                
            case 'delete':
                $query = "DELETE FROM rooms WHERE room_id = :room_id AND status = 'available'";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':room_id', $_POST['room_id']);
                $stmt->execute();
                break;
        }
    }
}

// ดึงข้อมูลห้องพัก
$query = "SELECT r.*, 
          (SELECT full_name FROM users u 
           JOIN contracts c ON u.user_id = c.tenant_id 
           WHERE c.room_id = r.room_id AND c.status = 'active' 
           LIMIT 1) as tenant_name
          FROM rooms r 
          ORDER BY r.floor, r.room_number";
$stmt = $db->prepare($query);
$stmt->execute();
$rooms = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการห้องพัก</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="#" class="logo">🏢 ระบบจัดการหอพัก</a>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="rooms.php" class="nav-link">จัดการห้องพัก</a></li>
                <li><a href="bookings.php" class="nav-link">คำขอจอง</a></li>
                <li><a href="tenants.php" class="nav-link">ผู้เช่า</a></li>
                <li><a href="bills.php" class="nav-link">บิล/ชำระเงิน</a></li>
                <li><a href="maintenance.php" class="nav-link">แจ้งซ่อม</a></li>
                <li><a href="chat.php" class="nav-link">💬 แชท</a></li>
                <li><a href="../logout.php" class="nav-link">ออกจากระบบ</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin: 30px 0;">
            <h1>🏠 จัดการห้องพัก</h1>
            <button class="btn btn-primary" onclick="openAddModal()">+ เพิ่มห้องใหม่</button>
        </div>

        <div class="card">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>เลขห้อง</th>
                            <th>ชั้น</th>
                            <th>ประเภท</th>
                            <th>ค่าเช่า/เดือน</th>
                            <th>ค่าน้ำ/หน่วย</th>
                            <th>ค่าไฟ/หน่วย</th>
                            <th>สถานะ</th>
                            <th>ผู้เช่า</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($rooms as $room): ?>
                            <tr>
                                <td><strong><?php echo $room['room_number']; ?></strong></td>
                                <td><?php echo $room['floor']; ?></td>
                                <td><?php echo $room['room_type']; ?></td>
                                <td>฿<?php echo number_format($room['monthly_rent'], 2); ?></td>
                                <td>฿<?php echo number_format($room['water_rate_per_unit'], 2); ?></td>
                                <td>฿<?php echo number_format($room['electric_rate_per_unit'], 2); ?></td>
                                <td>
                                    <?php if($room['status'] == 'available'): ?>
                                        <span class="badge badge-success">ว่าง</span>
                                    <?php elseif($room['status'] == 'occupied'): ?>
                                        <span class="badge badge-info">มีผู้เช่า</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">ซ่อมแซม</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $room['tenant_name'] ?? '-'; ?></td>
                                <td>
                                    <button class="btn btn-primary" style="padding: 6px 12px; font-size: 13px; margin-right: 5px;" 
                                            onclick='openEditModal(<?php echo json_encode($room); ?>)'>แก้ไข</button>
                                    <?php if($room['status'] == 'available'): ?>
                                        <button class="btn btn-danger" style="padding: 6px 12px; font-size: 13px;" 
                                                onclick="deleteRoom(<?php echo $room['room_id']; ?>)">ลบ</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal เพิ่ม/แก้ไขห้อง -->
    <div id="roomModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">เพิ่มห้องพักใหม่</h2>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="roomForm">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="room_id" id="roomId">
                    
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">เลขห้อง *</label>
                            <input type="text" name="room_number" id="roomNumber" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">ชั้น *</label>
                            <input type="number" name="floor" id="floor" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">ประเภทห้อง *</label>
                        <select name="room_type" id="roomType" class="form-control" required>
                            <option value="Standard">Standard</option>
                            <option value="Deluxe">Deluxe</option>
                            <option value="Suite">Suite</option>
                        </select>
                    </div>
                    
                    <div class="grid-3">
                        <div class="form-group">
                            <label class="form-label">ค่าเช่า/เดือน (฿) *</label>
                            <input type="number" step="0.01" name="monthly_rent" id="monthlyRent" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">ค่าน้ำ/หน่วย (฿) *</label>
                            <input type="number" step="0.01" name="water_rate" id="waterRate" class="form-control" value="18" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">ค่าไฟ/หน่วย (฿) *</label>
                            <input type="number" step="0.01" name="electric_rate" id="electricRate" class="form-control" value="8" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">รายละเอียด</label>
                        <textarea name="description" id="description" class="form-control"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">บันทึก</button>
                </form>
            </div>
        </div>
    </div>

    <script src="../js/rooms.js"></script>
</body>
</html>