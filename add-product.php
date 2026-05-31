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

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'seller') {
    header("Location: login.php");
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $size = trim($_POST['size']);
    $color = trim($_POST['color']);
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : NULL;
    $cancel_hours = intval($_POST['cancel_hours'] ?? 2);
    $cancel_enabled = isset($_POST['cancel_enabled']) ? 1 : 0;
    $user_id = $_SESSION['user_id'];
    
    try {
        $pdo->beginTransaction();
        
        // إدخال المنتج مع المقاس واللون وفترة الإلغاء
        $stmt = $pdo->prepare("INSERT INTO products (seller_id, name, description, price, stock, size, color, category_id, cancel_hours, cancel_enabled) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $name, $description, $price, $stock, $size, $color, $category_id, $cancel_hours, $cancel_enabled]);
        $product_id = $pdo->lastInsertId();
        
        // معالجة الصور
        $uploaded_images = [];
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $is_primary = true;
        
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $total_files = count($_FILES['images']['name']);
            
            for ($i = 0; $i < $total_files; $i++) {
                if ($_FILES['images']['error'][$i] == 0) {
                    $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
                    
                    if (in_array($ext, $allowed)) {
                        $new_name = time() . '_' . rand(1000, 9999) . '_' . $i . '.' . $ext;
                        $upload_path = 'uploads/' . $new_name;
                        
                        if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $upload_path)) {
                            $img_stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, ?)");
                            $img_stmt->execute([$product_id, $upload_path, $is_primary ? 1 : 0]);
                            $is_primary = false;
                            $uploaded_images[] = $upload_path;
                        }
                    }
                }
            }
        }
        
        $pdo->commit();
        
        if (count($uploaded_images) > 0) {
            $message = "✅ تم إضافة المنتج بنجاح مع " . count($uploaded_images) . " صور";
        } else {
            $message = "✅ تم إضافة المنتج بنجاح (بدون صور)";
        }
        
    } catch(PDOException $e) {
        $pdo->rollBack();
        $error = "خطأ: " . $e->getMessage();
    }
}

// جلب الفئات
$categories = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");
    $categories = $stmt->fetchAll();
} catch(PDOException $e) {}

// تضمين الهيدر
include_once 'header.php';
?>

<style>
    .add-product-container { max-width: 600px; margin: 30px auto; padding: 0 20px; }
    .form-card { 
        background: var(--bg-card); 
        padding: 30px; 
        border-radius: 20px; 
        box-shadow: 0 5px 20px rgba(0,0,0,0.1); 
    }
    h1 { 
        color: var(--text-heading); 
        text-align: center; 
        margin-bottom: 25px; 
        font-size: 24px; 
    }
    
    input, textarea, select { 
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
    input:focus, textarea:focus, select:focus { 
        outline: none; 
        border-color: var(--text-heading); 
    }
    input[type="file"] { padding: 10px; }
    label { 
        display: block; 
        margin-bottom: 5px; 
        font-weight: bold; 
        color: var(--text-secondary); 
        font-size: 13px; 
    }
    button { 
        background: var(--btn-primary); 
        color: var(--text-white); 
        border: none; 
        padding: 14px; 
        border-radius: 40px; 
        cursor: pointer; 
        width: 100%; 
        font-size: 16px; 
        font-weight: bold;
        transition: background 0.3s;
    }
    button:hover { background: var(--btn-primary-hover); }
    
    .message { 
        background: var(--success-bg); 
        color: var(--success-text); 
        padding: 12px; 
        border-radius: 10px; 
        margin-bottom: 20px; 
        text-align: center; 
    }
    .error { 
        background: var(--error-bg); 
        color: var(--error-text); 
        padding: 12px; 
        border-radius: 10px; 
        margin-bottom: 20px; 
        text-align: center; 
    }
    .file-list { 
        margin-top: 5px; 
        font-size: 12px; 
        color: var(--text-muted); 
    }
    .row-2cols { display: flex; gap: 15px; margin-bottom: 15px; }
    .row-2cols > div { flex: 1; }
    .info-text { 
        font-size: 12px; 
        color: var(--text-muted); 
        margin-top: -10px; 
        margin-bottom: 15px; 
    }
    hr { 
        margin: 20px 0; 
        border: none; 
        border-top: 1px solid var(--border-light); 
    }
    .section-title { 
        font-weight: bold; 
        color: var(--text-heading); 
        margin-bottom: 15px; 
        margin-top: 10px; 
    }
    
    @media (max-width: 768px) {
        .row-2cols { flex-direction: column; gap: 0; }
        .add-product-container { margin: 20px auto; }
        .form-card { padding: 20px; }
        h1 { font-size: 20px; }
    }
    
    @media (max-width: 480px) {
        .form-card { padding: 15px; }
        input, textarea, select { padding: 10px; }
    }
</style>

<div class="add-product-container">
    <div class="form-card">
        <h1>➕ إضافة منتج جديد</h1>
        
        <?php if($message): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="text" name="name" placeholder="اسم المنتج" required>
            <textarea name="description" rows="4" placeholder="وصف المنتج"></textarea>
            
            <select name="category_id" required>
                <option value="">-- اختر الفئة --</option>
                <?php foreach($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                <?php endforeach; ?>
            </select>
            
            <div class="row-2cols">
                <div>
                    <input type="number" name="price" step="1" placeholder="السعر (دج)" required>
                </div>
                <div>
                    <input type="number" name="stock" placeholder="الكمية المتوفرة" value="1" min="1" required>
                </div>
            </div>
            
            <div class="row-2cols">
                <div>
                    <label>📏 المقاسات المتاحة</label>
                    <input type="text" name="size" placeholder="مثال: S, M, L, XL">
                </div>
                <div>
                    <label>🎨 الألوان المتاحة</label>
                    <input type="text" name="color" placeholder="مثال: أحمر, أزرق, أخضر">
                </div>
            </div>
            
            <hr>
            <div class="section-title">⚙️ إعدادات إلغاء الطلب (لحالة الدفع عند الاستلام)</div>
            
            <div class="row-2cols">
                <div>
                    <label>⏱️ فترة الإلغاء (ساعات)</label>
                    <input type="number" name="cancel_hours" value="2" min="0" max="48" step="1">
                    <div class="info-text">عدد الساعات المسموحة للمشتري لإلغاء الطلب (0 = غير مسموح)</div>
                </div>
                <div style="display: flex; align-items: center;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="cancel_enabled" value="1" checked style="width: 20px; height: 20px; margin: 0;">
                        <span>✅ تفعيل خاصية إلغاء الطلب من قبل المشتري</span>
                    </label>
                </div>
            </div>
            
            <hr>
            <label>📸 صور المنتج (يمكنك اختيار عدة صور)</label>
            <input type="file" name="images[]" accept="image/*" multiple>
            <div class="file-list" id="fileList"></div>
            
            <button type="submit">📤 إضافة المنتج</button>
        </form>
    </div>
</div>

<script>
    // عرض أسماء الملفات المختارة
    var fileInput = document.querySelector('input[type="file"]');
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            var fileList = document.getElementById('fileList');
            if (fileList) {
                fileList.innerHTML = '';
                for (var i = 0; i < this.files.length; i++) {
                    fileList.innerHTML += '<div>📷 ' + this.files[i].name + '</div>';
                }
            }
        });
    }
</script>

<?php
include_once 'footer.php';
include_once 'bottom_nav.php';
?>
</body>
</html>