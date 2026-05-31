<?php
session_start();
require_once 'config.php';

// ============================================================
// المتغيرات المطلوبة للهيدر والشريط السفلي
// ============================================================
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = "عرض التذكرة";

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

// معالجة قراءة الإشعار
if (isset($_GET['read_notif'])) {
    $notif_id = intval($_GET['read_notif']);
    try {
        $notif_stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ? AND user_id = ?");
        $notif_stmt->execute([$notif_id, $_SESSION['user_id']]);
        $notification = $notif_stmt->fetch();
        
        if ($notification) {
            $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")->execute([$notif_id]);
            if (!empty($notification['link'])) {
                header("Location: " . $notification['link']);
                exit();
            }
        }
    } catch(PDOException $e) {}
    
    $current_url = strtok($_SERVER['REQUEST_URI'], '?');
    header("Location: " . $current_url);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$ticket_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$ticket_id) {
    header("Location: " . ($_SESSION['user_type'] == 'admin' ? "admin_dashboard.php?active_tab=support_tickets" : "my_tickets.php"));
    exit();
}

$is_admin = ($_SESSION['user_type'] == 'admin');
$user_id = $_SESSION['user_id'];

// جلب التذكرة
$ticket = null;
try {
    if ($is_admin) {
        $stmt = $pdo->prepare("SELECT t.*, u.full_name as user_name FROM support_tickets t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
        $stmt->execute([$ticket_id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE id = ? AND user_id = ?");
        $stmt->execute([$ticket_id, $user_id]);
    }
    $ticket = $stmt->fetch();
    if (!$ticket) {
        header("Location: " . ($is_admin ? "admin_dashboard.php?active_tab=support_tickets" : "my_tickets.php"));
        exit();
    }
} catch(PDOException $e) {
    die("خطأ في جلب التذكرة: " . $e->getMessage());
}

// جلب الردود
$replies = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM ticket_replies WHERE ticket_id = ? ORDER BY created_at ASC");
    $stmt->execute([$ticket_id]);
    $replies = $stmt->fetchAll();
} catch(PDOException $e) {}

// معالجة إضافة رد
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reply'])) {
    $reply_message = trim($_POST['reply_message']);
    if (!empty($reply_message)) {
        try {
            $pdo->beginTransaction();
            $user_type = $is_admin ? 'admin' : 'user';
            $stmt = $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, user_type, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$ticket_id, $user_id, $user_type, $reply_message]);
            
            if ($is_admin) {
                $new_status = $_POST['ticket_status'] ?? 'in_progress';
                $pdo->prepare("UPDATE support_tickets SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$new_status, $ticket_id]);
                $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, '✅ تم الرد على تذكرتك', ?, ?)");
                $notif->execute([$ticket['user_id'], "تم الرد على تذكرتك رقم #$ticket_id", "view_ticket.php?id=$ticket_id"]);
            } else {
                $pdo->prepare("UPDATE support_tickets SET status = 'open', updated_at = NOW() WHERE id = ?")->execute([$ticket_id]);
                $admins = $pdo->query("SELECT id FROM users WHERE user_type = 'admin'")->fetchAll();
                $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, '💬 رد جديد على تذكرة', ?, ?)");
                foreach ($admins as $admin) {
                    $notif->execute([$admin['id'], "تذكرة #$ticket_id: " . $_SESSION['user_name'] . " رد جديد", "admin_dashboard.php?active_tab=support_tickets"]);
                }
            }
            $pdo->commit();
            header("Location: view_ticket.php?id=$ticket_id");
            exit();
        } catch(PDOException $e) {
            $pdo->rollBack();
            $error = "حدث خطأ: " . $e->getMessage();
        }
    } else {
        $error = "الرجاء كتابة الرد";
    }
}

// تضمين الهيدر
include_once 'header.php';
?>

