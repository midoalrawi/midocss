<?php
session_start();
require __DIR__ . '/config.php';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "اسم المستخدم أو كلمة المرور غير صحيحة";
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام الإدارة - تسجيل الدخول</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap');
        
        :root {
            --primary: #2D5B7A;
            --primary-light: #3A7CA5;
            --secondary: #7EBDC2;
            --accent: #F3A712;
            --light: #F8F9FA;
            --dark: #2F4858;
            --gray: #6C757D;
            --error: #D64045;
            --border-radius: 8px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Tajawal', sans-serif;
        }
        
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4eaf1 100%);
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 440px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        
        .login-header {
            background: var(--primary);
            color: white;
            text-align: center;
            padding: 30px 20px;
        }
        
        .login-header h1 {
            font-weight: 500;
            font-size: 28px;
            margin-bottom: 8px;
        }
        
        .login-header p {
            font-weight: 300;
            opacity: 0.9;
        }
        
        .login-form {
            padding: 35px 30px 30px;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #DDE2E5;
            border-radius: var(--border-radius);
            font-size: 16px;
            transition: all 0.2s;
            background: #F8F9FA;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(45, 91, 122, 0.1);
            background: white;
        }
        
        .error-message {
            background-color: #FFEFEF;
            color: var(--error);
            padding: 14px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            text-align: center;
            font-size: 15px;
            border: 1px solid #FFC9C9;
        }
        
        .btn-login {
            width: 100%;
            padding: 15px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 17px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .btn-login:hover {
            background: var(--primary-light);
        }
        
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 25px 0;
            color: var(--gray);
            font-size: 14px;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #DDE2E5;
        }
        
        .divider::before {
            margin-left: 10px;
        }
        
        .divider::after {
            margin-right: 10px;
        }
        
        .system-info {
            text-align: center;
            color: var(--gray);
            font-size: 13px;
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid #EEF1F4;
        }
        
        @media (max-width: 480px) {
            .login-container {
                border-radius: 12px;
            }
            
            .login-form {
                padding: 25px 20px 20px;
            }
            
            .login-header {
                padding: 25px 15px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>مرحباً بعودتك</h1>
            <p>يجب تسجيل الدخول للمتابعة إلى النظام</p>
        </div>
        
        <div class="login-form">
            <?php if ($error): ?>
                <div class="error-message">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">اسم المستخدم</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="أدخل اسم المستخدم" required autocomplete="username">
                </div>
                
                <div class="form-group">
                    <label for="password">كلمة المرور</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="أدخل كلمة المرور" required autocomplete="current-password">
                </div>
                
                <button type="submit" class="btn-login">تسجيل الدخول</button>
                
                <div class="divider">نظام الإدارة</div>
                
                <div class="system-info">
                    © 2025 نظام الإدارة | الإصدار 1.0
                </div>
            </form>
        </div>
    </div>
</body>
</html>
