<?php
// owner/dashboard.php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'owner') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// ดึงสถิติ
$stats = [];

// จำนวนห้องทั้งหมด
$query = "SELECT COUNT(*) as total FROM rooms";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_rooms'] = $stmt->fetch()['total'];

// ห้องว่าง
$query = "SELECT COUNT(*) as available FROM rooms WHERE status = 'available'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['available_rooms'] = $stmt->fetch()['available'];

// ห้องที่มีผู้เช่า
$query = "SELECT COUNT(*) as occupied FROM rooms WHERE status = 'occupied'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['occupied_rooms'] = $stmt->fetch()['occupied'];

// รายได้เดือนนี้
$query = "SELECT SUM(total_amount) as revenue 
          FROM monthly_bills 
          WHERE MONTH(billing_month) = MONTH(CURRENT_DATE()) 
          AND YEAR(billing_month) = YEAR(CURRENT_DATE())
          AND payment_status = 'paid'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['monthly_revenue'] = $stmt->fetch()['revenue'] ?? 0;

// บิลค้างชำระ
$query = "SELECT COUNT(*) as unpaid 
          FROM monthly_bills 
          WHERE payment_status = 'unpaid'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['unpaid_bills'] = $stmt->fetch()['unpaid'];

// งานซ่อมรอดำเนินการ
$query = "SELECT COUNT(*) as pending 
          FROM maintenance_requests 
          WHERE status IN ('pending', 'assigned', 'in_progress')";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['pending_maintenance'] = $stmt->fetch()['pending'];

// คำขอจองห้องใหม่
$query = "SELECT COUNT(*) as pending_bookings 
          FROM bookings 
          WHERE status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['pending_bookings'] = $stmt->fetch()['pending_bookings'];

// ผู้เช่าทั้งหมด
$query = "SELECT COUNT(DISTINCT tenant_id) as total_tenants 
          FROM contracts 
          WHERE status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_tenants'] = $stmt->fetch()['total_tenants'];

// ดึงกิจกรรมล่าสุด
$recent_query = "
    (SELECT 'booking' as type, b.booking_id as id, u.full_name, r.room_number, b.booking_date as activity_date
     FROM bookings b
     JOIN users u ON b.user_id = u.user_id
     JOIN rooms r ON b.room_id = r.room_id
     WHERE b.status = 'pending'
     ORDER BY b.booking_date DESC LIMIT 5)
    UNION ALL
    (SELECT 'maintenance' as type, m.request_id as id, u.full_name, r.room_number, m.created_at as activity_date
     FROM maintenance_requests m
     JOIN users u ON m.tenant_id = u.user_id
     JOIN rooms r ON m.room_id = r.room_id
     WHERE m.status = 'pending'
     ORDER BY m.created_at DESC LIMIT 5)
    ORDER BY activity_date DESC LIMIT 10
";
$recent_stmt = $db->prepare($recent_query);
$recent_stmt->execute();
$recent_activities = $recent_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - เจ้าของหอพัก</title>
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
        <h1 style="margin: 30px 0 20px 0; color: var(--primary-color);">
            สวัสดี, <?php echo $_SESSION['full_name']; ?> 👋
        </h1>
        
        <!-- สถิติภาพรวม -->
        <div class="grid grid-4">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_rooms']; ?></div>
                <div class="stat-label">ห้องพักทั้งหมด</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number" style="color: var(--success-color);"><?php echo $stats['available_rooms']; ?></div>
                <div class="stat-label">ห้องว่าง</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number" style="color: var(--primary-color);"><?php echo $stats['occupied_rooms']; ?></div>
                <div class="stat-label">ห้องที่มีผู้เช่า</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number" style="color: var(--success-color);">
                    ฿<?php echo number_format($stats['monthly_revenue'], 2); ?>
                </div>
                <div class="stat-label">รายได้เดือนนี้</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number" style="color: var(--danger-color);"><?php echo $stats['unpaid_bills']; ?></div>
                <div class="stat-label">บิลค้างชำระ</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number" style="color: var(--warning-color);"><?php echo $stats['pending_maintenance']; ?></div>
                <div class="stat-label">งานซ่อมค้างดำเนินการ</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number" style="color: var(--accent-color);"><?php echo $stats['pending_bookings']; ?></div>
                <div class="stat-label">คำขอจองใหม่</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_tenants']; ?></div>
                <div class="stat-label">ผู้เช่าทั้งหมด</div>
            </div>
        </div>

        <!-- กิจกรรมล่าสุด -->
        <div class="card" style="margin-top: 30px;">
            <div class="card-header">🔔 กิจกรรมล่าสุด</div>
            
            <?php if(count($recent_activities) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ประเภท</th>
                                <th>ผู้ใช้</th>
                                <th>ห้อง</th>
                                <th>วันที่</th>
                                <th>การดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_activities as $activity): ?>
                                <tr>
                                    <td>
                                        <?php if($activity['type'] == 'booking'): ?>
                                            <span class="badge badge-info">จองห้อง</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">แจ้งซ่อม</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $activity['full_name']; ?></td>
                                    <td><?php echo $activity['room_number']; ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($activity['activity_date'])); ?></td>
                                    <td>
                                        <?php if($activity['type'] == 'booking'): ?>
                                            <a href="bookings.php?id=<?php echo $activity['id']; ?>" class="btn btn-primary" style="padding: 6px 15px; font-size: 13px;">ดูรายละเอียด</a>
                                        <?php else: ?>
                                            <a href="maintenance.php?id=<?php echo $activity['id']; ?>" class="btn btn-primary" style="padding: 6px 15px; font-size: 13px;">ดูรายละเอียด</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="text-align: center; padding: 30px; color: var(--dark-gray);">ไม่มีกิจกรรมใหม่</p>
            <?php endif; ?>
        </div>

        <!-- ลิงก์ด่วน -->
        <div class="card" style="margin-top: 20px;">
            <div class="card-header">⚡ เมนูด่วน</div>
            <div class="grid grid-4" style="gap: 15px;">
                <a href="rooms.php" class="btn btn-outline">จัดการห้องพัก</a>
                <a href="bills.php?action=create" class="btn btn-outline">สร้างบิลใหม่</a>
                <a href="bookings.php" class="btn btn-outline">อนุมัติการจอง</a>
                <a href="maintenance.php" class="btn btn-outline">มอบหมายงานซ่อม</a>
            </div>
        </div>
    </div>
</body>
</html>