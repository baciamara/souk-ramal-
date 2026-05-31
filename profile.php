<?php
session_start();
require_once 'config.php';

// إذا لم يكن المستخدم مسجلاً
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

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

// ============================================================
// المتغيرات المطلوبة للهيدر والشريط السفلي
// ============================================================
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = "حسابي";

// جلب الإشعارات
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

// جلب بيانات المستخدم من قاعدة البيانات
$userData = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $userData = [];
}

// ============================================================
// التحقق من إمكانية حذف الحساب
// ============================================================
$can_delete = true;
$delete_block_reason = '';

if ($userData['user_type'] == 'seller') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE seller_id = ? AND order_status NOT IN ('delivered_to_buyer', 'cancelled')");
    $stmt->execute([$_SESSION['user_id']]);
    $active_orders = $stmt->fetchColumn();
    if ($active_orders > 0) {
        $can_delete = false;
        $delete_block_reason = "⚠️ لا يمكن حذف الحساب لأن لديك $active_orders طلب(ات) نشط(ة) لم تكتمل بعد.";
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE seller_id = ? AND status = 'available'");
    $stmt->execute([$_SESSION['user_id']]);
    $active_products = $stmt->fetchColumn();
    if ($active_products > 0 && $can_delete) {
        $can_delete = false;
        $delete_block_reason = "⚠️ لا يمكن حذف الحساب لأن لديك $active_products منتج(ات) معروضة للبيع.";
    }
} elseif ($userData['user_type'] == 'buyer') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE buyer_id = ? AND order_status NOT IN ('delivered_to_buyer', 'cancelled')");
    $stmt->execute([$_SESSION['user_id']]);
    $active_orders = $stmt->fetchColumn();
    if ($active_orders > 0) {
        $can_delete = false;
        $delete_block_reason = "⚠️ لا يمكن حذف الحساب لأن لديك $active_orders طلب(ات) نشط(ة) لم تكتمل بعد.";
    }
} elseif ($userData['user_type'] == 'shipping_company') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE shipping_company_id = ? AND order_status NOT IN ('delivered_to_buyer', 'cancelled')");
    $stmt->execute([$_SESSION['user_id']]);
    $active_deliveries = $stmt->fetchColumn();
    if ($active_deliveries > 0) {
        $can_delete = false;
        $delete_block_reason = "⚠️ لا يمكن حذف الحساب لأن لديك $active_deliveries طلب(ات) توصيل نشط(ة).";
    }
} elseif ($userData['user_type'] == 'pickup_point') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE pickup_point_id = ? AND order_status NOT IN ('delivered_to_buyer', 'cancelled')");
    $stmt->execute([$_SESSION['user_id']]);
    $active_pickups = $stmt->fetchColumn();
    if ($active_pickups > 0) {
        $can_delete = false;
        $delete_block_reason = "⚠️ لا يمكن حذف الحساب لأن لديك $active_pickups طلب(ات) في انتظار التوصيل.";
    }
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id = ? AND status != 'closed'");
$stmt->execute([$_SESSION['user_id']]);
$open_tickets = $stmt->fetchColumn();
if ($open_tickets > 0 && $can_delete) {
    $can_delete = false;
    $delete_block_reason = "⚠️ لا يمكن حذف الحساب لأن لديك $open_tickets تذكرة(ة) دعم مفتوحة. يرجى إغلاقها أولاً.";
}

