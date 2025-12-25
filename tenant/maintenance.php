<?php
// tenant/maintenance.php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tenant') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

$message = '';

// ดึงข้อมูลห้องของผู้เช่า
$room_query = "SELECT r.room_id, r.room_number 
               FROM contracts c
               JOIN rooms r ON c.room_id = r.room_id
               WHERE c.tenant_id = :user_id AND c.status = 'active'
               LIMIT 1";
$room_stmt = $db->prepare($room_query);
$room_stmt->bindParam(':user_id', $user_id);
$room_stmt->execute();
$user_room = $room_stmt->fetch();

// สร้างคำขอแจ้งซ่อม
if (isset($_POST['create_request']) && $user_room) {
    $issue_title = sanitize_input($_POST['issue_title']);
    $issue_description = sanitize_input($_POST['issue_description']);
    $category = sanitize_input($_POST['category']);
    $priority = $_POST['priority'];
    
    $query = "INSERT INTO maintenance_requests (room_id, tenant_id, issue_title, issue_description, category, priority) 
              VALUES (:room_id, :tenant_id, :title, :description, :category, :priority)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':room_id', $user_room['room_id']);
    $stmt->bindParam(':tenant_id', $user_id);
    $stmt->bindParam(':title', $issue_title);
    $stmt->bindParam(':description', $issue_description);
    $stmt->bindParam(':category', $category);
    $stmt->bindParam(':priority', $priority);
    
    if ($stmt->execute()) {
        $request_id = $db->lastInsertId();
        
        // แจ้งเตือนเจ้าของหอ
        $owner_query = "SELECT user_id FROM users WHERE role = 'owner' LIMIT 1";
        $owner_stmt = $db->prepare($owner_query);
        $owner_stmt->execute();
        $owner = $owner_stmt->fetch();
        
        if ($owner) {
            send_notification($owner['user_id'], 'แจ้งซ่อมใหม่', 
                $_SESSION['full_name'] . ' แจ้งซ่อม: ' . $issue_title, 
                'maintenance', $request_id);
        }
        
        $message = '<div class="alert alert-success">แจ้งซ่อมสำเร็จ! เจ้าของหอจะดำเนินการโดยเร็ว</div>';
    }
}

// ดึงประวัติการแจ้งซ่อม
$history_query = "SELECT m.*, r.room_number, t.full_name as technician_name
                 FROM maintenance_requests m
                 JOIN rooms r ON m.room_id = r.room_id
                 LEFT JOIN users t ON m.technician_id = t.user_id
                 WHERE m.tenant_id = :user_id
                 ORDER BY m.created_at DESC";
