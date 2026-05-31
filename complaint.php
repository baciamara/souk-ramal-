<?php
session_start();
require_once 'config.php';

// أي مستخدم مسجل يمكنه الإبلاغ عن مشكلة
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    header("Location: my_orders.php");
    exit();
}

$order_id = $_GET['order_id'];
$user_id = $_SESSION['user_id'];

// جلب معلومات الطلب
try {
    $stmt = $pdo->prepare("
        SELECT o.*, u.full_name as seller_name, p.name as product_name
        FROM orders o
        JOIN users u ON o.seller_id = u.id
        JOIN products p ON o.product_id = p.id
        WHERE o.id = ? AND o.buyer_id = ? AND (o.order_status = 'delivered' OR o.order_status = 'delivered_to_buyer')
        AND NOT EXISTS (SELECT 1 FROM complaints WHERE order_id = o.id)
    ");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        header("Location: my_orders.php?error=لا يمكن الإبلاغ عن هذا الطلب");
        exit();
    }
} catch(PDOException $e) {
    header("Location: my_orders.php");
    exit();
}

// ============================================================
// المتغيرات المطلوبة للهيدر والشريط السفلي
// ============================================================
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = "الإبلاغ عن مشكلة";

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

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $complaint_type = $_POST['complaint_type'];
    $subject = trim($_POST['subject']);
    $complaint_message = trim($_POST['message']);
    
    if (empty($subject) || empty($complaint_message)) {
        $error = "يرجى ملئ عنوان ورسالة الشكوى";
    } else {
        try {
            $pdo->beginTransaction();
            
            // إدخال الشكوى
            $stmt = $pdo->prepare("
                INSERT INTO complaints (order_id, complainer_id, accused_id, complaint_type, subject, message, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $order_id, 
                $user_id, 
                $order['seller_id'], 
                $complaint_type, 
                $subject, 
                $complaint_message
            ]);
            
            // إرسال إشعار للمدير بوجود شكوى جديدة
            $admin_stmt = $pdo->prepare("SELECT id FROM users WHERE user_type = 'admin' LIMIT 1");
            $admin_stmt->execute();
            $admin = $admin_stmt->fetch();
            
            if ($admin) {
                $notif_title = "⚠️ شكوى جديدة";
                $notif_message = "تم تقديم شكوى جديدة بواسطة " . ($_SESSION['user_name'] ?? 'مستخدم') . " بخصوص الطلب #" . htmlspecialchars($order['order_number']);
                $notif_link = "admin_dashboard.php?active_tab=complaints";
                
                $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, user_type, title, message, link, is_read, created_at) VALUES (?, 'admin', ?, ?, ?, 0, NOW())");
                $notif_stmt->execute([$admin['id'], $notif_title, $notif_message, $notif_link]);
            }
            
            $pdo->commit();
            
            $message = "✅ تم إرسال شكواك بنجاح. سيتم مراجعتها من قبل الإدارة قريباً.";
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            $error = "حدث خطأ: " . $e->getMessage();
        }
    }
}

// تضمين الهيدر
include_once 'header.php';
?>

<style>
    .complaint-container { max-width: 600px; margin: 30px auto; padding: 0 20px; }
    .complaint-card {
        background: var(--bg-card); border-radius: 20px;
        box-shadow: 0 5px 25px var(--shadow-lg); overflow: hidden;
    }
    .complaint-header {
        background: var(--bg-header-card); padding: 20px; font-size: 20px;
        font-weight: bold; color: var(--text-heading); border-bottom: 2px solid var(--border-gold);
    }
    .complaint-body { padding: 25px; }
    .complaint-success {
        background: var(--success-bg); color: var(--success-text);
        padding: 20px; border-radius: 15px; text-align: center;
    }
    .complaint-success h3 { color: var(--success-text); }
    .complaint-error {
        background: var(--error-bg); color: var(--error-text);
        padding: 12px; border-radius: 10px; margin-bottom: 20px; text-align: center;
    }
    .complaint-info {
        background: var(--bg-card-alt); padding: 15px; border-radius: 15px;
        margin-bottom: 25px; color: var(--text-primary);
    }
    .complaint-info strong { color: var(--text-heading); }
    label {
        display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-primary);
    }
    select, input, textarea {
        width: 100%; padding: 12px; border: 1px solid var(--border-input); border-radius: 10px;
        font-family: inherit; background: var(--bg-input); color: var(--text-primary);
    }
    select { margin-bottom: 20px; }
    input { margin-bottom: 20px; }
    textarea { margin-bottom: 20px; }
    .complaint-submit {
        background: #e65100; color: white; border: none; padding: 14px;
        border-radius: 40px; cursor: pointer; width: 100%; font-size: 16px; font-weight: bold;
    }
    .complaint-submit:hover { background: #bf360c; }
    
    @media (max-width: 768px) {
        .complaint-body { padding: 18px; }
    }
</style>

<div class="complaint-container">
    <div class="complaint-card">
        <div class="complaint-header">⚠️ الإبلاغ عن مشكلة</div>
        <div class="complaint-body">
            <?php if($message): ?>
                <div class="complaint-success">
                    <h3>✅ تم بنجاح</h3>
                    <p><?php echo $message; ?></p>
                </div>
            <?php else: ?>
                <?php if($error): ?>
                    <div class="complaint-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="complaint-info">
                    <p><strong>📦 المنتج:</strong> <?php echo htmlspecialchars($order['product_name']); ?></p>
                    <p><strong>👩 البائع:</strong> <?php echo htmlspecialchars($order['seller_name']); ?></p>
                    <p><strong>📝 رقم الطلب:</strong> <?php echo htmlspecialchars($order['order_number']); ?></p>
                </div>
                
                <form method="POST">
                    <label>نوع المشكلة</label>
                    <select name="complaint_type" required>
                        <option value="product">⚠️ مشكلة في المنتج (تالف، معيب، غير مطابق)</option>
                        <option value="seller">👩 مشكلة مع البائع (تأخير، عدم استجابة، سلوك)</option>
                        <option value="delivery">🚚 مشكلة في التوصيل (تأخير، فقدان)</option>
                        <option value="other">📝 أخرى</option>
                    </select>
                    
                    <label>عنوان الشكوى</label>
                    <input type="text" name="subject" placeholder="مثال: المنتج غير مطابق للمواصفات" required>
                    
                    <label>تفاصيل المشكلة</label>
                    <textarea name="message" rows="5" placeholder="يرجى وصف المشكلة بالتفصيل..." required></textarea>
                    
                    <button type="submit" class="complaint-submit">📤 إرسال الشكوى</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// تضمين الفوتر والشريط السفلي
include_once 'footer.php';
include_once 'bottom_nav.php';
?>
</body>
</html>