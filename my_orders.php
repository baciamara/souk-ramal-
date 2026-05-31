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

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];

// دالة ترجمة طريقة التوصيل
function getDeliveryMethodText($method) {
    if ($method == 'home') return '🏠 توصيل للمنزل';
    elseif ($method == 'pickup') return '📍 نقطة تجميع';
    elseif ($method == 'shipping') return '🚚 شحن';
    elseif ($method == 'hand_delivery') return '🤝 توصيل يدوي';
    return $method ?: 'غير محدد';
}

// دالة ترجمة طريقة الدفع
function getPaymentTypeText($type) {
    if ($type == 'advance') return '💳 عربون (25% مقدم)';
    elseif ($type == 'cash_on_delivery') return '💵 دفع عند الاستلام (100%)';
    return $type ?: 'غير محدد';
}

// ========== دالة ترجمة حالة الطلب (معدلة) ==========
function getOrderStatusText($status, $payment_type = null) {
    if ($payment_type == 'cash_on_delivery' && $status == 'pending') return '⏳ في انتظار تأكيد البائع';
    if ($payment_type == 'cash_on_delivery' && $status == 'advance_confirmed_by_seller') return '✅ تم تأكيد الطلب من قبل البائع';
    
    $statuses = [
        'pending' => '⏳ في انتظار دفع العربون',
        'advance_paid_by_buyer' => '⏳ تم تأكيد الدفع، في انتظار تأكيد البائع',
        'advance_confirmed_by_seller' => '✅ تم تأكيد استلام العربون، جاري تجهيز الطلب',
        'ready_for_shipping' => '✅ الطلب جاهز للشحن',
        'picked_by_shipping' => '📦 تم استلام الطلب من شركة الشحن',
        'in_transit' => '🚚 الطلب قيد التوصيل للمنزل',
        'in_transit_to_pickup' => '🚚 الطلب قيد التوصيل إلى نقطة التجميع',
        'arrived_at_pickup' => '📍 وصل الطلب إلى نقطة التجميع',
        'delivered_to_buyer' => '🎉 تم تسليم الطلب',
        'cancelled' => '❌ ملغي'
    ];
    return $statuses[$status] ?? $status;
}

// دالة التحقق من إمكانية إلغاء الطلب
function canCancelOrder($order) {
    if ($order['order_status'] == 'cancelled' || $order['order_status'] == 'delivered_to_buyer') return false;
    if ($order['payment_type'] == 'advance') {
        return ($order['order_status'] != 'advance_confirmed_by_seller' && 
                $order['order_status'] != 'ready_for_shipping' &&
                $order['order_status'] != 'picked_by_shipping' &&
                $order['order_status'] != 'in_transit' &&
                $order['order_status'] != 'in_transit_to_pickup' &&
                $order['order_status'] != 'arrived_at_pickup');
    } else {
        if (empty($order['cancel_until'])) return true;
        return strtotime($order['cancel_until']) > time();
    }
}

// دالة حساب الوقت المتبقي للإلغاء
function getRemainingCancelTime($order) {
    if (empty($order['cancel_until']) || $order['payment_type'] != 'cash_on_delivery') return null;
    $remaining = strtotime($order['cancel_until']) - time();
    if ($remaining <= 0) return null;
    $hours = floor($remaining / 3600);
    $minutes = floor(($remaining % 3600) / 60);
    if ($hours > 0) return "{$hours} ساعة و {$minutes} دقيقة";
    return "{$minutes} دقيقة";
}

// حفظ مكان التمرير
$scroll_pos = isset($_GET['scroll_pos']) ? intval($_GET['scroll_pos']) : 0;

