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

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'shipping_company') {
    header("Location: login.php");
    exit();
}

$company_id = $_SESSION['shipping_company_id'] ?? 1;
$user_name = $_SESSION['user_name'];

// جلب اسم شركة التوصيل للاستخدام في الفاتورة
$company_name = '';
$company_phone = '';
try {
    $stmt = $pdo->prepare("SELECT name, phone FROM shipping_companies WHERE id = ?");
    $stmt->execute([$company_id]);
    $company = $stmt->fetch();
    $company_name = $company['name'] ?? 'شركة التوصيل';
    $company_phone = $company['phone'] ?? '';
} catch(PDOException $e) {
    $company_name = 'شركة التوصيل';
}

// دالة حساب المبلغ المستحق لشركة التوصيل (سعر التوصيل فقط)
function getCollectAmount($order) {
    return $order['delivery_fee'] ?? 0;
}

// دالة ترجمة حالة الطلب حسب طريقة التوصيل - محدثة وشاملة
function getStatusText($status, $delivery_method) {
    // الخريطة العامة لجميع الحالات
    $generalMap = [
        'pending' => '⏳ في انتظار الدفع',
        'advance_paid_by_buyer' => '💰 تم الإشعار بالدفع',
        'advance_confirmed_by_seller' => '✅ تم تأكيد العربون',
        'ready_for_shipping' => '📦 جاهز للشحن',
        'picked_by_shipping' => '📦 تم الاستلام من البائع',
        'delivered_to_buyer' => '🎉 تم التوصيل',
        'cancelled' => '❌ فشل التوصيل'
    ];
    
    // الخريطة الخاصة حسب طريقة التوصيل
    if ($delivery_method == 'home') {
        $methodMap = [
            'in_transit' => '🚚 قيد التوصيل للمنزل',
            'delivered_to_buyer' => '🎉 تم التوصيل للمنزل',
            'picked_by_shipping' => '📦 تم استلام الطرد من البائع',
            'cancelled' => '❌ فشل توصيل للمنزل'
        ];
    } else { // pickup
        $methodMap = [
            'in_transit_to_pickup' => '🚚 قيد التوصيل للنقطة',
            'arrived_at_pickup' => '📍 وصل للنقطة',
            'picked_by_shipping' => '📦 تم استلام الطرد من البائع',
            'cancelled' => '❌ فشل توصيل للنقطة'
        ];
    }
    
    // التحقق من الخريطة الخاصة أولاً
    if (isset($methodMap[$status])) {
        return $methodMap[$status];
    }
    
    // ثم الخريطة العامة
    if (isset($generalMap[$status])) {
        return $generalMap[$status];
    }
    
    // إذا لم توجد ترجمة، نرجع الحالة كما هي
    return $status;
}

// دالة ترجمة الحالة للعرض في الفاتورة
function getInvoiceStatusText($status) {
    if ($status == 'delivered_to_buyer') {
        return '✅ تم التوصيل بنجاح';
    } elseif ($status == 'cancelled') {
        return '❌ فشل التوصيل';
    }
    return '';
}

// دالة ترجمة الحالة للعرض في الجدول (نسخة مبسطة للبادج)
function getStatusBadge($status, $delivery_method) {
    $badgeClasses = [
        'pending' => 'badge-pending',
        'advance_paid_by_buyer' => 'badge-pending',
        'advance_confirmed_by_seller' => 'badge-picked',
        'ready_for_shipping' => 'badge-pending',
        'picked_by_shipping' => 'badge-picked',
        'in_transit' => 'badge-transit',
        'in_transit_to_pickup' => 'badge-transit',
        'arrived_at_pickup' => 'badge-arrived',
        'delivered_to_buyer' => 'badge-delivered',
        'cancelled' => 'badge-cancelled'
    ];
    
    $badgeClass = $badgeClasses[$status] ?? 'badge-pending';
    $statusText = getStatusText($status, $delivery_method);
    
    return '<span class="badge ' . $badgeClass . '">' . $statusText . '</span>';
}

// حفظ مكان التمرير والتبويب النشط
$scroll_pos = isset($_POST['scroll_pos']) ? intval($_POST['scroll_pos']) : (isset($_GET['scroll_pos']) ? intval($_GET['scroll_pos']) : 0);
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'pending';

