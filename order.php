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
// [إضافة] دالة لجلب نسبة العمولة للبائع
// ============================================================
function getSellerCommissionRate($pdo, $seller_id) {
    try {
        $stmt = $pdo->prepare("SELECT custom_commission_rate FROM users WHERE id = ?");
        $stmt->execute([$seller_id]);
        $custom_rate = $stmt->fetchColumn();
        if ($custom_rate !== null && $custom_rate !== '') {
            return floatval($custom_rate);
        }
        $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'default_commission_rate'");
        $default = $stmt->fetchColumn();
        return $default ? floatval($default) : 7;
    } catch(PDOException $e) {
        return 7;
    }
}
// ============================================================

// ============================================================
// [للباقي الصفحات] هذه المتغيرات ضرورية للشريط السفلي
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

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['product_id']) || empty($_GET['product_id'])) {
    header("Location: index.php");
    exit();
}

$product_id = $_GET['product_id'];
$buyer_id = $_SESSION['user_id'];
$advance_percentage = 25;

// ========== جلب إعدادات نظام الدفع ==========
$system_payment_type = 'both';
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'payment_type'");
    $stmt->execute();
    $result = $stmt->fetch();
    if ($result) {
        $system_payment_type = $result['setting_value'];
    }
} catch(PDOException $e) {}

// ========== جلب معلومات المنتج والبائع ==========
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               u.full_name as seller_name, 
               u.phone as seller_phone, 
               u.id as seller_id,
               a.rib_number as site_rib
        FROM products p 
        JOIN users u ON p.seller_id = u.id 
        CROSS JOIN (SELECT rib_number FROM users WHERE user_type = 'admin' LIMIT 1) a
        WHERE p.id = ? AND p.status = 'available' AND p.stock > 0
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

// تحويل المقاسات والألوان إلى مصفوفات
$sizes_array = [];
$colors_array = [];
if ($product['size']) {
    $size_string = str_replace(['،', ';', '|'], ',', $product['size']);
    $sizes_array = array_map('trim', explode(',', $size_string));
    $sizes_array = array_filter($sizes_array);
}
if ($product['color']) {
    $color_string = str_replace(['،', ';', '|'], ',', $product['color']);
    $colors_array = array_map('trim', explode(',', $color_string));
    $colors_array = array_filter($colors_array);
}