// معالجة إلغاء الطلب من قبل المشتري
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_by_buyer'])) {
    $order_id = intval($_POST['order_id']);
    $scroll_pos = intval($_POST['scroll_pos'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT order_status, payment_type, cancel_until, product_id, quantity FROM orders WHERE id = ? AND buyer_id = ?");
        $stmt->execute([$order_id, $buyer_id]);
        $order = $stmt->fetch();
        if (!$order) {
            $_SESSION['message'] = "⚠️ الطلب غير موجود";
        } else {
            $can_cancel = false;
            if ($order['payment_type'] == 'advance') {
                if ($order['order_status'] == 'pending' || $order['order_status'] == 'advance_paid_by_buyer') $can_cancel = true;
            } else {
                if (!empty($order['cancel_until']) && strtotime($order['cancel_until']) > time()) $can_cancel = true;
                elseif (empty($order['cancel_until'])) $can_cancel = true;
            }
            if ($can_cancel) {
                $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")->execute([$order['quantity'], $order['product_id']]);
                $pdo->prepare("UPDATE orders SET order_status = 'cancelled' WHERE id = ?")->execute([$order_id]);
                $_SESSION['message'] = "✅ تم إلغاء الطلب بنجاح";
            } else {
                $_SESSION['message'] = "⚠️ لا يمكن إلغاء الطلب في هذه المرحلة";
            }
        }
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ خطأ: " . $e->getMessage();
    }
    header("Location: my_orders.php?scroll_pos=" . $scroll_pos);
    exit();
}

$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);

// جلب طلبات المشتري
try {
    $stmt = $pdo->prepare("
        SELECT o.*, oi.product_name, oi.product_price, oi.quantity, oi.total_price as order_total,
               u.full_name as seller_name, o.delivery_fee, pp.point_name as pickup_point_name, pp.address as pickup_point_address
        FROM orders o 
        JOIN order_items oi ON o.id = oi.order_id 
        JOIN users u ON o.seller_id = u.id 
        LEFT JOIN pickup_points pp ON o.pickup_point_id = pp.id
        WHERE o.buyer_id = ? 
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$buyer_id]);
    $orders = $stmt->fetchAll();
} catch(PDOException $e) {
    $orders = [];
}

function hasRating($pdo, $order_id) {
    $stmt = $pdo->prepare("SELECT id FROM ratings WHERE order_id = ?");
    $stmt->execute([$order_id]);
    return $stmt->fetch() !== false;
}

function hasComplaint($pdo, $order_id) {
    $stmt = $pdo->prepare("SELECT id FROM complaints WHERE order_id = ?");
    $stmt->execute([$order_id]);
    return $stmt->fetch() !== false;
}

