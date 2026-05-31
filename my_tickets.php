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
$page_title = "تذاكري";

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

$user_id = $_SESSION['user_id'];

// جلب تذاكر المستخدم
$tickets = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $tickets = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "لم يتم العثور على جدول التذاكر";
}

// ========== معالجة قراءة الإشعار (بدون redirect) ==========
if (isset($_GET['read_notif'])) {
    $notif_id = intval($_GET['read_notif']);
    try {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")->execute([$notif_id]);
    } catch(PDOException $e) {}
    $current_url = strtok($_SERVER['REQUEST_URI'], '?');
    header("Location: " . $current_url);
    exit();
}

// تضمين الهيدر
include_once 'header.php';
?>

<style>
    .tickets-container { max-width: 1000px; margin: 20px auto; padding: 0 15px; }
    .tickets-header {
        background: var(--bg-header-card); padding: 15px; border-radius: 15px;
        text-align: center; margin-bottom: 20px; color: var(--text-heading);
    }
    .tickets-header h2 { color: var(--text-heading); }
    .tickets-card {
        background: var(--bg-card); border-radius: 15px; padding: 15px;
        margin-bottom: 20px; box-shadow: 0 2px 5px var(--shadow-sm);
    }
    .btn {
        background: var(--btn-primary); color: white; padding: 8px 16px;
        text-decoration: none; border-radius: 25px; display: inline-block;
        border: none; cursor: pointer; font-size: 14px; font-weight: bold; transition: 0.3s;
    }
    .btn:hover { background: var(--btn-primary-hover); }
    .btn-new { background: var(--btn-success); }
    .btn-new:hover { background: #1b5e20; }
    
    .table-wrapper { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; min-width: 500px; }
    th, td { padding: 10px; text-align: center; border-bottom: 1px solid var(--border-color); font-size: 13px; color: var(--text-primary); }
    th { background: #b8860b; color: white; }
    
    .status-open { background: var(--badge-pending-bg); color: var(--badge-pending-text); padding: 3px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
    .status-progress { background: var(--badge-info-bg); color: var(--badge-info-text); padding: 3px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
    .status-closed { background: var(--badge-success-bg); color: var(--badge-success-text); padding: 3px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
    
    @media (max-width: 600px) {
        th, td { padding: 8px 5px; font-size: 11px; }
        .btn { padding: 6px 12px; font-size: 12px; }
    }
</style>

<div class="tickets-container">
    <div class="tickets-header">
        <h2>🎫 تذاكر الدعم الفني</h2>
        <a href="submit_ticket.php" class="btn btn-new">➕ تذكرة جديدة</a>
    </div>

    <div class="tickets-card">
        <?php if(isset($error)): ?>
            <div style="background:var(--error-bg); padding:10px; border-radius:8px; color:var(--error-text);"><?php echo $error; ?></div>
        <?php elseif(count($tickets) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>#</th><th>الموضوع</th><th>الحالة</th><th>الأولوية</th><th>التاريخ</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($tickets as $ticket): ?>
                        <tr>
                            <td><?php echo $ticket['id']; ?></td>
                            <td><?php echo htmlspecialchars(mb_substr($ticket['subject'], 0, 30)); ?></td>
                            <td>
                                <?php if($ticket['status'] == 'open') echo '<span class="status-open">🟡 مفتوحة</span>';
                                elseif($ticket['status'] == 'in_progress') echo '<span class="status-progress">🔵 قيد المعالجة</span>';
                                else echo '<span class="status-closed">🟢 مغلقة</span>'; ?>
                            </td>
                            <td><?php echo $ticket['priority'] == 'high' ? '🔴 عالية' : ($ticket['priority'] == 'medium' ? '🟠 متوسطة' : '🟢 منخفضة'); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($ticket['created_at'])); ?></td>
                            <td><a href="view_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn" style="padding:4px 12px; font-size:11px;">👁️ عرض</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="text-align:center; color:var(--text-muted);">📭 لا توجد تذاكر بعد.</p>
            <p style="text-align:center;"><a href="submit_ticket.php" style="color:var(--text-link);">أنشئ تذكرة الآن</a></p>
        <?php endif; ?>
    </div>
</div>

<?php include_once 'footer.php'; ?>
<?php include_once 'bottom_nav.php'; ?>
</body>
</html>