// جلب قائمة الولايات
$wilayas = [];
try {
    $stmt = $pdo->query("SELECT name FROM wilayas ORDER BY name");
    $wilayas = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {}

$error = '';
$success = '';
$order_number = '';
$advance_amount = 0;
$remaining_amount = 0;
$new_order_id = 0;
$selected_size = '';
$selected_color = '';
$payment_type = '';

// حفظ مكان التمرير
$scroll_pos = isset($_POST['scroll_pos']) ? intval($_POST['scroll_pos']) : (isset($_GET['scroll_pos']) ? intval($_GET['scroll_pos']) : 0);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['confirm_payment'])) {
    $delivery_method = $_POST['delivery_method'];
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $buyer_notes = trim($_POST['buyer_notes'] ?? '');
    $quantity = intval($_POST['quantity']);
    $selected_size = isset($_POST['size']) ? trim($_POST['size']) : '';
    $selected_color = isset($_POST['color']) ? trim($_POST['color']) : '';
    $delivery_fee = floatval($_POST['delivery_fee'] ?? 0);
    $shipping_company_id = !empty($_POST['shipping_company_id']) ? intval($_POST['shipping_company_id']) : null;
    $pickup_point_id = !empty($_POST['pickup_point_id']) ? intval($_POST['pickup_point_id']) : null;
    $wilaya = trim($_POST['wilaya'] ?? '');
    $commune = trim($_POST['commune'] ?? '');
    $payment_type = $_POST['payment_type'] ?? 'advance';
    
    if ($quantity > $product['stock']) $quantity = $product['stock'];
    if ($quantity < 1) $quantity = 1;
    
    $total_price = ($product['price'] * $quantity) + $delivery_fee;
    
    // حساب المبالغ حسب طريقة الدفع
    if ($payment_type == 'advance') {
        $advance_amount = $total_price * ($advance_percentage / 100);
        $remaining_amount = $total_price - $advance_amount;
    } else {
        $advance_amount = 0;
        $remaining_amount = $total_price;
    }
    
    // حساب العمولة
    $seller_commission_rate = getSellerCommissionRate($pdo, $product['seller_id']);
    $commission_amount = ($product['price'] * $quantity) * ($seller_commission_rate / 100);
    
    // حساب cancel_until
    $cancel_until = null;
    if ($payment_type == 'cash_on_delivery' && $product['cancel_enabled'] && $product['cancel_hours'] > 0) {
        $cancel_until = date('Y-m-d H:i:s', strtotime("+{$product['cancel_hours']} hours"));
    }
    
    $order_number = 'ORD-' . time() . '-' . rand(1000, 9999);
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO orders (order_number, product_id, buyer_id, seller_id, quantity, total_price, advance_amount, 
                                delivery_method, delivery_address, buyer_notes, product_size, product_color, 
                                delivery_fee, shipping_company_id, pickup_point_id, payment_type, cancel_until,
                                commission_rate, commission_amount) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $order_number, $product_id, $buyer_id, $product['seller_id'],
            $quantity, $total_price, $advance_amount, $delivery_method,
            $delivery_address, $buyer_notes, $selected_size, $selected_color,
            $delivery_fee, $shipping_company_id, $pickup_point_id, $payment_type, $cancel_until,
            $seller_commission_rate, $commission_amount
        ]);
        
        $new_order_id = $pdo->lastInsertId();
        
        $item_stmt = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, total_price, product_size, product_color) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $item_stmt->execute([
            $new_order_id, 
            $product_id, 
            $product['name'], 
            $product['price'], 
            $quantity, 
            $product['price'] * $quantity,
            $selected_size,
            $selected_color
        ]);
        
        $new_stock = $product['stock'] - $quantity;
        $update_stock = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $update_stock->execute([$new_stock, $product_id]);
        
        if ($new_stock == 0) {
            $pdo->prepare("UPDATE products SET status = 'sold' WHERE id = ?")->execute([$product_id]);
        }
        
        $pdo->commit();
        
        $success = true;
        $scroll_pos = 0; // إعادة التمرير للأعلى بعد النجاح
        
    } catch(PDOException $e) {
        $pdo->rollBack();
        $error = "حدث خطأ: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلب المنتج - سوق الرمال</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Tahoma', Arial, sans-serif; background: var(--bg-body); }
        .container { max-width: 800px; margin: 40px auto; padding: 0 20px; }
        .card { background: var(--bg-card); border-radius: 20px; box-shadow: 0 5px 25px var(--shadow-md); overflow: hidden; }
        .card-header { background: var(--bg-header-card); padding: 20px; font-size: 20px; font-weight: bold; color: var(--text-heading); border-bottom: 2px solid var(--border-gold); }
        .card-body { padding: 25px; }
        .product-summary { background: var(--bg-card-alt); padding: 15px; border-radius: 15px; margin-bottom: 25px; color: var(--text-primary); }
        .product-summary h3 { color: var(--text-heading); }
        .product-summary strong { color: var(--text-heading); }
        .payment-box { background: var(--success-bg); color: var(--success-text); padding: 15px; border-radius: 15px; margin: 15px 0; border-right: 4px solid #2e7d32; }
        .payment-box h4 { color: var(--success-text); }
        .payment-box strong { color: var(--success-text); }
        .payment-box p { color: var(--success-text); }
        .payment-option { margin: 10px 0; padding: 12px; border: 2px solid var(--border-color); border-radius: 12px; cursor: pointer; transition: all 0.3s; background: var(--bg-card); color: var(--text-primary); }
        .payment-option.selected { border-color: var(--btn-primary); background: var(--bg-card-alt); }
        .payment-option-title { font-weight: bold; font-size: 16px; margin-bottom: 5px; color: var(--text-primary); }
        .payment-option-desc { font-size: 12px; color: var(--text-secondary); }
        .ccp-info { background: var(--bg-card-alt); padding: 15px; border-radius: 15px; margin: 10px 0; color: var(--text-primary); }
        .ccp-info h4 { color: var(--text-heading); }
        .rib-container { background: var(--bg-card); padding: 12px; border-radius: 10px; margin: 10px 0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; border: 1px solid var(--border-light); }
        .rib-code { direction: ltr; unicode-bidi: embed; font-size: 16px; font-weight: bold; font-family: monospace; background: var(--bg-rib); padding: 8px 12px; border-radius: 8px; word-break: break-all; flex: 1; text-align: left; color: var(--text-primary); }
        .btn-copy { background: var(--btn-copy); color: white; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-size: 14px; }
        .btn-copy:hover { background: #0b7dda; }
        label { display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-primary); }
        input, select, textarea { width: 100%; padding: 12px; border: 1px solid var(--border-input); border-radius: 10px; margin-bottom: 20px; font-family: inherit; background: var(--bg-input); color: var(--text-primary); }
        button { background: var(--btn-primary); color: white; border: none; padding: 12px 20px; border-radius: 40px; font-size: 16px; font-weight: bold; cursor: pointer; }
        button:hover { background: var(--btn-primary-hover); }
        .success-box { background: var(--success-bg); color: var(--success-text); padding: 20px; border-radius: 15px; text-align: center; }
        .success-box h3 { color: var(--success-text); }
        .error { background: var(--error-bg); color: var(--error-text); padding: 12px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        footer { background: var(--bg-footer); color: white; text-align: center; padding: 20px; margin-top: 40px; }
        .info-text { font-size: 12px; color: var(--text-secondary); margin-top: 5px; }
        .row-2cols { display: flex; gap: 15px; margin-bottom: 15px; }
        .row-2cols > div { flex: 1; }
        .delivery-section { background: var(--bg-card-alt); padding: 15px; border-radius: 15px; margin: 15px 0; color: var(--text-primary); }
        .delivery-section h4 { color: var(--text-heading); }
        .delivery-section label { color: var(--text-primary); }
        .delivery-option { margin: 10px 0; padding: 10px; border: 1px solid var(--border-color); border-radius: 10px; cursor: pointer; background: var(--bg-card); color: var(--text-primary); }
        .delivery-option.selected { border-color: var(--btn-primary); background: var(--bg-card-alt); }
        .delivery-option strong { color: var(--text-heading); }
        .address-field { margin-top: 15px; }
        .payment-box hr { border-color: rgba(255,255,255,0.2); }
        #paymentDetailsBox { background: var(--bg-card-alt); color: var(--text-primary); border-right: 4px solid var(--btn-primary); }
        #paymentDetailsBox h4 { color: var(--text-heading); }
        #paymentDetailsBox strong { color: var(--text-heading); }
        #paymentDetailsBox p { color: var(--text-primary); }
        @media (max-width: 600px) { .row-2cols { flex-direction: column; gap: 0; } }
    </style>  
</head>
<body>
<?php include_once 'header.php'; ?>

    <div class="container" id="mainContent">
        <div class="card">
            <div class="card-header">📝 إنشاء طلب شراء</div>
            <div class="card-body">
                <?php if(isset($success) && $success === true): ?>
                    <div class="success-box" id="successSection">
                        <h3>✅ تم إنشاء الطلب بنجاح!</h3>
                        <p>رقم الطلب: <strong><?php echo $order_number; ?></strong></p>
                        
                        <div class="payment-box" style="text-align:right;">
                            <?php if($payment_type == 'advance'): ?>
                                <h4>💳 الدفعة المقدمة (<?php echo $advance_percentage; ?>%)</h4>
                                <p><strong>المبلغ المطلوب دفعه مقدمًا:</strong> <?php echo number_format($advance_amount, 2); ?> دج</p>
                                <p><strong>المبلغ المتبقي عند الاستلام:</strong> <?php echo number_format($remaining_amount, 2); ?> دج</p>
                            <?php else: ?>
                                <h4>💵 دفع عند الاستلام</h4>
                                <p><strong>المبلغ المطلوب دفعه عند الاستلام:</strong> <?php echo number_format($remaining_amount, 2); ?> دج</p>
                                <?php if($product['cancel_enabled'] && $product['cancel_hours'] > 0): ?>
                                    <p class="info-text" style="color:#e65100;">⏰ يمكنك إلغاء الطلب خلال <?php echo $product['cancel_hours']; ?> ساعة من الآن</p>
                                <?php endif; ?>
                                <p class="info-text">📌 سيتم دفع المبلغ كاملاً عند استلام الطلب</p>
                            <?php endif; ?>
                            <?php if($selected_size): ?>
                            <p><strong>📏 المقاس:</strong> <?php echo htmlspecialchars($selected_size); ?></p>
                            <?php endif; ?>
                            <?php if($selected_color): ?>
                            <p><strong>🎨 اللون:</strong> <?php echo htmlspecialchars($selected_color); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <?php if($payment_type == 'advance'): ?>
                        <div class="ccp-info">
                            <h4>📱 تعليمات الدفع عبر بريدي موب:</h4>
                            <p>1. افتح تطبيق بريدي موب</p>
                            <p>2. اختر "تحويل إلى حساب بريدي"</p>
                            
                            <div class="rib-container">
                                <code class="rib-code" id="ribValue"><?php echo htmlspecialchars($product['site_rib']); ?></code>
                                <button onclick="copyToClipboard()" class="btn-copy">📋 نسخ الرقم</button>
                            </div>
                            
                            <p>3. أدخل المبلغ: <strong><?php echo number_format($advance_amount, 2); ?> دج</strong></p>
                            <p>4. اكتب رقم الطلب: <strong><?php echo $order_number; ?></strong> في خانة التعليق</p>
                            <p>5. بعد التحويل، اضغط الزر أدناه</p>
                        </div>
                        
                        <form method="POST" action="confirm_buyer_payment.php" style="margin-top:15px;">
                            <input type="hidden" name="order_id" value="<?php echo $new_order_id; ?>">
                            <button type="submit" style="background:#2196f3;">💰 لقد قمت بالدفع</button>
                        </form>
                        
                        <p class="info-text" style="margin-top:15px;">⚠️ لن يتم تجهيز الطلب إلا بعد تأكيد البائع استلام الدفعة المقدمة</p>
                        <?php else: ?>
                        <div class="ccp-info" style="background:var(--success-bg); color:var(--success-text);">
                            <h4>✅ تأكيد الطلب</h4>
                            <p>تم إنشاء طلبك بنجاح. في انتظار تأكيد البائع للطلب.</p>
                            <p><strong>المبلغ المطلوب عند الاستلام:</strong> <?php echo number_format($remaining_amount, 2); ?> دج</p>
                            <?php if($product['cancel_enabled'] && $product['cancel_hours'] > 0): ?>
                                <p class="info-text" style="color:#e65100;">⏰ يمكنك إلغاء الطلب خلال <?php echo $product['cancel_hours']; ?> ساعة من الآن</p>
                            <?php endif; ?>
                            <p class="info-text">📌 سيتم إعلامك بعد تأكيد البائع للطلب</p>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php if($error): ?>
                        <div class="error">❌ <?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <div class="product-summary">
                        <h3>🛍️ المنتج المطلوب</h3>
                        <p><strong><?php echo htmlspecialchars($product['name']); ?></strong></p>
                        <p>سعر القطعة: <?php echo number_format($product['price'], 2); ?> دج</p>
                        <p>المتبقي في المخزون: <?php echo $product['stock']; ?> قطعة</p>
                        <p>البائعة: <?php echo htmlspecialchars($product['seller_name']); ?></p>
                    </div>
                    
                    <form method="POST" id="orderForm">
                        <input type="hidden" name="scroll_pos" id="scrollPosInput" value="0">
                        
                        <div style="margin-bottom:20px;">
                            <label>📦 الكمية المطلوبة</label>
                            <input type="number" name="quantity" id="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>" required>
                        </div>
                        
                        <div class="row-2cols">
                            <?php if(count($sizes_array) > 0): ?>
                            <div>
                                <label>📏 المقاس</label>
                                <select name="size" id="sizeSelect" required>
                                    <option value="">-- اختر المقاس --</option>
                                    <?php foreach($sizes_array as $size): ?>
                                        <option value="<?php echo htmlspecialchars($size); ?>"><?php echo htmlspecialchars($size); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php else: ?>
                                <input type="hidden" name="size" value="">
                            <?php endif; ?>
                            
                            <?php if(count($colors_array) > 0): ?>
                            <div>
                                <label>🎨 اللون</label>
                                <select name="color" id="colorSelect" required>
                                    <option value="">-- اختر اللون --</option>
                                    <?php foreach($colors_array as $color): ?>
                                        <option value="<?php echo htmlspecialchars($color); ?>"><?php echo htmlspecialchars($color); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php else: ?>
                                <input type="hidden" name="color" value="">
                            <?php endif; ?>
                        </div>
                        
                        <div class="delivery-section">
                            <h4>📍 عنوان التوصيل</h4>
                            
                            <div class="form-group">
                                <label>الولاية</label>
                                <select id="wilayaSelect" required>
                                    <option value="">-- اختر الولاية --</option>
                                    <?php foreach($wilayas as $w): ?>
                                        <option value="<?php echo htmlspecialchars($w); ?>"><?php echo htmlspecialchars($w); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>البلدية</label>
                                <select id="communeSelect" required disabled>
                                    <option value="">-- اختر البلدية --</option>
                                </select>
                            </div>
                            
                            <div id="deliveryOptions" style="display: none;">
                                <label>🚚 طريقة التوصيل</label>
                                <div id="deliveryMethods"></div>
                            </div>
                            
                            <div id="homeAddressContainer" class="address-field" style="display: none;">
                                <label>🏠 عنوان التوصيل</label>
                                <textarea name="delivery_address" id="deliveryAddress" rows="3" placeholder="العنوان الكامل (الحي، الشارع، رقم البيت، الطابق...)"></textarea>
                            </div>
                            
                            <input type="hidden" name="delivery_method" id="deliveryMethod" value="">
                            <input type="hidden" name="delivery_fee" id="deliveryFee" value="0">
                            <input type="hidden" name="shipping_company_id" id="shippingCompanyId" value="">
                            <input type="hidden" name="pickup_point_id" id="pickupPointId" value="">
                            <input type="hidden" name="wilaya" id="selectedWilaya" value="">
                            <input type="hidden" name="commune" id="selectedCommune" value="">
                        </div>
                        
                        <label>📝 ملاحظات إضافية (اختياري)</label>
                        <textarea name="buyer_notes" rows="2" placeholder="أي معلومات إضافية..."></textarea>
                        
                        <div class="delivery-section" style="margin-top:20px;">
                            <h4>💰 طريقة الدفع</h4>
                            <input type="hidden" name="payment_type" id="paymentType" value="<?php echo ($system_payment_type == 'cash_only') ? 'cash_on_delivery' : 'advance'; ?>">
                            
                            <?php if($system_payment_type == 'both' || $system_payment_type == 'advance_only'): ?>
                            <div id="paymentMethodAdvance" class="payment-option <?php echo ($system_payment_type == 'advance_only') ? 'selected' : 'selected'; ?>" onclick="selectPaymentMethod('advance')">
                                <div class="payment-option-title">💳 دفع عربون (25% مقدماً)</div>
                                <div class="payment-option-desc">ادفع 25% من قيمة الطلب عبر بريدي موب كعربون، والباقي عند الاستلام</div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if($system_payment_type == 'both' || $system_payment_type == 'cash_only'): ?>
                            <div id="paymentMethodCash" class="payment-option <?php echo ($system_payment_type == 'cash_only') ? 'selected' : ''; ?>" onclick="selectPaymentMethod('cash_on_delivery')">
                                <div class="payment-option-title">💵 دفع عند الاستلام (100%)</div>
                                <div class="payment-option-desc">ادفع كامل المبلغ عند استلام الطلب (بدون دفع مقدم)</div>
                                <?php if($product['cancel_enabled'] && $product['cancel_hours'] > 0): ?>
                                    <div class="info-text" style="margin-top:5px;">⏱️ يمكنك إلغاء الطلب خلال <?php echo $product['cancel_hours']; ?> ساعة من إنشائه</div>
                                <?php elseif($product['cancel_enabled'] && $product['cancel_hours'] == 0): ?>
                                    <div class="info-text" style="margin-top:5px;">⚠️ لا يمكن إلغاء الطلب بعد إنشائه</div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="payment-box" id="paymentDetailsBox">
                            <h4>💰 تفاصيل الدفع</h4>
                            <p>سعر القطعة: <span id="unit-price"><?php echo number_format($product['price'], 2); ?></span> دج</p>
                            <p>الكمية: <span id="qty-display">1</span></p>
                            <p>إجمالي المنتجات: <span id="products-total"><?php echo number_format($product['price'], 2); ?></span> دج</p>
                            <p>سعر التوصيل: <span id="delivery-fee-display">0</span> دج</p>
                            <hr>
                            <p><strong>إجمالي الطلب: <span id="total-price"><?php echo number_format($product['price'], 2); ?></span> دج</strong></p>
                            <div id="advanceDetails" style="<?php echo ($system_payment_type == 'cash_only') ? 'display:none;' : 'display:block;'; ?>">
                                <p><strong>💳 الدفعة المقدمة (25%):</strong> <span id="advance-amount"><?php echo number_format($product['price'] * 0.25, 2); ?></span> دج</p>
                                <p><strong>💵 المتبقي عند الاستلام:</strong> <span id="remaining-amount"><?php echo number_format($product['price'] * 0.75, 2); ?></span> دج</p>
                            </div>
                            <div id="cashDetails" style="<?php echo ($system_payment_type == 'advance_only') ? 'display:none;' : 'display:block;'; ?>">
                                <p><strong>💵 المطلوب دفعه عند الاستلام:</strong> <span id="cash-amount"><?php echo number_format($product['price'], 2); ?></span> دج</p>
                                <div class="info-text">⚠️ سيتم دفع المبلغ كاملاً عند استلام الطلب</div>
                            </div>
                        </div>
                        
                        <button type="submit" id="submitBtn">✅ تأكيد الطلب</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        const price = <?php echo $product['price']; ?>;
        const stock = <?php echo $product['stock']; ?>;
        const advancePercent = <?php echo $advance_percentage; ?>;
        let currentDeliveryFee = 0;
        let currentPaymentType = '<?php echo ($system_payment_type == "cash_only") ? "cash_on_delivery" : "advance"; ?>';
        const systemPaymentType = '<?php echo $system_payment_type; ?>';
        
        window.addEventListener('load', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        
        function selectPaymentMethod(type) {
            currentPaymentType = type;
            document.getElementById('paymentType').value = type;
            
            var advanceDiv = document.getElementById('paymentMethodAdvance');
            var cashDiv = document.getElementById('paymentMethodCash');
            
            if (advanceDiv) advanceDiv.classList.remove('selected');
            if (cashDiv) cashDiv.classList.remove('selected');
            
            if (type === 'advance') {
                if (advanceDiv) advanceDiv.classList.add('selected');
                document.getElementById('advanceDetails').style.display = 'block';
                document.getElementById('cashDetails').style.display = 'none';
            } else {
                if (cashDiv) cashDiv.classList.add('selected');
                document.getElementById('advanceDetails').style.display = 'none';
                document.getElementById('cashDetails').style.display = 'block';
            }
            updateTotals();
        }
        
        document.getElementById('wilayaSelect').addEventListener('change', function() {
            let wilaya = this.value;
            document.getElementById('selectedWilaya').value = wilaya;
            let communeSelect = document.getElementById('communeSelect');
            
            if(!wilaya) {
                communeSelect.innerHTML = '<option value="">-- اختر البلدية --</option>';
                communeSelect.disabled = true;
                document.getElementById('deliveryOptions').style.display = 'none';
                return;
            }
            
            fetch(`get_communes_by_wilaya.php?wilaya=${encodeURIComponent(wilaya)}`)
                .then(res => res.json())
                .then(data => {
                    communeSelect.innerHTML = '<option value="">-- اختر البلدية --</option>';
                    data.forEach(commune => {
                        communeSelect.innerHTML += `<option value="${commune}">${commune}</option>`;
                    });
                    communeSelect.disabled = false;
                });
        });
        
        document.getElementById('communeSelect').addEventListener('change', function() {
            let commune = this.value;
            let wilaya = document.getElementById('wilayaSelect').value;
            document.getElementById('selectedCommune').value = commune;
            
            if(!commune) {
                document.getElementById('deliveryOptions').style.display = 'none';
                return;
            }
            
            fetch(`get_delivery_options.php?wilaya=${encodeURIComponent(wilaya)}&commune=${encodeURIComponent(commune)}`)
                .then(res => res.json())
                .then(data => {
                    let container = document.getElementById('deliveryMethods');
                    container.innerHTML = '';
                    
                    if(data.home_available) {
                        let homeDiv = document.createElement('div');
                        homeDiv.className = 'delivery-option';
                        homeDiv.innerHTML = `<strong>🏠 توصيل للمنزل</strong><br>سعر التوصيل: ${parseFloat(data.home_fee).toFixed(2)} دج`;
                        homeDiv.onclick = () => selectDeliveryMethod('home', data.home_fee, data.home_company_id, null);
                        container.appendChild(homeDiv);
                    }
                    
                    if(data.pickup_available && data.pickup_points) {
                        let pickupDiv = document.createElement('div');
                        pickupDiv.className = 'delivery-option';
                        pickupDiv.innerHTML = `<strong>📍 استلام من نقطة تجميع</strong><br>سعر التوصيل: ${parseFloat(data.pickup_fee).toFixed(2)} دج<br><small>${data.pickup_points.name} - ${data.pickup_points.address}</small>`;
                        pickupDiv.onclick = () => selectDeliveryMethod('pickup', data.pickup_fee, data.pickup_company_id, data.pickup_points.id);
                        container.appendChild(pickupDiv);
                    }
                    
                    if(data.home_available || data.pickup_available) {
                        document.getElementById('deliveryOptions').style.display = 'block';
                    } else {
                        container.innerHTML = '<p style="color:#c62828;">⚠️ لا توجد خدمات توصيل متوفرة في هذه المنطقة</p>';
                        document.getElementById('deliveryOptions').style.display = 'block';
                    }
                });
        });
        
        function selectDeliveryMethod(method, fee, companyId, pickupPointId) {
            document.querySelectorAll('.delivery-option').forEach(opt => opt.classList.remove('selected'));
            event.target.closest('.delivery-option').classList.add('selected');
            
            document.getElementById('deliveryMethod').value = method;
            currentDeliveryFee = parseFloat(fee);
            document.getElementById('deliveryFee').value = currentDeliveryFee;
            document.getElementById('shippingCompanyId').value = companyId;
            
            if(method === 'pickup' && pickupPointId) {
                document.getElementById('pickupPointId').value = pickupPointId;
                document.getElementById('homeAddressContainer').style.display = 'none';
                document.getElementById('deliveryAddress').required = false;
            } else {
                document.getElementById('pickupPointId').value = '';
                document.getElementById('homeAddressContainer').style.display = 'block';
                document.getElementById('deliveryAddress').required = true;
            }
            
            updateTotals();
        }
        
        function updateTotals() {
            let qty = parseInt(document.getElementById('quantity').value) || 1;
            if(qty > stock) qty = stock;
            if(qty < 1) qty = 1;
            document.getElementById('quantity').value = qty;
            document.getElementById('qty-display').innerText = qty;
            
            let productsTotal = price * qty;
            let deliveryFee = parseFloat(document.getElementById('deliveryFee').value) || 0;
            let total = productsTotal + deliveryFee;
            
            document.getElementById('products-total').innerText = productsTotal.toFixed(2);
            document.getElementById('delivery-fee-display').innerText = deliveryFee.toFixed(2);
            document.getElementById('total-price').innerText = total.toFixed(2);
            
            if (currentPaymentType === 'advance') {
                let advance = total * (advancePercent / 100);
                let remaining = total - advance;
                document.getElementById('advance-amount').innerText = advance.toFixed(2);
                document.getElementById('remaining-amount').innerText = remaining.toFixed(2);
                document.getElementById('cash-amount').innerText = total.toFixed(2);
            } else {
                document.getElementById('cash-amount').innerText = total.toFixed(2);
            }
        }
        
        function copyToClipboard() {
            var ribText = document.getElementById('ribValue');
            if (!ribText) return;
            var text = ribText.innerText.replace(/\s/g, '').trim();
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            alert('✅ تم نسخ رقم RIB: ' + text);
        }
        
        document.getElementById('quantity').addEventListener('change', updateTotals);
        document.getElementById('quantity').addEventListener('input', updateTotals);
        
        document.getElementById('submitBtn').addEventListener('click', function(e) {
            let deliveryMethod = document.getElementById('deliveryMethod').value;
            if(!deliveryMethod) {
                e.preventDefault();
                alert('⚠️ يرجى اختيار طريقة التوصيل أولاً');
                return;
            }
            if(deliveryMethod === 'home') {
                let address = document.getElementById('deliveryAddress').value.trim();
                if(!address) {
                    e.preventDefault();
                    alert('⚠️ يرجى إدخال عنوان التوصيل');
                }
            }
        });
    </script>
    <?php include_once 'footer.php'; ?>
    <?php include_once 'bottom_nav.php'; ?>
</body>
</html>