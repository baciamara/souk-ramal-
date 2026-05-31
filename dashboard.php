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
// معالجة قراءة الإشعار
// ============================================================
if (isset($_GET['read_notif'])) {
    $notif_id = intval($_GET['read_notif']);
    try {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")->execute([$notif_id]);
    } catch(PDOException $e) {}
    $current_url = strtok($_SERVER['REQUEST_URI'], '?');
    header("Location: " . $current_url);
    exit();
}

// ============================================================
// المتغيرات الضرورية للشريط السفلي والإشعارات
// ============================================================
$current_page = basename($_SERVER['PHP_SELF']);

// جلب إشعارات المستخدم
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
$user_name = $_SESSION['user_name'];

// دالة ترجمة طريقة الدفع
function getPaymentTypeText($type) {
    if ($type == 'advance') {
        return '💳 عربون (25%)';
    } elseif ($type == 'cash_on_delivery') {
        return '💵 عند الاستلام';
    }
    return $type ?: 'غير محدد';
}

// دالة حساب المبلغ المستحق للتحصيل عند التسليم
function getCollectAmount($order) {
    $products_total = $order['order_total'];
    $delivery_fee = $order['delivery_fee'] ?? 0;
    $total = $products_total + $delivery_fee;
    
    if ($order['payment_type'] == 'advance') {
        $advance_amount = $order['advance_amount'] ?? ($total * 0.25);
        return $total - $advance_amount;
    } else {
        return $total;
    }
}

// دالة التحقق من إمكانية إلغاء الطلب من قبل البائع
function canSellerCancelOrder($order_status) {
    $cancelable_statuses = [
        'pending',
        'advance_paid_by_buyer',
        'advance_confirmed_by_seller',
        'ready_for_shipping'
    ];
    return in_array($order_status, $cancelable_statuses);
}

// ========== دالة ترجمة حالة الطلب حسب طريقة التوصيل ==========
function getOrderStatusArabic($status, $delivery_method) {
    if ($delivery_method == 'home') {
        $map = [
            'pending' => '⏳ في انتظار الدفع',
            'advance_paid_by_buyer' => '💰 تم الإشعار بالدفع',
            'advance_confirmed_by_seller' => '✅ تم تأكيد الطلب',
            'ready_for_shipping' => '📦 جاهز للشحن',
            'picked_by_shipping' => '📦 استلمته شركة الشحن',
            'in_transit' => '🚚 قيد التوصيل للمنزل',
            'delivered_to_buyer' => '🎉 تم التسليم',
            'cancelled' => '❌ ملغي'
        ];
    } else {
        $map = [
            'pending' => '⏳ في انتظار الدفع',
            'advance_paid_by_buyer' => '💰 تم الإشعار بالدفع',
            'advance_confirmed_by_seller' => '✅ تم تأكيد الطلب',
            'ready_for_shipping' => '📦 جاهز للشحن',
            'picked_by_shipping' => '📦 استلمته شركة الشحن',
            'in_transit_to_pickup' => '🚚 قيد التوصيل للنقطة',
            'arrived_at_pickup' => '📍 وصل إلى نقطة التجميع',
            'delivered_to_buyer' => '🎉 تم التسليم',
            'cancelled' => '❌ ملغي'
        ];
    }
    return $map[$status] ?? $status;
}
// ========== نهاية الدالة ==========

// حفظ مكان التمرير
$scroll_pos = isset($_POST['scroll_pos']) ? intval($_POST['scroll_pos']) : (isset($_GET['scroll_pos']) ? intval($_GET['scroll_pos']) : 0);

// تأكيد الطلب
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_payment'])) {
    $order_id = intval($_POST['order_id']);
    $scroll_pos = intval($_POST['scroll_pos'] ?? 0);
    try {
        $pdo->prepare("UPDATE orders SET order_status = 'advance_confirmed_by_seller' WHERE id = ? AND seller_id = ?")->execute([$order_id, $user_id]);
        $_SESSION['status_message'] = "✅ تم تأكيد الطلب، يمكنك تجهيزه للشحن";
    } catch(PDOException $e) {
        $_SESSION['status_message'] = "❌ خطأ: " . $e->getMessage();
    }
    header("Location: dashboard.php?scroll_pos=" . $scroll_pos);
    exit();
}

