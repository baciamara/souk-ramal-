<?php
session_start();
require_once 'config.php';

// ============================================================
// المتغيرات المطلوبة للهيدر والشريط السفلي
// ============================================================
$current_page = basename($_SERVER['PHP_SELF']);

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

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: my-products.php");
    exit();
}

$product_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// جلب بيانات المنتج والتحقق من وجود طلبات غير جاهزة للشحن
$product = null;
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
        (SELECT COUNT(*) FROM orders WHERE product_id = p.id AND order_status IN ('pending', 'waiting', 'processing')) as has_unconfirmed_orders
        FROM products p WHERE p.id = ? AND p.seller_id = ?
    ");
    $stmt->execute([$product_id, $user_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        header("Location: my-products.php");
        exit();
    }
    
    // منع التعديل إذا كان هناك طلبات لم تجهز للشحن بعد
    if ($product['has_unconfirmed_orders'] > 0) {
        header("Location: my-products.php?error=لا يمكن تعديل المنتج لأن هناك طلبات لم تجهز للشحن بعد (حالة: pending, waiting, processing)");
        exit();
    }
} catch(PDOException $e) {
    header("Location: my-products.php");
    exit();
}

// جلب صور المنتج
$product_images = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC");
    $stmt->execute([$product_id]);
    $product_images = $stmt->fetchAll();
} catch(PDOException $e) {}

// جلب الفئات
$categories = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");
    $categories = $stmt->fetchAll();
} catch(PDOException $e) {}

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
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, stock = ?, size = ?, color = ?, category_id = ?, cancel_hours = ?, cancel_enabled = ? WHERE id = ? AND seller_id = ?");
        $stmt->execute([$name, $description, $price, $stock, $size, $color, $category_id, $cancel_hours, $cancel_enabled, $product_id, $user_id]);
        
        // حذف الصور المحددة
        if (isset($_POST['delete_images']) && !empty($_POST['delete_images'])) {
            foreach ($_POST['delete_images'] as $img_id) {
                $img_stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE id = ? AND product_id = ?");
                $img_stmt->execute([$img_id, $product_id]);
                $img = $img_stmt->fetch();
                if ($img && file_exists($img['image_path'])) {
                    unlink($img['image_path']);
                }
                $pdo->prepare("DELETE FROM product_images WHERE id = ? AND product_id = ?")->execute([$img_id, $product_id]);
            }
        }
        
        // إضافة صور جديدة
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $is_primary = count($product_images) == 0;
        
        if (isset($_FILES['new_images']) && !empty($_FILES['new_images']['name'][0])) {
            for ($i = 0; $i < count($_FILES['new_images']['name']); $i++) {
                if ($_FILES['new_images']['error'][$i] == 0) {
                    $ext = strtolower(pathinfo($_FILES['new_images']['name'][$i], PATHINFO_EXTENSION));
                    if (in_array($ext, $allowed)) {
                        $new_name = time() . '_' . rand(1000, 9999) . '_' . $i . '.' . $ext;
                        $upload_path = 'uploads/' . $new_name;
                        if (move_uploaded_file($_FILES['new_images']['tmp_name'][$i], $upload_path)) {
                            $img_stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, ?)");
                            $img_stmt->execute([$product_id, $upload_path, $is_primary ? 1 : 0]);
                            $is_primary = false;
                        }
                    }
                }
            }
        }
        
        $pdo->commit();
        $message = "✅ تم تحديث المنتج بنجاح";
        
        // تحديث المتغيرات
        $product['name'] = $name;
        $product['description'] = $description;
        $product['price'] = $price;
        $product['stock'] = $stock;
        $product['size'] = $size;
        $product['color'] = $color;
        $product['category_id'] = $category_id;
        $product['cancel_hours'] = $cancel_hours;
        $product['cancel_enabled'] = $cancel_enabled;
        
        $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC");
        $stmt->execute([$product_id]);
        $product_images = $stmt->fetchAll();
        
    } catch(PDOException $e) {
        $pdo->rollBack();
        $error = "خطأ: " . $e->getMessage();
    }
}

// تضمين الهيدر
include_once 'header.php';
?>

