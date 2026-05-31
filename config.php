<?php
// منع الوصول المباشر من المتصفح
if (strpos($_SERVER['SCRIPT_NAME'], 'config.php') !== false) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access denied');
}

$host = 'sql101.infinityfree.com';
$dbname = 'if0_41876055_souk_db';
$username = 'if0_41876055';
$password = 'sentekrouz39';

// لا حاجة لتعريف $port لأنه افتراضي 3306
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    // ============================================================
// ضبط المنطقة الزمنية للجزائر
// ============================================================
date_default_timezone_set('Africa/Algiers');
$pdo->exec("SET time_zone = '+01:00'");
} catch(PDOException $e) {
    die("❌ فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
}

// دالة للتحقق من إمكانية تعديل/حذف منتج
function canModifyProduct($product_id, $pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE product_id = ? AND order_status = 'delivered'");
    $stmt->execute([$product_id]);
    $completed_orders = $stmt->fetchColumn();
    
    if ($completed_orders > 0) {
        return ['status' => false, 'message' => '❌ لا يمكن تعديل أو حذف هذا المنتج لأنه مرتبط بـ ' . $completed_orders . ' طلب(ات) مكتملة'];
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE product_id = ? AND order_status IN ('pending', 'confirmed', 'shipped')");
    $stmt->execute([$product_id]);
    $pending_orders = $stmt->fetchColumn();
    
    if ($pending_orders > 0) {
        return ['status' => 'partial', 'message' => '⚠️ يوجد ' . $pending_orders . ' طلب(ات) قيد المعالجة. يمكنك تعديل السعر والكمية فقط، ولا يمكن حذف المنتج.'];
    }
    
    return ['status' => true, 'message' => 'يمكن تعديل أو حذف المنتج'];
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}
// ============================================================
// دالة حساب العمولة (متاحة في جميع الملفات)
// ============================================================
function calculateCommission($pdo, $seller_id, $amount) {
    // إذا كان المبلغ صفر أو سالب، العمولة صفر
    if ($amount <= 0) {
        return 0;
    }
    
    try {
        // جلب العمولة المخصصة للبائع
        $stmt = $pdo->prepare("SELECT custom_commission_rate FROM users WHERE id = ? AND user_type = 'seller'");
        $stmt->execute([$seller_id]);
        $custom_rate = $stmt->fetchColumn();
        
        // إذا كان هناك عمولة مخصصة (حتى 0 تعني إعفاء)
        if ($custom_rate !== null && $custom_rate !== '') {
            return $amount * (floatval($custom_rate) / 100);
        }
        
        // جلب العمولة الافتراضية من الإعدادات
        $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'default_commission_rate'");
        $default = $stmt->fetchColumn();
        $default_rate = $default ? floatval($default) : 7;
        
        return $amount * ($default_rate / 100);
        
    } catch(PDOException $e) {
        // في حالة الخطأ، نرجع العمولة الافتراضية 7%
        return $amount * 0.07;
    }
}
?>