// تأكيد جاهزية الطلب للشحن
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_ready'])) {
    $order_id = intval($_POST['order_id']);
    $scroll_pos = intval($_POST['scroll_pos'] ?? 0);
    try {
        $pdo->prepare("UPDATE orders SET order_status = 'ready_for_shipping' WHERE id = ? AND seller_id = ?")->execute([$order_id, $user_id]);
        $_SESSION['status_message'] = "✅ تم تأكيد جاهزية الطلب للشحن";
    } catch(PDOException $e) {
        $_SESSION['status_message'] = "❌ خطأ: " . $e->getMessage();
    }
    header("Location: dashboard.php?scroll_pos=" . $scroll_pos);
    exit();
}

// إلغاء الطلب من قبل البائع
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_by_seller'])) {
    $order_id = intval($_POST['order_id']);
    $scroll_pos = intval($_POST['scroll_pos'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT order_status, product_id, quantity FROM orders WHERE id = ? AND seller_id = ?");
        $stmt->execute([$order_id, $user_id]);
        $order = $stmt->fetch();
        
        if (!$order) {
            $_SESSION['status_message'] = "⚠️ الطلب غير موجود";
        } elseif (!canSellerCancelOrder($order['order_status'])) {
            $_SESSION['status_message'] = "⚠️ لا يمكن إلغاء الطلب في هذه المرحلة";
        } else {
            $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")->execute([$order['quantity'], $order['product_id']]);
            $pdo->prepare("UPDATE orders SET order_status = 'cancelled' WHERE id = ?")->execute([$order_id]);
            $_SESSION['status_message'] = "✅ تم إلغاء الطلب وإعادة الكمية إلى المخزون";
        }
    } catch(PDOException $e) {
        $_SESSION['status_message'] = "❌ خطأ: " . $e->getMessage();
    }
    header("Location: dashboard.php?scroll_pos=" . $scroll_pos);
    exit();
}

$status_message = $_SESSION['status_message'] ?? null;
unset($_SESSION['status_message']);

// ============================================================
// جلب إحصائيات البائع (باستخدام العمولات المخزنة)
// ============================================================

// إجمالي المبيعات
$total_sales_amount = 0;
$completed_orders = 0;
try {
    $stmt = $pdo->prepare("
        SELECT SUM(oi.total_price) as total 
        FROM orders o 
        JOIN order_items oi ON o.id = oi.order_id 
        WHERE o.seller_id = ? AND o.order_status = 'delivered_to_buyer'
    ");
    $stmt->execute([$user_id]);
    $total_sales_amount = $stmt->fetchColumn() ?? 0;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE seller_id = ? AND order_status = 'delivered_to_buyer'");
    $stmt->execute([$user_id]);
    $completed_orders = $stmt->fetchColumn() ?? 0;
} catch(PDOException $e) {}

// إجمالي العمولة (من العمولات المخزنة في جدول orders)
$total_commission = 0;
$avg_commission_rate = 0;
try {
    $stmt = $pdo->prepare("
        SELECT SUM(commission_amount) as total_commission, 
               AVG(commission_rate) as avg_rate
        FROM orders 
        WHERE seller_id = ? AND order_status = 'delivered_to_buyer'
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    $total_commission = $result['total_commission'] ?? 0;
    $avg_commission_rate = round($result['avg_rate'] ?? 0, 1);
} catch(PDOException $e) {}

$net_earnings = $total_sales_amount - $total_commission;

// جلب تقييم البائع
$seller_rating = 0;
$seller_rating_count = 0;
$stmt = $pdo->prepare("SELECT seller_avg_rating, seller_ratings_count FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$seller_data = $stmt->fetch();
$seller_rating = $seller_data['seller_avg_rating'] ?? 0;
$seller_rating_count = $seller_data['seller_ratings_count'] ?? 0;

// جلب الطلبات الواردة للبائع (غير الملغاة)
$incoming_orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT o.*, 
               oi.product_name, 
               oi.product_price, 
               oi.quantity,
               oi.total_price as order_total,
               u.full_name as buyer_name,
               u.phone as buyer_phone,
               oi.product_size,
               oi.product_color,
               o.delivery_fee,
               pp.point_name as pickup_point_name,
               pp.address as pickup_point_address
        FROM orders o 
        JOIN order_items oi ON o.id = oi.order_id 
        JOIN users u ON o.buyer_id = u.id 
        LEFT JOIN pickup_points pp ON o.pickup_point_id = pp.id
        WHERE o.seller_id = ? AND o.order_status != 'cancelled'
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $incoming_orders = $stmt->fetchAll();
} catch(PDOException $e) {}

// جلب الطلبات الملغاة للبائع
$cancelled_orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT o.*, 
               oi.product_name, 
               oi.product_price, 
               oi.quantity,
               oi.total_price as order_total,
               u.full_name as buyer_name,
               u.phone as buyer_phone,
               oi.product_size,
               oi.product_color,
               o.delivery_fee,
               pp.point_name as pickup_point_name,
               pp.address as pickup_point_address
        FROM orders o 
        JOIN order_items oi ON o.id = oi.order_id 
        JOIN users u ON o.buyer_id = u.id 
        LEFT JOIN pickup_points pp ON o.pickup_point_id = pp.id
        WHERE o.seller_id = ? AND o.order_status = 'cancelled'
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $cancelled_orders = $stmt->fetchAll();
} catch(PDOException $e) {}

// جلب منتجات البائع
$products = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               COALESCE(p.avg_rating, 0) as avg_rating, 
               COALESCE(p.ratings_count, 0) as ratings_count
        FROM products p 
        WHERE p.seller_id = ? 
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $products = $stmt->fetchAll();
} catch(PDOException $e) {}

