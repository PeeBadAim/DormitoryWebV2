<?php
// index.php
session_start();

// ถ้า login แล้วให้ redirect ไปหน้าที่เหมาะสม
if (isset($_SESSION['user_id'])) {
    switch($_SESSION['role']) {
        case 'owner':
            header('Location: owner/dashboard.php');
            break;
        case 'tenant':
            header('Location: tenant/dashboard.php');
            break;
        case 'technician':
            header('Location: technician/dashboard.php');
            break;
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการหอพัก</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .hero {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 50px 20px;
        }
        
        .hero-content {
            max-width: 1200px;
            text-align: center;
            color: white;
        }
        
        .hero h1 {
            font-size: 48px;
            margin-bottom: 20px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .hero p {
            font-size: 20px;
            margin-bottom: 40px;
            opacity: 0.95;
        }
        
        .hero-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .hero-buttons .btn {
            padding: 18px 40px;
            font-size: 18px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn-white {
            background: white;
            color: var(--primary-color);
        }
        
        .btn-white:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }
        
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 60px;
        }
        
        .feature-card {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 15px;
            border: 1px solid rgba(255,255,255,0.2);
            transition: all 0.3s;
        }
        
        .feature-card:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-5px);
        }
        
        .feature-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .feature-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .feature-desc {
            opacity: 0.9;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="hero">
        <div class="hero-content">
            <h1>🏢 ระบบจัดการหอพัก</h1>
            <p>ระบบจัดการหอพักแบบครบวงจร ใช้งานง่าย ครอบคลุมทุกฟังก์ชัน</p>
            
            <div class="hero-buttons">
                <a href="login.php" class="btn btn-white">เข้าสู่ระบบ</a>
                <a href="register.php" class="btn btn-primary">สมัครสมาชิก</a>
            </div>
            
            <div class="features">
                <div class="feature-card">
                    <div class="feature-icon">💰</div>
                    <div class="feature-title">จัดการบิลอัตโนมัติ</div>
                    <div class="feature-desc">สร้างบิลรายเดือนพร้อม QR Code สำหรับชำระเงินอัตโนมัติ</div>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🏠</div>
                    <div class="feature-title">จองห้องออนไลน์</div>
                    <div class="feature-desc">ผู้เช่าสามารถดูห้องว่างและจองได้ทันทีผ่านระบบ</div>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🔧</div>
                    <div class="feature-title">แจ้งซ่อมง่ายดาย</div>
                    <div class="feature-desc">แจ้งปัญหาและติดตามสถานะการซ่อมแซมแบบ Real-time</div>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">💬</div>
                    <div class="feature-title">ระบบแชทในตัว</div>
                    <div class="feature-desc">สื่อสารระหว่างเจ้าของหอ ผู้เช่า และช่างได้สะดวก</div>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <div class="feature-title">Dashboard ครบครัน</div>
                    <div class="feature-desc">ดูภาพรวมธุรกิจและสถิติต่างๆ ได้อย่างชัดเจน</div>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🔒</div>
                    <div class="feature-title">ปลอดภัยเชื่อถือได้</div>
                    <div class="feature-desc">ระบบรักษาความปลอดภัยข้อมูลระดับสูง</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>