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

// ========== التحقق من وضع الصيانة ==========
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
    $stmt->execute();
    $maintenance_mode = $stmt->fetchColumn();
    
    if ($maintenance_mode == '1') {
        $is_admin = (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin');
        if (!$is_admin) {
            include 'maintenance.php';
            exit();
        }
    }
} catch(PDOException $e) {}
// ========== نهاية التحقق ==========

// ========== جلب إشعارات المستخدم ==========
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
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")->execute([$notif_id]);
    } catch(PDOException $e) {}
    
    $current_url = strtok($_SERVER['REQUEST_URI'], '?');
    header("Location: " . $current_url);
    exit();
}
// ========== نهاية الإشعارات ==========

// تحديد الصفحة الحالية (يستخدم لتحديد الزر النشط في الشريط السفلي)
$current_page = basename($_SERVER['PHP_SELF']);

// جلب الفئات
$categories = [];
try {
    $stmt = $pdo->query("SELECT id, name, icon FROM categories WHERE is_active = 1 ORDER BY name");
    $categories = $stmt->fetchAll();
} catch(PDOException $e) {}

// تحديد طريقة الترتيب
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'latest';
$category_id = isset($_GET['category']) ? intval($_GET['category']) : null;
$products = [];

$order_by = "p.created_at DESC";
if ($sort == 'rating') {
    $order_by = "COALESCE(p.avg_rating, 0) DESC, p.ratings_count DESC";
} elseif ($sort == 'price_low') {
    $order_by = "p.price ASC";
} elseif ($sort == 'price_high') {
    $order_by = "p.price DESC";
}

