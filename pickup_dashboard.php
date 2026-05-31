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

// حذف جميع الإشعارات
if (isset($_GET['clear_notifications'])) {
    $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$_SESSION['user_id']]);
    header("Location: pickup_dashboard.php");
    exit();
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

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'pickup_point') {
    header("Location: login.php");
    exit();
}

$pickup_point_id = $_SESSION['pickup_point_id'] ?? 1;
$user_name = $_SESSION['user_name'];

// جلب اسم نقطة التجميع للاستخدام في الفاتورة
$pickup_point_name = '';
try {
    $stmt = $pdo->prepare("SELECT point_name FROM pickup_points WHERE id = ?");
    $stmt->execute([$pickup_point_id]);
    $pickup_point_name = $stmt->fetchColumn() ?? 'نقطة التجميع';
} catch(PDOException $e) {
    $pickup_point_name = 'نقطة التجميع';
}

// دالة حساب المبلغ المستحق لنقطة التجميع (مبلغ التوصيل فقط)
function getCollectAmount($order) {
    return $order['delivery_fee'] ?? 0;
}

// ========== دالة ترجمة حالة الطلب (موحدة) ==========
function getStatusText($status) {
    $map = [
        'in_transit_to_pickup' => '🚚 قيد التوصيل للنقطة',
        'arrived_at_pickup' => '📍 وصل إلى النقطة - في انتظار التسليم',
        'delivered_to_buyer' => '🎉 تم تسليم الطلب للمشتري',
        'pending' => '⏳ في انتظار الدفع',
        'advance_paid_by_buyer' => '💰 تم الإشعار بالدفع',
        'advance_confirmed_by_seller' => '✅ تم تأكيد الطلب',
        'ready_for_shipping' => '📦 جاهز للشحن',
        'picked_by_shipping' => '📦 استلمته شركة الشحن',
        'in_transit' => '🚚 قيد التوصيل للمنزل',
        'cancelled' => '❌ ملغي (فشل التسليم)'
    ];
    return $map[$status] ?? $status;
}

// دالة ترجمة الحالة للعرض في الفاتورة
function getInvoiceStatusText($status) {
    if ($status == 'delivered_to_buyer') {
        return '✅ تم التسليم بنجاح';
    } elseif ($status == 'cancelled') {
        return '❌ فشل التسليم';
    }
    return '';
}
// ========== نهاية الدالة ==========

// حفظ مكان التمرير
$scroll_pos = isset($_POST['scroll_pos']) ? intval($_POST['scroll_pos']) : (isset($_GET['scroll_pos']) ? intval($_GET['scroll_pos']) : 0);
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'pending';

// تأكيد وصول الطلب إلى نقطة التجميع
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_arrival'])) {
    $order_id = intval($_POST['order_id']);
    $scroll_pos = intval($_POST['scroll_pos'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT order_status FROM orders WHERE id = ? AND pickup_point_id = ?");
        $stmt->execute([$order_id, $pickup_point_id]);
        $current_status = $stmt->fetchColumn();
        
        if ($current_status == 'in_transit_to_pickup') {
            $pdo->prepare("UPDATE orders SET shipping_status = 'arrived_at_pickup', order_status = 'arrived_at_pickup' WHERE id = ? AND pickup_point_id = ?")
                ->execute([$order_id, $pickup_point_id]);
            $_SESSION['message'] = "✅ تم تأكيد وصول الطلب إلى نقطة التجميع";
        } else {
            $_SESSION['message'] = "⚠️ لا يمكن تأكيد الوصول في هذه المرحلة";
        }
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ خطأ: " . $e->getMessage();
    }
    header("Location: pickup_dashboard.php?scroll_pos=" . $scroll_pos . "&tab=" . $active_tab);
    exit();
}