// تحديث حالة الشحن
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_shipping_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['shipping_status'];
    $scroll_pos = intval($_POST['scroll_pos'] ?? 0);
    $active_tab = $_POST['active_tab'] ?? 'pending';
    
    $stmt = $pdo->prepare("SELECT delivery_method, order_status FROM orders WHERE id = ? AND shipping_company_id = ?");
    $stmt->execute([$order_id, $company_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        $_SESSION['message'] = "⚠️ الطلب غير موجود أو غير مخصص لشركتك";
        header("Location: shipping_dashboard.php?scroll_pos=" . $scroll_pos . "&tab=" . $active_tab);
        exit();
    }
    
    $delivery_method = $order['delivery_method'];
    $current_status = $order['order_status'];
    $allowed = false;
    $order_status = null;
    
    if ($delivery_method == 'home') {
        // التوصيل للمنزل - شركة التوصيل مسؤولة حتى التسليم
        if ($new_status == 'picked_from_seller' && $current_status == 'ready_for_shipping') {
            $allowed = true;
            $order_status = 'picked_by_shipping';
        } elseif ($new_status == 'in_transit' && $current_status == 'picked_by_shipping') {
            $allowed = true;
            $order_status = 'in_transit';
        } elseif ($new_status == 'delivered' && $current_status == 'in_transit') {
            $allowed = true;
            $order_status = 'delivered_to_buyer';
        } elseif ($new_status == 'delivery_failed' && $current_status == 'in_transit') {
            $allowed = true;
            $order_status = 'cancelled';
            // إعادة المخزون
            $stmt2 = $pdo->prepare("SELECT product_id, quantity FROM orders WHERE id = ?");
            $stmt2->execute([$order_id]);
            $order_data = $stmt2->fetch();
            if ($order_data) {
                $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")->execute([$order_data['quantity'], $order_data['product_id']]);
            }
        }
    } else { // pickup
        // التوصيل لنقطة تجميع - شركة التوصيل مسؤولة فقط حتى إيصالها للنقطة
        if ($new_status == 'picked_from_seller' && $current_status == 'ready_for_shipping') {
            $allowed = true;
            $order_status = 'picked_by_shipping';
        } elseif ($new_status == 'in_transit' && $current_status == 'picked_by_shipping') {
            $allowed = true;
            $order_status = 'in_transit_to_pickup';
        } elseif ($new_status == 'delivery_failed' && $current_status == 'in_transit_to_pickup') {
            // شركة التوصيل يمكنها الإبلاغ عن فشل التوصيل للنقطة فقط
            $allowed = true;
            $order_status = 'cancelled';
            $stmt2 = $pdo->prepare("SELECT product_id, quantity FROM orders WHERE id = ?");
            $stmt2->execute([$order_id]);
            $order_data = $stmt2->fetch();
            if ($order_data) {
                $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")->execute([$order_data['quantity'], $order_data['product_id']]);
            }
        }
        // ملاحظة: حالة arrived_at_pickup أصبحت من صلاحيات نقطة التجميع فقط
        // شركة التوصيل لا يمكنها تحديث الحالة بعد in_transit_to_pickup (ما عدا فشل التوصيل)
    }
    
    if ($allowed) {
        $update = $pdo->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
        $update->execute([$order_status, $order_id]);
        $_SESSION['message'] = "✅ تم تحديث حالة الطلب إلى: " . getStatusText($order_status, $delivery_method);
    } else {
        $_SESSION['message'] = "⚠️ لا يمكنك تحديث حالة الطلب بهذه الطريقة (غير مسموح)";
    }
    
    header("Location: shipping_dashboard.php?scroll_pos=" . $scroll_pos . "&tab=" . $active_tab);
    exit();
}

// جلب جميع الطلبات المخصصة لهذه الشركة مع اسم البائع
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
               pp.point_name,
               pp.address as pickup_address
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN users u ON o.buyer_id = u.id
        JOIN users s ON o.seller_id = s.id
        LEFT JOIN pickup_points pp ON o.pickup_point_id = pp.id
        WHERE o.shipping_company_id = ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$company_id]);
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
        } elseif (in_array($order['order_status'], ['pending', 'advance_paid_by_buyer', 'advance_confirmed_by_seller', 'ready_for_shipping', 'picked_by_shipping', 'in_transit', 'in_transit_to_pickup', 'arrived_at_pickup'])) {
            $pending_orders[] = $order;
            $total_pending_collect += $collect;
        }
    }
} catch(PDOException $e) {}

