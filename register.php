<?php
// register.php
require_once 'config/database.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = sanitize_input($_POST['full_name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    
    // Validation
    if ($password !== $confirm_password) {
        $error = 'รหัสผ่านไม่ตรงกัน';
    } elseif (strlen($password) < 6) {
        $error = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        // ตรวจสอบ username ซ้ำ
        $check_query = "SELECT user_id FROM users WHERE username = :username OR email = :email";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':username', $username);
        $check_stmt->bindParam(':email', $email);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $error = 'ชื่อผู้ใช้หรืออีเมลนี้มีในระบบแล้ว';
        } else {
            // สร้างบัญชี
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $insert_query = "INSERT INTO users (username, password, full_name, email, phone, role) 
                           VALUES (:username, :password, :full_name, :email, :phone, 'tenant')";
            
            $stmt = $db->prepare($insert_query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            
            if ($stmt->execute()) {
                $success = 'สมัครสมาชิกสำเร็จ! กำลังนำคุณไปยังหน้าเข้าสู่ระบบ...';
                header('refresh:2;url=login.php');
            } else {
                $error = 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก - ระบบจัดการหอพัก</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px 0;
        }
        
        .register-container {
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 500px;
            width: 90%;
            margin: 20px auto;
        }
        
        .register-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            padding: 30px;
            text-align: center;
            color: var(--white);
        }
        
        .register-header h1 {
            font-size: 26px;
            margin-bottom: 8px;
        }
        
        .register-body {
            padding: 35px 30px;
        }
        
        .grid-2-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .register-btn {
            width: 100%;
            padding: 15px;
            font-size: 16px;
            margin-top: 10px;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: var(--dark-gray);
        }
        
        .login-link a {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>📝 สมัครสมาชิก</h1>
            <p>สร้างบัญชีเพื่อจองห้องพัก</p>
        </div>
        
        <div class="register-body">
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">ชื่อ-นามสกุล *</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                
                <div class="grid-2-col">
                    <div class="form-group">
                        <label class="form-label">ชื่อผู้ใช้ *</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">เบอร์โทรศัพท์ *</label>
                        <input type="tel" name="phone" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">อีเมล *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                
                <div class="grid-2-col">
                    <div class="form-group">
                        <label class="form-label">รหัสผ่าน *</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">ยืนยันรหัสผ่าน *</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary register-btn">สมัครสมาชิก</button>
            </form>
            
            <div class="login-link">
                มีบัญชีอยู่แล้ว? <a href="login.php">เข้าสู่ระบบ</a>
            </div>
        </div>
    </div>
</body>
</html>