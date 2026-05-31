<?php
session_start();
// جلسة تنتهي بعد 15 دقيقة من الخمول
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 900)) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
$_SESSION['last_activity'] = time();
require_once 'config.php';

// ============================================================
// [للباقي الصفحات] هذه المتغيرات ضرورية للشريط السفلي
// يجب وضعها في بداية أي صفحة تريد ظهور الشريط السفلي فيها
// ============================================================
// تحديد الصفحة الحالية (يستخدم لتحديد الزر النشط في الشريط السفلي)
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = "تذكرة جديدة";

// جلب إشعارات المستخدم (إذا كانت الصفحة تحتاج إشعارات)
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

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $priority = $_POST['priority'];
    
    if (empty($subject) || empty($message)) {
        $error = "الرجاء إدخال الموضوع والرسالة";
    } else {
        try {
            $ticket_number = 'TKT-' . date('Ymd') . '-' . rand(1000, 9999);
            $stmt = $pdo->prepare("INSERT INTO support_tickets (ticket_number, user_id, subject, message, priority) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$ticket_number, $_SESSION['user_id'], $subject, $message, $priority]);
            
            // إشعار للمديرين
            $admins = $pdo->query("SELECT id FROM users WHERE user_type = 'admin'")->fetchAll();
            $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, '📢 تذكرة دعم جديدة', ?, 'admin_dashboard.php?active_tab=support_tickets')");
            foreach ($admins as $admin) {
                $notif_stmt->execute([$admin['id'], "تذكرة جديدة من: " . $_SESSION['user_name']]);
            }
            $success = "✅ تم إرسال تذكرتك بنجاح. سيتم الرد عليك قريباً.";
        } catch(PDOException $e) {
            $error = "حدث خطأ: " . $e->getMessage();
        }
    }
}

// تضمين الهيدر
include_once 'header.php';
?>

<style>
    .ticket-container { max-width: 550px; margin: 30px auto; padding: 0 15px; }
    .ticket-card { 
        background: var(--bg-card); 
        border-radius: 20px; 
        padding: 25px 20px; 
        box-shadow: 0 2px 10px var(--shadow-md); 
    }
    .ticket-title { color: var(--text-heading); text-align: center; margin-bottom: 20px; font-size: 22px; }
    .ticket-card input, 
    .ticket-card textarea, 
    .ticket-card select { 
        width: 100%; 
        padding: 12px; 
        margin-bottom: 15px; 
        border: 1px solid var(--border-input); 
        border-radius: 12px; 
        font-size: 14px; 
        font-family: inherit;
        background: var(--bg-input);
        color: var(--text-primary);
    }
    .ticket-card button { 
        background: var(--btn-primary); 
        color: white; 
        border: none; 
        padding: 14px; 
        border-radius: 40px; 
        cursor: pointer; 
        width: 100%; 
        font-size: 16px; 
        font-weight: bold;
        transition: 0.3s;
    }
    .ticket-card button:hover { background: var(--btn-primary-hover); }
    .error-msg { background: var(--error-bg); color: var(--error-text); padding: 12px; border-radius: 12px; margin-bottom: 15px; text-align: center; }
    .success-msg { background: var(--success-bg); color: var(--success-text); padding: 20px; border-radius: 12px; margin-bottom: 15px; text-align: center; }
    
    @media (max-width: 500px) {
        .ticket-container { margin: 20px auto; }
        .ticket-card { padding: 18px; }
    }
</style>

<div class="ticket-container">
    <div class="ticket-card">
        <h2 class="ticket-title">📝 تذكرة دعم جديدة</h2>
        
        <?php if($error): ?>
            <div class="error-msg"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="success-msg">
                <p style="font-size: 24px; margin-bottom: 10px;">✅</p>
                <p><?php echo $success; ?></p>
            </div>
        <?php else: ?>
            <form method="POST">
                <input type="text" name="subject" placeholder="📌 الموضوع" required>
                <select name="priority">
                    <option value="low">🟢 أولوية منخفضة</option>
                    <option value="medium" selected>🟠 أولوية متوسطة</option>
                    <option value="high">🔴 أولوية عالية</option>
                </select>
                <textarea name="message" rows="6" placeholder="✍️ اشرح مشكلتك بالتفصيل..." required></textarea>
                <button type="submit">📨 إرسال</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php
// تضمين الفوتر والشريط السفلي
include_once 'footer.php';
include_once 'bottom_nav.php';
?>
</body>
</html>