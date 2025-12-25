<?php
// login.php
require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT * FROM users WHERE username = :username AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        
        // Redirect ตาม role
        switch($user['role']) {
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
    } else {
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - ระบบจัดการหอพัก</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .login-container {
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 450px;
            width: 90%;
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            padding: 40px 30px;
            text-align: center;
            color: var(--white);
        }
        
        .login-header h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }
        
        .login-header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .login-btn {
            width: 100%;
            padding: 15px;
            font-size: 16px;
            margin-top: 10px;
        }
        
        .register-link {
            text-align: center;
            margin-top: 25px;
            color: var(--dark-gray);
        }
        
        .register-link a {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🏢 ระบบจัดการหอพัก</h1>
            <p>เข้าสู่ระบบเพื่อใช้งาน</p>
        </div>
        
        <div class="login-body">
            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">ชื่อผู้ใช้</label>
                    <input type="text" name="username" class="form-control" required autofocus>
                </div>
                
                <div class="form-group">
                    <label class="form-label">รหัสผ่าน</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary login-btn">เข้าสู่ระบบ</button>
            </form>
            
            <div class="register-link">
                ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิก</a>
            </div>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--medium-gray);">
                <p style="text-align: center; color: var(--dark-gray); font-size: 13px;">
                    <strong>ทดสอบระบบ:</strong><br>
                    เจ้าของหอ: owner / password<br>
                    ช่าง: tech01 / password
                </p>
            </div>
        </div>
    </div>
</body>
</html>