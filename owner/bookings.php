<?php
// owner/bookings.php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'owner') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$message = '';

// อนุมัติ/ปฏิเสธการจอง
if (isset($_POST['action_booking'])) {
    $booking_id = $_POST['booking_id'];
    $action = $_POST['action'];
    
    if ($action == 'approve') {
        // เริ่ม transaction
        $db->beginTransaction();
        
        try {
            // อัพเดทสถานะการจอง
            $update_booking = "UPDATE bookings SET status = 'approved' WHERE booking_id = :booking_id";
            $stmt = $db->prepare($update_booking);
            $stmt->bindParam(':booking_id', $booking_id);
            $stmt->execute();
            
            // ดึงข้อมูลการจอง
            $get_booking = "SELECT * FROM bookings WHERE booking_id = :booking_id";
            $stmt = $db->prepare($get_booking);
            $stmt->bindParam(':booking_id', $booking_id);
            $stmt->execute();
            $booking = $stmt->fetch();
            
            // สร้างสัญญาเช่า
            $create_contract = "INSERT INTO contracts (room_id, tenant_id, start_date, deposit_amount, status) 
                               VALUES (:room_id, :tenant_id, :start_date, :deposit, 'active')";
            $stmt = $db->prepare($create_contract);
            $stmt->bindParam(':room_id', $booking['room_id']);
            $stmt->bindParam(':tenant_id', $booking['user_id']);
            $stmt->bindParam(':start_date', $booking['move_in_date']);
            
            // คำนวณเงินมัดจำ (2 เดือน)
            $room_query = "SELECT monthly_rent FROM rooms WHERE room_id = :room_id";
            $room_stmt = $db->prepare($room_query);
            $room_stmt->bindParam(':room_id', $booking['room_id']);
            $room_stmt->execute();
            $room = $room_stmt->fetch();
            $deposit = $room['monthly_rent'] * 2;
            
            $stmt->bindParam(':deposit', $deposit);
            $stmt->execute();
            
            // อัพเดทสถานะห้อง
            $update_room = "UPDATE rooms SET status = 'occupied' WHERE room_id = :room_id";
            $stmt = $db->prepare($update_room);
            $stmt->bindParam(':room_id', $booking['room_id']);
            $stmt->execute();
            
            // แจ้งเตือนผู้เช่า
            send_notification($booking['user_id'], 'การจองได้รับอนุมัติ', 
                'การจองห้องของคุณได้รับอนุมัติแล้ว กรุณาติดต่อชำระเงินมัดจำ ' . number_format($deposit, 2) . ' บาท', 
                'booking', $booking_id);
            
            $db->commit();
            $message = '<div class="alert alert-success">อนุมัติการจองสำเร็จ</div>';
            
        } catch (Exception $e) {
            $db->rollBack();
            $message = '<div class="alert alert-danger">เกิดข้อผิดพลาด: ' . $e->getMessage() . '</div>';
        }
        
    } elseif ($action == 'reject') {
        $update_booking = "UPDATE bookings SET status = 'rejected' WHERE booking_id = :booking_id";
        $stmt = $db->prepare($update_booking);
        $stmt->bindParam(':booking_id', $booking_id);
        $stmt->execute();
        
        // ดึงข้อมูลเพื่อแจ้งเตือน
        $get_booking = "SELECT user_id FROM bookings WHERE booking_id = :booking_id";
        $stmt = $db->prepare($get_booking);
        $stmt->bindParam(':booking_id', $booking_id);
        $stmt->execute();
        $booking = $stmt->fetch();
        
        send_notification($booking['user_id'], 'การจองถูกปฏิเสธ', 
            'ขออภัย การจองห้องของคุณถูกปฏิเสธ', 'booking', $booking_id);
        
        $message = '<div class="alert alert-success">ปฏิเสธการจองสำเร็จ</div>';
    }
}

// ดึงรายการจอง
$query = "SELECT b.*, r.room_number, r.monthly_rent, r.room_type, u.full_name, u.email, u.phone
          FROM bookings b
          JOIN rooms r ON b.room_id = r.room_id
          JOIN users u ON b.user_id = u.user_id
          ORDER BY 
            CASE b.status 
                WHEN 'pending' THEN 1 
                WHEN 'approved' THEN 2 
                ELSE 3 
            END,
            b.booking_date DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$bookings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการคำขอจอง</title>
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
        
        <h1 style="margin: 30px 0;">📋 คำขอจองห้องพัก</h1>

        <div class="card">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>รหัส</th>
                            <th>ห้อง</th>
                            <th>ประเภท</th>
                            <th>ผู้จอง</th>
                            <th>เบอร์โทร</th>
                            <th>วันที่เข้าพัก</th>
                            <th>วันที่จอง</th>
                            <th>สถานะ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($bookings as $booking): ?>
                            <tr>
                                <td><strong>#<?php echo $booking['booking_id']; ?></strong></td>
                                <td><?php echo $booking['room_number']; ?></td>
                                <td><?php echo $booking['room_type']; ?></td>
                                <td><?php echo $booking['full_name']; ?></td>
                                <td><?php echo $booking['phone']; ?></td>
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
                                <td>
                                    <button class="btn btn-primary" style="padding: 6px 12px; font-size: 13px;" 
                                            onclick='viewBooking(<?php echo json_encode($booking); ?>)'>
                                        ดูรายละเอียด
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
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">รายละเอียดการจอง</h2>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="bookingContent"></div>
        </div>
    </div>

    <script>
        function viewBooking(booking) {
            const deposit = parseFloat(booking.monthly_rent) * 2;
            
            const content = `
                <div style="background: var(--light-gray); padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <h3 style="margin-bottom: 15px;">การจอง #${booking.booking_id}</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <strong>ห้อง:</strong> ${booking.room_number}<br>
                            <strong>ประเภท:</strong> ${booking.room_type}<br>
                            <strong>ค่าเช่า:</strong> ฿${parseFloat(booking.monthly_rent).toLocaleString()}/เดือน<br>
                            <strong>เงินมัดจำ:</strong> ฿${deposit.toLocaleString()}
                        </div>
                        <div>
                            <strong>ผู้จอง:</strong> ${booking.full_name}<br>
                            <strong>อีเมล:</strong> ${booking.email}<br>
                            <strong>เบอร์:</strong> ${booking.phone}<br>
                            <strong>วันที่เข้าพัก:</strong> ${new Date(booking.move_in_date).toLocaleDateString('th-TH')}
                        </div>
                    </div>
                    ${booking.notes ? `<div style="margin-top: 15px;"><strong>หมายเหตุ:</strong><br>${booking.notes}</div>` : ''}
                </div>
                
                ${booking.status === 'pending' ? `
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <form method="POST" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการอนุมัติการจองนี้?')">
                            <input type="hidden" name="booking_id" value="${booking.booking_id}">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" name="action_booking" class="btn btn-success" style="width: 100%;">
                                ✓ อนุมัติ
                            </button>
                        </form>
                        
                        <form method="POST" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการปฏิเสธการจองนี้?')">
                            <input type="hidden" name="booking_id" value="${booking.booking_id}">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" name="action_booking" class="btn btn-danger" style="width: 100%;">
                                ✗ ปฏิเสธ
                            </button>
                        </form>
                    </div>
                ` : `
                    <div class="alert alert-${booking.status === 'approved' ? 'success' : 'danger'}">
                        สถานะ: ${booking.status === 'approved' ? 'อนุมัติแล้ว' : 'ปฏิเสธแล้ว'}
                    </div>
                `}
            `;
            
            document.getElementById('bookingContent').innerHTML = content;
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