// إحصائيات - صافي المستحقات = المكتملة فقط (لأن الملغاة لا تستحق)
$net_collect_amount = $total_completed_collect;
$total_orders_count = count($orders);
$completed_count = count($completed_orders);
$pending_count = count($pending_orders);
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
    .shipping-container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
    
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
    
    .data-table { width: 100%; border-collapse: collapse; min-width: 1100px; }
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
    
    .status-select { 
        padding: 5px 8px; 
        border-radius: 20px; 
        border: 1px solid var(--text-heading); 
        background: var(--bg-input); 
        font-size: 12px; 
        color: var(--text-primary);
    }
    .status-btn { 
        background: var(--btn-primary); 
        color: var(--text-white); 
        border: none; 
        padding: 5px 12px; 
        border-radius: 20px; 
        cursor: pointer; 
        transition: 0.3s;
    }
    .status-btn:hover { background: var(--btn-primary-hover); }
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
    .badge-pending { background: var(--badge-pending-bg); color: var(--badge-pending-text); }
    .badge-picked { background: var(--badge-info-bg); color: var(--badge-info-text); }
    .badge-transit { background: #fff8e1; color: #f57f17; }
    .badge-arrived { background: var(--badge-success-bg); color: var(--badge-success-text); }
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
    
    .pickup-info {
        background: var(--badge-info-bg);
        color: var(--badge-info-text);
        padding: 8px 12px;
        border-radius: 10px;
        font-size: 11px;
        text-align: center;
        margin-top: 5px;
    }
    
    small { color: var(--text-muted); }
    
    @media (max-width: 768px) { 
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .shipping-container { margin: 20px auto; }
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
    
    function printShippingNote(orderNumber, productName, quantity, buyerName, buyerPhone, deliveryFee, sellerName, shippingCompanyName, orderDate, deliveryStatus) {
        var printWindow = window.open('', '_blank');
        var doc = printWindow.document;
        
        var statusColor = deliveryStatus === 'delivered' ? '#2e7d32' : '#c62828';
        var statusBg = deliveryStatus === 'delivered' ? '#e8f5e9' : '#ffebee';
        var statusText = deliveryStatus === 'delivered' ? '✅ تم التوصيل بنجاح' : '❌ فشل التوصيل';
        
        doc.write('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"><title>فاتورة توصيل - سوق الرمال</title><style>');
        doc.write('body{font-family:Tahoma,Arial,sans-serif;background:#f0e0c0;display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px;margin:0}');
        doc.write('.invoice-card{max-width:400px;width:100%;background:white;border-radius:20px;box-shadow:0 10px 30px rgba(0,0,0,0.15);overflow:hidden;direction:rtl}');
        doc.write('.invoice-header{background:linear-gradient(135deg, #b8860b, #d4a017);color:white;text-align:center;padding:20px}');
        doc.write('.invoice-header h1{font-size:24px;margin-bottom:5px}');
        doc.write('.invoice-body{padding:20px}');
        doc.write('.invoice-title{text-align:center;font-size:18px;font-weight:bold;color:#b8860b;border-bottom:2px dashed #f0e0c0;padding-bottom:10px;margin-bottom:20px}');
        doc.write('.invoice-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f0e0c0}');
        doc.write('.invoice-label{font-weight:bold;color:#555}');
        doc.write('.invoice-value{color:#333;font-weight:bold;text-align:left}');
        doc.write('.total-row{background:#e8f5e9;margin-top:15px;padding:10px;border-radius:10px}');
        doc.write('.total-row .invoice-value{font-size:18px;color:#2e7d32}');
        doc.write('.status-row{background:' + statusBg + ';margin-top:10px;padding:10px;border-radius:10px;text-align:center}');
        doc.write('.status-row .invoice-value{font-size:16px;color:' + statusColor + ';font-weight:bold}');
        doc.write('.invoice-footer{background:#f8e1b0;padding:15px;text-align:center;margin-top:15px;font-size:12px}');
        doc.write('.print-buttons{display:flex;justify-content:center;gap:15px;padding:15px;background:white;border-top:1px solid #eee}');
        doc.write('.btn-print-action{background:#b8860b;color:white;padding:8px 20px;border:none;border-radius:25px;cursor:pointer;font-size:14px}');
        doc.write('.btn-close-action{background:#999;color:white;padding:8px 20px;border:none;border-radius:25px;cursor:pointer;font-size:14px}');
        doc.write('@media print{body{background:white;padding:0;margin:0}.print-buttons{display:none}.invoice-card{box-shadow:none;margin:0;border-radius:0}}');
        doc.write('</style></head><body>');
        doc.write('<div class="invoice-card"><div class="invoice-header"><h1>🏜️ سوق الرمال الذهبية</h1><p>فاتورة توصيل - ' + shippingCompanyName + '</p></div>');
        doc.write('<div class="invoice-body"><div class="invoice-title">📄 إيصال توصيل الطلب</div>');
        doc.write('<div class="invoice-row"><span class="invoice-label">📝 رقم الطلب:</span><span class="invoice-value">'+orderNumber+'</span></div>');
        doc.write('<div class="invoice-row"><span class="invoice-label">📅 التاريخ:</span><span class="invoice-value">'+orderDate+'</span></div>');
        doc.write('<div class="invoice-row"><span class="invoice-label">🚚 شركة التوصيل:</span><span class="invoice-value">'+shippingCompanyName+'</span></div>');
        doc.write('<div class="invoice-row"><span class="invoice-label">🛍️ البائع:</span><span class="invoice-value">'+sellerName+'</span></div>');
        doc.write('<div class="invoice-row"><span class="invoice-label">📦 المنتج:</span><span class="invoice-value">'+productName+'</span></div>');
        doc.write('<div class="invoice-row"><span class="invoice-label">📦 الكمية:</span><span class="invoice-value">'+quantity+'</span></div>');
        doc.write('<div class="invoice-row"><span class="invoice-label">👤 المستلم:</span><span class="invoice-value">'+buyerName+'</span></div>');
        doc.write('<div class="invoice-row"><span class="invoice-label">📞 هاتف المستلم:</span><span class="invoice-value">'+buyerPhone+'</span></div>');
        doc.write('<div class="total-row"><div class="invoice-row"><span class="invoice-label">💰 مبلغ التوصيل المستحق:</span><span class="invoice-value">'+parseFloat(deliveryFee).toFixed(2)+' دج</span></div></div>');
        doc.write('<div class="status-row"><div class="invoice-row"><span class="invoice-label">📋 حالة التوصيل:</span><span class="invoice-value">'+statusText+'</span></div></div>');
        doc.write('</div><div class="invoice-footer"><p>🔔 هذا الإيصال يثبت توصيل الطلب</p><p>شكراً لتعاونكم معنا 💐</p><p class="note">سوق الرمال - سوق آمن للنساء</p></div>');
        doc.write('<div class="print-buttons"><button onclick="window.print()" class="btn-print-action">🖨️ طباعة</button><button onclick="window.close()" class="btn-close-action">✖️ إغلاق</button></div></div></body></html>');
        doc.close();
    }
    
    window.onload = function() {
        restoreScrollPosition();
    };
</script>

<div class="shipping-container">
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
            <div class="stat-label">✅ تم التوصيل</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['cancelled']; ?></div>
            <div class="stat-label">❌ فشل التوصيل</div>
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
        <button class="tab-btn <?php echo $active_tab == 'cancelled' ? 'active' : ''; ?>" onclick="showTab('cancelled')">❌ فشل التوصيل (<?php echo $stats['cancelled']; ?>)</button>
    </div>
    
    <!-- تبويب قيد المعالجة -->
    <div id="tab-pending" class="tab-content <?php echo $active_tab == 'pending' ? 'active' : ''; ?>">
        <div class="card">
            <div class="card-header">⏳ الطلبات قيد المعالجة</div>
            <div class="card-body">
                <?php if(count($pending_orders) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>رقم الطلب</th>
                                    <th>المنتج</th>
                                    <th>البائع</th>
                                    <th>المشتري</th>
                                    <th>الكمية</th>
                                    <th>المبلغ</th>
                                    <th>المبلغ المستحق</th>
                                    <th>طريقة التوصيل</th>
                                    <th>العنوان / نقطة التجميع</th>
                                    <th>حالة الشحن</th>
                                    <th>تحديث</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($pending_orders as $order): 
                                    $current_status = $order['order_status'];
                                    $collect_amount = getCollectAmount($order);
                                    $seller_name = $order['seller_name'] ?? 'غير معروف';
                                    $is_pickup = $order['delivery_method'] == 'pickup';
                                    // التحقق مما إذا كانت الحالة بعد in_transit_to_pickup (من صلاحيات نقطة التجميع)
                                    $managed_by_pickup = $is_pickup && in_array($current_status, ['in_transit_to_pickup', 'arrived_at_pickup']);
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong><br><small><?php echo date('Y-m-d', strtotime($order['created_at'])); ?></small></td>
                                    <td><?php echo htmlspecialchars($order['product_name']); ?> (<?php echo $order['quantity']; ?>)</td>
                                    <td><?php echo htmlspecialchars($seller_name); ?></td>
                                    <td><?php echo htmlspecialchars($order['buyer_name']); ?><br><small>📞 <?php echo htmlspecialchars($order['buyer_phone']); ?></small></td>
                                    <td><?php echo $order['quantity']; ?></td>
                                    <td><?php echo number_format($order['order_total'], 2); ?> دج</td>
                                    <td><strong><?php echo number_format($collect_amount, 2); ?> دج</strong></td>
                                    <td><?php echo $is_pickup ? '📍 نقطة تجميع' : '🏠 توصيل للمنزل'; ?></td>
                                    <td style="max-width:200px; word-break:break-word;">
                                        <?php 
                                        if($is_pickup && $order['point_name']) {
                                            echo '<strong>' . htmlspecialchars($order['point_name']) . '</strong><br><small>' . htmlspecialchars($order['pickup_address']) . '</small>';
                                        } else {
                                            echo nl2br(htmlspecialchars(substr($order['delivery_address'], 0, 60)));
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo getStatusBadge($current_status, $order['delivery_method']); ?></td>
                                    <td>
                                        <?php if($managed_by_pickup): ?>
                                            <!-- الطلب الآن من صلاحيات نقطة التجميع -->
                                            <div class="pickup-info">
                                                📍 بانتظار نقطة التجميع<br>
                                                <small>تم إيصال الطلب للنقطة</small>
                                            </div>
                                        <?php else: ?>
                                            <form method="POST" style="display:flex; gap:5px; flex-wrap:wrap; justify-content:center;" onsubmit="saveScrollPosition()">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="scroll_pos" value="0">
                                                <input type="hidden" name="active_tab" value="pending">
                                                <select name="shipping_status" class="status-select">
                                                    <?php if($current_status == 'ready_for_shipping'): ?>
                                                        <option value="picked_from_seller">📦 استلام من البائع</option>
                                                    <?php elseif($current_status == 'picked_by_shipping'): ?>
                                                        <option value="in_transit">🚚 قيد التوصيل</option>
                                                    <?php elseif(!$is_pickup && $current_status == 'in_transit'): ?>
                                                        <option value="delivered">✅ تم التوصيل للمنزل</option>
                                                        <option value="delivery_failed" style="color: var(--badge-danger-text);">❌ فشل التوصيل</option>
                                                    <?php elseif($is_pickup && $current_status == 'in_transit_to_pickup'): ?>
                                                        <option value="delivery_failed" style="color: var(--badge-danger-text);">❌ فشل التوصيل</option>
                                                    <?php endif; ?>
                                                </select>
                                                <button type="submit" name="update_shipping_status" class="status-btn">تحديث</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
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
            <div class="card-header">✅ الطلبات المكتملة (تم التوصيل)</div>
            <div class="card-body">
                <?php if(count($completed_orders) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>رقم الطلب</th>
                                    <th>المنتج</th>
                                    <th>البائع</th>
                                    <th>المشتري</th>
                                    <th>الكمية</th>
                                    <th>المبلغ</th>
                                    <th>المبلغ المستحق</th>
                                    <th>طريقة التوصيل</th>
                                    <th>العنوان / نقطة التجميع</th>
                                    <th>حالة الطلب</th>
                                    <th>فاتورة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($completed_orders as $order): 
                                    $collect_amount = getCollectAmount($order);
                                    $seller_name = $order['seller_name'] ?? 'غير معروف';
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong><br><small><?php echo date('Y-m-d', strtotime($order['created_at'])); ?></small></td>
                                    <td><?php echo htmlspecialchars($order['product_name']); ?> (<?php echo $order['quantity']; ?>)</td>
                                    <td><?php echo htmlspecialchars($seller_name); ?></td>
                                    <td><?php echo htmlspecialchars($order['buyer_name']); ?><br><small>📞 <?php echo htmlspecialchars($order['buyer_phone']); ?></small></td>
                                    <td><?php echo $order['quantity']; ?></td>
                                    <td><?php echo number_format($order['order_total'], 2); ?> دج</td>
                                    <td><strong><?php echo number_format($collect_amount, 2); ?> دج</strong></td>
                                    <td><?php echo $order['delivery_method'] == 'pickup' ? '📍 نقطة تجميع' : '🏠 توصيل للمنزل'; ?></td>
                                    <td style="max-width:200px; word-break:break-word;">
                                        <?php 
                                        if($order['delivery_method'] == 'pickup' && $order['point_name']) {
                                            echo '<strong>' . htmlspecialchars($order['point_name']) . '</strong><br><small>' . htmlspecialchars($order['pickup_address']) . '</small>';
                                        } else {
                                            echo nl2br(htmlspecialchars(substr($order['delivery_address'], 0, 60)));
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo getStatusBadge('delivered_to_buyer', $order['delivery_method']); ?></td>
                                    <td>
                                        <button onclick="printShippingNote(
                                            '<?php echo htmlspecialchars($order['order_number']); ?>',
                                            '<?php echo htmlspecialchars(addslashes($order['product_name'])); ?>',
                                            <?php echo $order['quantity']; ?>,
                                            '<?php echo htmlspecialchars(addslashes($order['buyer_name'])); ?>',
                                            '<?php echo htmlspecialchars($order['buyer_phone']); ?>',
                                            <?php echo $collect_amount; ?>,
                                            '<?php echo htmlspecialchars(addslashes($seller_name)); ?>',
                                            '<?php echo htmlspecialchars(addslashes($company_name)); ?>',
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
                    </div>
                <?php else: ?>
                    <p style="text-align:center; padding:40px; color: var(--text-muted);">📭 لا توجد طلبات مكتملة بعد</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- تبويب فشل التوصيل -->
    <div id="tab-cancelled" class="tab-content <?php echo $active_tab == 'cancelled' ? 'active' : ''; ?>">
        <div class="card">
            <div class="card-header">❌ الطلبات التي فشل توصيلها</div>
            <div class="card-body">
                <?php if(count($cancelled_orders) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>رقم الطلب</th>
                                    <th>المنتج</th>
                                    <th>البائع</th>
                                    <th>المشتري</th>
                                    <th>الكمية</th>
                                    <th>المبلغ</th>
                                    <th>المبلغ المستحق</th>
                                    <th>طريقة التوصيل</th>
                                    <th>العنوان / نقطة التجميع</th>
                                    <th>حالة الطلب</th>
                                    <th>فاتورة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($cancelled_orders as $order): 
                                    $collect_amount = getCollectAmount($order);
                                    $seller_name = $order['seller_name'] ?? 'غير معروف';
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong><br><small><?php echo date('Y-m-d', strtotime($order['created_at'])); ?></small></td>
                                    <td><?php echo htmlspecialchars($order['product_name']); ?> (<?php echo $order['quantity']; ?>)</td>
                                    <td><?php echo htmlspecialchars($seller_name); ?></td>
                                    <td><?php echo htmlspecialchars($order['buyer_name']); ?><br><small>📞 <?php echo htmlspecialchars($order['buyer_phone']); ?></small></td>
                                    <td><?php echo $order['quantity']; ?></td>
                                    <td><?php echo number_format($order['order_total'], 2); ?> دج</td>
                                    <td><strong><?php echo number_format($collect_amount, 2); ?> دج</strong></td>
                                    <td><?php echo $order['delivery_method'] == 'pickup' ? '📍 نقطة تجميع' : '🏠 توصيل للمنزل'; ?></td>
                                    <td style="max-width:200px; word-break:break-word;">
                                        <?php 
                                        if($order['delivery_method'] == 'pickup' && $order['point_name']) {
                                            echo '<strong>' . htmlspecialchars($order['point_name']) . '</strong><br><small>' . htmlspecialchars($order['pickup_address']) . '</small>';
                                        } else {
                                            echo nl2br(htmlspecialchars(substr($order['delivery_address'], 0, 60)));
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo getStatusBadge('cancelled', $order['delivery_method']); ?></td>
                                    <td>
                                        <button onclick="printShippingNote(
                                            '<?php echo htmlspecialchars($order['order_number']); ?>',
                                            '<?php echo htmlspecialchars(addslashes($order['product_name'])); ?>',
                                            <?php echo $order['quantity']; ?>,
                                            '<?php echo htmlspecialchars(addslashes($order['buyer_name'])); ?>',
                                            '<?php echo htmlspecialchars($order['buyer_phone']); ?>',
                                            <?php echo $collect_amount; ?>,
                                            '<?php echo htmlspecialchars(addslashes($seller_name)); ?>',
                                            '<?php echo htmlspecialchars(addslashes($company_name)); ?>',
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
                    </div>
                <?php else: ?>
                    <p style="text-align:center; padding:40px; color: var(--text-muted);">📭 لا توجد طلبات فشل توصيلها</p>
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