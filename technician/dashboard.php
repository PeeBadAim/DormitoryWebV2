<?php
// technician/dashboard.php
require_once '../config/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'technician') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// อัพเดทสถานะงาน
if (isset($_POST['update_status'])) {
    $request_id = $_POST['request_id'];
    $status = $_POST['status'];
    $notes = sanitize_input($_POST['notes']);
    
    $query = "UPDATE maintenance_requests SET status = :status, notes = :notes";
    if ($status == 'completed') {
        $query .= ", completed_at = NOW()";
    } elseif ($status == 'in_progress' && empty($notes)) {
        $query .= ", notes = 'ช่างกำลังดำเนินการ'";
    }
    $query .= " WHERE request_id = :request_id AND technician_id = :tech_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':notes', $notes);
    $stmt->bindParam(':request_id', $request_id);
    $stmt->bindParam(':tech_id', $user_id);
    $stmt->execute();
    
    // แจ้งเตือนผู้แจ้ง
    $req_query = "SELECT tenant_id, issue_title FROM maintenance_requests WHERE request_id = :id";
    $req_stmt = $db->prepare($req_query);
    $req_stmt->bindParam(':id', $request_id);
    $req_stmt->execute();
    $req = $req_stmt->fetch();
    
    $status_text = [
        'in_progress' => 'กำลังดำเนินการ',
        'completed' => 'เสร็จสิ้นแล้ว'
    ];
    
    send_notification($req['tenant_id'], 'อัพเดทการแจ้งซ่อม', 
        "งาน: {$req['issue_title']} - สถานะ: {$status_text[$status]}", 
        'maintenance', $request_id);
}

// สถิติ
$stats = [];

// งานที่ได้รับมอบหมาย
$query = "SELECT COUNT(*) as total FROM maintenance_requests WHERE technician_id = :tech_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':tech_id', $user_id);
$stmt->execute();
$stats['total_jobs'] = $stmt->fetch()['total'];

// งานที่รอดำเนินการ
$query = "SELECT COUNT(*) as pending FROM maintenance_requests 
          WHERE technician_id = :tech_id AND status IN ('assigned', 'in_progress')";
$stmt = $db->prepare($query);
$stmt->bindParam(':tech_id', $user_id);
$stmt->execute();
$stats['pending_jobs'] = $stmt->fetch()['pending'];

// งานที่เสร็จแล้ว
$query = "SELECT COUNT(*) as completed FROM maintenance_requests 
          WHERE technician_id = :tech_id AND status = 'completed'";
$stmt = $db->prepare($query);
$stmt->bindParam(':tech_id', $user_id);
$stmt->execute();
$stats['completed_jobs'] = $stmt->fetch()['completed'];

// ดึงงานทั้งหมด
$jobs_query = "SELECT m.*, r.room_number, u.full_name as tenant_name
              FROM maintenance_requests m
              JOIN rooms r ON m.room_id = r.room_id
              JOIN users u ON m.tenant_id = u.user_id
              WHERE m.technician_id = :tech_id
              ORDER BY 
                CASE m.status 
                    WHEN 'assigned' THEN 1 
                    WHEN 'in_progress' THEN 2 
                    ELSE 3 
                END,
                FIELD(m.priority, 'urgent', 'high', 'medium', 'low'),
                m.created_at DESC";
