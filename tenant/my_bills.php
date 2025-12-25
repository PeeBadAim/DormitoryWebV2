<?php
// tenant/my_bills.php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tenant') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// ดึงบิลทั้งหมด
$query = "SELECT b.*, r.room_number
          FROM monthly_bills b
          JOIN rooms r ON b.room_id = r.room_id
          WHERE b.tenant_id = :user_id
          ORDER BY b.billing_month DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$bills = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>บิลของฉัน</title>
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
        <h1 style="margin: 30px 0;">💰 บิลของฉัน</h1>

        <?php if(count($bills) > 0): ?>
            <div class="grid grid-2">
                <?php foreach($bills as $bill): ?>
                    <div class="card" style="cursor: pointer;" onclick='viewBill(<?php echo json_encode($bill); ?>)'>
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                            <div>
                                <h3 style="margin-bottom: 5px;">บิล #<?php echo $bill['bill_id']; ?></h3>
                                <p style="color: var(--dark-gray); margin: 0;">
                                    ห้อง <?php echo $bill['room_number']; ?> • 
                                    <?php echo date('F Y', strtotime($bill['billing_month'])); ?>
                                </p>
                            </div>
                            <span class="badge badge-<?php 
                                echo $bill['payment_status'] == 'paid' ? 'success' : 
                                    ($bill['payment_status'] == 'overdue' ? 'danger' : 'warning'); 
                            ?>">
                                <?php 
                                echo $bill['payment_status'] == 'paid' ? 'ชำระแล้ว' : 
                                    ($bill['payment_status'] == 'overdue' ? 'เกินกำหนด' : 'รอชำระ'); 
                                ?>
                            </span>
                        </div>
                        
                        <div style="background: var(--light-gray); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                            <div style="display: grid; gap: 8px;">
                                <div style="display: flex; justify-content: space-between;">
                                    <span>ค่าเช่า:</span>
                                    <span>฿<?php echo number_format($bill['room_rent'], 2); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span>ค่าน้ำ:</span>
                                    <span>฿<?php echo number_format($bill['water_cost'], 2); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span>ค่าไฟ:</span>
                                    <span>฿<?php echo number_format($bill['electric_cost'], 2); ?></span>
                                </div>
                                <div style="border-top: 2px solid var(--medium-gray); padding-top: 8px; display: flex; justify-content: space-between; font-weight: 700; font-size: 18px;">
                                    <span>รวม:</span>
                                    <span style="color: var(--accent-color);">฿<?php echo number_format($bill['total_amount'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div style="color: var(--dark-gray); font-size: 14px;">
                            ครบกำหนด: <?php echo date('d/m/Y', strtotime($bill['due_date'])); ?>
                            <?php if($bill['payment_date']): ?>
                                <br>ชำระเมื่อ: <?php echo date('d/m/Y', strtotime($bill['payment_date'])); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card" style="text-align: center; padding: 60px;">
                <h2 style="margin-bottom: 15px;">ยังไม่มีบิล</h2>
                <p style="color: var(--dark-gray);">บิลของคุณจะแสดงที่นี่</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal -->
    <div id="billModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2 class="modal-title">รายละเอียดบิล</h2>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="billContent"></div>
        </div>
    </div>

    <script>
        function viewBill(bill) {
            const content = `
                <div style="background: var(--light-gray); padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <h3 style="margin-bottom: 10px;">บิล #${bill.bill_id}</h3>
                    <div style="color: var(--dark-gray);">
                        ห้อง ${bill.room_number} • ${new Date(bill.billing_month).toLocaleDateString('th-TH', {month: 'long', year: 'numeric'})}
                    </div>
                </div>
                
                <table style="width: 100%; margin-bottom: 20px;">
                    <tr style="background: var(--light-gray);">
                        <td style="padding: 12px; font-weight: 600;">รายการ</td>
                        <td style="padding: 12px; text-align: right; font-weight: 600;">จำนวน</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px; border-bottom: 1px solid var(--medium-gray);">ค่าเช่าห้อง</td>
                        <td style="padding: 12px; text-align: right; border-bottom: 1px solid var(--medium-gray);">฿${parseFloat(bill.room_rent).toFixed(2)}</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px; border-bottom: 1px solid var(--medium-gray);">
                            ค่าน้ำ (${parseFloat(bill.water_usage).toFixed(2)} หน่วย)<br>
                            <small style="color: var(--dark-gray);">มิเตอร์: ${parseFloat(bill.water_previous_reading).toFixed(2)} → ${parseFloat(bill.water_current_reading).toFixed(2)}</small>
                        </td>
                        <td style="padding: 12px; text-align: right; border-bottom: 1px solid var(--medium-gray);">฿${parseFloat(bill.water_cost).toFixed(2)}</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px; border-bottom: 1px solid var(--medium-gray);">
                            ค่าไฟ (${parseFloat(bill.electric_usage).toFixed(2)} หน่วย)<br>
                            <small style="color: var(--dark-gray);">มิเตอร์: ${parseFloat(bill.electric_previous_reading).toFixed(2)} → ${parseFloat(bill.electric_current_reading).toFixed(2)}</small>
                        </td>
                        <td style="padding: 12px; text-align: right; border-bottom: 1px solid var(--medium-gray);">฿${parseFloat(bill.electric_cost).toFixed(2)}</td>
                    </tr>
                    <tr style="background: var(--light-gray); font-weight: 600; font-size: 18px;">
                        <td style="padding: 15px;">รวมทั้งสิ้น</td>
                        <td style="padding: 15px; text-align: right; color: var(--accent-color);">฿${parseFloat(bill.total_amount).toFixed(2)}</td>
                    </tr>
                </table>
                
                ${bill.qr_code_path ? `
                    <div style="text-align: center; padding: 20px; background: var(--light-gray); border-radius: 8px; margin-bottom: 20px;">
                        <h4 style="margin-bottom: 15px; color: var(--primary-color);">QR Code สำหรับชำระเงิน</h4>
                        <img src="../${bill.qr_code_path}" alt="QR Code" style="max-width: 300px; border-radius: 8px; box-shadow: var(--shadow);">
                        <p style="margin-top: 15px; color: var(--dark-gray);">สแกน QR Code เพื่อดูรายละเอียดบิล</p>
                        <button onclick="printQR('../${bill.qr_code_path}')" class="btn btn-primary" style="margin-top: 10px;">
                            พิมพ์ QR Code
                        </button>
                    </div>
                ` : ''}
                
                <div style="padding: 15px; background: ${bill.payment_status === 'paid' ? '#d4edda' : (bill.payment_status === 'overdue' ? '#f8d7da' : '#fff3cd')}; border-radius: 8px; text-align: center;">
                    <strong>สถานะ: ${bill.payment_status === 'paid' ? 'ชำระเงินแล้ว' : (bill.payment_status === 'overdue' ? 'เกินกำหนดชำระ' : 'รอชำระเงิน')}</strong><br>
                    <small>ครบกำหนด: ${new Date(bill.due_date).toLocaleDateString('th-TH')}</small>
                    ${bill.payment_date ? `<br><small>ชำระเมื่อ: ${new Date(bill.payment_date).toLocaleDateString('th-TH')}</small>` : ''}
                </div>
            `;
            
            document.getElementById('billContent').innerHTML = content;
            document.getElementById('billModal').classList.add('active');
        }
        
        function printQR(qrPath) {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Print QR Code</title>
                    <style>
                        body { text-align: center; padding: 50px; }
                        img { max-width: 400px; }
                    </style>
                </head>
                <body>
                    <h2>QR Code สำหรับชำระเงิน</h2>
                    <img src="${qrPath}" alt="QR Code">
                    <script>window.print(); window.close();</script>
                </body>
                </html>
            `);
        }
        
        function closeModal() {
            document.getElementById('billModal').classList.remove('active');
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>