<style>
    .ticket-container { max-width: 800px; margin: 30px auto; padding: 0 20px; }
    .ticket-card { background: var(--bg-card); border-radius: 20px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 10px var(--shadow-md); }
    .ticket-card h2 { color: var(--text-heading); margin-bottom: 15px; }
    .reply-box { padding: 15px; margin-bottom: 15px; border-radius: 15px; border-right: 4px solid #b8860b; }
    .admin-reply { background: var(--badge-info-bg); border-right-color: #1565c0; }
    .user-reply { background: var(--bg-header-card); border-right-color: #b8860b; }
    .reply-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
    .reply-header strong { color: var(--text-welcome); }
    .reply-header small { color: var(--text-muted); font-size: 12px; }
    .btn { background: var(--btn-primary); color: white; padding: 10px 20px; border: none; border-radius: 25px; cursor: pointer; font-size: 14px; font-weight: bold; transition: 0.3s; }
    .btn:hover { background: var(--btn-primary-hover); }
    textarea { width: 100%; padding: 12px; border-radius: 12px; border: 1px solid var(--border-input); font-family: inherit; font-size: 14px; background: var(--bg-input); color: var(--text-primary); }
    .status { display: inline-block; padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: bold; }
    .status-open { background: var(--badge-pending-bg); color: var(--badge-pending-text); }
    .status-closed { background: var(--badge-success-bg); color: var(--badge-success-text); }
    .status-in_progress { background: var(--badge-info-bg); color: var(--badge-info-text); }
    select { padding: 8px 12px; border-radius: 10px; border: 1px solid var(--border-input); margin-bottom: 10px; font-family: inherit; background: var(--bg-input); color: var(--text-primary); }
    .error-msg { background: var(--error-bg); color: var(--error-text); padding: 10px; border-radius: 10px; margin-bottom: 10px; }
    .closed-notice { background: var(--bg-card-alt); padding: 15px; border-radius: 10px; text-align: center; color: var(--text-muted); margin-top: 15px; }
    .info-row { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 10px; }
    .info-item { display: flex; align-items: center; gap: 5px; font-size: 13px; color: var(--text-primary); }
    .info-item strong { color: var(--text-heading); }
    hr { border: none; border-top: 1px solid var(--border-light); margin: 15px 0; }
    .ticket-card h4 { color: var(--text-welcome); }
    .ticket-card h3 { color: var(--text-heading); }
    .ticket-card p { color: var(--text-primary); }
    
    @media (max-width: 768px) {
        .ticket-card { padding: 18px; }
    }
</style>

<div class="ticket-container">
    <div class="ticket-card">
        <h2>📌 <?php echo htmlspecialchars($ticket['subject']); ?></h2>
        
        <div class="info-row">
            <?php if ($is_admin): ?>
                <div class="info-item"><strong>👤 صاحب التذكرة:</strong> <?php echo htmlspecialchars($ticket['user_name']); ?></div>
            <?php endif; ?>
            <div class="info-item">
                <strong>الحالة:</strong> 
                <span class="status status-<?php echo $ticket['status']; ?>">
                    <?php echo $ticket['status']=='open'?'🔓 مفتوحة':($ticket['status']=='in_progress'?'⏳ قيد المعالجة':'🔒 مغلقة'); ?>
                </span>
            </div>
            <div class="info-item">
                <strong>الأولوية:</strong> 
                <?php echo $ticket['priority']=='high'?'🔴 عالية':($ticket['priority']=='medium'?'🟠 متوسطة':'🟢 منخفضة'); ?>
            </div>
            <div class="info-item">
                <strong>📅</strong> <?php echo date('Y-m-d H:i', strtotime($ticket['created_at'])); ?>
            </div>
        </div>
        
        <hr>
        <h4>📝 الرسالة الأصلية:</h4>
        <p style="line-height:1.8;"><?php echo nl2br(htmlspecialchars($ticket['message'])); ?></p>
    </div>

    <div class="ticket-card">
        <h3>💬 الردود (<?php echo count($replies); ?>)</h3>
        
        <?php if(count($replies) > 0): ?>
            <?php foreach($replies as $reply): ?>
                <div class="reply-box <?php echo $reply['user_type'] == 'admin' ? 'admin-reply' : 'user-reply'; ?>">
                    <div class="reply-header">
                        <strong>
                            <?php if($reply['user_type'] == 'admin'): ?>
                                👑 الإدارة
                            <?php else: ?>
                                👤 <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                            <?php endif; ?>
                        </strong>
                        <small>📅 <?php echo date('Y-m-d H:i', strtotime($reply['created_at'])); ?></small>
                    </div>
                    <p style="line-height:1.6;"><?php echo nl2br(htmlspecialchars($reply['message'])); ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align:center; color:var(--text-muted); padding:20px;">لا توجد ردود بعد</p>
        <?php endif; ?>
        
        <?php if($ticket['status'] != 'closed' || $is_admin): ?>
            <form method="POST" style="margin-top:20px;">
                <textarea name="reply_message" rows="4" placeholder="✍️ اكتب ردك هنا..." required></textarea>
                <?php if ($is_admin): ?>
                    <select name="ticket_status" style="margin-top:10px;">
                        <option value="open" <?php echo $ticket['status']=='open'?'selected':''; ?>>🔓 مفتوحة</option>
                        <option value="in_progress" <?php echo $ticket['status']=='in_progress'?'selected':''; ?>>⏳ قيد المعالجة</option>
                        <option value="closed" <?php echo $ticket['status']=='closed'?'selected':''; ?>>🔒 مغلقة</option>
                    </select>
                <?php endif; ?>
                <?php if($error): ?>
                    <div class="error-msg"><?php echo $error; ?></div>
                <?php endif; ?>
                <button type="submit" name="reply" class="btn" style="margin-top:10px;">📨 إرسال الرد</button>
            </form>
        <?php else: ?>
            <div class="closed-notice">🔒 التذكرة مغلقة. لا يمكن إضافة ردود جديدة.</div>
        <?php endif; ?>
    </div>
</div>

<?php
include_once 'footer.php';
include_once 'bottom_nav.php';
?>
</body>
</html>