// معالجة تحديث الملف الشخصي
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $wilaya = trim($_POST['wilaya']);
        $commune = trim($_POST['commune']);
        
        if (empty($full_name)) {
            $error = "الاسم الكامل مطلوب";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, wilaya = ?, commune = ? WHERE id = ?");
                $stmt->execute([$full_name, $phone, $wilaya, $commune, $_SESSION['user_id']]);
                $_SESSION['user_name'] = $full_name;
                $success = "✅ تم تحديث المعلومات الشخصية بنجاح";
                $userData['full_name'] = $full_name;
                $userData['phone'] = $phone;
                $userData['wilaya'] = $wilaya;
                $userData['commune'] = $commune;
            } catch(PDOException $e) {
                $error = "❌ خطأ: " . $e->getMessage();
            }
        }
    }
    
    elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = "جميع الحقول مطلوبة";
        } elseif (strlen($new_password) < 6) {
            $error = "كلمة المرور الجديدة يجب أن تكون 6 أحرف على الأقل";
        } elseif ($new_password !== $confirm_password) {
            $error = "كلمة المرور الجديدة غير متطابقة";
        } else {
            if (password_verify($current_password, $userData['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                try {
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                    $success = "✅ تم تغيير كلمة المرور بنجاح";
                } catch(PDOException $e) {
                    $error = "❌ خطأ: " . $e->getMessage();
                }
            } else {
                $error = "❌ كلمة المرور الحالية غير صحيحة";
            }
        }
    }
    
    elseif (isset($_POST['delete_account']) && $can_delete) {
        $confirm_delete = $_POST['confirm_delete'];
        $delete_password = $_POST['delete_password'];
        
        if ($confirm_delete !== 'DELETE') {
            $error = "❌ الرجاء كتابة DELETE لتأكيد حذف الحساب";
        } elseif (empty($delete_password)) {
            $error = "❌ الرجاء إدخال كلمة المرور";
        } elseif (!password_verify($delete_password, $userData['password'])) {
            $error = "❌ كلمة المرور غير صحيحة";
        } else {
            try {
                if ($userData['user_type'] == 'seller') {
                    $stmt = $pdo->prepare("DELETE FROM products WHERE seller_id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                }
                $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$_SESSION['user_id']]);
                $pdo->prepare("DELETE FROM ticket_replies WHERE user_id = ?")->execute([$_SESSION['user_id']]);
                $pdo->prepare("DELETE FROM support_tickets WHERE user_id = ?")->execute([$_SESSION['user_id']]);
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$_SESSION['user_id']]);
                session_destroy();
                header("Location: register.php?msg=account_deleted");
                exit();
            } catch(PDOException $e) {
                $error = "❌ خطأ في حذف الحساب: " . $e->getMessage();
            }
        }
    }
}

// تحديد نوع الحساب بالعربية
$userTypeText = '';
switch($userData['user_type'] ?? '') {
    case 'admin': $userTypeText = '👑 مدير الموقع'; break;
    case 'seller': $userTypeText = '🛍️ بائع'; break;
    case 'buyer': $userTypeText = '🛒 مشتري'; break;
    case 'shipping_company': $userTypeText = '🚚 شركة توصيل'; break;
    case 'pickup_point': $userTypeText = '📍 نقطة تجميع'; break;
    default: $userTypeText = '👤 مستخدم';
}

