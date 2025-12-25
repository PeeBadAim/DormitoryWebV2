<?php
// owner/bills.php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'owner') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$message = '';

// สร้างบิลใหม่
if (isset($_POST['create_bill'])) {
    $contract_id = $_POST['contract_id'];
    $billing_month = $_POST['billing_month'];
    $water_previous = $_POST['water_previous'];
    $water_current = $_POST['water_current'];
    $electric_previous = $_POST['electric_previous'];
    $electric_current = $_POST['electric_current'];
    
    // ดึงข้อมูลสัญญา
    $query = "SELECT c.*, r.monthly_rent, r.water_rate_per_unit, r.electric_rate_per_unit, r.room_number
              FROM contracts c
              JOIN rooms r ON c.room_id = r.room_id
              WHERE c.contract_id = :contract_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':contract_id', $contract_id);
    $stmt->execute();
    $contract = $stmt->fetch();
    
    // คำนวณ
    $water_usage = $water_current - $water_previous;
    $water_cost = $water_usage * $contract['water_rate_per_unit'];
    $electric_usage = $electric_current - $electric_previous;
    $electric_cost = $electric_usage * $contract['electric_rate_per_unit'];
    $total = $contract['monthly_rent'] + $water_cost + $electric_cost;
    
    // สร้างบิล
    $insert_query = "INSERT INTO monthly_bills 
                    (contract_id, room_id, tenant_id, billing_month, room_rent, 
                     water_previous_reading, water_current_reading, water_usage, water_cost,
                     electric_previous_reading, electric_current_reading, electric_usage, electric_cost,
                     total_amount, due_date)
                    VALUES 
                    (:contract_id, :room_id, :tenant_id, :billing_month, :room_rent,
                     :water_prev, :water_curr, :water_usage, :water_cost,
                     :electric_prev, :electric_curr, :electric_usage, :electric_cost,
                     :total, DATE_ADD(:billing_month, INTERVAL 1 MONTH))";
    
    $stmt = $db->prepare($insert_query);
    $stmt->bindParam(':contract_id', $contract_id);
    $stmt->bindParam(':room_id', $contract['room_id']);
    $stmt->bindParam(':tenant_id', $contract['tenant_id']);
    $stmt->bindParam(':billing_month', $billing_month);
    $stmt->bindParam(':room_rent', $contract['monthly_rent']);
    $stmt->bindParam(':water_prev', $water_previous);
    $stmt->bindParam(':water_curr', $water_current);
    $stmt->bindParam(':water_usage', $water_usage);
    $stmt->bindParam(':water_cost', $water_cost);
    $stmt->bindParam(':electric_prev', $electric_previous);
    $stmt->bindParam(':electric_curr', $electric_current);
    $stmt->bindParam(':electric_usage', $electric_usage);
    $stmt->bindParam(':electric_cost', $electric_cost);
    $stmt->bindParam(':total', $total);
    
    if ($stmt->execute()) {
        $bill_id = $db->lastInsertId();
        
        // สร้าง QR Code
        $qr_data = "Bill ID: {$bill_id}\nRoom: {$contract['room_number']}\nAmount: {$total} THB\nDue: " . date('Y-m-d', strtotime($billing_month . ' +1 month'));
        $qr_filename = "bill_{$bill_id}_" . time() . ".png";
        $qr_path = generate_qr_code($qr_data, $qr_filename);
        
        // อัพเดท QR Code path
        $update_query = "UPDATE monthly_bills SET qr_code_path = :qr_path WHERE bill_id = :bill_id";
        $stmt = $db->prepare($update_query);
        $stmt->bindParam(':qr_path', $qr_path);
        $stmt->bindParam(':bill_id', $bill_id);
        $stmt->execute();
        
        // ส่งการแจ้งเตือน
        send_notification($contract['tenant_id'], 'บิลใหม่', 
            "มีบิลค่าเช่าประจำเดือน " . date('m/Y', strtotime($billing_month)) . " จำนวน " . number_format($total, 2) . " บาท",
            'bill', $bill_id);
        
        $message = '<div class="alert alert-success">สร้างบิลสำเร็จ!</div>';
    }
}

