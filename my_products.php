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

$user_id = $_SESSION['user_id'];

// جلب المنتجات مع اسم الفئة والتحقق من وجود طلبات غير جاهزة للشحن
$products = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name,
        (SELECT COUNT(*) FROM orders WHERE product_id = p.id AND order_status IN ('pending', 'waiting', 'processing')) as has_unconfirmed_orders,
        (SELECT COUNT(*) FROM orders WHERE product_id = p.id AND order_status NOT IN ('delivered', 'cancelled')) as has_active_orders
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.seller_id = ? 
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $products = $stmt->fetchAll();
} catch(PDOException $e) {}

// حذف منتج (فقط إذا لم يكن مرتبط بطلبات غير جاهزة للشحن)
if (isset($_GET['delete'])) {
    $product_id = $_GET['delete'];
    try {
        $check = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE product_id = ? AND order_status IN ('pending', 'waiting', 'processing')");
        $check->execute([$product_id]);
        $has_unconfirmed = $check->fetchColumn() > 0;
        
        if ($has_unconfirmed) {
            $error = "⚠️ لا يمكن حذف المنتج لأنه مرتبط بطلبات لم تجهز للشحن بعد";
        } else {
            // حذف الصور المرتبطة بالمنتج
            $img_stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
            $img_stmt->execute([$product_id]);
            while ($img = $img_stmt->fetch()) {
                if (file_exists($img['image_path'])) {
                    unlink($img['image_path']);
                }
            }
            $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$product_id]);
            
            // حذف المنتج
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND seller_id = ?");
            $stmt->execute([$product_id, $user_id]);
            $message = "✅ تم حذف المنتج";
        }
    } catch(PDOException $e) {
        $error = "خطأ: " . $e->getMessage();
    }
}

// عرض رسائل النجاح أو الخطأ
if (isset($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
}
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}

// تضمين الهيدر
include_once 'header.php';
?>