// جلب قائمة الولايات
$wilayas_list = [];
try {
    $stmt = $pdo->query("SELECT name FROM wilayas ORDER BY name");
    $wilayas_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {}

// استدعاء الهيدر الموحد
include_once 'header.php';
?>

<style>
    .profile-container { max-width: 800px; margin: 30px auto; padding: 0 20px; }
    .profile-card {
        background: var(--bg-card); border-radius: 25px; padding: 30px;
        box-shadow: 0 10px 30px var(--shadow-md); margin-bottom: 25px;
    }
    .profile-header { text-align: center; margin-bottom: 25px; }
    .avatar {
        width: 100px; height: 100px;
        background: linear-gradient(135deg, #b8860b, #d4a017);
        border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
        font-size: 48px; color: white; margin-bottom: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .profile-header h1 { color: var(--text-heading); margin: 0; font-size: 28px; }
    .profile-header p { color: var(--text-heading); }
    .info-section {
        background: var(--bg-card-alt); border-radius: 20px; padding: 20px; margin: 20px 0;
    }
    .info-section h3 { color: var(--text-heading); margin-bottom: 20px; border-right: 3px solid #b8860b; padding-right: 12px; }
    .info-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--border-light); }
    .info-label { font-weight: bold; color: var(--text-heading); width: 35%; }
    .info-value { width: 65%; color: var(--text-primary); }
    .form-group { margin-bottom: 18px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-primary); }
    .form-group input, .form-group select {
        width: 100%; padding: 12px; border: 1px solid var(--border-input); border-radius: 12px;
        font-size: 14px; background: var(--bg-input); color: var(--text-primary);
    }
    .form-group input:focus, .form-group select:focus { outline: none; border-color: #b8860b; }
    .btn {
        display: inline-block; padding: 10px 25px; border-radius: 40px; text-decoration: none;
        transition: 0.3s; font-weight: bold; border: none; cursor: pointer; font-size: 14px;
    }
    .btn-primary { background: var(--btn-primary); color: white; }
    .btn-primary:hover { background: var(--btn-primary-hover); }
    .btn-danger { background: #ffebee; color: #c62828; }
    .btn-danger:hover { background: #ffcdd2; }
    .success-msg { background: var(--success-bg); color: var(--success-text); padding: 12px; border-radius: 12px; margin-bottom: 20px; text-align: center; }
    .error-msg { background: var(--error-bg); color: var(--error-text); padding: 12px; border-radius: 12px; margin-bottom: 20px; text-align: center; }
    .toggle-section {
        cursor: pointer; background: var(--bg-header-card); padding: 12px 20px;
        border-radius: 30px; margin-top: 15px; text-align: center; font-weight: bold; color: var(--text-heading);
    }
    .toggle-section:hover { background: var(--bg-card-alt); }
    .hidden-section { display: none; margin-top: 20px; }
    .delete-warning { background: var(--error-bg); padding: 15px; border-radius: 15px; margin-bottom: 15px; text-align: center; color: var(--error-text); }
    .delete-blocked { background: var(--error-bg); padding: 15px; border-radius: 15px; text-align: center; color: var(--error-text); }
    @media (max-width: 600px) {
        .profile-card { padding: 20px; }
        .info-row { flex-direction: column; }
        .info-label { width: 100%; margin-bottom: 5px; }
        .info-value { width: 100%; }
    }
</style>

<div class="profile-container">
    <?php if($success): ?><div class="success-msg"><?php echo $success; ?></div><?php endif; ?>
    <?php if($error): ?><div class="error-msg"><?php echo $error; ?></div><?php endif; ?>
    
    <div class="profile-card">
        <div class="profile-header">
            <div class="avatar">👤</div>
            <h1><?php echo htmlspecialchars($userData['full_name'] ?? ''); ?></h1>
            <p><?php echo $userTypeText; ?></p>
        </div>
        
        <div class="info-section">
            <h3><i class="fas fa-info-circle"></i> معلومات الحساب</h3>
            <div class="info-row"><div class="info-label"><i class="fas fa-envelope"></i> البريد:</div><div class="info-value"><?php echo htmlspecialchars($userData['email'] ?? ''); ?></div></div>
            <div class="info-row"><div class="info-label"><i class="fas fa-phone"></i> الهاتف:</div><div class="info-value"><?php echo htmlspecialchars($userData['phone'] ?? 'غير مضاف'); ?></div></div>
            <div class="info-row"><div class="info-label"><i class="fas fa-calendar"></i> التسجيل:</div><div class="info-value"><?php echo date('Y-m-d', strtotime($userData['created_at'] ?? 'now')); ?></div></div>
            <div class="info-row"><div class="info-label"><i class="fas fa-check-circle"></i> الحالة:</div><div class="info-value"><?php echo ($userData['is_active'] ?? 1) == 1 ? '🟢 نشط' : '🔴 غير نشط'; ?></div></div>
            <?php if($userData['user_type'] == 'seller' && isset($userData['seller_avg_rating'])): ?>
            <div class="info-row"><div class="info-label"><i class="fas fa-star"></i> التقييم:</div><div class="info-value">⭐ <?php echo number_format($userData['seller_avg_rating'] ?? 0, 1); ?> / 5 (<?php echo $userData['seller_ratings_count'] ?? 0; ?> تقييم)</div></div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="profile-card">
        <div class="toggle-section" onclick="toggleSection('editInfo')"><i class="fas fa-edit"></i> تعديل المعلومات الشخصية ▼</div>
        <div id="editInfo" class="hidden-section">
            <form method="POST">
                <div class="form-group"><label><i class="fas fa-user"></i> الاسم الكامل</label><input type="text" name="full_name" value="<?php echo htmlspecialchars($userData['full_name'] ?? ''); ?>" required></div>
                <div class="form-group"><label><i class="fas fa-phone"></i> رقم الهاتف</label><input type="tel" name="phone" value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>"></div>
                <div class="form-group"><label><i class="fas fa-city"></i> الولاية</label><select name="wilaya"><option value="">-- اختر --</option><?php foreach($wilayas_list as $w): ?><option value="<?php echo htmlspecialchars($w); ?>" <?php echo ($userData['wilaya'] ?? '') == $w ? 'selected' : ''; ?>><?php echo htmlspecialchars($w); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label><i class="fas fa-map-marker-alt"></i> البلدية</label><input type="text" name="commune" value="<?php echo htmlspecialchars($userData['commune'] ?? ''); ?>" placeholder="اسم البلدية"></div>
                <button type="submit" name="update_profile" class="btn btn-primary" style="width:100%;">💾 حفظ التغييرات</button>
            </form>
        </div>
    </div>
    
    <div class="profile-card">
        <div class="toggle-section" onclick="toggleSection('changePassword')"><i class="fas fa-key"></i> تغيير كلمة المرور ▼</div>
        <div id="changePassword" class="hidden-section">
            <form method="POST">
                <div class="form-group"><label><i class="fas fa-lock"></i> كلمة المرور الحالية</label><input type="password" name="current_password" required></div>
                <div class="form-group"><label><i class="fas fa-key"></i> كلمة المرور الجديدة</label><input type="password" name="new_password" required><small style="color:var(--text-muted);">6 أحرف على الأقل</small></div>
                <div class="form-group"><label><i class="fas fa-check-circle"></i> تأكيد كلمة المرور</label><input type="password" name="confirm_password" required></div>
                <button type="submit" name="change_password" class="btn btn-primary" style="width:100%;">🔑 تغيير كلمة المرور</button>
            </form>
        </div>
    </div>
    
    <?php if($can_delete): ?>
    <div class="profile-card">
        <div class="toggle-section" onclick="toggleSection('deleteAccount')" style="background:var(--error-bg); color:var(--error-text);"><i class="fas fa-trash-alt"></i> حذف الحساب ▼</div>
        <div id="deleteAccount" class="hidden-section">
            <div class="delete-warning"><i class="fas fa-exclamation-triangle"></i> <strong>تحذير!</strong> هذا الإجراء لا يمكن التراجع عنه.</div>
            <form method="POST">
                <div class="form-group"><label><i class="fas fa-key"></i> كلمة المرور للتأكيد</label><input type="password" name="delete_password" required></div>
                <div class="form-group"><label><i class="fas fa-exclamation-circle"></i> اكتب DELETE للتأكيد</label><input type="text" name="confirm_delete" placeholder="اكتب DELETE" required></div>
                <button type="submit" name="delete_account" class="btn btn-danger" style="width:100%;" onclick="return confirm('هل أنت متأكد من حذف حسابك؟')">🗑️ حذف الحساب نهائياً</button>
            </form>
        </div>
    </div>
    <?php else: ?>
    <div class="profile-card"><div class="delete-blocked"><i class="fas fa-ban"></i> <strong>لا يمكن حذف الحساب</strong><br><?php echo $delete_block_reason; ?></div></div>
    <?php endif; ?>
</div>

<script>
function toggleSection(id) { var s = document.getElementById(id); s.style.display = (s.style.display === 'none' || s.style.display === '') ? 'block' : 'none'; }
</script>

<?php include_once 'footer.php'; ?>
<?php include_once 'bottom_nav.php'; ?>
</body>
</html>