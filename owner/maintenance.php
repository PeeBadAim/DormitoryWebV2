<?php
// owner/maintenance.php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'owner') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// มอบหมายงานให้ช่าง
if (isset($_POST['assign_technician'])) {
    $request_id = $_POST['request_id'];
    $technician_id = $_POST['technician_id'];
    
    $query = "UPDATE maintenance_requests 
              SET technician_id = :tech_id, status = 'assigned', assigned_at = NOW() 
              WHERE request_id = :request_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':tech_id', $technician_id);
    $stmt->bindParam(':request_id', $request_id);
    $stmt->execute();
    
    // แจ้งเตือนช่าง
    $req_query = "SELECT issue_title FROM maintenance_requests WHERE request_id = :id";
    $req_stmt = $db->prepare($req_query);
    $req_stmt->bindParam(':id', $request_id);
    $req_stmt->execute();
    $req = $req_stmt->fetch();
    
    send_notification($technician_id, 'งานซ่อมใหม่', 
        "คุณได้รับมอบหมายงาน: {$req['issue_title']}", 
        'maintenance', $request_id);
}

// อัพเดทสถานะ
if (isset($_POST['update_status'])) {
    $request_id = $_POST['request_id'];
    $status = $_POST['status'];
    
    $query = "UPDATE maintenance_requests SET status = :status";
    if ($status == 'completed') {
        $query .= ", completed_at = NOW()";
    }
    $query .= " WHERE request_id = :request_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':request_id', $request_id);
    $stmt->execute();
}

// ดึงข้อมูลช่าง
$tech_query = "SELECT user_id, full_name FROM users WHERE role = 'technician' AND status = 'active'";
$tech_stmt = $db->prepare($tech_query);
$tech_stmt->execute();
$technicians = $tech_stmt->fetchAll();

// ดึงรายการแจ้งซ่อม
$query = "SELECT m.*, r.room_number, u.full_name as tenant_name, 
          t.full_name as technician_name
          FROM maintenance_requests m
          JOIN rooms r ON m.room_id = r.room_id
          JOIN users u ON m.tenant_id = u.user_id
          LEFT JOIN users t ON m.technician_id = t.user_id
          ORDER BY 
            CASE m.status 
                WHEN 'pending' THEN 1 
                WHEN 'assigned' THEN 2 
                WHEN 'in_progress' THEN 3 
                ELSE 4 
            END,
            FIELD(m.priority, 'urgent', 'high', 'medium', 'low'),
            m.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$maintenance = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการแจ้งซ่อม</title>
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
        <h1 style="margin: 30px 0;">🔧 จัดการแจ้งซ่อม</h1>

        <div class="card">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>รหัส</th>
                            <th>ห้อง</th>
                            <th>ผู้แจ้ง</th>
                            <th>หัวข้อ</th>
                            <th>ประเภท</th>
                            <th>ความสำคัญ</th>
                            <th>สถานะ</th>
                            <th>ช่าง</th>
                            <th>วันที่</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($maintenance as $item): ?>
                            <tr>
                                <td><strong>#<?php echo $item['request_id']; ?></strong></td>
                                <td><?php echo $item['room_number']; ?></td>
                                <td><?php echo $item['tenant_name']; ?></td>
                                <td><?php echo $item['issue_title']; ?></td>
                                <td><?php echo $item['category'] ?? '-'; ?></td>
                                <td>
                                    <?php 
                                    $priority_colors = [
                                        'urgent' => 'danger',
                                        'high' => 'warning',
                                        'medium' => 'info',
                                        'low' => 'success'
                                    ];
                                    $color = $priority_colors[$item['priority']];
                                    ?>
                                    <span class="badge badge-<?php echo $color; ?>">
                                        <?php 
                                        $priorities = ['urgent' => 'ด่วนมาก', 'high' => 'สูง', 'medium' => 'ปานกลาง', 'low' => 'ต่ำ'];
                                        echo $priorities[$item['priority']];
                                        ?>
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
                                        'in_progress' => 'กำลังดำเนินการ',
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
                                <td>
                                    <button class="btn btn-primary" style="padding: 6px 12px; font-size: 13px;" 
                                            onclick='viewMaintenance(<?php echo json_encode($item); ?>)'>
                                        ดู
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal รายละเอียด -->
    <div id="maintenanceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">รายละเอียดการแจ้งซ่อม</h2>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="maintenanceContent">
                <!-- Content will be loaded by JS -->
            </div>
        </div>
    </div>

    <script>
        const technicians = <?php echo json_encode($technicians); ?>;
        
        function viewMaintenance(item) {
            let technicianOptions = '<option value="">-- เลือกช่าง --</option>';
            technicians.forEach(tech => {
                const selected = tech.user_id == item.technician_id ? 'selected' : '';
                technicianOptions += `<option value="${tech.user_id}" ${selected}>${tech.full_name}</option>`;
            });
            
            const content = `
                <div style="background: var(--light-gray); padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <h3 style="margin-bottom: 15px;">#${item.request_id} - ${item.issue_title}</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                        <div><strong>ห้อง:</strong> ${item.room_number}</div>
                        <div><strong>ผู้แจ้ง:</strong> ${item.tenant_name}</div>
                        <div><strong>ประเภท:</strong> ${item.category || '-'}</div>
                        <div><strong>วันที่:</strong> ${new Date(item.created_at).toLocaleDateString('th-TH')}</div>
                    </div>
                    <div><strong>รายละเอียด:</strong><br>${item.issue_description}</div>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="request_id" value="${item.request_id}">
                    
                    <div class="form-group">
                        <label class="form-label">มอบหมายช่าง</label>
                        <select name="technician_id" class="form-control">
                            ${technicianOptions}
                        </select>
                    </div>
                    
                    ${item.status !== 'completed' && item.status !== 'cancelled' ? `
                        <button type="submit" name="assign_technician" class="btn btn-primary" style="width: 100%; margin-bottom: 10px;">
                            บันทึก
                        </button>
                    ` : ''}
                    
                    <div class="form-group">
                        <label class="form-label">อัพเดทสถานะ</label>
                        <select name="status" class="form-control">
                            <option value="pending" ${item.status === 'pending' ? 'selected' : ''}>รอดำเนินการ</option>
                            <option value="assigned" ${item.status === 'assigned' ? 'selected' : ''}>มอบหมายแล้ว</option>
                            <option value="in_progress" ${item.status === 'in_progress' ? 'selected' : ''}>กำลังดำเนินการ</option>
                            <option value="completed" ${item.status === 'completed' ? 'selected' : ''}>เสร็จสิ้น</option>
                            <option value="cancelled" ${item.status === 'cancelled' ? 'selected' : ''}>ยกเลิก</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="update_status" class="btn btn-accent" style="width: 100%;">
                        อัพเดทสถานะ
                    </button>
                </form>
            `;
            
            document.getElementById('maintenanceContent').innerHTML = content;
            document.getElementById('maintenanceModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('maintenanceModal').classList.remove('active');
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>