// تأكيد تسليم الطلب للمشتري
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_delivery'])) {
    $order_id = intval($_POST['order_id']);
    $scroll_pos = intval($_POST['scroll_pos'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT order_status FROM orders WHERE id = ? AND pickup_point_id = ?");
        $stmt->execute([$order_id, $pickup_point_id]);
        $current_status = $stmt->fetchColumn();
        
        if ($current_status == 'arrived_at_pickup') {
            $pdo->prepare("UPDATE orders SET shipping_status = 'delivered', order_status = 'delivered_to_buyer' WHERE id = ? AND pickup_point_id = ?")
                ->execute([$order_id, $pickup_point_id]);
            $_SESSION['message'] = "✅ تم تأكيد تسليم الطلب للمشتري";
        } else {
            $_SESSION['message'] = "⚠️ لا يمكن تأكيد التسليم إلا بعد وصول الطلب للنقطة";
        }
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ خطأ: " . $e->getMessage();
    }
    header("Location: pickup_dashboard.php?scroll_pos=" . $scroll_pos . "&tab=" . $active_tab);
    exit();
}

// فشل تسليم الطلب من نقطة التجميع (إلغاء)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_delivery'])) {
    $order_id = intval($_POST['order_id']);
    $scroll_pos = intval($_POST['scroll_pos'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT order_status, product_id, quantity FROM orders WHERE id = ? AND pickup_point_id = ?");
        $stmt->execute([$order_id, $pickup_point_id]);
        $order_data = $stmt->fetch();
        
        if (!$order_data) {
            $_SESSION['message'] = "⚠️ الطلب غير موجود";
        } elseif ($order_data['order_status'] != 'arrived_at_pickup') {
            $_SESSION['message'] = "⚠️ لا يمكن إلغاء الطلب في هذه المرحلة";
        } else {
            // إعادة المخزون
            $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")->execute([$order_data['quantity'], $order_data['product_id']]);
            // إلغاء الطلب
            $pdo->prepare("UPDATE orders SET order_status = 'cancelled' WHERE id = ?")->execute([$order_id]);
            $_SESSION['message'] = "✅ تم إلغاء الطلب وإعادة الكمية إلى المخزون";
        }
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ خطأ: " . $e->getMessage();
    }
    header("Location: pickup_dashboard.php?scroll_pos=" . $scroll_pos . "&tab=" . $active_tab);
    exit();
}

// جلب جميع الطلبات الواردة إلى نقطة التجميع مع اسم البائع وشركة التوصيل
$orders = [];
$pending_orders = [];
$completed_orders = [];
$cancelled_orders = [];
$total_collect_amount = 0;
$total_pending_collect = 0;
$total_completed_collect = 0;
$total_cancelled_collect = 0;

try {
    $stmt = $pdo->prepare("
        SELECT o.*, 
               oi.product_name, 
               oi.quantity,
               oi.total_price as order_total,
               u.full_name as buyer_name,
               u.phone as buyer_phone,
               s.full_name as seller_name,
               s.phone as seller_phone,
               sc.name as shipping_company_name,
               sc.phone as shipping_company_phone
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN users u ON o.buyer_id = u.id
        JOIN users s ON o.seller_id = s.id
        LEFT JOIN shipping_companies sc ON o.shipping_company_id = sc.id
        WHERE o.pickup_point_id = ? 
        AND o.delivery_method = 'pickup'
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$pickup_point_id]);
    $orders = $stmt->fetchAll();
    
    foreach($orders as $order) {
        $collect = getCollectAmount($order);
        $total_collect_amount += $collect;
        
        if ($order['order_status'] == 'delivered_to_buyer') {
            $completed_orders[] = $order;
            $total_completed_collect += $collect;
        } elseif ($order['order_status'] == 'cancelled') {
            $cancelled_orders[] = $order;
            $total_cancelled_collect += $collect;
        } else {
            $pending_orders[] = $order;
            $total_pending_collect += $collect;
        }
    }
} catch(PDOException $e) {}

// إحصائيات
$net_collect_amount = $total_completed_collect;
$total_orders_count = count($orders);
$pending_count = count($pending_orders);
$completed_count = count($completed_orders);
$cancelled_count = count($cancelled_orders);

