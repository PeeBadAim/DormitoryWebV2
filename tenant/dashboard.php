<?php
// tenant/dashboard.php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tenant') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// ดึงข้อมูลสัญญาปัจจุบัน
$contract_query = "SELECT c.*, r.room_number, r.monthly_rent, r.room_type, r.floor
                  FROM contracts c
                  JOIN rooms r ON c.room_id = r.room_id
                  WHERE c.tenant_id = :user_id AND c.status = 'active'
                  LIMIT 1";
$contract_stmt = $db->prepare($contract_query);
$contract_stmt->bindParam(':user_id', $user_id);
$contract_stmt->execute();
$contract = $contract_stmt->fetch();

// ถ้ามีสัญญา ดึงข้อมูลเพิ่มเติม
if ($contract) {
    // บิลค้างชำระ
    $bills_query = "SELECT COUNT(*) as unpaid FROM monthly_bills 
                   WHERE tenant_id = :user_id AND payment_status = 'unpaid'";
    $bills_stmt = $db->prepare($bills_query);
    $bills_stmt->bindParam(':user_id', $user_id);
    $bills_stmt->execute();
    $unpaid_bills = $bills_stmt->fetch()['unpaid'];
    
    // งานซ่อมที่แจ้ง
    $maintenance_query = "SELECT COUNT(*) as pending FROM maintenance_requests 
                         WHERE tenant_id = :user_id AND status NOT IN ('completed', 'cancelled')";
    $maintenance_stmt = $db->prepare($maintenance_query);
    $maintenance_stmt->bindParam(':user_id', $user_id);
    $maintenance_stmt->execute();
    $pending_maintenance = $maintenance_stmt->fetch()['pending'];
    
    // บิลล่าสุด
    $latest_bill_query = "SELECT * FROM monthly_bills 
                         WHERE tenant_id = :user_id 
                         ORDER BY billing_month DESC LIMIT 1";
    $latest_bill_stmt = $db->prepare($latest_bill_query);
    $latest_bill_stmt->bindParam(':user_id', $user_id);
    $latest_bill_stmt->execute();
    $latest_bill = $latest_bill_stmt->fetch();
}

// การแจ้งเตือน
$notifications_query = "SELECT * FROM notifications 
                       WHERE user_id = :user_id 
                       ORDER BY created_at DESC LIMIT 5";