<style>
    .edit-product-container { max-width: 600px; margin: 30px auto; padding: 0 20px; }
    
    .card { 
        background: var(--bg-card); 
        border-radius: 15px; 
        box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        overflow: hidden; 
    }
    .card-header { 
        background: var(--bg-header-card); 
        padding: 15px 20px; 
        font-size: 18px; 
        font-weight: bold; 
        color: var(--text-heading); 
        border-bottom: 2px solid var(--text-heading); 
    }
    .card-body { padding: 20px; }
    
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
    .info-box { 
        background: var(--badge-info-bg); 
        color: var(--badge-info-text); 
        padding: 12px; 
        border-radius: 10px; 
        margin-bottom: 20px; 
        font-size: 14px; 
    }
    
    input, textarea, select { 
        width: 100%; 
        padding: 10px; 
        margin-bottom: 15px; 
        border: 1px solid var(--border-input, #ddd); 
        border-radius: 8px; 
        font-family: inherit; 
        background: var(--bg-input);
        color: var(--text-primary);
        transition: border-color 0.3s;
    }
    input:focus, textarea:focus, select:focus { 
        outline: none; 
        border-color: var(--text-heading); 
    }
    
    button { 
        background: var(--btn-primary); 
        color: var(--text-white); 
        border: none; 
        padding: 12px; 
        border-radius: 8px; 
        cursor: pointer; 
        width: 100%; 
        font-size: 16px; 
        transition: background 0.3s;
    }
    button:hover { background: var(--btn-primary-hover); }
    
    .current-images { 
        margin: 15px 0; 
        padding: 10px; 
        background: var(--bg-card-alt); 
        border-radius: 10px; 
    }
    .current-images img { 
        width: 60px; 
        height: 60px; 
        object-fit: cover; 
        margin: 5px; 
        border-radius: 8px; 
    }
    .image-item { display: inline-block; margin: 5px; text-align: center; }
    .image-item input { width: auto; margin: 5px 0 0; }
    
    .row-2cols { display: flex; gap: 15px; margin-bottom: 15px; }
    .row-2cols > div { flex: 1; }
    
    .info-text { 
        font-size: 12px; 
        color: var(--text-muted); 
        margin-top: -10px; 
        margin-bottom: 15px; 
    }
    
    label { 
        display: block; 
        margin-bottom: 5px; 
        font-weight: bold; 
        color: var(--text-secondary); 
        font-size: 13px; 
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
    
    @media (max-width: 600px) { 
        .row-2cols { flex-direction: column; gap: 0; } 
        .edit-product-container { margin: 20px auto; }
        .card-body { padding: 15px; }
    }
</style>

<div class="edit-product-container">
    <div class="card">
        <div class="card-header">✏️ تعديل المنتج</div>
        <div class="card-body">
            <?php if($message): ?>
                <div class="message"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($product['has_unconfirmed_orders'] == 0 && $product['has_unconfirmed_orders'] !== null): ?>
                <div class="info-box">
                    ℹ️ <strong>ملاحظة:</strong> هذا المنتج إما لا يوجد عليه طلبات، أو جميع طلباته بحالة "جاهز للشحن". 
                    التعديلات التي ستجريها الآن <strong>لن تؤثر</strong> على الطلبات السابقة.
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="text" name="name" placeholder="اسم المنتج" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                <textarea name="description" rows="4" placeholder="وصف المنتج"><?php echo htmlspecialchars($product['description']); ?></textarea>
                
                <select name="category_id" required>
                    <option value="">-- اختر الفئة --</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo ($product['category_id'] == $cat['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <div class="row-2cols">
                    <div>
                        <input type="number" name="price" step="1" placeholder="السعر (دج)" value="<?php echo $product['price']; ?>" required>
                    </div>
                    <div>
                        <input type="number" name="stock" placeholder="الكمية المتوفرة" value="<?php echo $product['stock']; ?>" min="1" required>
                    </div>
                </div>
                
                <div class="row-2cols">
                    <div>
                        <label>📏 المقاسات المتاحة</label>
                        <input type="text" name="size" placeholder="مثال: S, M, L, XL" value="<?php echo htmlspecialchars($product['size'] ?? ''); ?>">
                    </div>
                    <div>
                        <label>🎨 الألوان المتاحة</label>
                        <input type="text" name="color" placeholder="مثال: أحمر, أزرق, أخضر" value="<?php echo htmlspecialchars($product['color'] ?? ''); ?>">
                    </div>
                </div>
                
                <hr>
                <div class="section-title">⚙️ إعدادات إلغاء الطلب (لحالة الدفع عند الاستلام)</div>
                
                <div class="row-2cols">
                    <div>
                        <label>⏱️ فترة الإلغاء (ساعات)</label>
                        <input type="number" name="cancel_hours" value="<?php echo $product['cancel_hours'] ?? 2; ?>" min="0" max="48" step="1">
                        <div class="info-text">عدد الساعات المسموحة للمشتري لإلغاء الطلب (0 = غير مسموح)</div>
                    </div>
                    <div style="display: flex; align-items: center;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="cancel_enabled" value="1" <?php echo ($product['cancel_enabled'] ?? 1) ? 'checked' : ''; ?> style="width: 20px; height: 20px; margin: 0;">
                            <span>✅ تفعيل خاصية إلغاء الطلب من قبل المشتري</span>
                        </label>
                    </div>
                </div>
                
                <hr>
                
                <?php if(count($product_images) > 0): ?>
                <div class="current-images">
                    <label>📸 الصور الحالية (اختر للحذف):</label>
                    <div>
                        <?php foreach($product_images as $img): ?>
                        <div class="image-item">
                            <img src="<?php echo $img['image_path']; ?>" alt="صورة المنتج">
                            <br><input type="checkbox" name="delete_images[]" value="<?php echo $img['id']; ?>"> حذف
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <label>➕ إضافة صور جديدة</label>
                <input type="file" name="new_images[]" accept="image/*" multiple>
                
                <button type="submit">💾 حفظ التغييرات</button>
            </form>
        </div>
    </div>
</div>

<?php
include_once 'footer.php';
include_once 'bottom_nav.php';
?>
</body>
</html>