<?php
session_start();
require_once 'config.php';

// معالجة قراءة الإشعار
if (isset($_GET['read_notif'])) {
    $notif_id = intval($_GET['read_notif']);
    try {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")->execute([$notif_id]);
    } catch(PDOException $e) {}
    $current_url = strtok($_SERVER['REQUEST_URI'], '?');
    header("Location: " . $current_url);
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
$page_title = "اتصل بنا";

$user_notifications = [];
$unread_count = 0;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
        $stmt->execute([$_SESSION['user_id']]);
        $user_notifications = $stmt->fetchAll();
        $unread_count = count($user_notifications);
    } catch(PDOException $e) {}
}

$message_sent = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = "جميع الحقول مطلوبة";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "البريد الإلكتروني غير صحيح";
    } else {
        // إرسال البريد (يمكنك تفعيل هذا الجزء لاحقاً)
        $to = "info.soukramal@gmail.com";
        $headers = "From: " . $email . "\r\n";
        $headers .= "Reply-To: " . $email . "\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        $full_message = "<strong>الاسم:</strong> " . $name . "<br>";
        $full_message .= "<strong>البريد:</strong> " . $email . "<br>";
        $full_message .= "<strong>الموضوع:</strong> " . $subject . "<br>";
        $full_message .= "<strong>الرسالة:</strong><br>" . nl2br($message);
        
        // mail($to, $subject, $full_message, $headers);
        $message_sent = "✅ تم إرسال رسالتك بنجاح. سنتواصل معك قريباً.";
    }
}

include_once 'header.php';
?>

<style>
    .contact-container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
    
    .contact-card { 
        background: var(--bg-card); 
        border-radius: 20px; 
        padding: 35px; 
        box-shadow: 0 5px 20px rgba(0,0,0,0.08); 
    }
    
    .contact-card h1 { 
        color: var(--text-heading); 
        margin-bottom: 25px; 
        font-size: 32px; 
    }
    
    .contact-card h3 { 
        color: var(--text-heading); 
        margin-bottom: 20px; 
    }
    
    .success-msg { 
        background: var(--success-bg); 
        color: var(--success-text); 
        padding: 15px; 
        border-radius: 10px; 
        margin-bottom: 20px; 
        text-align: center;
    }
    
    .error-msg { 
        background: var(--error-bg); 
        color: var(--error-text); 
        padding: 15px; 
        border-radius: 10px; 
        margin-bottom: 20px; 
        text-align: center;
    }
    
    .contact-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
        gap: 40px; 
    }
    
    .contact-info p { 
        margin-bottom: 15px; 
        color: var(--text-primary);
    }
    
    .contact-info a { 
        color: var(--text-heading); 
        text-decoration: none;
        transition: opacity 0.3s;
    }
    
    .contact-info a:hover { 
        opacity: 0.8; 
    }
    
    .contact-form input, 
    .contact-form textarea { 
        width: 100%; 
        padding: 12px; 
        margin-bottom: 15px; 
        border: 1px solid var(--border-input, #ddd); 
        border-radius: 10px; 
        font-family: inherit; 
        font-size: 14px;
        background: var(--bg-input);
        color: var(--text-primary);
        transition: border-color 0.3s;
    }
    
    .contact-form input:focus, 
    .contact-form textarea:focus { 
        outline: none; 
        border-color: var(--text-heading); 
    }
    
    .contact-form button { 
        background: var(--btn-primary); 
        color: var(--text-white); 
        border: none; 
        padding: 12px 25px; 
        border-radius: 30px; 
        cursor: pointer; 
        font-size: 14px;
        font-weight: bold;
        transition: background 0.3s;
    }
    
    .contact-form button:hover { 
        background: var(--btn-primary-hover); 
    }
    
    @media (max-width: 768px) {
        .contact-container { margin: 20px auto; }
        .contact-card { padding: 20px; }
        .contact-card h1 { font-size: 24px; }
        .contact-grid { gap: 30px; }
    }
</style>

<div class="contact-container">
    <div class="contact-card">
        <h1>📝 اتصل بنا</h1>
        
        <?php if($message_sent): ?>
            <div class="success-msg"><?php echo $message_sent; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="error-msg"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="contact-grid">
            <div class="contact-info">
                <h3>📞 معلومات الاتصال</h3>
                <p><strong>الهاتف:</strong> <a href="tel:+213696025621">+213 696 02 56 21</a></p>
                <p><strong>البريد الإلكتروني:</strong> <a href="mailto:info@souk-ramal.com">info.soukramal@gmail.com</a></p>
                <p><strong>واتساب:</strong> <a href="https://wa.me/213696025621" target="_blank">+213 696 02 56 21</a></p>
                <p><strong>العنوان:</strong> ولاية الوادي، الجزائر</p>
            </div>
            
            <div class="contact-form">
                <h3>✉️ أرسل لنا رسالة</h3>
                <form method="POST">
                    <input type="text" name="name" placeholder="الاسم الكامل" required>
                    <input type="email" name="email" placeholder="البريد الإلكتروني" required>
                    <input type="text" name="subject" placeholder="الموضوع" required>
                    <textarea name="message" rows="5" placeholder="رسالتك..." required></textarea>
                    <button type="submit">📨 إرسال</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once 'footer.php'; ?>
<?php include_once 'bottom_nav.php'; ?>
</body>
</html>