try {
    if ($category_id) {
        $stmt = $pdo->prepare("
            SELECT p.*, u.full_name as seller_name, c.name as category_name
            FROM products p 
            JOIN users u ON p.seller_id = u.id 
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'available' AND p.is_hidden = 0 AND p.category_id = ?
            ORDER BY $order_by 
            LIMIT 20
        ");
        $stmt->execute([$category_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT p.*, u.full_name as seller_name, c.name as category_name
            FROM products p 
            JOIN users u ON p.seller_id = u.id 
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'available' AND p.is_hidden = 0
            ORDER BY $order_by 
            LIMIT 20
        ");
        $stmt->execute();
    }
    $products = $stmt->fetchAll();
} catch(PDOException $e) {
    $products = [];
}
$view_mode = isset($_COOKIE['product_view']) ? $_COOKIE['product_view'] : 'list';
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>سوق الرمال - سوق نسائي متكامل</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Tahoma', Arial, sans-serif; background: var(--bg-body); }
        
        header {
            background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('uploads/header-bg.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 60px 20px;
            text-align: center;
        }
        header h1 { font-size: 36px; margin-bottom: 10px; }
        header p { font-size: 18px; }
        
        nav { 
            background: var(--bg-nav); 
            box-shadow: var(--shadow-nav); 
            padding: 15px 30px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            flex-wrap: wrap; 
            gap: 10px; 
        }
        .logo { font-size: 24px; font-weight: bold; color: var(--text-heading); }
        .nav-links { display: flex; flex-wrap: wrap; gap: 5px; align-items: center; }
        .nav-links a { color: var(--text-primary); text-decoration: none; padding: 8px 15px; border-radius: 25px; transition: all 0.3s; }
        .nav-links a:hover { background: var(--border-light); }
        .btn-nav { background: var(--btn-primary); color: white !important; }
        
        .nav-links a.active-page {
            background: #8B4513 !important;
            color: white !important;
            box-shadow: 0 2px 5px var(--shadow-md);
        }
        
        .user-name { background: var(--user-name-bg); color: var(--user-name-text) !important; font-weight: bold; }
        .logout-btn { background: var(--logout-bg); color: var(--logout-text) !important; }
        
        /* ========== أزرار الإشعارات للشريط العلوي ========== */
        .notif-icon { position: relative; display: inline-block; cursor: pointer; }
        .notif-badge {
            position: absolute; top: -8px; right: -8px; background: var(--notif-badge); color: white;
            border-radius: 50%; padding: 2px 6px; font-size: 10px; min-width: 18px; text-align: center;
        }
        .notif-dropdown {
            display: none; position: absolute; left: 0; top: 35px;
            background: var(--bg-notif); border-radius: 10px;
            box-shadow: 0 5px 15px var(--shadow-lg); width: 280px; z-index: 1000;
        }
        .notif-item { display: block; padding: 10px; text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--notif-border); }
        .notif-item:hover { background: var(--notif-hover); }
        .notif-title { font-weight: bold; margin-bottom: 5px; color: var(--text-primary); }
        .notif-message { font-size: 12px; color: var(--text-secondary); }
        .notif-time { font-size: 10px; color: var(--text-muted); margin-top: 5px; }
        /* ========== نهاية الإشعارات ========== */
        
        .categories-bar {
            position: -webkit-sticky; position: sticky; top: 0;
            background: var(--bg-card); padding: 12px 20px;
            border-bottom: 2px solid var(--border-light); overflow-x: auto;
            white-space: nowrap; text-align: center; z-index: 1001;
            box-shadow: 0 2px 5px var(--shadow-sm);
        }
        .categories-bar a {
            display: inline-block; margin: 0 12px; text-decoration: none;
            color: var(--text-primary); padding: 6px 14px; border-radius: 25px; transition: all 0.3s;
        }
        .categories-bar a:hover { background: var(--border-light); }
        .categories-bar a.active { background: var(--btn-primary); color: white; }
        
        .sort-bar {
            background: var(--bg-card); padding: 10px 20px;
            border-bottom: 1px solid var(--border-color); display: flex;
            justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;
        }
        .sort-options { display: flex; gap: 15px; flex-wrap: wrap; align-items: center; }
        .sort-options span { color: var(--text-secondary); font-size: 13px; }
        .sort-options a {
            color: var(--text-primary); text-decoration: none; font-size: 13px;
            padding: 4px 12px; border-radius: 20px; transition: all 0.3s;
        }
        .sort-options a:hover { background: var(--border-light); }
        .sort-options a.active { background: var(--btn-primary); color: white; }
        
        .view-toggle {
            background: linear-gradient(135deg, #b8860b, #d4a017); color: white;
            border: none; padding: 6px 16px; border-radius: 30px; cursor: pointer;
            font-size: 13px; display: flex; align-items: center; gap: 8px; transition: all 0.3s;
        }
        .view-toggle:hover { transform: translateY(-2px); box-shadow: 0 2px 8px rgba(184,134,11,0.3); }
        
        .products { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .products h2 { text-align: center; color: var(--text-heading); margin-bottom: 30px; font-size: 28px; }
        
        .product-card {
            background: var(--bg-card); border-radius: 15px; overflow: hidden;
            box-shadow: 0 5px 15px var(--shadow-md); transition: transform 0.3s;
        }
        .product-card:hover { transform: translateY(-5px); }
        
        .product-image {
            height: 200px; background: var(--bg-card-alt); display: flex;
            align-items: center; justify-content: center; color: var(--text-heading);
            font-size: 48px; overflow: hidden;
        }
        .product-image img { width: 100%; height: 100%; object-fit: cover; }
        
        .product-info { padding: 15px; }
        .product-title { font-size: 18px; font-weight: bold; margin-bottom: 10px; color: var(--text-primary); }
        .product-price { color: var(--text-heading); font-size: 20px; font-weight: bold; }
        .product-rating { display: flex; align-items: center; gap: 5px; margin: 5px 0; font-size: 13px; }
        .product-rating .stars { color: var(--star-color); }
        .product-rating .count { color: var(--text-muted); }
        .seller-name { color: var(--text-muted); font-size: 12px; margin-top: 10px; }
        .product-category { font-size: 11px; color: var(--text-heading); margin-top: 5px; }
        
        .product-grid { display: flex; flex-direction: column; gap: 25px; }
        .product-grid.grid-view { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; }
        .product-grid.grid-view .product-card { margin: 0; }
        
        footer { background: var(--bg-footer); color: white; text-align: center; padding: 20px; margin-top: 40px; }
        
        /* ========== سلايدر ========== */
        .hero-slider {
            width: 100%; height: 400px; overflow: hidden; position: relative;
            margin-bottom: 30px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .slider-track { display: flex; width: 100%; height: 100%; transition: transform 0.5s ease-in-out; }
        .slider-slide {
            min-width: 100%; width: 100%; height: 100%;
            background-size: 100% 100%; background-position: center; background-repeat: no-repeat; position: relative;
        }
        .slider-slide::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.5), transparent); z-index: 1;
        }
        .slider-caption { position: absolute; bottom: 50px; left: 0; right: 0; text-align: center; padding: 20px; z-index: 2; }
        .slider-caption h3 { color: white; font-size: 32px; margin-bottom: 15px; text-shadow: 2px 2px 5px rgba(0,0,0,0.5); }
        .btn-slider {
            display: inline-block; background: #b8860b; color: white; padding: 12px 35px;
            border-radius: 50px; text-decoration: none; font-weight: bold; font-size: 16px;
            transition: all 0.3s ease; box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }
        .btn-slider:hover { background: #9a7209; transform: scale(1.05); }
        .slider-dots { position: absolute; bottom: 20px; left: 0; right: 0; text-align: center; z-index: 10; }
        .dot {
            display: inline-block; width: 12px; height: 12px; border-radius: 50%;
            background: rgba(255,255,255,0.6); margin: 0 6px; cursor: pointer; transition: all 0.3s;
        }
        .dot.active { background: #b8860b; width: 25px; border-radius: 10px; }
        
        @media (max-width: 768px) {
            nav { display: none; }
            .hero-slider { height: 250px; }
            .slider-caption h3 { font-size: 20px; }
            .btn-slider { padding: 8px 20px; font-size: 13px; }
            header { padding: 40px 20px; }
            header h1 { font-size: 28px; }
            .categories-bar { padding: 8px 10px; top: 60px; }
            .categories-bar a { margin: 0 8px; padding: 4px 10px; font-size: 13px; }
            .sort-bar { flex-direction: column; align-items: stretch; }
            .sort-options { justify-content: center; }
            .view-toggle { justify-content: center; width: 100%; max-width: 130px; margin: 0 auto; }
            .product-grid.grid-view { grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 15px; }
            .product-image { height: 180px; }
        }
        
        @media (max-width: 480px) {
            .product-grid.grid-view { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; }
            .product-info { padding: 10px; }
        }
    </style>
</head>
<body onload="restoreScrollPosition()">
    
  <?php include_once 'header.php'; ?>
    
    <div class="categories-bar" id="categoriesBar">
        <a href="index.php?sort=<?php echo $sort; ?>" class="<?php echo !$category_id ? 'active' : ''; ?>">🏠 الكل</a>
        <?php foreach($categories as $cat): ?>
            <a href="index.php?category=<?php echo $cat['id']; ?>&sort=<?php echo $sort; ?>" class="<?php echo ($category_id == $cat['id']) ? 'active' : ''; ?>">
                <?php echo $cat['icon'] ?? '🏷️'; ?> <?php echo htmlspecialchars($cat['name']); ?>
            </a>
        <?php endforeach; ?>
    </div>
    
    <div class="sort-bar">
        <div class="sort-options">
            <span>📊 ترتيب حسب:</span>
            <a href="index.php?<?php echo $category_id ? 'category='.$category_id.'&' : ''; ?>sort=latest" class="<?php echo $sort == 'latest' ? 'active' : ''; ?>">🆕 الأحدث</a>
            <a href="index.php?<?php echo $category_id ? 'category='.$category_id.'&' : ''; ?>sort=rating" class="<?php echo $sort == 'rating' ? 'active' : ''; ?>">⭐ الأعلى تقييماً</a>
            <a href="index.php?<?php echo $category_id ? 'category='.$category_id.'&' : ''; ?>sort=price_low" class="<?php echo $sort == 'price_low' ? 'active' : ''; ?>">💰 السعر: من الأقل للأعلى</a>
            <a href="index.php?<?php echo $category_id ? 'category='.$category_id.'&' : ''; ?>sort=price_high" class="<?php echo $sort == 'price_high' ? 'active' : ''; ?>">💰 السعر: من الأعلى للأقل</a>
        </div>
        
        <button class="view-toggle" id="viewToggleBtn" onclick="toggleView()">
            <span id="viewIcon"><?php echo $view_mode == 'grid' ? '📋' : '🔲'; ?></span>
            <span id="viewText"><?php echo $view_mode == 'grid' ? 'قائمي' : 'شبكي'; ?></span>
        </button>
    </div>

    <!-- السلايدر -->
    <?php
    $slides = [
        ['img' => 'uploads/slide1.jpg', 'text' => 'تسوقي بأمان وراحة'],
        ['img' => 'uploads/slide2.jpg', 'text' => 'تخفيضات تصل إلى 50%'],
        ['img' => 'uploads/slide3.jpg', 'text' => 'توصيل سريع لكل الجزائر'],
        ['img' => 'uploads/slide4.jpg', 'text' => 'منتجات أصلية 100%']
    ];
    ?>
    
    <div class="hero-slider">
        <div class="slider-track" id="sliderTrack">
            <?php foreach($slides as $slide): ?>
            <div class="slider-slide" style="background-image: url('<?php echo $slide['img']; ?>');">
                <div class="slider-caption">
                    <h3><?php echo $slide['text']; ?></h3>
                    <?php if(!isset($_SESSION['user_id'])): ?>
                    <a href="register.php" class="btn-slider">انضمي إلينا الآن</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <div class="slider-slide" style="background-image: url('<?php echo $slides[0]['img']; ?>');">
                <div class="slider-caption">
                    <h3><?php echo $slides[0]['text']; ?></h3>
                    <?php if(!isset($_SESSION['user_id'])): ?>
                    <a href="register.php" class="btn-slider">انضمي إلينا الآن</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="slider-dots" id="sliderDots">
            <?php for($i = 0; $i < count($slides); $i++): ?>
            <span class="dot <?php echo $i === 0 ? 'active' : ''; ?>" data-index="<?php echo $i; ?>"></span>
            <?php endfor; ?>
        </div>
    </div>

    <div class="products">
        <h2>✨ <?php echo $category_id ? 'منتجات الفئة المختارة' : ($sort == 'rating' ? 'المنتجات الأعلى تقييماً' : 'أحدث المنتجات'); ?></h2>
        <div class="product-grid <?php echo $view_mode == 'grid' ? 'grid-view' : ''; ?>" id="productGrid">
            <?php if(count($products) > 0): ?>
                <?php foreach($products as $product): ?>
                    <div class="product-card">
                        <a href="product.php?id=<?php echo $product['id']; ?>" style="text-decoration: none; display: block;">
                            <div class="product-image">
                                <?php 
                                $primary_image = '';
                                try {
                                    $img_stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? AND is_primary = 1 LIMIT 1");
                                    $img_stmt->execute([$product['id']]);
                                    $primary_image = $img_stmt->fetchColumn();
                                } catch(PDOException $e) {}
                                
                                if($primary_image && file_exists($primary_image)): ?>
                                    <img src="<?php echo $primary_image; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php else: ?>
                                    🛍️
                                <?php endif; ?>
                            </div>
                        </a>
                        <div class="product-info">
                            <a href="product.php?id=<?php echo $product['id']; ?>" style="text-decoration: none; color: inherit;">
                                <div class="product-title"><?php echo htmlspecialchars($product['name']); ?></div>
                            </a>
                            <div class="product-price"><?php echo number_format($product['price'], 2); ?> دج</div>
                            
                            <?php if($product['avg_rating'] > 0): ?>
                            <div class="product-rating">
                                <span class="stars"><?php echo str_repeat('★', round($product['avg_rating'])); ?><?php echo str_repeat('☆', 5 - round($product['avg_rating'])); ?></span>
                                <span class="count">(<?php echo $product['ratings_count']; ?>)</span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if($product['category_name']): ?>
                                <div class="product-category">🏷️ <?php echo htmlspecialchars($product['category_name']); ?></div>
                            <?php endif; ?>
                            <div class="seller-name">👤 بواسطة: <?php echo htmlspecialchars($product['seller_name']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align:center; grid-column:1/-1; padding:40px;">
                    <p>✨ لا توجد منتجات في هذه الفئة بعد.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include_once 'footer.php'; ?>
    
    <?php include_once 'bottom_nav.php'; ?>
    
    <script>
        function toggleNotifications() {
            var dropdown = document.getElementById('notifDropdown');
            if(dropdown) {
                dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
            }
        }
        
        document.addEventListener('click', function(e) {
            var icon = document.querySelector('.notif-icon');
            var dropdown = document.getElementById('notifDropdown');
            if(icon && dropdown && !icon.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
        
        const track = document.getElementById('sliderTrack');
        const dots = document.querySelectorAll('.dot');
        let currentIndex = 0;
        const totalOriginalSlides = <?php echo count($slides); ?>;
        let isTransitioning = false;

        function goToSlide(index, immediate = false) {
            if (isTransitioning && !immediate) return;
            
            currentIndex = index;
            track.style.transition = immediate ? "none" : "transform 0.5s ease-in-out";
            track.style.transform = 'translateX(' + (currentIndex * 100) + '%)';
            
            dots.forEach((dot, i) => {
                dot.classList.toggle('active', i === (currentIndex % totalOriginalSlides));
            });
        }

        function nextSlide() {
            if (isTransitioning) return;
            
            currentIndex++;
            goToSlide(currentIndex);

            if (currentIndex === totalOriginalSlides) {
                isTransitioning = true;
                setTimeout(() => {
                    goToSlide(0, true);
                    isTransitioning = false;
                }, 500);
            }
        }

        dots.forEach(dot => {
            dot.addEventListener('click', function() {
                const index = parseInt(this.getAttribute('data-index'));
                goToSlide(index);
                clearInterval(autoSlide);
                autoSlide = setInterval(nextSlide, 4000);
            });
        });

        let autoSlide = setInterval(nextSlide, 4000);
        
        function toggleView() {
            var container = document.getElementById('productGrid');
            var icon = document.getElementById('viewIcon');
            var text = document.getElementById('viewText');
            
            if (container.classList.contains('grid-view')) {
                container.classList.remove('grid-view');
                icon.innerHTML = '🔲';
                text.innerHTML = 'شبكي';
                document.cookie = "product_view=list; path=/; max-age=" + (30 * 24 * 60 * 60);
            } else {
                container.classList.add('grid-view');
                icon.innerHTML = '📋';
                text.innerHTML = 'قائمي';
                document.cookie = "product_view=grid; path=/; max-age=" + (30 * 24 * 60 * 60);
            }
        }

        window.addEventListener('load', function() {
            var activeLink = document.querySelector('.categories-bar a.active');
            if (activeLink) {
                var bar = document.getElementById('categoriesBar');
                bar.scrollLeft = activeLink.offsetLeft - (bar.offsetWidth / 2) + (activeLink.offsetWidth / 2);
            }
        });
    </script>
</body>
</html>