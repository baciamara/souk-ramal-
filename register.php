<?php
session_start();
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $agree_terms = isset($_POST['agree_terms']) ? true : false;
    $user_type = $_POST['user_type'];
    
    // التحقق من جميع الحقول
    if (empty($full_name) || empty($email) || empty($phone) || empty($password)) {
        $error = "جميع الحقول مطلوبة";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "البريد الإلكتروني غير صحيح";
    } elseif (strlen($password) < 6) {
        $error = "كلمة المرور يجب أن تكون 6 أحرف على الأقل";
    } elseif ($password !== $confirm_password) {
        $error = "كلمة المرور غير متطابقة";
    } elseif (!$agree_terms) {
        $error = "يجب الموافقة على سياسة الاستخدام";
    } else {
        try {
            // التحقق من عدم تكرار البريد الإلكتروني
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            
            if ($check->rowCount() > 0) {
                $error = "البريد الإلكتروني مسجل مسبقاً";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // تعيين حالة الحساب:
                // - المشتري: مفعل تلقائياً (is_active = 1)
                // - البائع، شركة التوصيل، نقطة التجميع: يحتاج موافقة المدير (is_active = 0)
                $is_active = ($user_type == 'buyer') ? 1 : 0;
                
                // company_id و pickup_point_id ستكون NULL في البداية
                $sql = "INSERT INTO users (full_name, email, phone, password, user_type, is_active, company_id, pickup_point_id) 
                        VALUES (?, ?, ?, ?, ?, ?, NULL, NULL)";
                $stmt = $pdo->prepare($sql);
                
                if ($stmt->execute([$full_name, $email, $phone, $hashed_password, $user_type, $is_active])) {
                    
                    // ========== إرسال إشعار للمدير ==========
                    if ($user_type == 'seller' || $user_type == 'shipping_company' || $user_type == 'pickup_point') {
                        // جلب جميع المديرين (قد يكون هناك أكثر من مدير)
                        $admins = $pdo->query("SELECT id FROM users WHERE user_type = 'admin'")->fetchAll();
                        
                        if (count($admins) > 0) {
                            $notif_title = '';
                            $notif_message = '';
                            $notif_link = '';
                            
                            if ($user_type == 'seller') {
                                $notif_title = '🧑 بائع جديد';
                                $notif_message = "البائع '$full_name' قام بالتسجيل وينتظر الموافقة";
                                $notif_link = 'admin_dashboard.php?active_tab=sellers';
                            } elseif ($user_type == 'shipping_company') {
                                $notif_title = '🚚 شركة توصيل جديدة';
                                $notif_message = "شركة التوصيل '$full_name' قامت بالتسجيل وتنتظر الموافقة";
                                $notif_link = 'admin_dashboard.php?active_tab=shipping_companies';
                            } elseif ($user_type == 'pickup_point') {
                                $notif_title = '📍 نقطة تجميع جديدة';
                                $notif_message = "نقطة التجميع '$full_name' قامت بالتسجيل وتنتظر الموافقة";
                                $notif_link = 'admin_dashboard.php?active_tab=pickup_points';
                            }
                            
                            $notif_stmt = $pdo->prepare("
                                INSERT INTO notifications (user_id, title, message, link, is_read, created_at) 
                                VALUES (?, ?, ?, ?, 0, NOW())
                            ");
                            
                            foreach ($admins as $admin) {
                                $notif_stmt->execute([$admin['id'], $notif_title, $notif_message, $notif_link]);
                            }
                        } else {
                            error_log("لم يتم العثور على مدير لإرسال الإشعار");
                        }
                    }
                    // ========================================
                    
                    // رسالة النجاح حسب نوع الحساب
                    if ($user_type == 'seller') {
                        $success = "تم إرسال طلب التسجيل كبائع. سيتم تفعيل حسابك بعد مراجعة المدير.";
                    } elseif ($user_type == 'shipping_company') {
                        $success = "تم تسجيل حساب شركة التوصيل بنجاح. سيتم تفعيل حسابك بعد موافقة المدير.";
                    } elseif ($user_type == 'pickup_point') {
                        $success = "تم تسجيل حساب نقطة التجميع بنجاح. سيتم تفعيل حسابك بعد موافقة المدير.";
                    } else {
                        $success = "تم التسجيل بنجاح. يمكنك تسجيل الدخول الآن.";
                    }
                } else {
                    $error = "حدث خطأ، حاول مرة أخرى";
                }
            }
        } catch(PDOException $e) {
            $error = "خطأ في قاعدة البيانات: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل جديد - سوق الرمال</title>
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
            max-width: 500px;
            padding: 30px;
        }
        h1 { text-align: center; color: #b8860b; margin-bottom: 30px; }
        h3 { text-align: center; color: #666; margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: #333; font-weight: bold; text-align: right; }
        
        input, select {
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
        input:focus, select:focus { outline: none; border-color: #b8860b; }
        
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
            margin-top: 10px;
        }
        button[type="submit"]:hover { background: #9a7008; }
        
        .error { background: #ffebee; color: #c62828; padding: 12px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        .success { background: #e8f5e9; color: #2e7d32; padding: 12px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        .terms { display: flex; align-items: center; gap: 10px; margin: 15px 0; justify-content: flex-start; }
        .terms input { width: auto; margin: 0; }
        .terms label { margin: 0; }
        .terms a { color: #b8860b; text-decoration: none; }
        .login-link { text-align: center; margin-top: 20px; color: #666; }
        .login-link a { color: #b8860b; text-decoration: none; }
        .note { font-size: 12px; color: #999; text-align: center; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏜️ سوق الرمال</h1>
        <h3>إنشاء حساب جديد</h3>
        
        <?php if($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <label>الاسم الكامل</label>
            <input type="text" name="full_name" required>
            
            <label>البريد الإلكتروني</label>
            <input type="email" name="email" required>
            
            <label>رقم الهاتف</label>
            <input type="tel" name="phone" required>
            
            <label>كلمة المرور</label>
            <div class="password-field">
                <input type="password" name="password" id="password" required>
                <button type="button" class="toggle-password" onclick="togglePassword('password')">👁️</button>
            </div>
            
            <label>تأكيد كلمة المرور</label>
            <div class="password-field">
                <input type="password" name="confirm_password" id="confirm_password" required>
                <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">👁️</button>
            </div>
            
            <label>نوع الحساب</label>
            <select name="user_type" id="user_type" required>
                <option value="buyer">مشتري</option>
                <option value="seller">بائع (تاجر)</option>
                <option value="shipping_company">🚚 شركة توصيل</option>
                <option value="pickup_point">📍 نقطة تجميع</option>
            </select>
            
            <div class="terms">
                <input type="checkbox" name="agree_terms" id="agree_terms" required>
                <label for="agree_terms">أوافق على <a href="terms.php" target="_blank">سياسة الاستخدام</a> وشروط الخدمة</label>
            </div>
            
            <button type="submit">تسجيل</button>
        </form>
        
        <div class="login-link">
            لديك حساب بالفعل؟ <a href="login.php">تسجيل الدخول</a>
        </div>
        <div class="note">
            ملاحظة: البائعون وشركات التوصيل ونقاط التجميع يحتاجون موافقة المدير قبل تفعيل حساباتهم
        </div>
    </div>
    
    <script>
        function togglePassword(fieldId) {
            var field = document.getElementById(fieldId);
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