$notifications_stmt = $db->prepare($notifications_query);
$notifications_stmt->bindParam(':user_id', $user_id);
$notifications_stmt->execute();
$notifications = $notifications_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard ผู้เช่า</title>
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
        <h1 style="margin: 30px 0;">สวัสดี, <?php echo $_SESSION['full_name']; ?> 👋</h1>

        <?php if ($contract): ?>
            <!-- ข้อมูลห้องพัก -->
            <div class="card" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); color: white;">
                <h2 style="margin-bottom: 20px;">🏠 ห้องพักของคุณ</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div>
                        <div style="font-size: 14px; opacity: 0.9;">เลขห้อง</div>
                        <div style="font-size: 28px; font-weight: 700; margin-top: 5px;"><?php echo $contract['room_number']; ?></div>
                    </div>
                    <div>
                        <div style="font-size: 14px; opacity: 0.9;">ประเภท</div>
                        <div style="font-size: 20px; font-weight: 600; margin-top: 5px;"><?php echo $contract['room_type']; ?></div>
                    </div>
                    <div>
                        <div style="font-size: 14px; opacity: 0.9;">ชั้น</div>
                        <div style="font-size: 20px; font-weight: 600; margin-top: 5px;"><?php echo $contract['floor']; ?></div>
                    </div>
                    <div>
                        <div style="font-size: 14px; opacity: 0.9;">ค่าเช่า/เดือน</div>
                        <div style="font-size: 24px; font-weight: 700; margin-top: 5px;">฿<?php echo number_format($contract['monthly_rent'], 0); ?></div>
                    </div>
                </div>
            </div>

            <!-- สถิติ -->
            <div class="grid grid-3" style="margin-top: 25px;">
                <div class="stat-card">
                    <div class="stat-number" style="color: <?php echo $unpaid_bills > 0 ? 'var(--danger-color)' : 'var(--success-color)'; ?>">
                        <?php echo $unpaid_bills; ?>
                    </div>
                    <div class="stat-label">บิลค้างชำระ</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--warning-color);">
                        <?php echo $pending_maintenance; ?>
                    </div>
                    <div class="stat-label">งานซ่อมที่แจ้ง</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--primary-color);">
                        <?php 
                        $days = floor((time() - strtotime($contract['start_date'])) / (60*60*24));
                        echo floor($days / 30);
                        ?>
                    </div>
                    <div class="stat-label">เดือนที่พักอยู่</div>
                </div>
            </div>

            <!-- บิลล่าสุด -->
            <?php if ($latest_bill): ?>
                <div class="card" style="margin-top: 25px;">
                    <div class="card-header">💰 บิลล่าสุด</div>
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; align-items: center;">
                        <div>
                            <div style="display: grid; gap: 12px;">
                                <div style="display: flex; justify-content: space-between;">
                                    <span>ค่าเช่าห้อง:</span>
                                    <strong>฿<?php echo number_format($latest_bill['room_rent'], 2); ?></strong>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span>ค่าน้ำ (<?php echo $latest_bill['water_usage']; ?> หน่วย):</span>
                                    <strong>฿<?php echo number_format($latest_bill['water_cost'], 2); ?></strong>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span>ค่าไฟ (<?php echo $latest_bill['electric_usage']; ?> หน่วย):</span>
                                    <strong>฿<?php echo number_format($latest_bill['electric_cost'], 2); ?></strong>
                                </div>
                                <div style="border-top: 2px solid var(--medium-gray); padding-top: 12px; display: flex; justify-content: space-between; font-size: 18px;">
                                    <strong>รวมทั้งสิ้น:</strong>
                                    <strong style="color: var(--accent-color);">฿<?php echo number_format($latest_bill['total_amount'], 2); ?></strong>
                                </div>
                            </div>
                            <div style="margin-top: 15px;">
                                <span class="badge badge-<?php echo $latest_bill['payment_status'] == 'paid' ? 'success' : 'warning'; ?>">
                                    <?php echo $latest_bill['payment_status'] == 'paid' ? 'ชำระแล้ว' : 'รอชำระ'; ?>
                                </span>
                                <span style="margin-left: 10px; color: var(--dark-gray);">
                                    ครบกำหนด: <?php echo date('d/m/Y', strtotime($latest_bill['due_date'])); ?>
                                </span>
                            </div>
                        </div>
                        <div style="text-align: center;">
                            <a href="my_bills.php" class="btn btn-primary">ดูบิลทั้งหมด</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- การแจ้งเตือน -->
            <?php if (count($notifications) > 0): ?>
                <div class="card" style="margin-top: 25px;">
                    <div class="card-header">🔔 การแจ้งเตือน</div>
                    <?php foreach($notifications as $notif): ?>
                        <div style="padding: 15px; border-bottom: 1px solid var(--light-gray); <?php echo $notif['is_read'] ? 'opacity: 0.6;' : ''; ?>">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div style="flex: 1;">
                                    <strong><?php echo $notif['title']; ?></strong>
                                    <p style="margin: 5px 0 0 0; color: var(--dark-gray);"><?php echo $notif['message']; ?></p>
                                </div>
                                <small style="color: var(--dark-gray); white-space: nowrap; margin-left: 15px;">
                                    <?php echo date('d/m/Y H:i', strtotime($notif['created_at'])); ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- ยังไม่มีห้อง -->
            <div class="card" style="text-align: center; padding: 60px 30px;">
                <h2 style="margin-bottom: 15px;">คุณยังไม่มีห้องพัก</h2>
                <p style="color: var(--dark-gray); margin-bottom: 30px;">
                    เริ่มต้นค้นหาห้องพักที่เหมาะกับคุณและจองได้เลย!
                </p>
                <a href="browse_rooms.php" class="btn btn-primary" style="padding: 15px 40px; font-size: 16px;">
                    ดูห้องพักว่าง
                </a>
            </div>
        <?php endif; ?>

        <!-- เมนูด่วน -->
        <div class="grid grid-3" style="margin-top: 25px;">
            <a href="my_bills.php" class="btn btn-outline" style="padding: 20px;">
                💰 ดูบิลทั้งหมด
            </a>
            <a href="maintenance.php" class="btn btn-outline" style="padding: 20px;">
                🔧 แจ้งซ่อม
            </a>
            <a href="../owner/chat.php" class="btn btn-outline" style="padding: 20px;">
                💬 ติดต่อเจ้าของหอ
            </a>
        </div>
    </div>
</body>
</html>