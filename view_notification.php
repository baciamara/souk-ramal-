<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$notif_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$notif_id) {
    header("Location: index.php");
    exit();
}

// جلب الإشعار
$notification = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([$notif_id, $_SESSION['user_id']]);
    $notification = $stmt->fetch();
    
    if (!$notification) {
        header("Location: index.php");
        exit();
    }
    
    // تحديث حالة الإشعار إلى مقروء
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")->execute([$notif_id]);
} catch(PDOException $e) {
    header("Location: index.php");
    exit();
}

// ============================================================
// المتغيرات المطلوبة للهيدر والشريط السفلي
// ============================================================
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = "الإشعار";

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

include_once 'header.php';
?>

<style>
    .notif-view-container { max-width: 700px; margin: 30px auto; padding: 0 20px; }
    .notif-view-card { background: var(--bg-card); border-radius: 20px; padding: 30px; box-shadow: 0 5px 20px var(--shadow-lg); }
    .notif-view-icon { font-size: 48px; text-align: center; margin-bottom: 15px; }
    .notif-view-title { font-size: 22px; font-weight: bold; color: var(--text-heading); margin-bottom: 10px; text-align: center; }
    .notif-view-date { text-align: center; color: var(--text-muted); font-size: 13px; margin-bottom: 20px; }
    .notif-view-message { font-size: 16px; line-height: 1.8; color: var(--text-primary); background: var(--bg-card-alt); padding: 20px; border-radius: 15px; }
    .notif-view-link { display: block; text-align: center; margin-top: 25px; }
    .notif-view-link a { background: var(--btn-primary); color: white; text-decoration: none; padding: 12px 25px; border-radius: 30px; font-weight: bold; transition: 0.3s; display: inline-block; }
    .notif-view-link a:hover { background: var(--btn-primary-hover); }
    
    @media (max-width: 768px) {
        .notif-view-card { padding: 20px; }
        .notif-view-title { font-size: 18px; }
        .notif-view-message { font-size: 14px; padding: 15px; }
    }
</style>

<div class="notif-view-container">
    <div class="notif-view-card">
        <div class="notif-view-icon">
            <?php 
            // أيقونة حسب نوع الإشعار
            $title = $notification['title'] ?? '';
            if (strpos($title, 'تذكرة') !== false || strpos($title, 'تذكرتك') !== false) {
                echo '🎫';
            } elseif (strpos($title, 'شكوى') !== false) {
                echo '⚠️';
            } elseif (strpos($title, 'طلب') !== false) {
                echo '📦';
            } elseif (strpos($title, '✅') !== false || strpos($title, 'نجاح') !== false) {
                echo '✅';
            } else {
                echo '📢';
            }
            ?>
        </div>
        <h2 class="notif-view-title"><?php echo htmlspecialchars($notification['title']); ?></h2>
        <div class="notif-view-date">📅 <?php echo date('Y-m-d H:i', strtotime($notification['created_at'])); ?></div>
        <div class="notif-view-message">
            <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
        </div>
        <?php if(!empty($notification['link'])): ?>
            <div class="notif-view-link">
                <a href="<?php echo $notification['link']; ?>">🔗 الذهاب إلى التفاصيل</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include_once 'footer.php';
include_once 'bottom_nav.php';
?>
</body>
</html>