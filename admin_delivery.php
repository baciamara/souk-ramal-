<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: login.php");
    exit();
}

// ============================================================
// المتغيرات المطلوبة للهيدر والشريط السفلي
// ============================================================
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = "إدارة التوصيل";

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

// ========== إنشاء الجداول ==========
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS wilayas (
        id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(100) NOT NULL UNIQUE,
        code VARCHAR(10) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS communes (
        id INT PRIMARY KEY AUTO_INCREMENT, wilaya_id INT NOT NULL, name VARCHAR(100) NOT NULL,
        FOREIGN KEY (wilaya_id) REFERENCES wilayas(id) ON DELETE CASCADE,
        UNIQUE KEY unique_commune (wilaya_id, name)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS shipping_companies (
        id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(100) NOT NULL, phone VARCHAR(20) NULL,
        email VARCHAR(100) NULL, delivery_fee_base DECIMAL(10,2) DEFAULT 0,
        commission_rate DECIMAL(5,2) DEFAULT 0, is_active BOOLEAN DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS shipping_coverage (
        id INT PRIMARY KEY AUTO_INCREMENT, company_id INT NOT NULL, wilaya VARCHAR(100) NOT NULL,
        commune VARCHAR(100) NULL, home_delivery_fee DECIMAL(10,2) DEFAULT 0,
        pickup_delivery_fee DECIMAL(10,2) DEFAULT 0,
        FOREIGN KEY (company_id) REFERENCES shipping_companies(id) ON DELETE CASCADE
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pickup_points (
        id INT PRIMARY KEY AUTO_INCREMENT, company_id INT NULL, wilaya VARCHAR(100) NOT NULL,
        commune VARCHAR(100) NOT NULL, point_name VARCHAR(200) NOT NULL, address TEXT NOT NULL,
        manager_name VARCHAR(100) NULL, manager_phone VARCHAR(20) NULL,
        is_active BOOLEAN DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_fees (
        id INT PRIMARY KEY AUTO_INCREMENT, wilaya VARCHAR(100) NULL,
        commune VARCHAR(100) NULL, delivery_fee DECIMAL(10,2) NOT NULL
    )");
} catch(PDOException $e) {}

// ========== المعالجات ==========
$message = '';
$error = '';

// معالجة ربط حساب شركة توصيل
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['link_shipping_account'])) {
    $user_id = intval($_POST['user_id']); $company_id = intval($_POST['company_id']);
    try {
        $stmt = $pdo->prepare("UPDATE users SET company_id = ?, is_active = 1 WHERE id = ? AND user_type = 'shipping_company'");
        $stmt->execute([$company_id, $user_id]);
        $message = "✅ تم ربط حساب شركة التوصيل بنجاح وتفعيله";
    } catch(PDOException $e) { $error = "❌ خطأ: " . $e->getMessage(); }
}

// معالجة ربط حساب نقطة تجميع
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['link_pickup_account'])) {
    $user_id = intval($_POST['user_id']); $pickup_point_id = intval($_POST['pickup_point_id']);
    try {
        $stmt = $pdo->prepare("UPDATE users SET pickup_point_id = ?, is_active = 1 WHERE id = ? AND user_type = 'pickup_point'");
        $stmt->execute([$pickup_point_id, $user_id]);
        $message = "✅ تم ربط حساب نقطة التجميع بنجاح وتفعيله";
    } catch(PDOException $e) { $error = "❌ خطأ: " . $e->getMessage(); }
}

// معالجة إضافة ولاية
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_wilaya'])) {
    $wilaya = trim($_POST['wilaya']); $code = trim($_POST['code']);
    if (!empty($wilaya)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO wilayas (name, code) VALUES (?, ?)");
            $stmt->execute([$wilaya, $code ?: null]);
            $message = "✅ تم إضافة الولاية '$wilaya' بنجاح";
        } catch(PDOException $e) { $error = "⚠️ الولاية موجودة بالفعل"; }
    }
}

// معالجة تعديل ولاية
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_wilaya'])) {
    $id = intval($_POST['wilaya_id']); $wilaya = trim($_POST['wilaya']); $code = trim($_POST['code']);
    try {
        $stmt = $pdo->prepare("UPDATE wilayas SET name=?, code=? WHERE id=?");
        $stmt->execute([$wilaya, $code ?: null, $id]);
        $message = "✅ تم تعديل الولاية بنجاح";
    } catch(PDOException $e) { $error = "❌ خطأ: " . $e->getMessage(); }
}

// معالجة حذف ولاية
if (isset($_GET['delete_wilaya'])) {
    $id = intval($_GET['delete_wilaya']);
    try { $pdo->prepare("DELETE FROM wilayas WHERE id = ?")->execute([$id]); $message = "✅ تم حذف الولاية بنجاح"; }
    catch(PDOException $e) { $error = "❌ خطأ في الحذف"; }
}

// معالجة إضافة بلدية
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_commune'])) {
    $wilaya_id = intval($_POST['wilaya_id']); $commune = trim($_POST['commune']);
    if (!empty($commune) && $wilaya_id > 0) {
        try {
            $stmt = $pdo->prepare("INSERT INTO communes (wilaya_id, name) VALUES (?, ?)");
            $stmt->execute([$wilaya_id, $commune]);
            $message = "✅ تم إضافة البلدية '$commune' بنجاح";
        } catch(PDOException $e) { $error = "⚠️ البلدية موجودة بالفعل"; }
    }
}

// معالجة تعديل بلدية
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_commune'])) {
    $id = intval($_POST['commune_id']); $wilaya_id = intval($_POST['wilaya_id']); $commune = trim($_POST['commune']);
    try {
        $stmt = $pdo->prepare("UPDATE communes SET wilaya_id=?, name=? WHERE id=?");
        $stmt->execute([$wilaya_id, $commune, $id]);
        $message = "✅ تم تعديل البلدية بنجاح";
    } catch(PDOException $e) { $error = "❌ خطأ: " . $e->getMessage(); }
}

// معالجة حذف بلدية
if (isset($_GET['delete_commune'])) {
    $id = intval($_GET['delete_commune']);
    try { $pdo->prepare("DELETE FROM communes WHERE id = ?")->execute([$id]); $message = "✅ تم حذف البلدية بنجاح"; }
    catch(PDOException $e) { $error = "❌ خطأ في الحذف"; }
}

