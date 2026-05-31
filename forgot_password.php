<?php
session_start();
require_once 'config.php';

$message = '';
$error = '';
$step = 'request'; // request, verify, reset, success

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['contact']) && !isset($_POST['code']) && !isset($_POST['new_password'])) {
        // طلب إعادة تعيين (بريد إلكتروني أو هاتف)
        $contact = trim($_POST['contact']);
        $method = filter_var($contact, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        
        try {
            if ($method == 'email') {
                $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE email = ?");
                $stmt->execute([$contact]);
            } else {
                $stmt = $pdo->prepare("SELECT id, full_name, phone FROM users WHERE phone = ?");
                $stmt->execute([$contact]);
            }
            $user = $stmt->fetch();
            
            if ($user) {
                // إنشاء رمز تحقق عشوائي
                $reset_code = rand(100000, 999999);
                $_SESSION['reset_code'] = $reset_code;
                $_SESSION['reset_contact'] = $contact;
                $_SESSION['reset_method'] = $method;
                $_SESSION['reset_user_id'] = $user['id'];
                
                // هنا يمكن إرسال الرمز عبر البريد أو SMS
                // للتبسيط، سنعرض الرمز على الشاشة (في الإنتاج يجب إرساله)
                $step = 'verify';
                $message = "تم إرسال رمز التحقق: <strong>$reset_code</strong> إلى $contact";
            } else {
                $error = "البريد الإلكتروني أو رقم الهاتف غير مسجل";
            }
        } catch(PDOException $e) {
            $error = "حدث خطأ: " . $e->getMessage();
        }
    } elseif (isset($_POST['code']) && isset($_POST['new_password']) && !isset($_POST['contact'])) {
        // إعادة تعيين كلمة المرور
        $code = $_POST['code'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $error = "كلمة المرور غير متطابقة";
        } elseif (strlen($new_password) < 6) {
            $error = "كلمة المرور يجب أن تكون 6 أحرف على الأقل";
        } elseif ($code != $_SESSION['reset_code']) {
            $error = "رمز التحقق غير صحيح";
        } else {
            $user_id = $_SESSION['reset_user_id'];
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            try {
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                $message = "تم تغيير كلمة المرور بنجاح. يمكنك الآن تسجيل الدخول.";
                $step = 'success';
                
                // تنظيف الجلسة
                unset($_SESSION['reset_code']);
                unset($_SESSION['reset_contact']);
                unset($_SESSION['reset_method']);
                unset($_SESSION['reset_user_id']);
            } catch(PDOException $e) {
                $error = "حدث خطأ: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['resend_code'])) {
        // إعادة إرسال الرمز
        $reset_code = rand(100000, 999999);
        $_SESSION['reset_code'] = $reset_code;
        $contact = $_SESSION['reset_contact'];
        $message = "تم إعادة إرسال رمز التحقق: <strong>$reset_code</strong> إلى $contact";
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نسيت كلمة المرور - سوق الرمال</title>
    <style>
        body { font-family: 'Tahoma', Arial, sans-serif; background: linear-gradient(135deg, #f5a623, #f8e1b0); display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .container { background: white; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.2); width: 100%; max-width: 450px; padding: 35px; }
        h1 { text-align: center; color: #b8860b; margin-bottom: 10px; }
        h3 { text-align: center; color: #666; margin-bottom: 25px; font-weight: normal; }
        label { display: block; margin-bottom: 8px; color: #333; font-weight: bold; }
        input, select { width: 100%; padding: 12px; border: 2px solid #f0e0c0; border-radius: 10px; font-size: 16px; margin-bottom: 20px; font-family: inherit; }
        input:focus, select:focus { outline: none; border-color: #b8860b; }
        button { width: 100%; background: #b8860b; color: white; border: none; padding: 14px; border-radius: 10px; font-size: 16px; font-weight: bold; cursor: pointer; transition: background 0.3s; }
        button:hover { background: #9a7008; }
        .btn-secondary { background: #666; margin-top: 10px; }
        .btn-secondary:hover { background: #555; }
        .message { background: #e8f5e9; color: #2e7d32; padding: 12px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        .error { background: #ffebee; color: #c62828; padding: 12px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { color: #b8860b; text-decoration: none; }
        .info-text { font-size: 12px; color: #999; margin-top: -15px; margin-bottom: 15px; }
        .contact-type { display: flex; gap: 15px; margin-bottom: 15px; }
        .contact-type label { display: flex; align-items: center; gap: 5px; font-weight: normal; cursor: pointer; }
        .contact-type input { width: auto; margin: 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏜️ سوق الرمال</h1>
        
        <?php if($step == 'request'): ?>
            <h3>استعادة كلمة المرور</h3>
            <p style="text-align:center; color:#666; margin-bottom:20px;">أدخل بريدك الإلكتروني أو رقم هاتفك</p>
            
            <?php if($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <label>البريد الإلكتروني أو رقم الهاتف</label>
                <input type="text" name="contact" placeholder="مثال: example@email.com أو 0555123456" required>
                <div class="info-text">سيتم إرسال رمز التحقق إلى بريدك الإلكتروني أو هاتفك</div>
                <button type="submit">إرسال رمز التحقق</button>
            </form>
            
        <?php elseif($step == 'verify'): ?>
            <h3>تأكيد الهوية</h3>
            
            <?php if($message): ?>
                <div class="message"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <label>رمز التحقق</label>
                <input type="text" name="code" placeholder="أدخل الرقم المكون من 6 أرقام" required>
                
                <label>كلمة المرور الجديدة</label>
                <input type="password" name="new_password" placeholder="********" required>
                
                <label>تأكيد كلمة المرور الجديدة</label>
                <input type="password" name="confirm_password" placeholder="********" required>
                
                <button type="submit">تغيير كلمة المرور</button>
            </form>
            
            <form method="POST" style="margin-top: 10px;">
                <button type="submit" name="resend_code" value="1" class="btn-secondary">إعادة إرسال رمز التحقق</button>
            </form>
            
        <?php elseif($step == 'success'): ?>
            <div class="message"><?php echo $message; ?></div>
            <div class="back-link">
                <a href="login.php">← تسجيل الدخول</a>
            </div>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="login.php">← العودة إلى تسجيل الدخول</a>
        </div>
    </div>
</body>
</html>