<style>
    .my-products-container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
    
    .card { 
        background: var(--bg-card); 
        border-radius: 15px; 
        box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        margin-bottom: 30px; 
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
    .card-body { padding: 20px; overflow-x: auto; }
    
    /* أزرار الإجراءات */
    .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; align-items: center; }
    .btn { 
        color: var(--text-white); 
        padding: 6px 12px; 
        text-decoration: none; 
        border-radius: 6px; 
        font-size: 12px; 
        display: inline-block; 
        border: none; 
        cursor: pointer; 
        transition: 0.3s; 
    }
    .btn:hover { opacity: 0.8; transform: scale(1.02); }
    .btn-edit { background: var(--btn-primary); }
    .btn-hide { background: var(--btn-warning, #e65100); }
    .btn-restore { background: var(--btn-success); }
    .btn-delete { background: var(--btn-danger); }
    
    .badge-pending { 
        background: var(--badge-pending-bg); 
        color: var(--badge-pending-text); 
        padding: 4px 10px; 
        border-radius: 20px; 
        font-size: 11px; 
        display: inline-block; 
        font-weight: bold;
    }
    .badge-allowed { 
        background: var(--badge-success-bg); 
        color: var(--badge-success-text); 
        padding: 4px 10px; 
        border-radius: 20px; 
        font-size: 11px; 
        display: inline-block; 
        font-weight: bold;
    }
    .badge-hidden { 
        background: var(--bg-card-alt, #eeeeee); 
        color: var(--text-muted); 
        padding: 4px 10px; 
        border-radius: 20px; 
        font-size: 11px; 
        display: inline-block; 
        font-weight: bold;
    }
    
    table { width: 100%; border-collapse: collapse; min-width: 800px; }
    th, td { 
        padding: 12px; 
        text-align: center; 
        border-bottom: 1px solid var(--border-color); 
        color: var(--text-primary);
        vertical-align: middle; 
    }
    th { 
        background: var(--btn-primary); 
        color: var(--text-white); 
    }
    tr:hover { background: var(--hover-row-bg); }
    
    .message { 
        background: var(--success-bg); 
        color: var(--success-text); 
        padding: 10px; 
        border-radius: 8px; 
        margin-bottom: 15px; 
        text-align: center; 
    }
    .error { 
        background: var(--error-bg); 
        color: var(--error-text); 
        padding: 10px; 
        border-radius: 8px; 
        margin-bottom: 15px; 
        text-align: center; 
    }
    .warning-info { 
        font-size: 11px; 
        color: var(--badge-pending-text); 
        display: block; 
        margin-top: 5px; 
    }
    .success-info { 
        font-size: 11px; 
        color: var(--success-text); 
        display: block; 
        margin-top: 5px; 
    }
    .product-status { font-size: 12px; }
    
    @media (max-width: 768px) {
        .my-products-container { margin: 20px auto; }
        table { min-width: 600px; }
        th, td { padding: 8px 6px; font-size: 12px; }
        .btn { padding: 4px 8px; font-size: 10px; }
    }
</style>

<div class="my-products-container">
    <div class="card">
        <div class="card-header">📦 منتجاتي (<?php echo count($products); ?>)</div>
        <div class="card-body">
            <?php if(isset($message) && $message): ?>
                <div class="message"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if(isset($error) && $error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if(count($products) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>المنتج</th>
                            <th>الفئة</th>
                            <th>السعر</th>
                            <th>الكمية</th>
                            <th>الحالة</th>
                            <th>تاريخ الإضافة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo htmlspecialchars($product['category_name'] ?? '—'); ?></td>
                                <td><?php echo number_format($product['price'], 2); ?> دج</td>
                                <td><?php echo $product['stock']; ?> قطعة</td>
                                <td class="product-status">
                                    <?php if($product['is_hidden'] == 1): ?>
                                        <span class="badge-hidden">🔴 مخفي عن الزبائن</span>
                                    <?php else: ?>
                                        <?php echo $product['status'] == 'available' ? '🟢 متوفر' : '🔴 تم البيع'; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($product['created_at'])); ?></td>
                                <td>
                                    <?php if($product['has_unconfirmed_orders'] > 0): ?>
                                        <!-- حالة المنع: يوجد طلبات لم تجهز للشحن بعد -->
                                        <div class="action-buttons">
                                            <span class="badge-pending">⏳ مرتبط بطلبات لم تجهز للشحن</span>
                                            <span class="warning-info">🔒 لا يمكن التعديل أو الإخفاء أو الحذف</span>
                                        </div>
                                    <?php else: ?>
                                        <!-- حالة السماح: جميع الطلبات جاهزة للشحن أو لا يوجد طلبات -->
                                        <div class="action-buttons">
                                            <!-- زر التعديل -->
                                            <a href="edit-product.php?id=<?php echo $product['id']; ?>" class="btn btn-edit">✏️ تعديل</a>
                                            
                                            <!-- زر الإخفاء/الاستعادة - يتغير حسب حالة المنتج -->
                                            <?php if($product['is_hidden'] == 1): ?>
                                                <a href="toggle_hide_product.php?id=<?php echo $product['id']; ?>" class="btn btn-restore" onclick="return confirm('🟢 استعادة المنتج؟ سيظهر للزبائن مرة أخرى')">
                                                    🟢 استعادة
                                                </a>
                                            <?php else: ?>
                                                <a href="toggle_hide_product.php?id=<?php echo $product['id']; ?>" class="btn btn-hide" onclick="return confirm('🙈 إخفاء المنتج؟ لن يظهر للزبائن')">
                                                    🙈 إخفاء
                                                </a>
                                            <?php endif; ?>
                                            
                                            <!-- زر الحذف -->
                                            <a href="?delete=<?php echo $product['id']; ?>" class="btn btn-delete" onclick="return confirm('⚠️ تحذير: هل أنت متأكد من حذف المنتج نهائياً؟')">
                                                🗑️ حذف
                                            </a>
                                        </div>
                                        <?php if($product['has_active_orders'] > 0 && $product['is_hidden'] == 0): ?>
                                            <small class="success-info">✅ الطلبات جاهزة للشحن - التعديل سيؤثر فقط على الطلبات الجديدة</small>
                                        <?php endif; ?>
                                        <?php if($product['is_hidden'] == 1): ?>
                                            <small class="warning-info">👁️ المنتج مخفي حالياً - لن يراه الزبائن</small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align:center; padding:40px; color: var(--text-muted);">لا توجد منتجات مضافة بعد.</p>
                <p style="text-align:center;"><a href="add-product.php" class="btn btn-edit">➕ أضف منتجك الأول</a></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
include_once 'footer.php';
include_once 'bottom_nav.php';
?>
</body>
</html>