// معالجة إضافة شركة توصيل
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_shipping_company'])) {
    $name = trim($_POST['name']); $phone = trim($_POST['phone']); $email = trim($_POST['email']);
    $delivery_fee_base = floatval($_POST['delivery_fee_base']); $commission_rate = floatval($_POST['commission_rate']);
    try {
        $stmt = $pdo->prepare("INSERT INTO shipping_companies (name, phone, email, delivery_fee_base, commission_rate) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $phone, $email, $delivery_fee_base, $commission_rate]);
        $message = "✅ تم إضافة شركة التوصيل بنجاح";
    } catch(PDOException $e) { $error = "❌ خطأ: " . $e->getMessage(); }
}

// معالجة حذف شركة توصيل
if (isset($_GET['delete_company'])) {
    $id = intval($_GET['delete_company']);
    try { $pdo->prepare("DELETE FROM shipping_companies WHERE id = ?")->execute([$id]); $message = "✅ تم حذف شركة التوصيل بنجاح"; }
    catch(PDOException $e) { $error = "❌ خطأ في الحذف"; }
}

// معالجة تعديل شركة توصيل
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_shipping_company'])) {
    $id = intval($_POST['company_id']); $name = trim($_POST['name']); $phone = trim($_POST['phone']);
    $email = trim($_POST['email']); $delivery_fee_base = floatval($_POST['delivery_fee_base']);
    $commission_rate = floatval($_POST['commission_rate']); $is_active = isset($_POST['is_active']) ? 1 : 0;
    try {
        $stmt = $pdo->prepare("UPDATE shipping_companies SET name=?, phone=?, email=?, delivery_fee_base=?, commission_rate=?, is_active=? WHERE id=?");
        $stmt->execute([$name, $phone, $email, $delivery_fee_base, $commission_rate, $is_active, $id]);
        $message = "✅ تم تعديل شركة التوصيل بنجاح";
    } catch(PDOException $e) { $error = "❌ خطأ: " . $e->getMessage(); }
}

// معالجة إضافة تغطية
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_shipping_coverage'])) {
    $company_id = intval($_POST['sc_company_id']); $wilaya = trim($_POST['sc_wilaya']);
    $commune = !empty($_POST['sc_commune']) ? trim($_POST['sc_commune']) : null;
    $home_fee = floatval($_POST['sc_home_fee']); $pickup_fee = floatval($_POST['sc_pickup_fee']);
    try {
        $stmt = $pdo->prepare("INSERT INTO shipping_coverage (company_id, wilaya, commune, home_delivery_fee, pickup_delivery_fee) VALUES (?,?,?,?,?)");
        $stmt->execute([$company_id, $wilaya, $commune, $home_fee, $pickup_fee]);
        $message = "✅ تم إضافة تغطية شركة التوصيل بنجاح";
    } catch(PDOException $e) { $error = "❌ خطأ: " . $e->getMessage(); }
}

// معالجة تعديل تغطية
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_shipping_coverage'])) {
    $id = intval($_POST['coverage_id']); $company_id = intval($_POST['sc_company_id']);
    $wilaya = trim($_POST['sc_wilaya']); $commune = !empty($_POST['sc_commune']) ? trim($_POST['sc_commune']) : null;
    $home_fee = floatval($_POST['sc_home_fee']); $pickup_fee = floatval($_POST['sc_pickup_fee']);
    try {
        $stmt = $pdo->prepare("UPDATE shipping_coverage SET company_id=?, wilaya=?, commune=?, home_delivery_fee=?, pickup_delivery_fee=? WHERE id=?");
        $stmt->execute([$company_id, $wilaya, $commune, $home_fee, $pickup_fee, $id]);
        $message = "✅ تم تعديل التغطية بنجاح";
    } catch(PDOException $e) { $error = "❌ خطأ: " . $e->getMessage(); }
}

// معالجة حذف تغطية
if (isset($_GET['delete_coverage'])) {
    $id = intval($_GET['delete_coverage']);
    try { $pdo->prepare("DELETE FROM shipping_coverage WHERE id = ?")->execute([$id]); $message = "✅ تم حذف التغطية بنجاح"; }
    catch(PDOException $e) { $error = "❌ خطأ في الحذف"; }
}

// معالجة حذف نقطة تجميع
if (isset($_GET['delete_pickup'])) {
    $id = intval($_GET['delete_pickup']);
    try { $pdo->prepare("DELETE FROM pickup_points WHERE id = ?")->execute([$id]); $message = "✅ تم حذف نقطة التجميع بنجاح"; }
    catch(PDOException $e) { $error = "❌ خطأ في الحذف"; }
}

// معالجة إضافة نقطة تجميع
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_pickup_point'])) {
    $company_id = intval($_POST['company_id']); $wilaya = trim($_POST['wilaya']); $commune = trim($_POST['commune']);
    $point_name = trim($_POST['point_name']); $address = trim($_POST['address']);
    $manager_name = trim($_POST['manager_name']); $manager_phone = trim($_POST['manager_phone']);
    try {
        $stmt = $pdo->prepare("INSERT INTO pickup_points (company_id, wilaya, commune, point_name, address, manager_name, manager_phone) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$company_id, $wilaya, $commune, $point_name, $address, $manager_name, $manager_phone]);
        $message = "✅ تم إضافة نقطة التجميع بنجاح";
    } catch(PDOException $e) { $error = "❌ خطأ: " . $e->getMessage(); }
}

// معالجة تعديل نقطة تجميع
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_pickup_point'])) {
    $id = intval($_POST['pickup_id']); $company_id = intval($_POST['company_id']);
    $wilaya = trim($_POST['wilaya']); $commune = trim($_POST['commune']);
    $point_name = trim($_POST['point_name']); $address = trim($_POST['address']);
    $manager_name = trim($_POST['manager_name']); $manager_phone = trim($_POST['manager_phone']);
    try {
        $stmt = $pdo->prepare("UPDATE pickup_points SET company_id=?, wilaya=?, commune=?, point_name=?, address=?, manager_name=?, manager_phone=? WHERE id=?");
        $stmt->execute([$company_id, $wilaya, $commune, $point_name, $address, $manager_name, $manager_phone, $id]);
        $message = "✅ تم تعديل نقطة التجميع بنجاح";
    } catch(PDOException $e) { $error = "❌ خطأ: " . $e->getMessage(); }
}

