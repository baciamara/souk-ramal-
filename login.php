<?php
session_start();
require_once 'config.php';

$error = '';

// حماية CSRF: إنشاء توكن جديد لكل جلسة
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// التحقق من محاولات تسجيل الدخول الفاشلة
function is_ip_blocked($pdo, $ip) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->execute([$ip]);
    $attempts = $stmt->fetchColumn();
    return $attempts >= 5;
}

function log_failed_attempt($pdo, $email, $ip) {
    $stmt = $pdo->prepare("INSERT INTO login_attempts (email, ip_address, attempt_time) VALUES (?, ?, NOW())");
    $stmt->execute([$email, $ip]);
}

function clear_failed_attempts($pdo, $ip) {
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
    $stmt->execute([$ip]);
}

// جلب عنوان IP للمستخدم
function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
    return $_SERVER['REMOTE_ADDR'];
}

$ip_address = get_client_ip();

// إنشاء جدول login_attempts إذا لم يكن موجوداً
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(100),
            ip_address VARCHAR(45),
            attempt_time DATETIME
        )
    ");
} catch(PDOException $e) {}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // التحقق من CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "طلب غير صالح، يرجى إعادة المحاولة";
    } elseif (is_ip_blocked($pdo, $ip_address)) {
        $error = "لقد تجاوزت الحد المسموح من المحاولات الفاشلة. يرجى المحاولة بعد 15 دقيقة.";
    } else {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // تسجيل دخول ناجح: مسح محاولات الفشل السابقة
                clear_failed_attempts($pdo, $ip_address);
                
                // التحقق من أن الحساب ليس معطلاً
                if ($user['is_disabled'] == 1) {
                    $error = "حسابك معطل من قبل الإدارة. يرجى التواصل مع الدعم.";
                }
                // التحقق من أن البائع مفعل (is_active)
                elseif ($user['user_type'] == 'seller' && $user['is_active'] == 0) {
                    $error = "حسابك قيد المراجعة من قبل المدير. يرجى الانتظار.";
                }
                // التحقق من أن شركة التوصيل مفعلة
                elseif ($user['user_type'] == 'shipping_company' && $user['is_active'] == 0) {
                    $error = "حساب شركة التوصيل قيد المراجعة. يرجى الانتظار.";
                }
                // التحقق من أن نقطة التجميع مفعلة
                elseif ($user['user_type'] == 'pickup_point' && $user['is_active'] == 0) {
                    $error = "حساب نقطة التجميع قيد المراجعة. يرجى الانتظار.";
                } else {
                    // التحقق من وضع الصيانة
                    $maintenance_mode = false;
                    try {
                        $stmt2 = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
                        $stmt2->execute();
                        $maintenance_mode = $stmt2->fetchColumn() == '1';
                    } catch(PDOException $e) {}
                    
                    if ($maintenance_mode && $user['user_type'] != 'admin') {
                        include 'maintenance.php';
                        exit();
                    }
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['last_activity'] = time(); // تتبع وقت آخر نشاط
                    
                    if ($user['company_id']) {
                        $_SESSION['shipping_company_id'] = $user['company_id'];
                    }
                    if ($user['pickup_point_id']) {
                        $_SESSION['pickup_point_id'] = $user['pickup_point_id'];
                    }
                    
                    // توجيه المستخدم حسب نوع الحساب
                    if ($user['user_type'] == 'admin') {
                        header("Location: admin_dashboard.php");
                    } elseif ($user['user_type'] == 'seller') {
                        header("Location: dashboard.php");
                    } elseif ($user['user_type'] == 'shipping_company') {
                        header("Location: shipping_dashboard.php");
                    } elseif ($user['user_type'] == 'pickup_point') {
                        header("Location: pickup_dashboard.php");
                    } else {
                        header("Location: index.php");
                    }
                    exit();
                }
            } else {
                // تسجيل محاولة فاشلة
                log_failed_attempt($pdo, $email, $ip_address);
                $error = "البريد الإلكتروني أو كلمة المرور غير صحيحة";
            }
        } catch(PDOException $e) {
            $error = "خطأ في قاعدة البيانات: " . $e->getMessage();
        }
    }
    
    // تجديد CSRF Token لمنع إعادة الاستخدام
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - سوق الرمال</title>
    <style>
        body {
            font-family: 'Tahoma', Arial, sans-serif;
            background: linear-gradient(135deg, #f5a623, #f8e1b0);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
            padding: 30px;
        }
        h1 { text-align: center; color: #b8860b; margin-bottom: 30px; }
        h3 { text-align: center; color: #666; margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: #333; font-weight: bold; text-align: right; }
        
        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #f0e0c0;
            border-radius: 10px;
            font-size: 16px;
            font-family: inherit;
            box-sizing: border-box;
            margin-bottom: 20px;
            text-align: right;
            direction: rtl;
        }
        input:focus { outline: none; border-color: #b8860b; }
        
        .password-field {
            position: relative;
            margin-bottom: 20px;
        }
        .password-field input {
            width: 100%;
            padding: 12px;
            padding-left: 45px;
            margin: 0;
            box-sizing: border-box;
            text-align: right;
            direction: rtl;
        }
        .toggle-password {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            background: none;
            border: none;
            font-size: 18px;
            color: #999;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s;
        }
        .toggle-password:hover {
            color: #b8860b;
            background: #f0e0c0;
        }
        
        button[type="submit"] {
            width: 100%;
            background: #b8860b;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }
        button[type="submit"]:hover { background: #9a7008; }
        
        .error { background: #ffebee; color: #c62828; padding: 12px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        .links { display: flex; justify-content: flex-end; margin: 15px 0; }
        .links a { color: #b8860b; text-decoration: none; font-size: 13px; }
        .register-link { text-align: center; margin-top: 20px; color: #666; }
        .register-link a { color: #b8860b; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏜️ سوق الرمال</h1>
        <h3>تسجيل الدخول</h3>
        
        <?php if($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <label>البريد الإلكتروني</label>
            <input type="email" name="email" required>
            
            <label>كلمة المرور</label>
            <div class="password-field">
                <input type="password" name="password" id="password" required>
                <button type="button" class="toggle-password" onclick="togglePassword()">👁️</button>
            </div>
            
            <div class="links">
                <a href="forgot_password.php">نسيت كلمة المرور؟</a>
            </div>
            
            <button type="submit">دخول</button>
        </form>
        
        <div class="register-link">
            ليس لديك حساب؟ <a href="register.php">إنشاء حساب جديد</a>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            var field = document.getElementById('password');
            var btn = event.currentTarget;
            if (field.type === "password") {
                field.type = "text";
                btn.innerHTML = "🙈";
            } else {
                field.type = "password";
                btn.innerHTML = "👁️";
            }
        }
    </script>
</body>
</html>