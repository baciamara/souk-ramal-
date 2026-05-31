<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: admin_delivery.php");
    exit();
}

$id = intval($_GET['id']);

// جلب بيانات سعر التوصيل
try {
    $stmt = $pdo->prepare("SELECT * FROM delivery_fees WHERE id = ?");
    $stmt->execute([$id]);
    $fee = $stmt->fetch();
    
    if (!$fee) {
        header("Location: admin_delivery.php");
        exit();
    }
} catch(PDOException $e) {
    header("Location: admin_delivery.php");
    exit();
}

// جلب قائمة الولايات ونقاط التجميع للقوائم المنسدلة
$wilayas = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT wilaya FROM pickup_points WHERE wilaya IS NOT NULL AND wilaya != '' ORDER BY wilaya");
    $wilayas = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {}

$communes = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT wilaya, commune FROM pickup_points WHERE commune IS NOT NULL AND commune != '' ORDER BY wilaya, commune");
    $communes = $stmt->fetchAll();
} catch(PDOException $e) {}

// معالجة تحديث البيانات
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_fee'])) {
    $wilaya = !empty($_POST['wilaya']) ? trim($_POST['wilaya']) : null;
    $commune = !empty($_POST['commune']) ? trim($_POST['commune']) : null;
    $delivery_fee = floatval($_POST['delivery_fee']);
    
    try {
        $stmt = $pdo->prepare("UPDATE delivery_fees SET wilaya = ?, commune = ?, delivery_fee = ? WHERE id = ?");
        $stmt->execute([$wilaya, $commune, $delivery_fee, $id]);
        $message = "✅ تم تحديث سعر التوصيل بنجاح";
        
        // إعادة جلب البيانات بعد التحديث
        $stmt = $pdo->prepare("SELECT * FROM delivery_fees WHERE id = ?");
        $stmt->execute([$id]);
        $fee = $stmt->fetch();
    } catch(PDOException $e) {
        $message = "❌ خطأ: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل سعر التوصيل - سوق الرمال</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Tahoma', Arial, sans-serif; background: #f5f5f5; }
        header { background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('uploads/header-bg.jpg'); background-size: cover; background-position: center; color: white; padding: 40px 20px; text-align: center; }
        header h1 { font-size: 28px; margin-bottom: 10px; }
        nav { background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 15px 30px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
        .logo { font-size: 24px; font-weight: bold; color: #b8860b; }
        .nav-links { display: flex; flex-wrap: wrap; gap: 5px; align-items: center; }
        .nav-links a { color: #333; text-decoration: none; padding: 8px 15px; border-radius: 25px; }
        .nav-links a:hover { background: #f0e0c0; }
        .btn-nav { background: #b8860b; color: white !important; }
        .logout-btn { background: #ffebee; color: #c62828 !important; }
        .container { max-width: 800px; margin: 40px auto; padding: 0 20px; }
        .card { background: white; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
        .card-header { background: #f8e1b0; padding: 15px 20px; font-size: 18px; font-weight: bold; color: #b8860b; }
        .card-body { padding: 25px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; color: #333; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        button { background: #b8860b; color: white; border: none; padding: 12px 25px; border-radius: 25px; cursor: pointer; font-size: 16px; }
        .btn-cancel { background: #999; text-decoration: none; display: inline-block; padding: 12px 25px; border-radius: 25px; color: white; margin-right: 10px; }
        .message { background: #e8f5e9; color: #2e7d32; padding: 12px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        footer { background: #333; color: white; text-align: center; padding: 20px; margin-top: 40px; }
        @media (max-width: 768px) { nav { flex-direction: column; text-align: center; } }
    </style>
</head>
<body>
    <header>
        <h1>🏜️ سوق الرمال الذهبية</h1>
        <p>تعديل سعر التوصيل للمنزل</p>
    </header>
    
    <nav>
        <div class="logo">سوق الرمال</div>
        <div class="nav-links">
            <a href="admin_dashboard.php">📊 لوحة التحكم</a>
            <a href="admin_delivery.php" class="btn-nav">🚚 إدارة التوصيل</a>
            <a href="logout.php" class="logout-btn">🚪 تسجيل خروج</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="card">
            <div class="card-header">✏️ تعديل سعر التوصيل للمنزل</div>
            <div class="card-body">
                <?php if($message): ?>
                    <div class="message"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label>الولاية (اختياري)</label>
                        <select name="wilaya">
                            <option value="">-- جميع الولايات --</option>
                            <?php foreach($wilayas as $w): ?>
                                <option value="<?php echo htmlspecialchars($w); ?>" <?php echo $fee['wilaya'] == $w ? 'selected' : ''; ?>><?php echo htmlspecialchars($w); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color:#666;">إذا اخترت ولاية فقط، فسيطبق السعر على كل بلدياتها</small>
                    </div>
                    
                    <div class="form-group">
                        <label>البلدية (اختياري)</label>
                        <select name="commune">
                            <option value="">-- جميع البلديات --</option>
                            <?php foreach($communes as $c): ?>
                                <option value="<?php echo htmlspecialchars($c['commune']); ?>" <?php echo $fee['commune'] == $c['commune'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['commune']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>سعر التوصيل (دج)</label>
                        <input type="number" name="delivery_fee" step="0.01" value="<?php echo $fee['delivery_fee']; ?>" required>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" name="update_fee">💾 حفظ التعديلات</button>
                        <a href="admin_delivery.php" class="btn-cancel">إلغاء</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <footer>
        <p>© 2026 سوق الرمال - جميع الحقوق محفوظة</p>
    </footer>
</body>
</html>