// معالجة حذف سعر توصيل
if (isset($_GET['delete_fee'])) {
    $id = intval($_GET['delete_fee']);
    try { $pdo->prepare("DELETE FROM delivery_fees WHERE id = ?")->execute([$id]); $message = "✅ تم حذف سعر التوصيل بنجاح"; }
    catch(PDOException $e) { $error = "❌ خطأ في الحذف"; }
}

// معالجة إضافة سعر توصيل
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_delivery_fee'])) {
    $wilaya = !empty($_POST['wilaya']) ? trim($_POST['wilaya']) : null;
    $commune = !empty($_POST['commune']) ? trim($_POST['commune']) : null;
    $delivery_fee = floatval($_POST['delivery_fee']);
    try {
        $stmt = $pdo->prepare("INSERT INTO delivery_fees (wilaya, commune, delivery_fee) VALUES (?,?,?)");
        $stmt->execute([$wilaya, $commune, $delivery_fee]);
        $message = "✅ تم إضافة سعر التوصيل بنجاح";
    } catch(PDOException $e) { $error = "❌ خطأ: " . $e->getMessage(); }
}

// معالجة تعديل سعر توصيل
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_delivery_fee'])) {
    $id = intval($_POST['fee_id']); $wilaya = !empty($_POST['wilaya']) ? trim($_POST['wilaya']) : null;
    $commune = !empty($_POST['commune']) ? trim($_POST['commune']) : null;
    $delivery_fee = floatval($_POST['delivery_fee']);
    try {
        $stmt = $pdo->prepare("UPDATE delivery_fees SET wilaya=?, commune=?, delivery_fee=? WHERE id=?");
        $stmt->execute([$wilaya, $commune, $delivery_fee, $id]);
        $message = "✅ تم تعديل سعر التوصيل بنجاح";
    } catch(PDOException $e) { $error = "❌ خطأ: " . $e->getMessage(); }
}

// ========== جلب البيانات ==========
$wilayas = [];
try { $stmt = $pdo->query("SELECT * FROM wilayas ORDER BY CAST(code AS UNSIGNED) ASC"); $wilayas = $stmt->fetchAll(); } catch(PDOException $e) {}

$communes = [];
try { $stmt = $pdo->query("SELECT c.*, w.name as wilaya_name, w.code as wilaya_code FROM communes c JOIN wilayas w ON c.wilaya_id = w.id ORDER BY c.name ASC"); $communes = $stmt->fetchAll(); } catch(PDOException $e) {}

$shipping_companies = [];
try { $stmt = $pdo->query("SELECT * FROM shipping_companies ORDER BY name"); $shipping_companies = $stmt->fetchAll(); } catch(PDOException $e) {}

$coverages = [];
try { $stmt = $pdo->query("SELECT sc.*, c.name as company_name FROM shipping_coverage sc JOIN shipping_companies c ON sc.company_id = c.id ORDER BY c.name, sc.wilaya, sc.commune"); $coverages = $stmt->fetchAll(); } catch(PDOException $e) {}

$pickup_points = [];
try { $stmt = $pdo->query("SELECT p.*, c.name as company_name FROM pickup_points p LEFT JOIN shipping_companies c ON p.company_id = c.id WHERE p.point_name IS NOT NULL AND p.point_name != '' ORDER BY p.point_name ASC"); $pickup_points = $stmt->fetchAll(); } catch(PDOException $e) {}

$delivery_fees = [];
try { $stmt = $pdo->query("SELECT * FROM delivery_fees ORDER BY wilaya, commune"); $delivery_fees = $stmt->fetchAll(); } catch(PDOException $e) {}

$unlinked_shipping_users = [];
try { $stmt = $pdo->prepare("SELECT * FROM users WHERE user_type='shipping_company' AND (company_id IS NULL OR company_id=0) AND is_active=0 ORDER BY created_at DESC"); $stmt->execute(); $unlinked_shipping_users = $stmt->fetchAll(); } catch(PDOException $e) {}

$unlinked_pickup_users = [];
try { $stmt = $pdo->prepare("SELECT * FROM users WHERE user_type='pickup_point' AND (pickup_point_id IS NULL OR pickup_point_id=0) AND is_active=0 ORDER BY created_at DESC"); $stmt->execute(); $unlinked_pickup_users = $stmt->fetchAll(); } catch(PDOException $e) {}

$linked_shipping_users = [];
try { $stmt = $pdo->prepare("SELECT u.*, c.name as company_name FROM users u JOIN shipping_companies c ON u.company_id=c.id WHERE u.user_type='shipping_company' AND u.is_active=1 ORDER BY u.created_at DESC"); $stmt->execute(); $linked_shipping_users = $stmt->fetchAll(); } catch(PDOException $e) {}

$linked_pickup_users = [];
try { $stmt = $pdo->prepare("SELECT u.*, p.point_name, p.wilaya, p.commune FROM users u JOIN pickup_points p ON u.pickup_point_id=p.id WHERE u.user_type='pickup_point' AND u.is_active=1 ORDER BY u.created_at DESC"); $stmt->execute(); $linked_pickup_users = $stmt->fetchAll(); } catch(PDOException $e) {}

$edit_company = null;
if (isset($_GET['edit_company'])) {
    $id = intval($_GET['edit_company']);
    try { $stmt = $pdo->prepare("SELECT * FROM shipping_companies WHERE id=?"); $stmt->execute([$id]); $edit_company = $stmt->fetch(); } catch(PDOException $e) {}
}

include_once 'header.php';
?>