$stats = [
    'total_orders' => $total_orders_count,
    'pending' => $pending_count,
    'completed' => $completed_count,
    'cancelled' => $cancelled_count,
    'total_collect_amount' => $total_collect_amount,
    'pending_collect' => $total_pending_collect,
    'completed_collect' => $total_completed_collect,
    'cancelled_collect' => $total_cancelled_collect,
    'net_collect_amount' => $net_collect_amount
];

$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);

// تضمين الهيدر
include_once 'header.php';
?>

<style>
    .pickup-container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
    
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
    
    .data-table { width: 100%; border-collapse: collapse; min-width: 1000px; }
    .data-table th, .data-table td { 
        padding: 12px; 
        text-align: center; 
        border-bottom: 1px solid var(--border-color); 
        color: var(--text-primary);
    }
    .data-table th { 
        background: var(--btn-primary); 
        color: var(--text-white); 
    }
    .data-table tr:hover { background: var(--hover-row-bg); }
    
    .btn-confirm { 
        background: var(--btn-success); 
        color: var(--text-white); 
        border: none; 
        padding: 6px 12px; 
        border-radius: 20px; 
        cursor: pointer; 
        font-size: 12px; 
        transition: 0.3s;
    }
    .btn-confirm:hover { background: var(--btn-success-hover); }
    .btn-deliver { 
        background: var(--btn-info, #2196f3); 
        color: var(--text-white); 
        border: none; 
        padding: 6px 12px; 
        border-radius: 20px; 
        cursor: pointer; 
        font-size: 12px; 
        transition: 0.3s;
    }
    .btn-deliver:hover { background: var(--btn-info-hover, #1976d2); }
    .btn-cancel { 
        background: var(--btn-danger); 
        color: var(--text-white); 
        border: none; 
        padding: 6px 12px; 
        border-radius: 20px; 
        cursor: pointer; 
        font-size: 12px; 
        margin-right: 5px; 
        transition: 0.3s;
    }
    .btn-cancel:hover { background: var(--btn-danger-hover); }
    .btn-print { 
        background: var(--btn-info, #2196f3); 
        color: var(--text-white); 
        border: none; 
        padding: 6px 12px; 
        border-radius: 20px; 
        cursor: pointer; 
        font-size: 12px; 
        transition: 0.3s;
    }
    .btn-print:hover { background: var(--btn-info-hover, #1976d2); }
    
    .badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; display: inline-block; font-weight: bold; }
    .badge-transit { background: #fff8e1; color: #f57f17; }
    .badge-arrived { background: var(--badge-info-bg); color: var(--badge-info-text); }
    .badge-delivered { background: var(--badge-success-bg); color: var(--badge-success-text); }
    .badge-cancelled { background: var(--badge-danger-bg); color: var(--badge-danger-text); }
    
    .success { 
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
    
    small { color: var(--text-muted); }
    
    @media (max-width: 768px) { 
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .pickup-container { margin: 20px auto; }
        .data-table th, .data-table td { padding: 8px 6px; font-size: 11px; }
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
    
    function printDeliveryNote(orderNumber, productName, quantity, buyerName, buyerPhone, deliveryFee, pickupPointName, sellerName, shippingCompanyName, orderDate, deliveryStatus) {
        var printWindow = window.open('', '_blank');
        var doc = printWindow.document;
        
        var statusColor = deliveryStatus === 'delivered' ? '#2e7d32' : '#c62828';
        var statusBg = deliveryStatus === 'delivered' ? '#e8f5e9' : '#ffebee';
        var statusText = deliveryStatus === 'delivered' ? '✅ تم التسليم بنجاح' : '❌ فشل التسليم';
        
        doc.write('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"><title>فاتورة استلام - سوق الرمال</title><style>');
        doc.write('body{font-family:Tahoma,Arial,sans-serif;background:#f0e0c0;display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px;margin:0}');
        doc.write('.invoice-card{max-width:420px;width:100%;background:white;border-radius:20px;box-shadow:0 10px 30px rgba(0,0,0,0.15);overflow:hidden;direction:rtl}');
        doc.write('.invoice-header{background:linear-gradient(135deg, #b8860b, #d4a017);color:white;text-align:center;padding:20px}');
        doc.write('.invoice-header h1{font-size:22px;margin-bottom:3px}');
        doc.write('.invoice-header p{font-size:12px;opacity:0.9}');
        doc.write('.invoice-body{padding:20px}');
        doc.write('.invoice-title{text-align:center;font-size:16px;font-weight:bold;color:#b8860b;border-bottom:2px dashed #f0e0c0;padding-bottom:10px;margin-bottom:20px}');
        doc.write('.invoice-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f0e0c0}');
        doc.write('.invoice-label{font-weight:bold;color:#555;font-size:11px}');
        doc.write('.invoice-value{color:#333;font-weight:bold;text-align:left;font-size:11px}');
        doc.write('.total-row{background:#e8f5e9;margin-top:15px;padding:10px;border-radius:10px}');
        doc.write('.total-row .invoice-value{font-size:16px;color:#2e7d32}');
        doc.write('.status-row{background:' + statusBg + ';margin-top:10px;padding:10px;border-radius:10px;text-align:center}');
        doc.write('.status-row .invoice-value{font-size:15px;color:' + statusColor + ';font-weight:bold}');
        doc.write('.invoice-footer{background:#f8e1b0;padding:15px;text-align:center;margin-top:15px;font-size:11px}');
        doc.write('.print-buttons{display:flex;justify-content:center;gap:15px;padding:15px;background:white;border-top:1px solid #eee}');
        doc.write('.btn-print-action{background:#b8860b;color:white;padding:8px 20px;border:none;border-radius:25px;cursor:pointer;font-size:14px}');
        doc.write('.btn-close-action{background:#999;color:white;padding:8px 20px;border:none;border-radius:25px;cursor:pointer;font-size:14px}');
        doc.write('@media print{body{background:white;padding:0;margin:0}.print-buttons{display:none}.invoice-card{box-shadow:none;margin:0;border-radius:0}}');
        doc.write('</style></head><body>');
        doc.write('<div class="invoice-card"><div class="invoice-header"><h1>🏜️ سوق الرمال الذهبية</h1><p>نقطة تجميع: ' + pickupPointName + '</p></div>');
        doc.write('<div class="invoice-body"><div class="invoice-title">📄 إيصال استلام الطلب</div>');
        doc.write('<div class="invoice-row"><span class="invoice-label">📝 رقم الطلب:</span><span class="invoice-value">'+orderNumber+'</span></div>');
        doc.write('<div class="invoice-row"><span class="invoice-label">📅 التاريخ:</span><span class="invoice-value">'+orderDate+'</span></div>');
        doc.write('<div class="invoice-row"><span class="invoice-label">📍 نقطة التجميع:</span><span class="invoice-value">'+pickupPointName+'</span></div>');
        doc.write('<div class="invoice-row"><span class="invoice-label">🚚 شركة التوصيل:</span><span class="invoice-value">'+shippingCompanyName+'</span></div>');
        doc.write('<div class="invoice-row"><span class="invoice-label">🛍️ البائع:</span><span class="invoice-value">'+sellerName+'</span></div>');
        doc.write('<div class="invoice-row"><span class="invoice-label">📦 المنتج:</span><span class="invoice-value">'+productName+'</span></div>');
        doc.write('<div class="invoice-row"><span class="invoice-label">📦 الكمية:</span><span class="invoice-value">'+quantity+'</span></div>');
        doc.write('<div class="invoice-row"><span class="invoice-label">👤 المستلم:</span><span class="invoice-value">'+buyerName+'</span></div>');
        doc.write('<div class="invoice-row"><span class="invoice-label">📞 هاتف المستلم:</span><span class="invoice-value">'+buyerPhone+'</span></div>');
        doc.write('<div class="total-row"><div class="invoice-row"><span class="invoice-label">💰 مبلغ التوصيل المستحق:</span><span class="invoice-value">'+parseFloat(deliveryFee).toFixed(2)+' دج</span></div></div>');
        doc.write('<div class="status-row"><div class="invoice-row"><span class="invoice-label">📋 حالة التسليم:</span><span class="invoice-value">'+statusText+'</span></div></div>');
        doc.write('</div><div class="invoice-footer"><p>🔔 هذا الإيصال يثبت استلام الطلب من نقطة التجميع</p><p>شكراً لتعاونكم معنا 💐</p><p class="note">سوق الرمال - سوق آمن للنساء</p></div>');
        doc.write('<div class="print-buttons"><button onclick="window.print()" class="btn-print-action">🖨️ طباعة</button><button onclick="window.close()" class="btn-close-action">✖️ إغلاق</button></div></div></body></html>');
        doc.close();
    }
    
    window.onload = function() {
        restoreScrollPosition();
    };
</script>

<div class="pickup-container">
    <?php if(isset($message)): ?>
        <div class="<?php echo strpos($message, '⚠️') !== false ? 'error' : 'success'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['total_orders']; ?></div>
            <div class="stat-label">📦 إجمالي الطلبات</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['pending']; ?></div>
            <div class="stat-label">⏳ قيد المعالجة</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['completed']; ?></div>
            <div class="stat-label">✅ تم التسليم</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['cancelled']; ?></div>
            <div class="stat-label">❌ فشل التسليم</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo number_format($stats['total_collect_amount'], 2); ?> دج</div>
            <div class="stat-label">💰 إجمالي المستحقات</div>
        </div>
        <div class="stat-box">
            <div class="stat-number" style="color: var(--badge-danger-text);"><?php echo number_format($stats['cancelled_collect'], 2); ?> دج</div>
            <div class="stat-label">❌ المستحقات الملغاة</div>
        </div>
        <div class="stat-box">
            <div class="stat-number" style="color: var(--success-text); font-size: 26px;"><?php echo number_format($stats['net_collect_amount'], 2); ?> دج</div>
            <div class="stat-label">💎 صافي المستحقات</div>
        </div>
    </div>
    
    <!-- التبويبات -->
    <div class="tabs">
        <button class="tab-btn <?php echo $active_tab == 'pending' ? 'active' : ''; ?>" onclick="showTab('pending')">⏳ قيد المعالجة (<?php echo $stats['pending']; ?>)</button>
        <button class="tab-btn <?php echo $active_tab == 'completed' ? 'active' : ''; ?>" onclick="showTab('completed')">✅ المكتملة (<?php echo $stats['completed']; ?>)</button>
        <button class="tab-btn <?php echo $active_tab == 'cancelled' ? 'active' : ''; ?>" onclick="showTab('cancelled')">❌ فشل التسليم (<?php echo $stats['cancelled']; ?>)</button>
    </div>
    
    <!-- تبويب قيد المعالجة -->
    <div id="tab-pending" class="tab-content <?php echo $active_tab == 'pending' ? 'active' : ''; ?>">
        <div class="card">
            <div class="card-header">⏳ الطلبات قيد المعالجة</div>
            <div class="card-body">
                <?php if(count($pending_orders) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>رقم الطلب</th>
                                <th>المنتج</th>
                                <th>البائع</th>
                                <th>شركة التوصيل</th>
                                <th>المشتري</th>
                                <th>الكمية</th>
                                <th>المبلغ</th>
                                <th>المبلغ المستحق</th>
                                <th>حالة الطلب</th>
                                <th>إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($pending_orders as $order): 
                                $statusText = getStatusText($order['order_status']);
                                $badgeClass = '';
                                if ($order['order_status'] == 'in_transit_to_pickup') {
                                    $badgeClass = 'badge-transit';
                                } elseif ($order['order_status'] == 'arrived_at_pickup') {
                                    $badgeClass = 'badge-arrived';
                                } else {
                                    $badgeClass = 'badge-transit';
                                }
                                $collect_amount = getCollectAmount($order);
                                $seller_name = $order['seller_name'] ?? 'غير معروف';
                                $shipping_company_name = $order['shipping_company_name'] ?? 'غير معروفة';
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong><br><small><?php echo date('Y-m-d', strtotime($order['created_at'])); ?></small></td>
                                <td><?php echo htmlspecialchars($order['product_name']); ?> (<small><?php echo $order['quantity']; ?> قطعة</small>)</td>
                                <td><?php echo htmlspecialchars($seller_name); ?></td>
                                <td><?php echo htmlspecialchars($shipping_company_name); ?></td>
                                <td><?php echo htmlspecialchars($order['buyer_name']); ?><br><small>📞 <?php echo htmlspecialchars($order['buyer_phone']); ?></small></td>
                                <td><?php echo $order['quantity']; ?></td>
                                <td><?php echo number_format($order['order_total'], 2); ?> دج</td>
                                <td><strong><?php echo number_format($collect_amount, 2); ?> دج</strong></td>
                                <td><span class="badge <?php echo $badgeClass; ?>"><?php echo $statusText; ?></span></td>
                                <td>
                                    <?php if($order['order_status'] == 'in_transit_to_pickup'): ?>
                                        <form method="POST" onsubmit="saveScrollPosition()" style="display:inline-block;">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <input type="hidden" name="scroll_pos" value="0">
                                            <button type="submit" name="confirm_arrival" class="btn-confirm" onclick="return confirm('هل تم استلام الطلب في نقطة التجميع؟')">📍 تأكيد الوصول</button>
                                        </form>
                                    <?php elseif($order['order_status'] == 'arrived_at_pickup'): ?>
                                        <form method="POST" onsubmit="saveScrollPosition()" style="display:inline-block;">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <input type="hidden" name="scroll_pos" value="0">
                                            <button type="submit" name="confirm_delivery" class="btn-deliver" onclick="return confirm('هل تم تسليم الطلب للمشتري؟')">✅ تأكيد التسليم</button>
                                            <button type="submit" name="cancel_delivery" class="btn-cancel" onclick="return confirm('فشل تسليم الطلب؟ سيتم إلغاء الطلب وإعادة الكمية للمخزون')">❌ فشل التسليم</button>
                                        </form>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="stats-grid" style="margin-top:20px;">
                        <div class="stat-box"><div class="stat-number"><?php echo number_format($stats['pending_collect'], 2); ?> دج</div><div class="stat-label">💰 إجمالي مستحقات قيد المعالجة</div></div>
                    </div>
                <?php else: ?>
                    <p style="text-align:center; padding:40px; color: var(--text-muted);">📭 لا توجد طلبات قيد المعالجة</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- تبويب المكتملة -->
    <div id="tab-completed" class="tab-content <?php echo $active_tab == 'completed' ? 'active' : ''; ?>">
        <div class="card">
            <div class="card-header">✅ الطلبات المكتملة (تم تسليمها للمشتري)</div>
            <div class="card-body">
                <?php if(count($completed_orders) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>رقم الطلب</th>
                                <th>المنتج</th>
                                <th>البائع</th>
                                <th>شركة التوصيل</th>
                                <th>المشتري</th>
                                <th>الكمية</th>
                                <th>المبلغ</th>
                                <th>المبلغ المستحق</th>
                                <th>حالة الطلب</th>
                                <th>فاتورة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($completed_orders as $order): 
                                $collect_amount = getCollectAmount($order);
                                $seller_name = $order['seller_name'] ?? 'غير معروف';
                                $shipping_company_name = $order['shipping_company_name'] ?? 'غير معروفة';
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong><br><small><?php echo date('Y-m-d', strtotime($order['created_at'])); ?></small></td>
                                <td><?php echo htmlspecialchars($order['product_name']); ?> (<small><?php echo $order['quantity']; ?> قطعة</small>)</td>
                                <td><?php echo htmlspecialchars($seller_name); ?></td>
                                <td><?php echo htmlspecialchars($shipping_company_name); ?></td>
                                <td><?php echo htmlspecialchars($order['buyer_name']); ?><br><small>📞 <?php echo htmlspecialchars($order['buyer_phone']); ?></small></td>
                                <td><?php echo $order['quantity']; ?></td>
                                <td><?php echo number_format($order['order_total'], 2); ?> دج</td>
                                <td><strong><?php echo number_format($collect_amount, 2); ?> دج</strong></td>
                                <td><span class="badge badge-delivered">🎉 تم تسليم الطلب للمشتري</span></td>
                                <td>
                                    <button onclick="printDeliveryNote(
                                        '<?php echo htmlspecialchars($order['order_number']); ?>',
                                        '<?php echo htmlspecialchars(addslashes($order['product_name'])); ?>',
                                        <?php echo $order['quantity']; ?>,
                                        '<?php echo htmlspecialchars(addslashes($order['buyer_name'])); ?>',
                                        '<?php echo htmlspecialchars($order['buyer_phone']); ?>',
                                        <?php echo $collect_amount; ?>,
                                        '<?php echo htmlspecialchars(addslashes($pickup_point_name)); ?>',
                                        '<?php echo htmlspecialchars(addslashes($seller_name)); ?>',
                                        '<?php echo htmlspecialchars(addslashes($shipping_company_name)); ?>',
                                        '<?php echo date('Y-m-d', strtotime($order['created_at'])); ?>',
                                        'delivered'
                                    )" class="btn-print">
                                        🖨️ طباعة
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align:center; padding:40px; color: var(--text-muted);">📭 لا توجد طلبات مكتملة بعد</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- تبويب فشل التسليم -->
    <div id="tab-cancelled" class="tab-content <?php echo $active_tab == 'cancelled' ? 'active' : ''; ?>">
        <div class="card">
            <div class="card-header">❌ الطلبات التي فشل تسليمها</div>
            <div class="card-body">
                <?php if(count($cancelled_orders) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>رقم الطلب</th>
                                <th>المنتج</th>
                                <th>البائع</th>
                                <th>شركة التوصيل</th>
                                <th>المشتري</th>
                                <th>الكمية</th>
                                <th>المبلغ</th>
                                <th>المبلغ المستحق</th>
                                <th>حالة الطلب</th>
                                <th>فاتورة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($cancelled_orders as $order): 
                                $collect_amount = getCollectAmount($order);
                                $seller_name = $order['seller_name'] ?? 'غير معروف';
                                $shipping_company_name = $order['shipping_company_name'] ?? 'غير معروفة';
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong><br><small><?php echo date('Y-m-d', strtotime($order['created_at'])); ?></small></td>
                                <td><?php echo htmlspecialchars($order['product_name']); ?> (<small><?php echo $order['quantity']; ?> قطعة</small>)</td>
                                <td><?php echo htmlspecialchars($seller_name); ?></td>
                                <td><?php echo htmlspecialchars($shipping_company_name); ?></td>
                                <td><?php echo htmlspecialchars($order['buyer_name']); ?><br><small>📞 <?php echo htmlspecialchars($order['buyer_phone']); ?></small></td>
                                <td><?php echo $order['quantity']; ?></td>
                                <td><?php echo number_format($order['order_total'], 2); ?> دج</td>
                                <td><strong><?php echo number_format($collect_amount, 2); ?> دج</strong></td>
                                <td><span class="badge badge-cancelled">❌ فشل التسليم</span></td>
                                <td>
                                    <button onclick="printDeliveryNote(
                                        '<?php echo htmlspecialchars($order['order_number']); ?>',
                                        '<?php echo htmlspecialchars(addslashes($order['product_name'])); ?>',
                                        <?php echo $order['quantity']; ?>,
                                        '<?php echo htmlspecialchars(addslashes($order['buyer_name'])); ?>',
                                        '<?php echo htmlspecialchars($order['buyer_phone']); ?>',
                                        <?php echo $collect_amount; ?>,
                                        '<?php echo htmlspecialchars(addslashes($pickup_point_name)); ?>',
                                        '<?php echo htmlspecialchars(addslashes($seller_name)); ?>',
                                        '<?php echo htmlspecialchars(addslashes($shipping_company_name)); ?>',
                                        '<?php echo date('Y-m-d', strtotime($order['created_at'])); ?>',
                                        'cancelled'
                                    )" class="btn-print">
                                        🖨️ طباعة
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align:center; padding:40px; color: var(--text-muted);">📭 لا توجد طلبات فشل تسليمها</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
include_once 'footer.php';
include_once 'bottom_nav.php';
?>
</body>
</html>