$history_stmt = $db->prepare($history_query);
$history_stmt->bindParam(':user_id', $user_id);
$history_stmt->execute();
$maintenance_history = $history_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แจ้งซ่อม</title>
    <link rel="stylesheet" href="../css/style.css">
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
                <li><a href="../owner/chat.php" class="nav-link">💬 แชท</a></li>
                <li><a href="../logout.php" class="nav-link">ออกจากระบบ</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <?php echo $message; ?>
        
        <h1 style="margin: 30px 0;">🔧 แจ้งซ่อม</h1>

        <?php if ($user_room): ?>
            <!-- ฟอร์มแจ้งซ่อม -->
            <div class="card">
                <div class="card-header">แจ้งปัญหา/แจ้งซ่อมใหม่</div>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">ห้อง</label>
                        <input type="text" class="form-control" value="<?php echo $user_room['room_number']; ?>" disabled>
                    </div>
                    
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">หมวดหมู่ *</label>
                            <select name="category" class="form-control" required>
                                <option value="">-- เลือก --</option>
                                <option value="ไฟฟ้า">ไฟฟ้า</option>
                                <option value="ประปา">ประปา</option>
                                <option value="เฟอร์นิเจอร์">เฟอร์นิเจอร์</option>
                                <option value="แอร์">แอร์/พัดลม</option>
                                <option value="ประตู-หน้าต่าง">ประตู/หน้าต่าง</option>
                                <option value="อื่นๆ">อื่นๆ</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">ความเร่งด่วน *</label>
                            <select name="priority" class="form-control" required>
                                <option value="low">ต่ำ</option>
                                <option value="medium" selected>ปานกลาง</option>
                                <option value="high">สูง</option>
                                <option value="urgent">ด่วนมาก</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">หัวข้อ *</label>
                        <input type="text" name="issue_title" class="form-control" 
                               placeholder="เช่น หลอดไฟในห้องน้ำเสีย" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">รายละเอียด *</label>
                        <textarea name="issue_description" class="form-control" rows="4" 
                                  placeholder="อธิบายปัญหาโดยละเอียด..." required></textarea>
                    </div>
                    
                    <button type="submit" name="create_request" class="btn btn-primary">
                        ส่งคำขอแจ้งซ่อม
                    </button>
                </form>
            </div>

            <!-- ประวัติการแจ้งซ่อม -->
            <?php if(count($maintenance_history) > 0): ?>
                <div class="card" style="margin-top: 30px;">
                    <div class="card-header">📋 ประวัติการแจ้งซ่อม</div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>รหัส</th>
                                    <th>หัวข้อ</th>
                                    <th>หมวดหมู่</th>
                                    <th>ความสำคัญ</th>
                                    <th>สถานะ</th>
                                    <th>ช่าง</th>
                                    <th>วันที่</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($maintenance_history as $item): ?>
                                    <tr style="cursor: pointer;" onclick='viewDetail(<?php echo json_encode($item); ?>)'>
                                        <td><strong>#<?php echo $item['request_id']; ?></strong></td>
                                        <td><?php echo $item['issue_title']; ?></td>
                                        <td><?php echo $item['category']; ?></td>
                                        <td>
                                            <?php 
                                            $priority_colors = [
                                                'urgent' => 'danger',
                                                'high' => 'warning',
                                                'medium' => 'info',
                                                'low' => 'success'
                                            ];
                                            $priorities = ['urgent' => 'ด่วนมาก', 'high' => 'สูง', 'medium' => 'ปานกลาง', 'low' => 'ต่ำ'];
                                            ?>
                                            <span class="badge badge-<?php echo $priority_colors[$item['priority']]; ?>">
                                                <?php echo $priorities[$item['priority']]; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $status_colors = [
                                                'pending' => 'warning',
                                                'assigned' => 'info',
                                                'in_progress' => 'info',
                                                'completed' => 'success',
                                                'cancelled' => 'danger'
                                            ];
                                            $statuses = [
                                                'pending' => 'รอดำเนินการ',
                                                'assigned' => 'มอบหมายแล้ว',
                                                'in_progress' => 'กำลังทำ',
                                                'completed' => 'เสร็จสิ้น',
                                                'cancelled' => 'ยกเลิก'
                                            ];
                                            ?>
                                            <span class="badge badge-<?php echo $status_colors[$item['status']]; ?>">
                                                <?php echo $statuses[$item['status']]; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $item['technician_name'] ?? '-'; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($item['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="card" style="text-align: center; padding: 60px;">
                <h2 style="margin-bottom: 15px;">คุณยังไม่มีห้องพัก</h2>
                <p style="color: var(--dark-gray); margin-bottom: 30px;">
                    กรุณาจองห้องก่อนจึงจะสามารถแจ้งซ่อมได้
                </p>
                <a href="browse_rooms.php" class="btn btn-primary">ดูห้องพักว่าง</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">รายละเอียดการแจ้งซ่อม</h2>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="detailContent"></div>
        </div>
    </div>

    <script>
        function viewDetail(item) {
            const priorities = {'urgent': 'ด่วนมาก', 'high': 'สูง', 'medium': 'ปานกลาง', 'low': 'ต่ำ'};
            const statuses = {
                'pending': 'รอดำเนินการ',
                'assigned': 'มอบหมายแล้ว',
                'in_progress': 'กำลังดำเนินการ',
                'completed': 'เสร็จสิ้น',
                'cancelled': 'ยกเลิก'
            };
            
            const content = `
                <div style="background: var(--light-gray); padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <h3 style="margin-bottom: 15px;">#${item.request_id} - ${item.issue_title}</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                        <div><strong>ห้อง:</strong> ${item.room_number}</div>
                        <div><strong>หมวดหมู่:</strong> ${item.category}</div>
                        <div><strong>ความสำคัญ:</strong> ${priorities[item.priority]}</div>
                        <div><strong>สถานะ:</strong> ${statuses[item.status]}</div>
                        <div><strong>ช่าง:</strong> ${item.technician_name || 'ยังไม่มอบหมาย'}</div>
                        <div><strong>วันที่แจ้ง:</strong> ${new Date(item.created_at).toLocaleDateString('th-TH')}</div>
                    </div>
                    <div style="margin-top: 15px;">
                        <strong>รายละเอียด:</strong><br>
                        ${item.issue_description}
                    </div>
                    ${item.notes ? `
                        <div style="margin-top: 15px; padding: 15px; background: var(--white); border-radius: 8px;">
                            <strong>หมายเหตุจากช่าง:</strong><br>
                            ${item.notes}
                        </div>
                    ` : ''}
                    ${item.completed_at ? `
                        <div style="margin-top: 15px;">
                            <strong>เสร็จสิ้นเมื่อ:</strong> ${new Date(item.completed_at).toLocaleDateString('th-TH')}
                        </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('detailContent').innerHTML = content;
            document.getElementById('detailModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('detailModal').classList.remove('active');
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>