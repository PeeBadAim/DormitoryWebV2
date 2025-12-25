<?php
// owner/tenants.php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'owner') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// สิ้นสุดสัญญา
if (isset($_POST['terminate_contract'])) {
    $contract_id = $_POST['contract_id'];
    
    $db->beginTransaction();
    try {
        // อัพเดทสถานะสัญญา
        $update_contract = "UPDATE contracts SET status = 'terminated', end_date = CURRENT_DATE 
                           WHERE contract_id = :contract_id";
        $stmt = $db->prepare($update_contract);
        $stmt->bindParam(':contract_id', $contract_id);
        $stmt->execute();
        
        // อัพเดทสถานะห้อง
        $get_room = "SELECT room_id, tenant_id FROM contracts WHERE contract_id = :contract_id";
        $stmt = $db->prepare($get_room);
        $stmt->bindParam(':contract_id', $contract_id);
        $stmt->execute();
        $contract = $stmt->fetch();
        
        $update_room = "UPDATE rooms SET status = 'available' WHERE room_id = :room_id";
        $stmt = $db->prepare($update_room);
        $stmt->bindParam(':room_id', $contract['room_id']);
        $stmt->execute();
        
        // แจ้งเตือน
        send_notification($contract['tenant_id'], 'สิ้นสุดสัญญาเช่า', 
            'สัญญาเช่าของคุณได้สิ้นสุดแล้ว', 'contract', $contract_id);
        
        $db->commit();
        $message = '<div class="alert alert-success">สิ้นสุดสัญญาเรียบร้อย</div>';
    } catch (Exception $e) {
        $db->rollBack();
        $message = '<div class="alert alert-danger">เกิดข้อผิดพลาด</div>';
    }
}

// ดึงข้อมูลผู้เช่า
$query = "SELECT c.*, r.room_number, r.monthly_rent, u.full_name, u.email, u.phone,
          DATEDIFF(CURRENT_DATE, c.start_date) as days_rented,
          (SELECT COUNT(*) FROM monthly_bills WHERE contract_id = c.contract_id AND payment_status = 'unpaid') as unpaid_bills
          FROM contracts c
          JOIN rooms r ON c.room_id = r.room_id
          JOIN users u ON c.tenant_id = u.user_id
          ORDER BY c.status, r.room_number";
$stmt = $db->prepare($query);
$stmt->execute();
$tenants = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้เช่า</title>
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
        <?php if(isset($message)) echo $message; ?>
        
        <h1 style="margin: 30px 0;">👥 จัดการผู้เช่า</h1>

        <div class="card">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ห้อง</th>
                            <th>ชื่อผู้เช่า</th>
                            <th>เบอร์โทร</th>
                            <th>อีเมล</th>
                            <th>วันที่เข้าพัก</th>
                            <th>ระยะเวลา</th>
                            <th>ค่าเช่า/เดือน</th>
                            <th>บิลค้างชำระ</th>
                            <th>สถานะ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($tenants as $tenant): ?>
                            <tr>
                                <td><strong><?php echo $tenant['room_number']; ?></strong></td>
                                <td><?php echo $tenant['full_name']; ?></td>
                                <td><?php echo $tenant['phone']; ?></td>
                                <td><?php echo $tenant['email']; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($tenant['start_date'])); ?></td>
                                <td>
                                    <?php 
                                    $months = floor($tenant['days_rented'] / 30);
                                    echo $months . ' เดือน';
                                    ?>
                                </td>
                                <td>฿<?php echo number_format($tenant['monthly_rent'], 2); ?></td>
                                <td>
                                    <?php if($tenant['unpaid_bills'] > 0): ?>
                                        <span class="badge badge-danger"><?php echo $tenant['unpaid_bills']; ?> บิล</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($tenant['status'] == 'active'): ?>
                                        <span class="badge badge-success">ใช้งาน</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">สิ้นสุด</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-primary" style="padding: 6px 12px; font-size: 13px;" 
                                            onclick='viewTenant(<?php echo json_encode($tenant); ?>)'>
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

    <!-- Modal -->
    <div id="tenantModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">ข้อมูลผู้เช่า</h2>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="tenantContent"></div>
        </div>
    </div>

    <script>
        function viewTenant(tenant) {
            const content = `
                <div style="background: var(--light-gray); padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <h3 style="margin-bottom: 15px;">${tenant.full_name}</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <strong>ห้อง:</strong> ${tenant.room_number}<br>
                            <strong>เบอร์:</strong> ${tenant.phone}<br>
                            <strong>อีเมล:</strong> ${tenant.email}
                        </div>
                        <div>
                            <strong>เข้าพัก:</strong> ${new Date(tenant.start_date).toLocaleDateString('th-TH')}<br>
                            <strong>ค่าเช่า:</strong> ฿${parseFloat(tenant.monthly_rent).toLocaleString()}/เดือน<br>
                            <strong>เงินมัดจำ:</strong> ฿${parseFloat(tenant.deposit_amount).toLocaleString()}
                        </div>
                    </div>
                </div>
                
                <div style="background: ${tenant.unpaid_bills > 0 ? '#fff3cd' : '#d4edda'}; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <strong>บิลค้างชำระ:</strong> ${tenant.unpaid_bills} บิล
                </div>
                
                ${tenant.status === 'active' ? `
                    <form method="POST" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการสิ้นสุดสัญญานี้?')">
                        <input type="hidden" name="contract_id" value="${tenant.contract_id}">
                        <button type="submit" name="terminate_contract" class="btn btn-danger" style="width: 100%;">
                            สิ้นสุดสัญญา
                        </button>
                    </form>
                ` : '<div class="alert alert-danger">สัญญาสิ้นสุดแล้ว</div>'}
            `;
            
            document.getElementById('tenantContent').innerHTML = content;
            document.getElementById('tenantModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('tenantModal').classList.remove('active');
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>