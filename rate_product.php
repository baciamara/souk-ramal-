<?php
session_start();
require_once 'config.php';

// ============================================================
// المتغيرات المطلوبة للهيدر والشريط السفلي
// ============================================================
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = "تقييم المنتج";

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

// أي مستخدم مسجل يمكنه تقييم المنتج (لأنه قد يكون اشتراه)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    header("Location: my_orders.php");
    exit();
}

$order_id = $_GET['order_id'];
$user_id = $_SESSION['user_id'];

// التحقق من أن الطلب مكتمل ولم يتم تقييمه بعد
try {
    $stmt = $pdo->prepare("
        SELECT o.*, p.name as product_name, p.id as product_id, o.seller_id
        FROM orders o
        JOIN products p ON o.product_id = p.id
        WHERE o.id = ? AND o.buyer_id = ? AND (o.order_status = 'delivered' OR o.order_status = 'delivered_to_buyer')
        AND NOT EXISTS (SELECT 1 FROM ratings WHERE order_id = o.id)
    ");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        header("Location: my_orders.php?error=لا يمكن تقييم هذا الطلب");
        exit();
    }
} catch(PDOException $e) {
    header("Location: my_orders.php");
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);
    
    if ($rating < 1 || $rating > 5) {
        $error = "التقييم يجب أن يكون بين 1 و 5 نجوم";
    } else {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO ratings (order_id, product_id, seller_id, buyer_id, rating, comment) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$order_id, $order['product_id'], $order['seller_id'], $user_id, $rating, $comment]);
            
            // تحديث متوسط تقييم المنتج
            $stmt = $pdo->prepare("
                UPDATE products 
                SET avg_rating = (SELECT AVG(rating) FROM ratings WHERE product_id = ?),
                    ratings_count = (SELECT COUNT(*) FROM ratings WHERE product_id = ?)
                WHERE id = ?
            ");
            $stmt->execute([$order['product_id'], $order['product_id'], $order['product_id']]);
            
            // تحديث متوسط تقييم البائع
            $stmt = $pdo->prepare("
                UPDATE users 
                SET seller_avg_rating = (SELECT AVG(rating) FROM ratings WHERE seller_id = ?),
                    seller_ratings_count = (SELECT COUNT(*) FROM ratings WHERE seller_id = ?)
                WHERE id = ?
            ");
            $stmt->execute([$order['seller_id'], $order['seller_id'], $order['seller_id']]);
            
            $pdo->commit();
            $message = "✅ شكراً لتقييمك! تم إضافة تقييمك بنجاح";
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            $error = "حدث خطأ: " . $e->getMessage();
        }
    }
}

// تضمين الهيدر
include_once 'header.php';
?>

<style>
    .rate-container { max-width: 500px; margin: 30px auto; padding: 0 20px; }
    .rate-card {
        background: var(--bg-card); border-radius: 20px; padding: 30px;
        box-shadow: 0 5px 20px var(--shadow-lg);
    }
    .rate-title { color: var(--text-heading); text-align: center; margin-bottom: 20px; font-size: 24px; }
    .stars { display: flex; justify-content: center; gap: 10px; margin: 20px 0; }
    .star { font-size: 40px; cursor: pointer; color: #555; transition: color 0.2s; }
    .star.selected { color: var(--star-color); }
    html.dark-mode .star { color: #555; }
    html.dark-mode .star.selected { color: var(--star-color); }
    textarea {
        width: 100%; padding: 12px; border: 1px solid var(--border-input); border-radius: 10px;
        font-family: inherit; margin-bottom: 20px; background: var(--bg-input); color: var(--text-primary);
    }
    button {
        background: var(--btn-primary); color: white; border: none; padding: 14px;
        border-radius: 40px; cursor: pointer; width: 100%; font-size: 16px; font-weight: bold;
    }
    button:hover { background: var(--btn-primary-hover); }
    .message { background: var(--success-bg); color: var(--success-text); padding: 15px; border-radius: 15px; margin-bottom: 20px; text-align: center; }
    .error { background: var(--error-bg); color: var(--error-text); padding: 12px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
    .product-info { background: var(--bg-card-alt); padding: 15px; border-radius: 15px; margin-bottom: 20px; color: var(--text-primary); }
    .product-info strong { color: var(--text-heading); }
    label { display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-primary); }
    
    @media (max-width: 768px) {
        .rate-card { padding: 20px; }
        .star { font-size: 32px; }
    }
</style>

<div class="rate-container">
    <div class="rate-card">
        <h1 class="rate-title">⭐ تقييم المنتج</h1>
        
        <?php if($message): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php else: ?>
            <?php if($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="product-info">
                <p><strong>📦 المنتج:</strong> <?php echo htmlspecialchars($order['product_name']); ?></p>
                <p><strong>📝 رقم الطلب:</strong> <?php echo htmlspecialchars($order['order_number']); ?></p>
            </div>
            
            <form method="POST">
                <label>⭐ تقييمك (1 إلى 5 نجوم)</label>
                <div class="stars" id="stars">
                    <span class="star" data-value="1">★</span>
                    <span class="star" data-value="2">★</span>
                    <span class="star" data-value="3">★</span>
                    <span class="star" data-value="4">★</span>
                    <span class="star" data-value="5">★</span>
                </div>
                <input type="hidden" name="rating" id="rating" required>
                
                <label>💬 تعليقك (اختياري)</label>
                <textarea name="comment" rows="4" placeholder="شاركنا تجربتك مع هذا المنتج..."></textarea>
                
                <button type="submit">📝 إرسال التقييم</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    const stars = document.querySelectorAll('.star');
    const ratingInput = document.getElementById('rating');
    
    stars.forEach(star => {
        star.addEventListener('click', function() {
            const value = this.getAttribute('data-value');
            ratingInput.value = value;
            
            stars.forEach(s => {
                if (s.getAttribute('data-value') <= value) {
                    s.classList.add('selected');
                } else {
                    s.classList.remove('selected');
                }
            });
        });
    });
</script>

<?php
include_once 'footer.php';
include_once 'bottom_nav.php';
?>
</body>
</html>