// ดึงข้อมูลบิล
$query = "SELECT b.*, r.room_number, u.full_name as tenant_name
          FROM monthly_bills b
          JOIN rooms r ON b.room_id = r.room_id
          JOIN users u ON b.tenant_id = u.user_id
          ORDER BY b.billing_month DESC, b.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$bills = $stmt->fetchAll();

// ดึงสัญญาที่ใช้งานอยู่
$contracts_query = "SELECT c.contract_id, c.room_id, r.room_number, u.full_name as tenant_name
                   FROM contracts c
                   JOIN rooms r ON c.room_id = r.room_id
                   JOIN users u ON c.tenant_id = u.user_id
                   WHERE c.status = 'active'
                   ORDER BY r.room_number";
$contracts_stmt = $db->prepare($contracts_query);
$contracts_stmt->execute();
$contracts = $contracts_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการบิล</title>
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
        <?php echo $message; ?>
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin: 30px 0;">
            <h1>💰 จัดการบิล</h1>
            <button class="btn btn-primary" onclick="openCreateBillModal()">+ สร้างบิลใหม่</button>
        </div>

        <div class="card">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>เลขบิล</th>
                            <th>ห้อง</th>
                            <th>ผู้เช่า</th>
                            <th>เดือน</th>
                            <th>จำนวนเงิน</th>
                            <th>สถานะ</th>
                            <th>ครบกำหนด</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($bills as $bill): ?>
                            <tr>
                                <td><strong>#<?php echo $bill['bill_id']; ?></strong></td>
                                <td><?php echo $bill['room_number']; ?></td>
                                <td><?php echo $bill['tenant_name']; ?></td>
                                <td><?php echo date('m/Y', strtotime($bill['billing_month'])); ?></td>
                                <td><strong>฿<?php echo number_format($bill['total_amount'], 2); ?></strong></td>
                                <td>
                                    <?php if($bill['payment_status'] == 'paid'): ?>
                                        <span class="badge badge-success">ชำระแล้ว</span>
                                    <?php elseif($bill['payment_status'] == 'overdue'): ?>
                                        <span class="badge badge-danger">เกินกำหนด</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">รอชำระ</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($bill['due_date'])); ?></td>
                                <td>
                                    <button class="btn btn-primary" style="padding: 6px 12px; font-size: 13px;" 
                                            onclick='viewBillDetail(<?php echo json_encode($bill); ?>)'>ดู</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal สร้างบิล -->
    <div id="createBillModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">สร้างบิลรายเดือนใหม่</h2>
                <span class="close-modal" onclick="closeBillModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">เลือกห้อง/ผู้เช่า *</label>
                        <select name="contract_id" class="form-control" required>
                            <option value="">-- เลือก --</option>
                            <?php foreach($contracts as $contract): ?>
                                <option value="<?php echo $contract['contract_id']; ?>">
                                    ห้อง <?php echo $contract['room_number']; ?> - <?php echo $contract['tenant_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">เดือนที่เรียกเก็บ *</label>
                        <input type="month" name="billing_month" class="form-control" required>
                    </div>
                    
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">มิเตอร์น้ำครั้งก่อน *</label>
                            <input type="number" step="0.01" name="water_previous" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">มิเตอร์น้ำครั้งนี้ *</label>
                            <input type="number" step="0.01" name="water_current" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">มิเตอร์ไฟครั้งก่อน *</label>
                            <input type="number" step="0.01" name="electric_previous" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">มิเตอร์ไฟครั้งนี้ *</label>
                            <input type="number" step="0.01" name="electric_current" class="form-control" required>
                        </div>
                    </div>
                    
                    <button type="submit" name="create_bill" class="btn btn-primary" style="width: 100%;">สร้างบิล</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal ดูรายละเอียดบิล -->
    <div id="billDetailModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h2 class="modal-title">รายละเอียดบิล</h2>
                <span class="close-modal" onclick="closeDetailModal()">&times;</span>
            </div>
            <div class="modal-body" id="billDetailContent">
                <!-- Content will be loaded by JS -->
            </div>
        </div>
    </div>

    <script src="../js/bills.js"></script>
</body>
</html>