function getComplaintStatus($pdo, $order_id) {
    $stmt = $pdo->prepare("SELECT status FROM complaints WHERE order_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$order_id]);
    $complaint = $stmt->fetch();
    if ($complaint) {
        switch($complaint['status']) {
            case 'pending': return '⏳ قيد الانتظار';
            case 'reviewing': return '🔍 قيد المراجعة';
            case 'resolved': return '✅ تم الحل';
            case 'rejected': return '❌ مرفوض';
            default: return $complaint['status'];
        }
    }
    return null;
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلباتي - سوق الرمال</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Tahoma', Arial, sans-serif; background: var(--bg-body); color: var(--text-primary); }
        header { background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('uploads/header-bg.jpg'); background-size: cover; background-position: center; color: white; padding: 50px 20px; text-align: center; }
        header h1 { font-size: 34px; margin-bottom: 10px; }
        header p { font-size: 16px; }
        nav { background: var(--bg-nav); box-shadow: var(--shadow-nav); padding: 15px 30px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
        .logo { font-size: 24px; font-weight: bold; color: var(--text-heading); }
        .nav-links { display: flex; flex-wrap: wrap; gap: 5px; align-items: center; }
        .nav-links a { color: var(--text-primary); text-decoration: none; padding: 8px 15px; border-radius: 25px; }
        .nav-links a:hover { background: var(--border-light); }
        .btn-nav { background: var(--btn-primary); color: white !important; }
        .user-name { background: var(--user-name-bg); color: var(--user-name-text) !important; font-weight: bold; }
        .logout-btn { background: var(--logout-bg); color: var(--logout-text) !important; }
        .btn-rate { background: #2196f3; color: white; padding: 5px 12px; border-radius: 20px; text-decoration: none; font-size: 12px; display: inline-block; margin-top: 10px; }
        .btn-rated { background: #2e7d32; color: white; padding: 5px 12px; border-radius: 20px; font-size: 12px; display: inline-block; margin-top: 10px; }
        .btn-complaint { background: #e65100; color: white; padding: 5px 12px; border-radius: 20px; text-decoration: none; font-size: 12px; display: inline-block; margin-top: 10px; margin-right: 10px; }
        .btn-complaint-sent { background: #999; color: white; padding: 5px 12px; border-radius: 20px; font-size: 12px; display: inline-block; margin-top: 10px; margin-right: 10px; cursor: default; }
        .btn-cancel { background: #c62828; color: white; padding: 5px 12px; border-radius: 20px; border: none; cursor: pointer; font-size: 12px; display: inline-block; margin-top: 10px; margin-right: 10px; }
        .complaint-status { font-size: 11px; display: block; margin-top: 5px; color: var(--text-secondary); }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .card { background: var(--bg-card); border-radius: 15px; box-shadow: var(--shadow-md); margin-bottom: 30px; overflow: hidden; }
        .card-header { background: var(--bg-header-card); padding: 15px 20px; font-size: 20px; font-weight: bold; color: var(--text-heading); }
        .card-body { padding: 20px; }
        .order-item { border: 1px solid var(--border-color); border-radius: 15px; margin-bottom: 20px; overflow: hidden; }
        .order-header { background: var(--bg-card-alt); padding: 15px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
        .order-number { font-weight: bold; color: var(--text-heading); }
        .order-status { padding: 4px 12px; border-radius: 20px; font-size: 12px; display: inline-block; }
        .status-pending { background: var(--badge-pending-bg); color: var(--badge-pending-text); }
        .status-advance_paid_by_buyer { background: var(--badge-pending-bg); color: var(--badge-pending-text); }
        .status-advance_confirmed_by_seller { background: var(--badge-success-bg); color: var(--badge-success-text); }
        .status-ready_for_shipping { background: var(--badge-info-bg); color: var(--badge-info-text); }
        .status-picked_by_shipping { background: var(--badge-info-bg); color: var(--badge-info-text); }
        .status-in_transit { background: var(--badge-warning-bg); color: var(--badge-warning-text); }
        .status-in_transit_to_pickup { background: var(--badge-warning-bg); color: var(--badge-warning-text); }
        .status-arrived_at_pickup { background: var(--badge-info-bg); color: var(--badge-info-text); }
        .status-delivered_to_buyer { background: var(--badge-success-bg); color: var(--badge-success-text); }
        .status-cancelled { background: var(--badge-danger-bg); color: var(--badge-danger-text); }
        .order-body { padding: 15px; display: flex; gap: 20px; flex-wrap: wrap; }
        .order-product-info { flex: 1; }
        .order-product-name { font-size: 18px; font-weight: bold; margin-bottom: 5px; color: var(--text-primary); }
        .order-product-price { color: var(--text-heading); font-weight: bold; }
        .order-details { width: 100%; margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border-color); display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 10px; font-size: 14px; }
        .order-details span { color: var(--text-secondary); }
        .order-details div { color: var(--text-primary); }
        .advance-box { background: var(--bg-card-alt); padding: 10px; border-radius: 10px; margin-top: 10px; font-size: 13px; color: var(--text-primary); }
        .action-buttons { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; align-items: center; }
        .message { background: var(--success-bg); color: var(--success-text); padding: 12px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        .error-msg { background: var(--error-bg); color: var(--error-text); padding: 12px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        footer { background: var(--bg-footer); color: white; text-align: center; padding: 20px; margin-top: 40px; }
        .info-text { color: var(--text-secondary); }
        @media (max-width: 768px) { nav { display: none !important; } header { padding: 30px 20px; } header h1 { font-size: 28px; } }
    </style>
    <script>
        function restoreScrollPosition() { let scrollPos = <?php echo $scroll_pos; ?>; if (scrollPos > 0) window.scrollTo(0, scrollPos); }
        function saveScrollPosition() { let scrollPos = window.scrollY; let url = new URL(window.location.href); url.searchParams.set('scroll_pos', scrollPos); window.history.pushState({}, '', url); }
    </script>
</head>
<body onload="restoreScrollPosition()">
<?php include_once 'header.php'; ?>    
    
    <div class="container">
        <?php if(isset($message)): ?>
            <div class="<?php echo strpos($message, '✅') !== false ? 'message' : 'error-msg'; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">📋 طلباتي (<?php echo count($orders); ?>)</div>
            <div class="card-body">
                <?php if(count($orders) > 0): ?>
                    <?php foreach($orders as $order): ?>
                        <?php $can_cancel = canCancelOrder($order); $remaining_time = getRemainingCancelTime($order); ?>
                        <div class="order-item">
                            <div class="order-header">
                                <div>
                                    <span class="order-number">📝 <?php echo htmlspecialchars($order['order_number']); ?></span>
                                    <span style="margin-right:15px; color:var(--text-muted);">📅 <?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></span>
                                </div>
                                <div>
                                    <?php
                                    $status_text = getOrderStatusText($order['order_status'], $order['payment_type']);
                                    $status_class = 'status-pending';
                                    switch($order['order_status']) {
                                        case 'pending': $status_class = 'status-pending'; break;
                                        case 'advance_paid_by_buyer': $status_class = 'status-advance_paid_by_buyer'; break;
                                        case 'advance_confirmed_by_seller': $status_class = 'status-advance_confirmed_by_seller'; break;
                                        case 'ready_for_shipping': $status_class = 'status-ready_for_shipping'; break;
                                        case 'picked_by_shipping': $status_class = 'status-picked_by_shipping'; break;
                                        case 'in_transit': $status_class = 'status-in_transit'; break;
                                        case 'in_transit_to_pickup': $status_class = 'status-in_transit_to_pickup'; break;
                                        case 'arrived_at_pickup': $status_class = 'status-arrived_at_pickup'; break;
                                        case 'delivered_to_buyer': $status_class = 'status-delivered_to_buyer'; break;
                                        case 'cancelled': $status_class = 'status-cancelled'; break;
                                    }
                                    ?>
                                    <span class="order-status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                </div>
                            </div>
                            <div class="order-body">
                                <div class="order-product-info">
                                    <div class="order-product-name"><?php echo htmlspecialchars($order['product_name']); ?></div>
                                    <div class="order-product-price"><?php echo number_format($order['order_total'], 2); ?> دج</div>
                                    <div style="margin-top:5px; color:var(--text-secondary);">👤 بائع: <?php echo htmlspecialchars($order['seller_name']); ?></div>
                                    <div>📦 الكمية: <?php echo $order['quantity']; ?></div>
                                    <div>💰 سعر القطعة وقت الشراء: <?php echo number_format($order['product_price'], 2); ?> دج</div>
                                    
                                    <div class="action-buttons">
                                        <?php if($order['order_status'] == 'delivered_to_buyer' || $order['order_status'] == 'delivered'): ?>
                                            <?php if(hasRating($pdo, $order['id'])): ?>
                                                <div class="btn-rated">⭐ تم التقييم</div>
                                            <?php else: ?>
                                                <a href="rate_product.php?order_id=<?php echo $order['id']; ?>" class="btn-rate">⭐ تقييم المنتج</a>
                                            <?php endif; ?>
                                            <?php if(hasComplaint($pdo, $order['id'])): $complaint_status = getComplaintStatus($pdo, $order['id']); ?>
                                                <div style="background:linear-gradient(135deg, #b8860b, #d4a017); color:white; padding:5px 12px; border-radius:20px; font-size:12px; display:inline-block; margin-top:10px; box-shadow:0 2px 5px rgba(0,0,0,0.1);">📋 تم الإبلاغ<?php if($complaint_status): ?><span style="font-size:10px; background:rgba(255,255,255,0.2); padding:2px 6px; border-radius:15px; margin-right:5px;"><?php echo $complaint_status; ?></span><?php endif; ?></div>
                                            <?php else: ?>
                                                <a href="complaint.php?order_id=<?php echo $order['id']; ?>" style="background:linear-gradient(135deg, #e65100, #bf360c); color:white; padding:5px 12px; border-radius:20px; text-decoration:none; font-size:12px; display:inline-block; margin-top:10px; transition:all 0.3s; box-shadow:0 2px 5px rgba(0,0,0,0.1);" onmouseover="this.style.transform='scale(1.02)';" onmouseout="this.style.transform='scale(1)';" onclick="return confirm('هل تريد الإبلاغ عن مشكلة في هذا الطلب؟')">📋 الإبلاغ عن مشكلة</a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if($can_cancel && $order['order_status'] != 'cancelled'): ?>
                                            <form method="POST" style="display:inline;" onsubmit="saveScrollPosition()">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="scroll_pos" value="0">
                                                <button type="submit" name="cancel_by_buyer" class="btn-cancel" onclick="return confirm('هل تريد إلغاء الطلب؟ سيتم إعادة الكمية إلى المخزون.')">❌ إلغاء الطلب<?php if($remaining_time): ?> (متبقي <?php echo $remaining_time; ?>)<?php endif; ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="order-details">
                                    <div><span>💳 طريقة الدفع:</span> <?php echo getPaymentTypeText($order['payment_type'] ?? 'advance'); ?></div>
                                    <div><span>🚚 طريقة التوصيل:</span> <?php echo getDeliveryMethodText($order['delivery_method']); ?></div>
                                    <?php if(isset($order['delivery_fee']) && $order['delivery_fee'] > 0): ?>
                                        <div><span>🚚 سعر التوصيل:</span> <?php echo number_format($order['delivery_fee'], 2); ?> دج</div>
                                    <?php endif; ?>
                                    <?php if($order['delivery_method'] == 'pickup' && $order['pickup_point_name']): ?>
                                        <div><span>📍 نقطة التجميع:</span> <?php echo nl2br(htmlspecialchars($order['pickup_point_name'] . ' - ' . $order['pickup_point_address'])); ?></div>
                                    <?php elseif($order['delivery_method'] == 'home'): ?>
                                        <div><span>🏠 عنوان التوصيل:</span> <?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></div>
                                    <?php else: ?>
                                        <div><span>📍 عنوان التوصيل:</span> <?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></div>
                                    <?php endif; ?>
                                    <?php 
                                    $products_total = $order['order_total']; $delivery_fee = $order['delivery_fee'] ?? 0;
                                    $total_with_delivery = $products_total + $delivery_fee; $payment_type = $order['payment_type'] ?? 'advance';
                                    if ($payment_type == 'advance'): $advance_amount = $order['advance_amount']; $remaining = $total_with_delivery - $advance_amount; ?>
                                        <div class="advance-box">
                                            <div><span>💰 إجمالي المنتجات:</span> <?php echo number_format($products_total, 2); ?> دج</div>
                                            <?php if($delivery_fee > 0): ?><div><span>🚚 سعر التوصيل:</span> <?php echo number_format($delivery_fee, 2); ?> دج</div><div><span>💵 إجمالي الطلب:</span> <?php echo number_format($total_with_delivery, 2); ?> دج</div><?php endif; ?>
                                            <div><span>💰 الدفعة المقدمة (25%):</span> <?php echo number_format($advance_amount, 2); ?> دج</div>
                                            <div><span>💵 المتبقي عند الاستلام:</span> <?php echo number_format($remaining, 2); ?> دج</div>
                                        </div>
                                    <?php else: ?>
                                        <div class="advance-box" style="background:var(--success-bg); color:var(--success-text);">
                                            <div><span>💰 إجمالي المنتجات:</span> <?php echo number_format($products_total, 2); ?> دج</div>
                                            <?php if($delivery_fee > 0): ?><div><span>🚚 سعر التوصيل:</span> <?php echo number_format($delivery_fee, 2); ?> دج</div><div><span>💵 إجمالي الطلب:</span> <?php echo number_format($total_with_delivery, 2); ?> دج</div><?php endif; ?>
                                            <div><span>💵 المطلوب دفعه عند الاستلام:</span> <?php echo number_format($total_with_delivery, 2); ?> دج</div>
                                            <div class="info-text" style="font-size:11px; color:var(--success-text);">⚠️ سيتم دفع المبلغ كاملاً عند استلام الطلب</div>
                                            <?php if($remaining_time): ?><div class="info-text" style="font-size:11px; color:#e65100;">⏱️ يمكنك إلغاء الطلب خلال <?php echo $remaining_time; ?></div><?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if(!empty($order['buyer_notes'])): ?>
                                        <div><span>📝 ملاحظاتي:</span> <?php echo nl2br(htmlspecialchars($order['buyer_notes'])); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center; padding:40px; color:var(--text-muted);">
                        <p>📭 لا توجد طلبات سابقة</p>
                        <p style="margin-top:10px;"><a href="index.php" style="color:var(--text-link);">→ ابدئي التسوق الآن</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include_once 'footer.php'; ?>
    <?php include_once 'bottom_nav.php'; ?>
    
</body>
</html>