$jobs_stmt = $db->prepare($jobs_query);
$jobs_stmt->bindParam(':tech_id', $user_id);
$jobs_stmt->execute();
$jobs = $jobs_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard ช่างซ่อม</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="#" class="logo">🔧 ระบบช่างซ่อม</a>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link">งานของฉัน</a></li>
                <li><a href="../owner/chat.php" class="nav-link">💬 แชท</a></li>
                <li><a href="../logout.php" class="nav-link">ออกจากระบบ</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <h1 style="margin: 30px 0;">สวัสดี, <?php echo $_SESSION['full_name']; ?> 🔧</h1>
        
        <div class="grid grid-3">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_jobs']; ?></div>
                <div class="stat-label">งานทั้งหมด</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number" style="color: var(--warning-color);"><?php echo $stats['pending_jobs']; ?></div>
                <div class="stat-label">กำลังดำเนินการ</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number" style="color: var(--success-color);"><?php echo $stats['completed_jobs']; ?></div>
                <div class="stat-label">เสร็จสิ้นแล้ว</div>
            </div>
        </div>

        <div class="card" style="margin-top: 30px;">
            <div class="card-header">📋 งานซ่อมของฉัน</div>
            
            <?php if(count($jobs) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>รหัส</th>
                                <th>ห้อง</th>
                                <th>ผู้แจ้ง</th>
                                <th>หัวข้อ</th>
                                <th>ความสำคัญ</th>
                                <th>สถานะ</th>
                                <th>วันที่</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($jobs as $job): ?>
                                <tr>
                                    <td><strong>#<?php echo $job['request_id']; ?></strong></td>
                                    <td><?php echo $job['room_number']; ?></td>
                                    <td><?php echo $job['tenant_name']; ?></td>
                                    <td><?php echo $job['issue_title']; ?></td>
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
                                        <span class="badge badge-<?php echo $priority_colors[$job['priority']]; ?>">
                                            <?php echo $priorities[$job['priority']]; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_colors = [
                                            'assigned' => 'warning',
                                            'in_progress' => 'info',
                                            'completed' => 'success'
                                        ];
                                        $statuses = [
                                            'assigned' => 'รับงานแล้ว',
                                            'in_progress' => 'กำลังทำ',
                                            'completed' => 'เสร็จสิ้น'
                                        ];
                                        ?>
                                        <span class="badge badge-<?php echo $status_colors[$job['status']]; ?>">
                                            <?php echo $statuses[$job['status']]; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($job['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-primary" style="padding: 6px 12px; font-size: 13px;" 
                                                onclick='viewJob(<?php echo json_encode($job); ?>)'>
                                            ดู/อัพเดท
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="text-align: center; padding: 50px; color: var(--dark-gray);">
                    ยังไม่มีงานที่ได้รับมอบหมาย
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal -->
    <div id="jobModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">รายละเอียดงาน</h2>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="jobContent"></div>
        </div>
    </div>

    <script>
        function viewJob(job) {
            const priorities = {'urgent': 'ด่วนมาก', 'high': 'สูง', 'medium': 'ปานกลาง', 'low': 'ต่ำ'};
            
            const content = `
                <div style="background: var(--light-gray); padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <h3 style="margin-bottom: 15px;">#${job.request_id} - ${job.issue_title}</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                        <div><strong>ห้อง:</strong> ${job.room_number}</div>
                        <div><strong>ผู้แจ้ง:</strong> ${job.tenant_name}</div>
                        <div><strong>ความสำคัญ:</strong> ${priorities[job.priority]}</div>
                        <div><strong>วันที่แจ้ง:</strong> ${new Date(job.created_at).toLocaleDateString('th-TH')}</div>
                    </div>
                    <div><strong>รายละเอียด:</strong><br>${job.issue_description}</div>
                    ${job.notes ? `<div style="margin-top: 10px;"><strong>หมายเหตุ:</strong><br>${job.notes}</div>` : ''}
                </div>
                
                ${job.status !== 'completed' ? `
                    <form method="POST">
                        <input type="hidden" name="request_id" value="${job.request_id}">
                        
                        <div class="form-group">
                            <label class="form-label">สถานะ</label>
                            <select name="status" class="form-control" required>
                                <option value="in_progress" ${job.status === 'in_progress' ? 'selected' : ''}>กำลังดำเนินการ</option>
                                <option value="completed">เสร็จสิ้น</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">หมายเหตุ</label>
                            <textarea name="notes" class="form-control" placeholder="บันทึกผลการทำงาน...">${job.notes || ''}</textarea>
                        </div>
                        
                        <button type="submit" name="update_status" class="btn btn-primary" style="width: 100%;">
                            อัพเดทสถานะ
                        </button>
                    </form>
                ` : '<div class="alert alert-success">งานนี้เสร็จสิ้นแล้ว</div>'}
            `;
            
            document.getElementById('jobContent').innerHTML = content;
            document.getElementById('jobModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('jobModal').classList.remove('active');
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>