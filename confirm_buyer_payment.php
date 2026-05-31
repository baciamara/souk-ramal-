<?php
session_start();
require_once 'config.php';

// ============================================================
// المتغيرات المطلوبة للهيدر والشريط السفلي
// ============================================================
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = "تأكيد الدفع";

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

// التأكد من أن المستخدم مسجل دخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// التأكد من وجود order_id
if (!isset($_POST['order_id']) || empty($_POST['order_id'])) {
    header("Location: index.php");
    exit();
}

$order_id = $_POST['order_id'];
$buyer_id = $_SESSION['user_id'];
$scroll_pos = isset($_POST['scroll_pos']) ? intval($_POST['scroll_pos']) : 0;

try {
    // جلب معلومات الطلب كاملة
    $stmt = $pdo->prepare("
        SELECT o.*, p.name as product_name, p.price as product_price, 
               u.full_name as seller_name, u.email as seller_email 
        FROM orders o
        JOIN products p ON o.product_id = p.id
        JOIN users u ON o.seller_id = u.id
        WHERE o.id = ? AND o.buyer_id = ?
    ");
    $stmt->execute([$order_id, $buyer_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        throw new Exception("الطلب غير موجود");
    }
    
    // التحقق من طريقة الدفع
    $payment_type = $order['payment_type'] ?? 'advance';
    
    if ($payment_type == 'cash_on_delivery') {
        // دفع عند الاستلام: لا يحتاج إلى دفع مقدم، ولكن يبقى pending حتى يؤكد البائع
        $message = "✅ تم إنشاء طلبك بنجاح (دفع عند الاستلام)";
        $message .= "<br><br>📊 تفاصيل الطلب:";
        $message .= "<br>📝 رقم الطلب: " . htmlspecialchars($order['order_number']);
        $message .= "<br>🛍️ المنتج: " . htmlspecialchars($order['product_name']);
        $message .= "<br>📦 الكمية: " . $order['quantity'];
        
        $products_total = $order['product_price'] * $order['quantity'];
        $delivery_fee = $order['delivery_fee'] ?? 0;
        $total_with_delivery = $products_total + $delivery_fee;
        
        $message .= "<br>💰 إجمالي المنتجات: " . number_format($products_total, 2) . " دج";
        $message .= "<br>🚚 سعر التوصيل: " . number_format($delivery_fee, 2) . " دج";
        $message .= "<br><strong>💵 إجمالي الطلب: " . number_format($total_with_delivery, 2) . " دج</strong>";
        $message .= "<br><br>📢 سيتم إشعار البائع لتأكيد الطلب وتجهيزه.";
        $message .= "<br>💰 سيتم دفع المبلغ كاملاً عند استلام الطلب.";
        
        // لا نغير حالة الطلب هنا، تبقى pending حتى يؤكد البائع
        
    } else {
        // دفع عربون: يحتاج إلى تأكيد دفع
        if ($order['order_status'] != 'pending') {
            throw new Exception("لا يمكن تأكيد الدفع لهذا الطلب في هذه المرحلة");
        }
        
        $updateStmt = $pdo->prepare("
            UPDATE orders 
            SET advance_paid_by_buyer = 1, 
                advance_paid_by_buyer_date = NOW(), 
                order_status = 'advance_paid_by_buyer' 
            WHERE id = ? AND buyer_id = ?
        ");
        $updateStmt->execute([$order_id, $buyer_id]);
        
        // حساب المبالغ
        $products_total = $order['product_price'] * $order['quantity'];
        $delivery_fee = $order['delivery_fee'] ?? 0;
        $total_with_delivery = $products_total + $delivery_fee;
        $advance_amount = $order['advance_amount'] ?? ($total_with_delivery * 0.25);
        $remaining_amount = $total_with_delivery - $advance_amount;
        
        $message = "✅ تم تأكيد دفع الدفعة المقدمة للطلب رقم: " . htmlspecialchars($order['order_number']);
        $message .= "<br><br>📊 تفاصيل الطلب:";
        $message .= "<br>🛍️ المنتج: " . htmlspecialchars($order['product_name']);
        $message .= "<br>📦 الكمية: " . $order['quantity'];
        $message .= "<br>💰 إجمالي المنتجات: " . number_format($products_total, 2) . " دج";
        $message .= "<br>🚚 سعر التوصيل: " . number_format($delivery_fee, 2) . " دج";
        $message .= "<br><strong>💵 إجمالي الطلب: " . number_format($total_with_delivery, 2) . " دج</strong>";
        $message .= "<br>💳 الدفعة المقدمة (25%): " . number_format($advance_amount, 2) . " دج";
        $message .= "<br>💵 المتبقي عند الاستلام: " . number_format($remaining_amount, 2) . " دج";
        $message .= "<br><br>📢 سيتم إشعار البائع لتأكيد استلام الدفعة وتجهيز طلبك.";
    }
    
} catch(Exception $e) {
    $error = "❌ حدث خطأ: " . $e->getMessage();
} catch(PDOException $e) {
    $error = "❌ حدث خطأ في قاعدة البيانات: " . $e->getMessage();
}

// تضمين الهيدر
include_once 'header.php';
?>

<style>
    .confirm-container { max-width: 550px; margin: 40px auto; padding: 0 20px; }
    .confirm-card {
        background: var(--bg-card); border-radius: 20px;
        box-shadow: 0 15px 35px var(--shadow-lg); padding: 30px; text-align: center;
    }
    .confirm-title { color: var(--text-heading); margin-bottom: 20px; font-size: 24px; }
    .confirm-success {
        background: var(--success-bg); color: var(--success-text);
        padding: 20px; border-radius: 15px; margin-bottom: 20px;
        text-align: right; line-height: 1.8;
    }
    .confirm-success strong { color: var(--success-text); }
    .confirm-error { background: var(--error-bg); color: var(--error-text); padding: 15px; border-radius: 10px; margin-bottom: 20px; }
    .btn-confirm-action {
        display: inline-block; background: var(--btn-primary); color: white;
        padding: 12px 25px; border-radius: 30px; text-decoration: none;
        margin: 10px; font-weight: bold; transition: 0.3s;
    }
    .btn-confirm-action:hover { background: var(--btn-primary-hover); transform: translateY(-2px); }
    hr { margin: 15px 0; border: none; border-top: 1px solid var(--border-light); }
    
    @media (max-width: 768px) {
        .confirm-card { padding: 20px; }
    }
</style>

<div class="confirm-container">
    <div class="confirm-card">
        <h1 class="confirm-title">🏜️ سوق الرمال</h1>
        
        <?php if(isset($message)): ?>
            <div class="confirm-success">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
            <div class="confirm-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div>
            <a href="my_orders.php" class="btn-confirm-action">📋 عرض طلباتي</a>
        </div>
    </div>
</div>

<?php
include_once 'footer.php';
include_once 'bottom_nav.php';
?>
</body>
</html>