$total_products = count($products);
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'active';

// تضمين الهيدر
include_once 'header.php';
?>

<style>
    .dashboard-container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
    
    .stats-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(155px, 1fr)); 
        gap: 15px; 
        margin-bottom: 25px; 
    }
    .stat-box { 
        background: var(--bg-header-card);
        padding: 18px 15px; 
        border-radius: 15px; 
        text-align: center; 
        box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        transition: 0.3s;
        border: 1px solid #e8d49a;
    }
    .stat-box:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.12);
    }
    .stat-number { 
        font-size: 24px; 
        font-weight: bold; 
        color: var(--text-welcome);
        margin-bottom: 3px;
    }
    .stat-label { 
        color: var(--text-primary); 
        margin-top: 5px; 
        font-size: 12px; 
        font-weight: 500;
        opacity: 0.8;
    }
    
    .quick-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
        justify-content: center;
        padding: 15px;
        background: var(--bg-card);
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 25px;
    }
    
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
    
    .btn { 
        display: inline-block; 
        background: var(--btn-primary); 
        color: var(--text-white); 
        padding: 10px 20px; 
        text-decoration: none; 
        border-radius: 25px; 
        margin: 5px; 
        font-size: 14px; 
        border: none;
        cursor: pointer;
        transition: 0.3s;
    }
    .btn:hover { background: var(--btn-primary-hover); }
    .btn-cancel { 
        background: var(--btn-danger); 
        color: var(--text-white); 
        border: none; 
        padding: 6px 12px; 
        border-radius: 20px; 
        cursor: pointer; 
        font-size: 11px; 
        margin: 2px; 
        transition: 0.3s;
    }
    .btn-cancel:hover { background: var(--btn-danger-hover); }
    
    .orders-table { width: 100%; border-collapse: collapse; min-width: 1100px; }
    .orders-table th, .orders-table td { 
        padding: 12px 10px; 
        text-align: center; 
        border-bottom: 1px solid var(--border-color); 
        color: var(--text-primary);
    }
    .orders-table th { 
        background: var(--btn-primary); 
        color: var(--text-white); 
        font-weight: bold; 
    }
    .orders-table tr:hover { background: var(--hover-row-bg); }
    
    .products-table { width: 100%; border-collapse: collapse; min-width: 600px; }
    .products-table th, .products-table td { 
        padding: 10px 8px; 
        text-align: center; 
        border-bottom: 1px solid var(--border-color); 
        color: var(--text-primary);
    }
    .products-table th { 
        background: var(--btn-primary); 
        color: var(--text-white); 
    }
    .products-table tr:hover { background: var(--hover-row-bg); }
    
    .btn-payment { 
        background: var(--btn-warning, #e65100); 
        color: var(--text-white); 
        border: none; 
        padding: 6px 12px; 
        border-radius: 20px; 
        cursor: pointer; 
        font-size: 11px; 
        transition: 0.3s;
    }
    .btn-payment:hover { background: var(--btn-warning-hover, #bf360c); }
    .btn-ready { 
        background: var(--btn-success); 
        color: var(--text-white); 
        border: none; 
        padding: 6px 12px; 
        border-radius: 20px; 
        cursor: pointer; 
        font-size: 11px; 
        transition: 0.3s;
    }
    .btn-ready:hover { background: var(--btn-success-hover); }
    .btn-print { 
        background: var(--btn-info, #2196f3); 
        color: var(--text-white); 
        border: none; 
        padding: 6px 12px; 
        border-radius: 20px; 
        cursor: pointer; 
        font-size: 11px; 
        transition: 0.3s;
    }
    .btn-print:hover { background: var(--btn-info-hover, #1976d2); }
    
    .badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; display: inline-block; font-weight: bold; }
    .badge-pending { background: var(--badge-pending-bg); color: var(--badge-pending-text); }
    .badge-paid { background: var(--badge-pending-bg); color: var(--badge-pending-text); }
    .badge-confirmed { background: var(--badge-success-bg); color: var(--badge-success-text); }
    .badge-ready { background: var(--badge-info-bg); color: var(--badge-info-text); }
    .badge-shipped { background: var(--badge-info-bg); color: var(--badge-info-text); }
    .badge-delivered { background: var(--badge-success-bg); color: var(--badge-success-text); }
    .badge-cancelled { background: var(--badge-danger-bg); color: var(--badge-danger-text); }
    
    .address-cell { max-width: 200px; word-break: break-word; }
    .success-msg { 
        background: var(--success-bg); 
        color: var(--success-text); 
        padding: 12px; 
        border-radius: 10px; 
        margin-bottom: 20px; 
        text-align: center; 
    }
    .rating-stars-small { color: #ffc107; font-size: 12px; }
    
    .tabs { 
        display: flex; 
        gap: 10px; 
        margin-bottom: 20px; 
        flex-wrap: wrap; 
        border-bottom: 2px solid var(--text-heading); 
        padding-bottom: 10px; 
    }
    .tab-btn { 
        padding: 8px 16px; 
        background: var(--bg-header-card); 
        border: none; 
        border-radius: 25px; 
        cursor: pointer; 
        color: var(--text-primary);
        transition: 0.3s;
    }
    .tab-btn.active { 
        background: var(--btn-primary); 
        color: var(--text-white); 
    }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    
    small { color: var(--text-muted); }
    
    @media (max-width: 768px) {
        .orders-table th, .orders-table td { padding: 8px 6px; font-size: 11px; }
        .btn-payment, .btn-ready, .btn-print, .btn-cancel { padding: 4px 8px; font-size: 10px; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }
</style>

<script>
    function saveScrollPosition() {
        let scrollPos = window.scrollY;
        document.querySelectorAll('input[name="scroll_pos"]').forEach(function(input) {
            input.value = scrollPos;
        });
    }
    
    function restoreScrollPosition() {
        let scrollPos = <?php echo $scroll_pos; ?>;
        if (scrollPos > 0) {
            window.scrollTo(0, scrollPos);
        }
    }
    
    function showTab(tabName) {
        saveScrollPosition();
        document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.getElementById('tab-' + tabName).classList.add('active');
        if (event && event.target) {
            event.target.classList.add('active');
        }
        let url = new URL(window.location.href);
        url.searchParams.set('tab', tabName);
        url.searchParams.set('scroll_pos', window.scrollY);
        window.history.pushState({}, '', url);
    }
</script>

<div class="dashboard-container" onload="restoreScrollPosition()">
    <!-- أزرار سريعة -->
    <div class="quick-actions">
        <a href="add-product.php" class="btn">➕ إضافة منتج جديد</a>
        <a href="my-products.php" class="btn">📦 إدارة منتجاتي</a>
    </div>
    
    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-number">⭐ <?php echo number_format($seller_rating, 1); ?>/5</div>
            <div class="stat-label">تقييمك (<?php echo $seller_rating_count; ?> تقييم)</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo number_format($total_sales_amount, 2); ?> دج</div>
            <div class="stat-label">💰 إجمالي المبيعات</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $completed_orders; ?></div>
            <div class="stat-label">📋 عدد الطلبات</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo number_format($total_commission, 2); ?> دج</div>
            <div class="stat-label">💸 عمولة الموقع <?php if($avg_commission_rate > 0): ?>(معدل <?php echo $avg_commission_rate; ?>%)<?php endif; ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-number" style="color: var(--success-text);"><?php echo number_format($net_earnings, 2); ?> دج</div>
            <div class="stat-label">💰 صافي أرباحك</div>
        </div>
    </div>
    
    <?php if(isset($status_message)): ?>
        <div class="success-msg"><?php echo $status_message; ?></div>
    <?php endif; ?>
    
    <div class="tabs">
        <button class="tab-btn <?php echo $active_tab == 'active' ? 'active' : ''; ?>" onclick="showTab('active')">📋 الطلبات النشطة (<?php echo count($incoming_orders); ?>)</button>
        <button class="tab-btn <?php echo $active_tab == 'cancelled' ? 'active' : ''; ?>" onclick="showTab('cancelled')">❌ الطلبات الملغاة (<?php echo count($cancelled_orders); ?>)</button>
    </div>
    
    <!-- تبويب الطلبات النشطة -->
    <div id="tab-active" class="tab-content <?php echo $active_tab == 'active' ? 'active' : ''; ?>">
        <div class="card">
            <div class="card-header">📋 الطلبات النشطة</div>
            <div class="card-body">
                <?php if(count($incoming_orders) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table class="orders-table">
                            <thead>
                                <tr>
                                    <th>رقم الطلب</th>
                                    <th>المنتج</th>
                                    <th>المشتري</th>
                                    <th>الكمية</th>
                                    <th>المبلغ</th>
                                    <th>طريقة الدفع</th>
                                    <th>المبلغ المستحق</th>
                                    <th>العنوان / نقطة التجميع</th>
                                    <th>الحالة</th>
                                    <th>إجراء</th>
                                    <th>فاتورة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($incoming_orders as $order): 
                                    $status_text = getOrderStatusArabic($order['order_status'], $order['delivery_method']);
                                    $payment_type_text = getPaymentTypeText($order['payment_type'] ?? 'advance');
                                    $collect_amount = getCollectAmount($order);
                                    $can_cancel = canSellerCancelOrder($order['order_status']);
                                    
                                    $badge_class = '';
                                    switch($order['order_status']) {
                                        case 'pending': $badge_class = 'badge-pending'; break;
                                        case 'advance_paid_by_buyer': $badge_class = 'badge-paid'; break;
                                        case 'advance_confirmed_by_seller': $badge_class = 'badge-confirmed'; break;
                                        case 'ready_for_shipping': $badge_class = 'badge-ready'; break;
                                        case 'picked_by_shipping': $badge_class = 'badge-shipped'; break;
                                        case 'in_transit': $badge_class = 'badge-shipped'; break;
                                        case 'in_transit_to_pickup': $badge_class = 'badge-shipped'; break;
                                        case 'arrived_at_pickup': $badge_class = 'badge-shipped'; break;
                                        case 'delivered_to_buyer': $badge_class = 'badge-delivered'; break;
                                        default: $badge_class = 'badge-pending';
                                    }
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong><br><small><?php echo date('Y-m-d', strtotime($order['created_at'])); ?></small></td>
                                    <td><?php echo htmlspecialchars($order['product_name']); ?><br><small><?php echo number_format($order['product_price'], 2); ?> دج/قطعة</small></td>
                                    <td><?php echo htmlspecialchars($order['buyer_name']); ?><br><small>📞 <?php echo htmlspecialchars($order['buyer_phone']); ?></small></td>
                                    <td><?php echo $order['quantity']; ?> قطعة</td>
                                    <td><strong><?php echo number_format($order['order_total'], 2); ?> دج</strong></td>
                                    <td><?php echo $payment_type_text; ?></td>
                                    <td><strong><?php echo number_format($collect_amount, 2); ?> دج</strong></td>
                                    <td class="address-cell">
                                        <?php 
                                        if($order['delivery_method'] == 'pickup' && $order['pickup_point_name']) {
                                            echo '📍 <strong>' . htmlspecialchars($order['pickup_point_name']) . '</strong><br><small>' . nl2br(htmlspecialchars(substr($order['pickup_point_address'], 0, 60))) . '</small>';
                                        } else {
                                            echo nl2br(htmlspecialchars(substr($order['delivery_address'], 0, 60)));
                                        }
                                        ?>
                                    </td>
                                    <td><span class="badge <?php echo $badge_class; ?>"><?php echo $status_text; ?></span></td>
                                    <td>
                                        <?php if($order['order_status'] == 'pending' || $order['order_status'] == 'advance_paid_by_buyer'): ?>
                                            <form method="POST" onsubmit="saveScrollPosition()" style="display:inline-block;">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="scroll_pos" value="0">
                                                <button type="submit" name="confirm_payment" class="btn-payment" onclick="return confirm('هل تريد تأكيد هذا الطلب؟')">💰 تأكيد الطلب</button>
                                            </form>
                                        <?php elseif($order['order_status'] == 'advance_confirmed_by_seller'): ?>
                                            <form method="POST" onsubmit="saveScrollPosition()" style="display:inline-block;">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="scroll_pos" value="0">
                                                <button type="submit" name="confirm_ready" class="btn-ready" onclick="return confirm('هل الطلب جاهز للشحن؟')">✅ تجهيز للشحن</button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if($can_cancel): ?>
                                            <form method="POST" onsubmit="saveScrollPosition()" style="display:inline-block;">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="scroll_pos" value="0">
                                                <button type="submit" name="cancel_by_seller" class="btn-cancel" onclick="return confirm('هل تريد إلغاء الطلب؟ سيتم إعادة الكمية إلى المخزون.')">❌ إلغاء</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button onclick="printInvoice(
                                            '<?php echo $order['order_number']; ?>',
                                            '<?php echo addslashes($order['product_name']); ?>',
                                            <?php echo $order['quantity']; ?>,
                                            <?php echo $order['order_total']; ?>,
                                            '<?php echo addslashes($order['delivery_address']); ?>',
                                            '<?php echo addslashes($order['buyer_name']); ?>',
                                            '<?php echo $order['buyer_phone']; ?>',
                                            <?php echo $order['advance_amount']; ?>,
                                            '<?php echo addslashes($order['product_size'] ?? ''); ?>',
                                            '<?php echo addslashes($order['product_color'] ?? ''); ?>',
                                            <?php echo $order['delivery_fee'] ?? 0; ?>,
                                            '<?php echo $order['delivery_method']; ?>',
                                            '<?php echo addslashes($order['pickup_point_name'] ?? ''); ?>',
                                            '<?php echo addslashes($order['pickup_point_address'] ?? ''); ?>'
                                        )" class="btn-print">
                                            🖨️ طباعة
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align:center; padding:30px; color: var(--text-muted);">📭 لا توجد طلبات نشطة</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- تبويب الطلبات الملغاة -->
    <div id="tab-cancelled" class="tab-content <?php echo $active_tab == 'cancelled' ? 'active' : ''; ?>">
        <div class="card">
            <div class="card-header">❌ الطلبات الملغاة</div>
            <div class="card-body">
                <?php if(count($cancelled_orders) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table class="orders-table">
                            <thead>
                                <tr>
                                    <th>رقم الطلب</th>
                                    <th>المنتج</th>
                                    <th>المشتري</th>
                                    <th>الكمية</th>
                                    <th>المبلغ</th>
                                    <th>طريقة الدفع</th>
                                    <th>العنوان / نقطة التجميع</th>
                                    <th>الحالة</th>
                                    <th>فاتورة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($cancelled_orders as $order): 
                                    $status_text = getOrderStatusArabic($order['order_status'], $order['delivery_method']);
                                    $payment_type_text = getPaymentTypeText($order['payment_type'] ?? 'advance');
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong><br><small><?php echo date('Y-m-d', strtotime($order['created_at'])); ?></small></td>
                                    <td><?php echo htmlspecialchars($order['product_name']); ?><br><small><?php echo number_format($order['product_price'], 2); ?> دج/قطعة</small></td>
                                    <td><?php echo htmlspecialchars($order['buyer_name']); ?><br><small>📞 <?php echo htmlspecialchars($order['buyer_phone']); ?></small></td>
                                    <td><?php echo $order['quantity']; ?> قطعة</td>
                                    <td><strong><?php echo number_format($order['order_total'], 2); ?> دج</strong></td>
                                    <td><?php echo $payment_type_text; ?></td>
                                    <td class="address-cell">
                                        <?php 
                                        if($order['delivery_method'] == 'pickup' && $order['pickup_point_name']) {
                                            echo '📍 <strong>' . htmlspecialchars($order['pickup_point_name']) . '</strong><br><small>' . nl2br(htmlspecialchars(substr($order['pickup_point_address'], 0, 60))) . '</small>';
                                        } else {
                                            echo nl2br(htmlspecialchars(substr($order['delivery_address'], 0, 60)));
                                        }
                                        ?>
                                    </td>
                                    <td><span class="badge badge-cancelled">❌ ملغي</span></td>
                                    <td>
                                        <button onclick="printInvoice(
                                            '<?php echo $order['order_number']; ?>',
                                            '<?php echo addslashes($order['product_name']); ?>',
                                            <?php echo $order['quantity']; ?>,
                                            <?php echo $order['order_total']; ?>,
                                            '<?php echo addslashes($order['delivery_address']); ?>',
                                            '<?php echo addslashes($order['buyer_name']); ?>',
                                            '<?php echo $order['buyer_phone']; ?>',
                                            <?php echo $order['advance_amount']; ?>,
                                            '<?php echo addslashes($order['product_size'] ?? ''); ?>',
                                            '<?php echo addslashes($order['product_color'] ?? ''); ?>',
                                            <?php echo $order['delivery_fee'] ?? 0; ?>,
                                            '<?php echo $order['delivery_method']; ?>',
                                            '<?php echo addslashes($order['pickup_point_name'] ?? ''); ?>',
                                            '<?php echo addslashes($order['pickup_point_address'] ?? ''); ?>'
                                        )" class="btn-print">
                                            🖨️ طباعة
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align:center; padding:30px; color: var(--text-muted);">📭 لا توجد طلبات ملغاة</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">📦 منتجاتي (<?php echo $total_products; ?>)</div>
        <div class="card-body">
            <?php if(count($products) > 0): ?>
                <div style="overflow-x: auto;">
                    <table class="products-table">
                        <thead>
                            <tr>
                                <th>المنتج</th>
                                <th>السعر</th>
                                <th>الكمية</th>
                                <th>التقييم</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo number_format($product['price'], 2); ?> دج</td>
                                <td><?php echo $product['stock']; ?> قطعة</td>
                                <td>
                                    <?php if($product['avg_rating'] > 0): ?>
                                        <div class="rating-stars-small"><?php echo str_repeat('★', round($product['avg_rating'])); ?><?php echo str_repeat('☆', 5 - round($product['avg_rating'])); ?></div>
                                        <small>(<?php echo $product['ratings_count']; ?> تقييم)</small>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $product['status'] == 'available' ? '🟢 متوفر' : '🔴 تم البيع'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="text-align:center; padding:30px; color: var(--text-muted);">📭 لا توجد منتجات مضافة بعد</p>
                <p style="text-align:center;"><a href="add-product.php" class="btn">➕ أضف منتجك الأول</a></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function printInvoice(orderNumber, productName, quantity, totalPrice, address, buyerName, buyerPhone, advanceAmount, productSize, productColor, deliveryFee, deliveryMethod, pickupPointName, pickupPointAddress) {
    var printWindow = window.open('', '_blank');
    var doc = printWindow.document;
    
    var productsTotal = parseFloat(totalPrice);
    var deliveryFeeValue = parseFloat(deliveryFee) || 0;
    var totalWithDelivery = productsTotal + deliveryFeeValue;
    var advanceAmountCalc = parseFloat(advanceAmount);
    var remainingWithDelivery = totalWithDelivery - advanceAmountCalc;
    
    doc.write('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"><title>فاتورة الطلب - سوق الرمال</title><style>');
    doc.write('body{font-family:Tahoma,Arial,sans-serif;background:#f0e0c0;display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px;margin:0}');
    doc.write('.invoice-card{max-width:400px;width:100%;background:white;border-radius:20px;box-shadow:0 10px 30px rgba(0,0,0,0.15);overflow:hidden;direction:rtl}');
    doc.write('.invoice-header{background:linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url("uploads/header-bg.jpg");background-size:cover;background-position:center;color:white;text-align:center;padding:20px}');
    doc.write('.invoice-header h1{font-size:26px;margin-bottom:5px}');
    doc.write('.invoice-body{padding:20px}');
    doc.write('.invoice-title{text-align:center;font-size:18px;font-weight:bold;color:#b8860b;border-bottom:2px dashed #f0e0c0;padding-bottom:10px;margin-bottom:20px}');
    doc.write('.invoice-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f0e0c0}');
    doc.write('.invoice-label{font-weight:bold;color:#555}');
    doc.write('.invoice-value{color:#333;font-weight:bold;text-align:left}');
    doc.write('.invoice-amount{font-size:18px;color:#b8860b}');
    doc.write('.advance-box{background:#e8f5e9;padding:10px;border-radius:10px;margin-top:10px}');
    doc.write('.invoice-footer{background:#f8e1b0;padding:15px;text-align:center;margin-top:15px}');
    doc.write('.print-buttons{display:flex;justify-content:center;gap:15px;padding:15px;background:white;border-top:1px solid #eee}');
    doc.write('.print-buttons button{padding:8px 20px;border:none;border-radius:25px;cursor:pointer;font-size:14px}');
    doc.write('.btn-print-action{background:#b8860b;color:white}');
    doc.write('.btn-close-action{background:#999;color:white}');
    doc.write('@media print{body{background:white;padding:0;margin:0}.print-buttons{display:none}.invoice-card{box-shadow:none;margin:0;border-radius:0}}');
    doc.write('</style></head><body>');
    doc.write('<div class="invoice-card"><div class="invoice-header"><h1> سوق الرمال الذهبية</h1><p>سوق نسائي متكامل - ولاية الوادي</p></div>');
    doc.write('<div class="invoice-body"><div class="invoice-title">📄 فاتورة تسليم الطلب</div>');
    doc.write('<div class="invoice-row"><span class="invoice-label">👤 اسم المشتري:</span><span class="invoice-value">'+buyerName+'</span></div>');
    doc.write('<div class="invoice-row"><span class="invoice-label">📞 رقم الهاتف:</span><span class="invoice-value">'+buyerPhone+'</span></div>');
    doc.write('<div class="invoice-row"><span class="invoice-label">📝 رقم الطلب:</span><span class="invoice-value">'+orderNumber+'</span></div>');
    doc.write('<div class="invoice-row"><span class="invoice-label">🛍️ المنتج:</span><span class="invoice-value">'+productName+'</span></div>');
    doc.write('<div class="invoice-row"><span class="invoice-label">📦 الكمية:</span><span class="invoice-value">'+quantity+'</span></div>');
    doc.write('<div class="invoice-row"><span class="invoice-label">📏 المقاس:</span><span class="invoice-value">' + (productSize || '—') + '</span></div>');
    doc.write('<div class="invoice-row"><span class="invoice-label">🎨 اللون:</span><span class="invoice-value">' + (productColor || '—') + '</span></div>');
    doc.write('<div class="invoice-row"><span class="invoice-label">💰 إجمالي المنتجات:</span><span class="invoice-value">'+productsTotal.toFixed(2)+' دج</span></div>');
    doc.write('<div class="invoice-row"><span class="invoice-label">🚚 سعر التوصيل:</span><span class="invoice-value">'+deliveryFeeValue.toFixed(2)+' دج</span></div>');
    doc.write('<div class="invoice-row"><span class="invoice-label invoice-amount">💵 إجمالي الطلب:</span><span class="invoice-value invoice-amount">'+totalWithDelivery.toFixed(2)+' دج</span></div>');
    doc.write('<div class="advance-box"><div class="invoice-row"><span class="invoice-label">💳 الدفعة المقدمة (25%):</span><span class="invoice-value">'+advanceAmountCalc.toFixed(2)+' دج</span></div>');
    doc.write('<div class="invoice-row"><span class="invoice-label">💵 المتبقي عند الاستلام:</span><span class="invoice-value">'+remainingWithDelivery.toFixed(2)+' دج</span></div></div>');
    
    if(deliveryMethod === 'pickup' && pickupPointName) {
        doc.write('<div class="invoice-row"><span class="invoice-label">📍 نقطة التجميع:</span><span class="invoice-value">' + pickupPointName + '<br>' + (pickupPointAddress || '') + '</span></div>');
    } else {
        doc.write('<div class="invoice-row"><span class="invoice-label">📍 عنوان التوصيل:</span><span class="invoice-value">' + (address ? address.replace(/\n/g,'<br>') : '—') + '</span></div>');
    }
    
    doc.write('</div><div class="invoice-footer"><p>🔔 <strong>يرجى تقديم هذه الفاتورة عند استلام الطلب</strong></p><p>شكراً لتسوقك معنا 💐</p><p class="note">سوق الرمال - سوق آمن للنساء</p></div>');
    doc.write('<div class="print-buttons"><button onclick="window.print()" class="btn-print-action">🖨️ طباعة</button><button onclick="window.close()" class="btn-close-action">✖️ إغلاق</button></div></div></body></html>');
    doc.close();
}

// استعادة مكان التمرير عند التحميل
window.onload = function() {
    restoreScrollPosition();
};
</script>

<?php
include_once 'footer.php';
include_once 'bottom_nav.php';
?>
</body>
</html>