<style>
    .delivery-container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
    
    /* التبويبات */
    .nav-tabs { 
        display: flex; gap: 8px; margin-bottom: 25px; flex-wrap: wrap; 
        border-bottom: 2px solid var(--text-heading); padding-bottom: 10px; 
    }
    .nav-tab { 
        background: var(--bg-header-card); border: none; padding: 10px 18px; 
        border-radius: 25px; cursor: pointer; font-size: 13px; font-weight: bold; 
        transition: 0.3s; color: var(--text-primary); text-align: center; white-space: nowrap;
    }
    .nav-tab:hover { opacity: 0.8; } 
    .nav-tab.active { background: var(--btn-primary); color: var(--text-white); font-weight: bold; }
    
    /* بطاقات */
    .card { background: var(--bg-card); border-radius: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); margin-bottom: 30px; overflow: hidden; }
    .card-header { 
        background: var(--bg-header-card); padding: 15px 20px; font-size: 18px; font-weight: bold; 
        color: var(--text-heading); border-bottom: 2px solid var(--text-heading); 
        display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; 
    }
    .card-body { padding: 20px; overflow-x: auto; }
    
    /* حقول */
    .form-group { margin-bottom: 15px; }
    label { display: block; margin-bottom: 6px; font-weight: bold; color: var(--text-secondary); font-size: 13px; }
    input, select, textarea { 
        width: 100%; padding: 10px 12px; border: 1px solid var(--border-input, #ddd); 
        border-radius: 10px; font-size: 14px; font-family: inherit; 
        background: var(--bg-input); color: var(--text-primary); transition: border-color 0.3s;
    }
    input:focus, select:focus, textarea:focus { outline: none; border-color: var(--text-heading); }
    
    /* أزرار */
    .btn-add {
        background: var(--btn-success, #2e7d32); color: var(--text-white); border: none;
        padding: 10px 20px; border-radius: 30px; cursor: pointer; font-size: 14px; font-weight: bold;
        transition: all 0.3s; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap;
    }
    .btn-add:hover { background: var(--btn-success-hover, #1b5e20); transform: translateY(-1px); }
    .btn-add-sm {
        background: var(--btn-success, #2e7d32); color: var(--text-white); border: none;
        padding: 8px 15px; border-radius: 25px; cursor: pointer; font-size: 12px; font-weight: bold;
        transition: all 0.3s; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap;
    }
    .btn-add-sm:hover { background: var(--btn-success-hover, #1b5e20); transform: translateY(-1px); }
    
    /* أزرار الإجراءات */
    .btn-small { 
        padding: 5px 12px; font-size: 11px; margin: 2px; display: inline-block; 
        border-radius: 20px; text-decoration: none; color: var(--text-white); 
        border: none; cursor: pointer; font-weight: bold; transition: 0.3s; white-space: nowrap;
    }
    
    /* رسائل */
    .success-msg { background: var(--success-bg); color: var(--success-text); padding: 15px; border-radius: 12px; margin-bottom: 20px; text-align: center; }
    .error-msg { background: var(--error-bg); color: var(--error-text); padding: 15px; border-radius: 12px; margin-bottom: 20px; text-align: center; }
    
    /* نوافذ */
    .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center; }
    .modal-content { background: var(--bg-card); width: 600px; max-width: 95%; padding: 25px; border-radius: 20px; max-height: 85vh; overflow-y: auto; }
    
    h3 { color: var(--text-heading); }
    
    /* تجاوب */
    @media (max-width: 768px) { 
        .nav-tab { padding: 10px 12px; font-size: 12px; flex: 0 1 calc(50% - 8px); }
        .nav-tab:only-child { flex: 1 1 100%; }
        .delivery-container { margin: 20px auto; }
        .btn-add { padding: 8px 16px; font-size: 12px; }
        .btn-add-sm { padding: 6px 12px; font-size: 11px; }
    }
    @media (max-width: 480px) {
        .nav-tab { padding: 8px 10px; font-size: 11px; }
    }
</style>

<div class="delivery-container">
    <?php if($message): ?><div class="success-msg"><?php echo $message; ?></div><?php endif; ?>
    <?php if($error): ?><div class="error-msg"><?php echo $error; ?></div><?php endif; ?>
    
    <div class="nav-tabs">
        <button class="nav-tab active" onclick="showSection('wilayas')">🏙️ الولايات</button>
        <button class="nav-tab" onclick="showSection('communes')">📍 البلديات</button>
        <button class="nav-tab" onclick="showSection('companies')">🚚 شركات التوصيل</button>
        <button class="nav-tab" onclick="showSection('coverage')">🌍 تغطية الشركات</button>
        <button class="nav-tab" onclick="showSection('pickup')">📍 نقاط التجميع</button>
        <button class="nav-tab" onclick="showSection('fees')">💰 أسعار التوصيل</button>
        <button class="nav-tab" onclick="showSection('accounts')">👥 ربط الحسابات</button>
    </div>
    
    <!-- الولايات -->
    <div id="section_wilayas" class="card">
        <div class="card-header">🏙️ إدارة الولايات</div>
        <div class="card-body">
            <form method="POST" style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap;">
                <input type="hidden" name="wilaya_id" id="wilaya_id">
                <input type="text" name="code" id="wilaya_code" placeholder="الرمز" style="width:100px;">
                <input type="text" name="wilaya" id="wilaya_name" placeholder="اسم الولاية" required style="flex:1; min-width:200px;">
                <button type="submit" name="add_wilaya" id="wilayaSubmitBtn" class="btn-add">➕ إضافة</button>
            </form>
            <?php if(count($wilayas) > 0): ?>
                <div style="overflow-x:auto;"><table class="data-table">
                    <thead><tr><th>الولاية</th><th>إجراءات</th></tr></thead>
                    <tbody><?php foreach($wilayas as $w): ?>
                        <tr><td><?php echo htmlspecialchars($w['code'] ? $w['code'].' - ' : '') . htmlspecialchars($w['name']); ?></td>
                        <td>
                            <button onclick="editWilaya(<?php echo $w['id']; ?>, '<?php echo addslashes($w['name']); ?>', '<?php echo addslashes($w['code'] ?? ''); ?>')" class="btn-small btn-edit">✏️ تعديل</button>
                            <a href="?delete_wilaya=<?php echo $w['id']; ?>" class="btn-small btn-danger" onclick="return confirm('حذف الولاية؟')">🗑️ حذف</a>
                        </td></tr>
                    <?php endforeach; ?></tbody>
                </table></div>
            <?php else: ?><p style="text-align:center;padding:30px;color:var(--text-muted);">📭 لا توجد ولايات</p><?php endif; ?>
        </div>
    </div>
    
    <!-- البلديات -->
    <div id="section_communes" class="card" style="display:none;">
        <div class="card-header">📍 إدارة البلديات</div>
        <div class="card-body">
            <form method="POST" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px;">
                <input type="hidden" name="commune_id" id="commune_id">
                <select name="wilaya_id" id="commune_wilaya_id" required style="flex:1; min-width:200px;"><option value="">-- اختر الولاية --</option>
                    <?php foreach($wilayas as $w):?><option value="<?php echo $w['id']; ?>"><?php echo htmlspecialchars($w['code'] ? $w['code'].' - ' : '') . htmlspecialchars($w['name']); ?></option><?php endforeach;?>
                </select>
                <input type="text" name="commune" id="commune_name" placeholder="اسم البلدية" required style="flex:1; min-width:200px;">
                <button type="submit" name="add_commune" id="communeSubmitBtn" class="btn-add">➕ إضافة</button>
            </form>
            <?php if(count($communes) > 0): ?><div style="overflow-x:auto;"><table class="data-table">
                <thead><tr><th>الولاية</th><th>البلدية</th><th>إجراءات</th></tr></thead>
                <tbody><?php foreach($communes as $c): ?>
                    <tr><td><?php echo htmlspecialchars($c['wilaya_code'] ? $c['wilaya_code'].' - ' : '') . htmlspecialchars($c['wilaya_name']); ?></td>
                    <td><?php echo htmlspecialchars($c['name']); ?></td>
                    <td>
                        <button onclick="editCommune(<?php echo $c['id']; ?>, <?php echo $c['wilaya_id']; ?>, '<?php echo addslashes($c['name']); ?>')" class="btn-small btn-edit">✏️ تعديل</button>
                        <a href="?delete_commune=<?php echo $c['id']; ?>" class="btn-small btn-danger" onclick="return confirm('حذف البلدية؟')">🗑️ حذف</a>
                    </td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
            <?php else: ?><p style="text-align:center;padding:30px;color:var(--text-muted);">📭 لا توجد بلديات</p><?php endif; ?>
        </div>
    </div>
    
    <!-- شركات التوصيل -->
    <div id="section_companies" class="card" style="display:none;">
        <div class="card-header">🚚 شركات التوصيل <button onclick="openCompanyModal()" class="btn-add-sm">➕ إضافة شركة</button></div>
        <div class="card-body">
            <?php if(count($shipping_companies) > 0): ?><div style="overflow-x:auto;"><table class="data-table">
                <thead><tr><th>اسم الشركة</th><th>الهاتف</th><th>السعر الأساسي</th><th>العمولة</th><th>الحالة</th><th>إجراءات</th></tr></thead>
                <tbody><?php foreach($shipping_companies as $company): ?>
                    <tr><td><?php echo htmlspecialchars($company['name']); ?></td>
                    <td><?php echo htmlspecialchars($company['phone'] ?? '—'); ?></td>
                    <td><?php echo number_format($company['delivery_fee_base'],2); ?> دج</td>
                    <td><?php echo $company['commission_rate']; ?>%</td>
                    <td><?php echo $company['is_active'] ? '🟢 نشط' : '🔴 غير نشط'; ?></td>
                    <td>
                        <a href="?edit_company=<?php echo $company['id']; ?>" class="btn-small btn-edit">✏️ تعديل</a>
                        <a href="?delete_company=<?php echo $company['id']; ?>" class="btn-small btn-danger" onclick="return confirm('حذف الشركة؟')">🗑️ حذف</a>
                    </td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
            <?php else: ?><p style="text-align:center;padding:30px;color:var(--text-muted);">📭 لا توجد شركات</p><?php endif; ?>
        </div>
    </div>
    
    <!-- تغطية الشركات -->
    <div id="section_coverage" class="card" style="display:none;">
        <div class="card-header">🌍 تغطية شركات التوصيل</div>
        <div class="card-body">
            <form method="POST" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; margin-bottom:20px;">
                <input type="hidden" name="coverage_id" id="coverage_id">
                <div class="form-group" style="flex:1;min-width:150px;"><label>شركة التوصيل</label><select name="sc_company_id" id="cov_company_id" required><?php foreach($shipping_companies as $c):?><option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach;?></select></div>
                <div class="form-group" style="flex:1;min-width:150px;"><label>الولاية</label><select id="coverage_wilaya" name="sc_wilaya" required><option value="">-- اختر --</option><?php foreach($wilayas as $w):?><option value="<?php echo $w['name']; ?>"><?php echo htmlspecialchars($w['code'] ? $w['code'].' - ' : '') . htmlspecialchars($w['name']); ?></option><?php endforeach;?></select></div>
                <div class="form-group" style="flex:1;min-width:150px;"><label>البلدية</label><select id="coverage_commune" name="sc_commune"><option value="">-- جميع --</option></select></div>
                <div class="form-group" style="flex:1;min-width:120px;"><label>🏠 المنزل</label><input type="number" name="sc_home_fee" id="cov_home_fee" step="0.01" value="600" required></div>
                <div class="form-group" style="flex:1;min-width:120px;"><label>📍 النقطة</label><input type="number" name="sc_pickup_fee" id="cov_pickup_fee" step="0.01" value="350" required></div>
                <div class="form-group"><button type="submit" name="add_shipping_coverage" id="covSubmitBtn" class="btn-add">➕ إضافة</button></div>
            </form>
            <?php if(count($coverages) > 0): ?><div style="overflow-x:auto;"><table class="data-table">
                <thead><tr><th>شركة التوصيل</th><th>الولاية</th><th>البلدية</th><th>🏠 المنزل</th><th>📍 النقطة</th><th>إجراءات</th></tr></thead>
                <tbody><?php foreach($coverages as $cov): ?>
                    <tr><td><?php echo htmlspecialchars($cov['company_name']); ?></td><td><?php echo htmlspecialchars($cov['wilaya']); ?></td>
                    <td><?php echo htmlspecialchars($cov['commune'] ?? 'جميع البلديات'); ?></td>
                    <td><?php echo number_format($cov['home_delivery_fee'],2); ?> دج</td>
                    <td><?php echo number_format($cov['pickup_delivery_fee'],2); ?> دج</td>
                    <td>
                        <button onclick="editCoverage(<?php echo $cov['id']; ?>, <?php echo $cov['company_id']; ?>, '<?php echo addslashes($cov['wilaya']); ?>', '<?php echo addslashes($cov['commune'] ?? ''); ?>', <?php echo $cov['home_delivery_fee']; ?>, <?php echo $cov['pickup_delivery_fee']; ?>)" class="btn-small btn-edit">✏️ تعديل</button>
                        <a href="?delete_coverage=<?php echo $cov['id']; ?>" class="btn-small btn-danger" onclick="return confirm('حذف التغطية؟')">🗑️ حذف</a>
                    </td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
            <?php else: ?><p style="text-align:center;padding:30px;color:var(--text-muted);">📭 لا توجد تغطيات</p><?php endif; ?>
        </div>
    </div>
    
    <!-- نقاط التجميع -->
    <div id="section_pickup" class="card" style="display:none;">
        <div class="card-header">📍 نقاط التجميع <button onclick="openPickupModal()" class="btn-add-sm">➕ إضافة نقطة</button></div>
        <div class="card-body">
            <?php if(count($pickup_points) > 0): ?><div style="overflow-x:auto;"><table class="data-table">
                <thead><tr><th>اسم النقطة</th><th>الشركة</th><th>الولاية</th><th>البلدية</th><th>العنوان</th><th>المسؤول</th><th>الهاتف</th><th>إجراءات</th></tr></thead>
                <tbody><?php foreach($pickup_points as $point): ?>
                    <tr><td><?php echo htmlspecialchars($point['point_name']); ?></td>
                    <td><?php echo htmlspecialchars($point['company_name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($point['wilaya']); ?></td>
                    <td><?php echo htmlspecialchars($point['commune']); ?></td>
                    <td style="max-width:150px;word-break:break-word;"><?php echo htmlspecialchars($point['address']); ?></td>
                    <td><?php echo htmlspecialchars($point['manager_name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($point['manager_phone'] ?? '—'); ?></td>
                    <td>
                        <button onclick="editPickupPoint(<?php echo $point['id']; ?>, '<?php echo addslashes($point['point_name']); ?>', <?php echo $point['company_id'] ?? 0; ?>, '<?php echo addslashes($point['wilaya']); ?>', '<?php echo addslashes($point['commune']); ?>', '<?php echo addslashes($point['address']); ?>', '<?php echo addslashes($point['manager_name'] ?? ''); ?>', '<?php echo addslashes($point['manager_phone'] ?? ''); ?>')" class="btn-small btn-edit">✏️ تعديل</button>
                        <a href="?delete_pickup=<?php echo $point['id']; ?>" class="btn-small btn-danger" onclick="return confirm('حذف نقطة التجميع؟')">🗑️ حذف</a>
                    </td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
            <?php else: ?><p style="text-align:center;padding:30px;color:var(--text-muted);">📭 لا توجد نقاط تجميع</p><?php endif; ?>
        </div>
    </div>
    
    <!-- أسعار التوصيل -->
    <div id="section_fees" class="card" style="display:none;">
        <div class="card-header">💰 أسعار التوصيل للمنزل</div>
        <div class="card-body">
            <form method="POST" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; margin-bottom:20px;">
                <input type="hidden" name="fee_id" id="fee_id">
                <div class="form-group" style="flex:1;min-width:150px;"><label>الولاية</label><select name="wilaya" id="fee_wilaya"><option value="">-- جميع --</option><?php foreach($wilayas as $w):?><option value="<?php echo $w['name']; ?>"><?php echo htmlspecialchars($w['code'] ? $w['code'].' - ' : '') . htmlspecialchars($w['name']); ?></option><?php endforeach;?></select></div>
                <div class="form-group" style="flex:1;min-width:150px;"><label>البلدية</label><select name="commune" id="fee_commune"><option value="">-- جميع --</option><?php foreach($communes as $c):?><option value="<?php echo $c['name']; ?>"><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach;?></select></div>
                <div class="form-group" style="flex:1;min-width:120px;"><label>السعر (دج)</label><input type="number" name="delivery_fee" id="fee_amount" step="0.01" value="500"></div>
                <div class="form-group"><button type="submit" name="add_delivery_fee" id="feeSubmitBtn" class="btn-add">➕ إضافة</button></div>
            </form>
            <?php if(count($delivery_fees) > 0): ?><div style="overflow-x:auto;"><table class="data-table">
                <thead><tr><th>الولاية</th><th>البلدية</th><th>السعر</th><th>إجراءات</th></tr></thead>
                <tbody><?php foreach($delivery_fees as $fee): ?>
                    <tr><td><?php echo htmlspecialchars($fee['wilaya'] ?? 'جميع الولايات'); ?></td>
                    <td><?php echo htmlspecialchars($fee['commune'] ?? 'جميع البلديات'); ?></td>
                    <td><?php echo number_format($fee['delivery_fee'],2); ?> دج</td>
                    <td>
                        <button onclick="editFee(<?php echo $fee['id']; ?>, '<?php echo addslashes($fee['wilaya'] ?? ''); ?>', '<?php echo addslashes($fee['commune'] ?? ''); ?>', <?php echo $fee['delivery_fee']; ?>)" class="btn-small btn-edit">✏️ تعديل</button>
                        <a href="?delete_fee=<?php echo $fee['id']; ?>" class="btn-small btn-danger" onclick="return confirm('حذف السعر؟')">🗑️ حذف</a>
                    </td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
            <?php else: ?><p style="text-align:center;padding:30px;color:var(--text-muted);">📭 لا توجد أسعار</p><?php endif; ?>
        </div>
    </div>
    
    <!-- ربط الحسابات -->
    <div id="section_accounts" class="card" style="display:none;">
        <div class="card-header">👥 ربط حسابات شركات التوصيل ونقاط التجميع</div>
        <div class="card-body">
            <h3 style="color:var(--badge-pending-text);margin-bottom:10px;">🚚 حسابات شركات التوصيل (في انتظار الربط)</h3>
            <?php if(count($unlinked_shipping_users) > 0): ?><div style="overflow-x:auto;"><table class="data-table">
                <thead><tr><th>الاسم</th><th>البريد</th><th>الهاتف</th><th>تاريخ التسجيل</th><th>ربط بشركة</th></tr></thead>
                <tbody><?php foreach($unlinked_shipping_users as $user): ?>
                    <tr><td><?php echo htmlspecialchars($user['full_name']); ?></td><td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['phone']); ?></td><td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                    <td><form method="POST" style="display:flex;gap:8px;"><input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                        <select name="company_id" required style="flex:1;"><option value="">-- اختر --</option>
                            <?php foreach($shipping_companies as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?>
                        </select><button type="submit" name="link_shipping_account" class="btn-success" style="font-size:11px;padding:6px 12px;">🔗 ربط</button></form></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
            <?php else: ?><p style="padding:10px;background:var(--bg-card-alt);border-radius:10px;color:var(--text-primary);">✅ لا توجد حسابات في انتظار الربط</p><?php endif; ?>
            
            <h3 style="color:var(--badge-pending-text);margin:25px 0 10px;">📍 حسابات نقاط التجميع (في انتظار الربط)</h3>
            <?php if(count($unlinked_pickup_users) > 0): ?><div style="overflow-x:auto;"><table class="data-table">
                <thead><tr><th>الاسم</th><th>البريد</th><th>الهاتف</th><th>تاريخ التسجيل</th><th>ربط بنقطة</th></tr></thead>
                <tbody><?php foreach($unlinked_pickup_users as $user): ?>
                    <tr><td><?php echo htmlspecialchars($user['full_name']); ?></td><td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['phone']); ?></td><td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                    <td><form method="POST" style="display:flex;gap:8px;"><input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                        <select name="pickup_point_id" required style="flex:1;"><option value="">-- اختر --</option>
                            <?php foreach($pickup_points as $p): ?><option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['point_name']).' ('.htmlspecialchars($p['wilaya']).')'; ?></option><?php endforeach; ?>
                        </select><button type="submit" name="link_pickup_account" class="btn-success" style="font-size:11px;padding:6px 12px;">🔗 ربط</button></form></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
            <?php else: ?><p style="padding:10px;background:var(--bg-card-alt);border-radius:10px;color:var(--text-primary);">✅ لا توجد حسابات في انتظار الربط</p><?php endif; ?>
            
            <h3 style="color:var(--success-text);margin:25px 0 10px;">✅ الحسابات المفعلة</h3>
            <?php if(count($linked_shipping_users) > 0 || count($linked_pickup_users) > 0): ?><div style="overflow-x:auto;"><table class="data-table">
                <thead><tr><th>الاسم</th><th>البريد</th><th>النوع</th><th>الجهة المرتبطة</th><th>الموقع</th></tr></thead>
                <tbody><?php foreach($linked_shipping_users as $user): ?>
                    <tr><td><?php echo htmlspecialchars($user['full_name']); ?></td><td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td>🚚 شركة توصيل</td><td><?php echo htmlspecialchars($user['company_name']); ?></td><td>—</td></tr>
                <?php endforeach; ?>
                <?php foreach($linked_pickup_users as $user): ?>
                    <tr><td><?php echo htmlspecialchars($user['full_name']); ?></td><td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td>📍 نقطة تجميع</td><td><?php echo htmlspecialchars($user['point_name']); ?></td>
                    <td><?php echo htmlspecialchars($user['wilaya'].' / '.$user['commune']); ?></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
            <?php else: ?><p style="color:var(--text-muted);">لا توجد حسابات مفعلة بعد</p><?php endif; ?>
        </div>
    </div>
</div>

<!-- النوافذ المنبثقة -->
<div id="companyModal" class="modal">
    <div class="modal-content">
        <h3 id="companyModalTitle" style="color:var(--text-heading);margin-bottom:20px;">➕ إضافة شركة توصيل</h3>
        <form method="POST">
            <input type="hidden" name="company_id" id="company_id">
            <div class="form-group"><label>اسم الشركة</label><input type="text" name="name" id="company_name" required></div>
            <div class="form-group"><label>رقم الهاتف</label><input type="text" name="phone" id="company_phone"></div>
            <div class="form-group"><label>البريد الإلكتروني</label><input type="email" name="email" id="company_email"></div>
            <div class="form-group"><label>سعر التوصيل الأساسي (دج)</label><input type="number" name="delivery_fee_base" id="company_fee" step="0.01" value="500"></div>
            <div class="form-group"><label>عمولة الموقع (%)</label><input type="number" name="commission_rate" id="company_commission" step="0.01" value="0"></div>
            <div class="form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;"><input type="checkbox" name="is_active" id="company_active" value="1" checked style="width:auto;"> مفعل</label></div>
            <div style="display:flex;gap:10px;margin-top:15px;">
                <button type="submit" name="add_shipping_company" id="companySubmitBtn" class="btn-add" style="flex:1;">💾 حفظ</button>
                <button type="button" onclick="closeCompanyModal()" class="btn-danger" style="flex:1;">✖️ إلغاء</button>
            </div>
        </form>
    </div>
</div>

<div id="pickupModal" class="modal">
    <div class="modal-content">
        <h3 id="pickupModalTitle" style="color:var(--text-heading);margin-bottom:20px;">➕ إضافة نقطة تجميع</h3>
        <form method="POST">
            <input type="hidden" name="pickup_id" id="pickup_id">
            <div class="form-group"><label>شركة التوصيل</label><select name="company_id" id="pickup_company_id" required><?php foreach($shipping_companies as $c):?><option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach;?></select></div>
            <div class="form-group"><label>الولاية</label><select id="pickup_wilaya_modal" name="wilaya" required><option value="">-- اختر --</option><?php foreach($wilayas as $w):?><option value="<?php echo $w['name']; ?>"><?php echo htmlspecialchars($w['code'] ? $w['code'].' - ' : '') . htmlspecialchars($w['name']); ?></option><?php endforeach;?></select></div>
            <div class="form-group"><label>البلدية</label><select id="pickup_commune_modal" name="commune" required><option value="">-- اختر --</option></select></div>
            <div class="form-group"><label>اسم النقطة</label><input type="text" name="point_name" id="pickup_point_name" required></div>
            <div class="form-group"><label>العنوان</label><input type="text" name="address" id="pickup_address" required></div>
            <div class="form-group"><label>المسؤول</label><input type="text" name="manager_name" id="pickup_manager_name"></div>
            <div class="form-group"><label>هاتف المسؤول</label><input type="text" name="manager_phone" id="pickup_manager_phone"></div>
            <div style="display:flex;gap:10px;margin-top:15px;">
                <button type="submit" name="add_pickup_point" id="pickupSubmitBtn" class="btn-add" style="flex:1;">💾 حفظ</button>
                <button type="button" onclick="closePickupModal()" class="btn-danger" style="flex:1;">✖️ إلغاء</button>
            </div>
        </form>
    </div>
</div>

<script>
    function showSection(s) {
        ['wilayas','communes','companies','coverage','pickup','fees','accounts'].forEach(function(x){ document.getElementById('section_'+x).style.display='none'; });
        document.getElementById('section_'+s).style.display='block';
        document.querySelectorAll('.nav-tab').forEach(function(b){ b.classList.remove('active'); });
        event.target.classList.add('active');
    }
    
    function editWilaya(id, name, code) {
        document.getElementById('wilaya_id').value = id;
        document.getElementById('wilaya_name').value = name;
        document.getElementById('wilaya_code').value = code;
        document.getElementById('wilayaSubmitBtn').name = 'edit_wilaya';
        document.getElementById('wilayaSubmitBtn').innerText = '💾 حفظ التعديل';
        document.getElementById('wilaya_name').focus();
    }
    
    function editCommune(id, wilayaId, name) {
        document.getElementById('commune_id').value = id;
        document.getElementById('commune_wilaya_id').value = wilayaId;
        document.getElementById('commune_name').value = name;
        document.getElementById('communeSubmitBtn').name = 'edit_commune';
        document.getElementById('communeSubmitBtn').innerText = '💾 حفظ التعديل';
        document.getElementById('commune_name').focus();
    }
    
    var allCommunes=[];
    fetch('get_all_communes.php').then(function(r){return r.json()}).then(function(d){allCommunes=d;});
    document.getElementById('coverage_wilaya').addEventListener('change',function(){
        var w=this.value, sel=document.getElementById('coverage_commune');
        sel.innerHTML='<option value="">-- جميع --</option>';
        if(w) allCommunes.filter(function(c){return c.wilaya===w}).forEach(function(c){sel.innerHTML+='<option value="'+c.name+'">'+c.name+'</option>';});
    });
    
    function editCoverage(id, companyId, wilaya, commune, homeFee, pickupFee) {
        document.getElementById('coverage_id').value = id;
        document.getElementById('cov_company_id').value = companyId;
        document.getElementById('coverage_wilaya').value = wilaya;
        document.getElementById('coverage_wilaya').dispatchEvent(new Event('change'));
        setTimeout(function(){ document.getElementById('coverage_commune').value = commune; }, 300);
        document.getElementById('cov_home_fee').value = homeFee;
        document.getElementById('cov_pickup_fee').value = pickupFee;
        document.getElementById('covSubmitBtn').name = 'edit_shipping_coverage';
        document.getElementById('covSubmitBtn').innerText = '💾 حفظ التعديل';
        document.getElementById('section_coverage').scrollIntoView({behavior:'smooth'});
    }
    
    var pw=document.getElementById('pickup_wilaya_modal');
    var pc=document.getElementById('pickup_commune_modal');
    if(pw) pw.addEventListener('change',function(){
        var w=this.value; pc.innerHTML='<option value="">-- اختر --</option>';
        if(w) fetch('get_communes_by_wilaya.php?wilaya='+encodeURIComponent(w)).then(function(r){return r.json()}).then(function(d){d.forEach(function(c){pc.innerHTML+='<option value="'+c+'">'+c+'</option>';});});
    });
    
    function editFee(id, wilaya, commune, amount) {
        document.getElementById('fee_id').value = id;
        document.getElementById('fee_wilaya').value = wilaya;
        document.getElementById('fee_commune').value = commune;
        document.getElementById('fee_amount').value = amount;
        document.getElementById('feeSubmitBtn').name = 'edit_delivery_fee';
        document.getElementById('feeSubmitBtn').innerText = '💾 حفظ التعديل';
        document.getElementById('fee_amount').focus();
    }
    
    function openCompanyModal() {
        document.getElementById('companyModalTitle').innerText='➕ إضافة شركة توصيل';
        document.getElementById('company_id').value=''; document.getElementById('company_name').value='';
        document.getElementById('company_phone').value=''; document.getElementById('company_email').value='';
        document.getElementById('company_fee').value='500'; document.getElementById('company_commission').value='0';
        document.getElementById('company_active').checked=true;
        document.getElementById('companySubmitBtn').name='add_shipping_company';
        document.getElementById('companyModal').style.display='flex';
    }
    function closeCompanyModal() { document.getElementById('companyModal').style.display='none'; }
    
    function openPickupModal() {
        document.getElementById('pickupModalTitle').innerText='➕ إضافة نقطة تجميع';
        document.getElementById('pickup_id').value=''; document.getElementById('pickup_point_name').value='';
        document.getElementById('pickup_address').value=''; document.getElementById('pickup_manager_name').value='';
        document.getElementById('pickup_manager_phone').value='';
        document.getElementById('pickupSubmitBtn').name='add_pickup_point';
        document.getElementById('pickupModal').style.display='flex';
    }
    function closePickupModal() { document.getElementById('pickupModal').style.display='none'; }
    
    function editPickupPoint(id, name, companyId, wilaya, commune, address, manager, phone) {
        document.getElementById('pickupModalTitle').innerText='✏️ تعديل نقطة تجميع';
        document.getElementById('pickup_id').value=id;
        document.getElementById('pickup_point_name').value=name;
        document.getElementById('pickup_company_id').value=companyId||'';
        document.getElementById('pickup_address').value=address;
        document.getElementById('pickup_manager_name').value=manager;
        document.getElementById('pickup_manager_phone').value=phone;
        document.getElementById('pickupSubmitBtn').name='edit_pickup_point';
        document.getElementById('pickup_wilaya_modal').value=wilaya;
        document.getElementById('pickup_wilaya_modal').dispatchEvent(new Event('change'));
        setTimeout(function(){ document.getElementById('pickup_commune_modal').value=commune; },300);
        document.getElementById('pickupModal').style.display='flex';
    }
    
    <?php if($edit_company): ?>
    window.onload=function(){
        document.getElementById('companyModalTitle').innerText='✏️ تعديل شركة توصيل';
        document.getElementById('company_id').value='<?php echo $edit_company['id']; ?>';
        document.getElementById('company_name').value='<?php echo addslashes($edit_company['name']); ?>';
        document.getElementById('company_phone').value='<?php echo addslashes($edit_company['phone']); ?>';
        document.getElementById('company_email').value='<?php echo addslashes($edit_company['email']); ?>';
        document.getElementById('company_fee').value='<?php echo $edit_company['delivery_fee_base']; ?>';
        document.getElementById('company_commission').value='<?php echo $edit_company['commission_rate']; ?>';
        document.getElementById('company_active').checked=<?php echo $edit_company['is_active'] ? 'true' : 'false'; ?>;
        document.getElementById('companySubmitBtn').name='edit_shipping_company';
        document.getElementById('companyModal').style.display='flex';
        document.querySelectorAll('.nav-tab').forEach(function(b){b.classList.remove('active');});
        document.querySelector('.nav-tab:nth-child(3)').classList.add('active');
        document.getElementById('section_companies').style.display='block';
    };
    <?php endif; ?>
</script>

<?php include_once 'footer.php'; include_once 'bottom_nav.php'; ?>
</body>
</html>