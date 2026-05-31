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
// ============================================================

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$product_id = $_GET['id'];

// جلب تفاصيل المنتج
try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.full_name as seller_name, u.phone as seller_phone, u.wilaya as seller_wilaya
        FROM products p 
        JOIN users u ON p.seller_id = u.id 
        WHERE p.id = ? AND p.status = 'available'
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        header("Location: index.php");
        exit();
    }
} catch(PDOException $e) {
    header("Location: index.php");
    exit();
}

// جلب جميع صور المنتج
$product_images = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC");
    $stmt->execute([$product_id]);
    $product_images = $stmt->fetchAll();
} catch(PDOException $e) {}

// جلب التقييمات للمنتج
$ratings = [];
$avg_rating = 0;
$ratings_count = 0;
try {
    $stmt = $pdo->prepare("
        SELECT r.*, u.full_name as buyer_name 
        FROM ratings r 
        JOIN users u ON r.buyer_id = u.id 
        WHERE r.product_id = ? 
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$product_id]);
    $ratings = $stmt->fetchAll();
    
    // حساب متوسط التقييم
    $avg_stmt = $pdo->prepare("SELECT AVG(rating) as avg, COUNT(*) as cnt FROM ratings WHERE product_id = ?");
    $avg_stmt->execute([$product_id]);
    $avg_data = $avg_stmt->fetch();
    $avg_rating = round($avg_data['avg'] ?? 0, 1);
    $ratings_count = $avg_data['cnt'] ?? 0;
} catch(PDOException $e) {}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($product['name']); ?> - سوق الرمال</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Tahoma', Arial, sans-serif; background: var(--bg-body); }
        header { background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('uploads/header-bg.jpg'); background-size: cover; background-position: center; color: white; padding: 50px 20px; text-align: center; }
        header h1 { font-size: 34px; margin-bottom: 10px; }
        header p { font-size: 16px; opacity: 0.9; }
        
        nav { background: var(--bg-nav); box-shadow: var(--shadow-nav); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .logo { font-size: 24px; font-weight: bold; color: var(--text-heading); }
        .nav-links { display: flex; flex-wrap: wrap; gap: 5px; align-items: center; }
        .nav-links a { color: var(--text-primary); text-decoration: none; padding: 8px 15px; border-radius: 25px; }
        .nav-links a:hover { background: var(--border-light); }
        .btn-nav { background: var(--btn-primary); color: white !important; }
        .user-name { background: var(--user-name-bg); color: var(--user-name-text) !important; font-weight: bold; }
        .logout-btn { background: var(--logout-bg); color: var(--logout-text) !important; }
        
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        
        /* معرض الصور */
        .gallery { display: flex; gap: 20px; flex-wrap: wrap; }
        .main-image {
            flex: 2; background: var(--bg-card-alt); border-radius: 20px; overflow: hidden;
            min-height: 400px; display: flex; align-items: center; justify-content: center; cursor: pointer;
        }
        .main-image img { width: 100%; height: 100%; object-fit: contain; max-height: 400px; }
        .thumbnails { flex: 1; display: flex; flex-direction: column; gap: 10px; }
        .thumb {
            background: var(--bg-card-alt); border-radius: 15px; overflow: hidden;
            cursor: pointer; transition: transform 0.2s; border: 2px solid transparent;
        }
        .thumb:hover { transform: scale(1.02); }
        .thumb.active { border-color: #b8860b; }
        .thumb img { width: 100%; height: 80px; object-fit: cover; }
        
        .product-info {
            margin-top: 30px; padding: 20px; background: var(--bg-card);
            border-radius: 20px; box-shadow: 0 2px 10px var(--shadow-md);
        }
        .product-title { font-size: 28px; color: var(--text-primary); margin-bottom: 15px; }
        .product-price { font-size: 32px; color: var(--text-heading); font-weight: bold; margin-bottom: 20px; }
        .product-description { color: var(--text-secondary); line-height: 1.8; margin-bottom: 25px; }
        
        /* قسم التقييمات */
        .ratings-section {
            margin-top: 30px; padding: 20px; background: var(--bg-card-alt); border-radius: 15px;
        }
        .ratings-section h3 { color: var(--text-heading); margin-bottom: 15px; }
        .rating-item { border-bottom: 1px solid var(--border-color); padding: 12px 0; }
        .rating-stars { color: var(--star-color); font-size: 16px; margin-bottom: 5px; }
        .rating-comment { color: var(--text-primary); margin: 5px 0; }
        .rating-meta { font-size: 11px; color: var(--text-muted); }
        .no-ratings { text-align: center; padding: 20px; color: var(--text-muted); }
        
        .seller-info { background: var(--bg-card-alt); padding: 20px; border-radius: 15px; margin-bottom: 25px; }
        .seller-info h3 { color: var(--text-heading); margin-bottom: 10px; }
        .seller-info p { color: var(--text-primary); }
        .seller-info strong { color: var(--text-heading); }
        
        .btn-order {
            display: block; width: 100%; background: var(--btn-primary); color: white;
            text-align: center; text-decoration: none; padding: 15px; border-radius: 40px;
            font-size: 18px; font-weight: bold; transition: background 0.3s;
        }
        .btn-order:hover { background: var(--btn-primary-hover); }
        .btn-disabled { background: #555; color: #aaa; display: block; width: 100%; text-align: center; padding: 15px; border-radius: 40px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: var(--text-link); text-decoration: none; }
        footer { background: var(--bg-footer); color: white; text-align: center; padding: 20px; margin-top: 40px; }
        
        @media (max-width: 768px) {
            .gallery { flex-direction: column; }
            .thumbnails { flex-direction: row; order: 2; }
            .thumb img { height: 60px; }
            nav { display: none !important; }
            header { padding: 30px 20px; }
            header h1 { font-size: 28px; }
            .nav-links { justify-content: center; }
        }
        
        @media (max-width: 480px) {
            .product-title { font-size: 22px; }
            .product-price { font-size: 24px; }
        }
    </style>
</head>
<body onload="restoreScrollPosition()">
<?php include_once 'header.php'; ?>
    
    <div class="container">
        
        <!-- معرض الصور -->
        <div class="gallery">
            <div class="main-image" id="mainImage" onclick="goToOrder()">
                <?php if(count($product_images) > 0 && !empty($product_images[0]['image_path'])): ?>
                    <img src="<?php echo $product_images[0]['image_path']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" id="mainImg">
                <?php else: ?>
                    <div style="font-size: 80px; color: var(--text-heading);">🛍️</div>
                <?php endif; ?>
            </div>
            
            <?php if(count($product_images) > 1): ?>
            <div class="thumbnails" id="thumbnails">
                <?php foreach($product_images as $index => $img): ?>
                    <div class="thumb <?php echo $index == 0 ? 'active' : ''; ?>" onclick="changeImage(<?php echo $index; ?>, '<?php echo $img['image_path']; ?>')">
                        <img src="<?php echo $img['image_path']; ?>" alt="صورة <?php echo $index+1; ?>">
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- معلومات المنتج وزر الطلب -->
        <div class="product-info">
            <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
            <div class="product-price"><?php echo number_format($product['price'], 2); ?> دج</div>
            
            <div class="product-description">
                <?php echo !empty($product['description']) ? nl2br(htmlspecialchars($product['description'])) : 'لا يوجد وصف للمنتج'; ?>
            </div>
            
            <!-- المقاس واللون -->
            <?php if($product['size']): ?>
            <div style="margin-bottom: 15px; padding: 10px; background: var(--bg-card-alt); border-radius: 10px;">
                <span style="font-weight: bold; color: var(--text-primary);">📏 المقاسات المتاحة:</span> 
                <span style="color: var(--text-primary);"><?php echo htmlspecialchars($product['size']); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if($product['color']): ?>
            <div style="margin-bottom: 15px; padding: 10px; background: var(--bg-card-alt); border-radius: 10px;">
                <span style="font-weight: bold; color: var(--text-primary);">🎨 الألوان المتاحة:</span> 
                <span style="color: var(--text-primary);"><?php echo htmlspecialchars($product['color']); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="seller-info">
                <h3>👩 معلومات البائع</h3>
                <p><strong>الاسم:</strong> <?php echo htmlspecialchars($product['seller_name']); ?></p>
                <p><strong>رقم الهاتف:</strong> <?php echo htmlspecialchars($product['seller_phone']); ?></p>
                <p><strong>الولاية:</strong> <?php echo htmlspecialchars($product['seller_wilaya'] ?: 'الوادي'); ?></p>
                <p><strong>📦 الكمية المتوفرة:</strong> <?php echo $product['stock']; ?> قطعة</p>
            </div>
            
            <?php if($product['stock'] > 0): ?>
                <a href="order.php?product_id=<?php echo $product['id']; ?>" class="btn-order" id="orderBtn">🛒 طلب المنتج</a>
            <?php else: ?>
                <div class="btn-disabled">❌ غير متوفر حالياً (نفذت الكمية)</div>
            <?php endif; ?>
        </div>
        
        <!-- قسم التقييمات -->
        <div class="ratings-section">
            <h3>⭐ تقييمات المنتج 
                <?php if($ratings_count > 0): ?>
                    (<?php echo $avg_rating; ?>/5 - <?php echo $ratings_count; ?> تقييم)
                <?php endif; ?>
            </h3>
            
            <?php if(count($ratings) > 0): ?>
                <?php foreach($ratings as $rating): ?>
                    <div class="rating-item">
                        <div class="rating-stars">
                            <?php 
                            $full_stars = floor($rating['rating']);
                            $empty_stars = 5 - $full_stars;
                            echo str_repeat('★', $full_stars);
                            echo str_repeat('☆', $empty_stars);
                            ?>
                        </div>
                        <?php if(!empty($rating['comment'])): ?>
                            <div class="rating-comment"><?php echo nl2br(htmlspecialchars($rating['comment'])); ?></div>
                        <?php endif; ?>
                        <div class="rating-meta">
                            بواسطة <?php echo htmlspecialchars($rating['buyer_name']); ?> | <?php echo date('Y-m-d', strtotime($rating['created_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-ratings">
                    <p>📭 لا توجد تقييمات لهذا المنتج بعد</p>
                    <p style="font-size:12px; margin-top:5px;">كن أول من يقيم هذا المنتج بعد شرائه</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        let currentImageIndex = 0;
        let images = [];
        
        <?php if(count($product_images) > 0): ?>
            images = [
                <?php foreach($product_images as $img): ?>
                    '<?php echo $img['image_path']; ?>',
                <?php endforeach; ?>
            ];
        <?php endif; ?>
        
        function changeImage(index, imagePath) {
            currentImageIndex = index;
            document.getElementById('mainImg').src = imagePath;
            
            var thumbs = document.querySelectorAll('.thumb');
            thumbs.forEach(function(thumb, i) {
                if (i == index) {
                    thumb.classList.add('active');
                } else {
                    thumb.classList.remove('active');
                }
            });
        }
        
        function goToOrder() {
            var orderBtn = document.getElementById('orderBtn');
            if (orderBtn && orderBtn.href) {
                window.location.href = orderBtn.href;
            }
        }
    </script>
    <?php include_once 'footer.php'; ?>
    <?php include_once 'bottom_nav.php'; ?>
    
</body>
</html>
