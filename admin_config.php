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
// التحقق من صلاحية المدير
// ============================================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: login.php");
    exit();
}

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
// المتغيرات المطلوبة للهيدر والشريط السفلي
// ============================================================
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = "لوحة تحكم المدير";

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

// حذف جميع الإشعارات
if (isset($_GET['clear_notifications'])) {
    $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$_SESSION['user_id']]);
    header("Location: admin_dashboard.php");
    exit();
}

// ============================================================
// دوال إدارة العمولات
// ============================================================
function getDefaultCommission($pdo) {
    try {
        $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'default_commission_rate'");
        $result = $stmt->fetch();
        return $result ? floatval($result['setting_value']) : 7;
    } catch(PDOException $e) {
        return 7;
    }
}

function getSellerCommissionRate($pdo, $seller_id) {
    try {
        $stmt = $pdo->prepare("SELECT custom_commission_rate FROM users WHERE id = ?");
        $stmt->execute([$seller_id]);
        $custom_rate = $stmt->fetchColumn();
        if ($custom_rate !== null && $custom_rate !== '') {
            return floatval($custom_rate);
        }
        return getDefaultCommission($pdo);
    } catch(PDOException $e) {
        return getDefaultCommission($pdo);
    }
}

// ============================================================
// دوال مساعدة للترجمة
// ============================================================
function getPaymentTypeText($type) {
    if ($type == 'advance') return '💳 عربون (25%)';
    elseif ($type == 'cash_on_delivery') return '💵 عند الاستلام';
    return $type ?: 'غير محدد';
}

function translateOrderStatus($status) {
    $statuses = [
        'pending' => '⏳ قيد الانتظار',
        'advance_paid_by_buyer' => '💰 تم إشعار الدفع',
        'advance_confirmed_by_seller' => '✅ تم تأكيد الطلب',
        'ready_for_shipping' => '📦 جاهز للشحن',
        'picked_by_shipping' => '📦 استلمته شركة الشحن',
        'in_transit' => '🚚 قيد التوصيل للمنزل',
        'in_transit_to_pickup' => '🚚 قيد التوصيل للنقطة',
        'arrived_at_pickup' => '📍 وصل للنقطة',
        'delivered_to_buyer' => '🎉 تم التسليم',
        'cancelled' => '❌ ملغي'
    ];
    return $statuses[$status] ?? $status;
}

function translateComplaintType($type) {
    $types = [
        'product' => '⚠️ منتج',
        'seller' => '👩 بائع',
        'delivery' => '🚚 توصيل',
        'other' => '📝 أخرى'
    ];
    return $types[$type] ?? $type;
}

function translateComplaintStatus($status) {
    $statuses = [
        'pending' => '⏳ قيد الانتظار',
        'reviewing' => '🔍 قيد المراجعة',
        'resolved' => '✅ تم الحل',
        'rejected' => '❌ مرفوض'
    ];
    return $statuses[$status] ?? $status;
}

// ============================================================
// إعدادات نظام الدفع
// ============================================================
$system_payment_type = 'both';
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'payment_type'");
    $stmt->execute();
    $result = $stmt->fetch();
    if ($result) $system_payment_type = $result['setting_value'];
} catch(PDOException $e) {}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_payment_settings'])) {
    $new_payment_type = $_POST['payment_type'];
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (id INT AUTO_INCREMENT PRIMARY KEY, setting_key VARCHAR(100) NOT NULL UNIQUE, setting_value TEXT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('payment_type', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$new_payment_type, $new_payment_type]);
        $system_payment_type = $new_payment_type;
        $_SESSION['message'] = "✅ تم تحديث إعدادات نظام الدفع بنجاح";
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ خطأ: " . $e->getMessage();
    }
    header("Location: admin_dashboard.php?active_tab=payment_settings&scroll_pos=" . ($_POST['scroll_pos'] ?? 0));
    exit();
}

// ============================================================
// إعدادات وضع الصيانة
// ============================================================
$maintenance_mode = false;
$maintenance_message = "🔧 الموقع تحت الصيانة حالياً. نعتذر عن الإزعاج، سيعود قريباً.";
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
    $stmt->execute();
    $maintenance_mode = $stmt->fetchColumn() == '1';
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_message'");
    $stmt->execute();
    $msg = $stmt->fetchColumn();
    if ($msg) $maintenance_message = $msg;
} catch(PDOException $e) {}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_maintenance'])) {
    $new_maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
    $new_maintenance_message = trim($_POST['maintenance_message']);
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (id INT AUTO_INCREMENT PRIMARY KEY, setting_key VARCHAR(100) NOT NULL UNIQUE, setting_value TEXT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('maintenance_mode', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$new_maintenance_mode, $new_maintenance_mode]);
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('maintenance_message', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$new_maintenance_message, $new_maintenance_message]);
        $_SESSION['message'] = "✅ تم تحديث إعدادات وضع الصيانة بنجاح";
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ خطأ: " . $e->getMessage();
    }
    header("Location: admin_dashboard.php?active_tab=maintenance&scroll_pos=" . ($_POST['scroll_pos'] ?? 0));
    exit();
}

// ============================================================
// إرسال الإشعارات من المدير للمستخدمين
// ============================================================
$users_list = [];
try {
    $users_list = $pdo->query("SELECT id, full_name, user_type FROM users WHERE user_type != 'admin' ORDER BY user_type, full_name")->fetchAll();
} catch(PDOException $e) {}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_notification'])) {
    $target_type = $_POST['target_type'];
    $specific_user_id = !empty($_POST['specific_user_id']) ? intval($_POST['specific_user_id']) : null;
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $link = trim($_POST['link'] ?? '');
    
    if (!empty($title) && !empty($message)) {
        try {
            if ($target_type == 'all') {
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, user_type, title, message, link) SELECT id, user_type, ?, ?, ? FROM users WHERE user_type != 'admin'");
                $stmt->execute([$title, $message, $link]);
                $_SESSION['message'] = "✅ تم إرسال الإشعار لجميع المستخدمين";
            } elseif ($target_type == 'buyers') {
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, user_type, title, message, link) SELECT id, user_type, ?, ?, ? FROM users WHERE user_type = 'buyer'");
                $stmt->execute([$title, $message, $link]);
                $_SESSION['message'] = "✅ تم إرسال الإشعار لجميع المشترين";
            } elseif ($target_type == 'sellers') {
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, user_type, title, message, link) SELECT id, user_type, ?, ?, ? FROM users WHERE user_type = 'seller'");
                $stmt->execute([$title, $message, $link]);
                $_SESSION['message'] = "✅ تم إرسال الإشعار لجميع البائعين";
            } elseif ($target_type == 'specific_user' && $specific_user_id) {
                $stmt = $pdo->prepare("SELECT user_type FROM users WHERE id = ?");
                $stmt->execute([$specific_user_id]);
                $user = $stmt->fetch();
                if ($user) {
                    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, user_type, title, message, link) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$specific_user_id, $user['user_type'], $title, $message, $link]);
                    $_SESSION['message'] = "✅ تم إرسال الإشعار للمستخدم";
                } else {
                    $_SESSION['message'] = "⚠️ المستخدم غير موجود";
                }
            }
        } catch(PDOException $e) {
            $_SESSION['message'] = "❌ خطأ: " . $e->getMessage();
        }
    } else {
        $_SESSION['message'] = "⚠️ الرجاء إدخال عنوان ورسالة الإشعار";
    }
    header("Location: admin_dashboard.php?active_tab=notifications&scroll_pos=" . ($_POST['scroll_pos'] ?? 0));
    exit();
}

// حفظ مكان التمرير والتبويب النشط
if (isset($_GET['scroll_pos']) && is_numeric($_GET['scroll_pos'])) {
    $_SESSION['scroll_pos'] = intval($_GET['scroll_pos']);
}
if (isset($_GET['active_tab'])) {
    $_SESSION['active_tab'] = $_GET['active_tab'];
}
$scroll_pos = $_SESSION['scroll_pos'] ?? 0;
$active_tab = $_SESSION['active_tab'] ?? 'dashboard';

// ============================================================
// إدارة عمولات البائعين
// ============================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_default_commission'])) {
    $new_default_commission = floatval($_POST['default_commission']);
    try {
        $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'default_commission_rate'");
        $stmt->execute([$new_default_commission]);
        $_SESSION['message'] = "✅ تم تحديث نسبة العمولة الافتراضية إلى $new_default_commission%";
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ خطأ: " . $e->getMessage();
    }
    header("Location: admin_dashboard.php?active_tab=sellers&scroll_pos=" . ($_POST['scroll_pos'] ?? 0));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['set_custom_commission'])) {
    $seller_id = intval($_POST['seller_id']);
    $custom_rate = ($_POST['custom_rate'] !== '') ? floatval($_POST['custom_rate']) : null;
    $commission_note = trim($_POST['commission_note']);
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET custom_commission_rate = ?, commission_note = ? WHERE id = ? AND user_type = 'seller'");
        $stmt->execute([$custom_rate, $commission_note, $seller_id]);
        
        if ($custom_rate === null) {
            $_SESSION['message'] = "✅ تم إعادة تعيين العمولة للقيمة الافتراضية";
        } elseif ($custom_rate == 0) {
            $_SESSION['message'] = "✅ تم إعفاء البائع من العمولة";
        } else {
            $_SESSION['message'] = "✅ تم تعيين عمولة مخصصة بنسبة $custom_rate% للبائع";
        }
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ خطأ: " . $e->getMessage();
    }
    header("Location: admin_dashboard.php?active_tab=sellers&scroll_pos=" . ($_POST['scroll_pos'] ?? 0));
    exit();
}

if (isset($_GET['reset_commission'])) {
    $seller_id = intval($_GET['reset_commission']);
    try {
        $pdo->prepare("UPDATE users SET custom_commission_rate = NULL, commission_note = NULL WHERE id = ? AND user_type = 'seller'")->execute([$seller_id]);
        $_SESSION['message'] = "✅ تم إعادة تعيين العمولة للقيمة الافتراضية";
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ خطأ: " . $e->getMessage();
    }
    header("Location: admin_dashboard.php?active_tab=sellers&scroll_pos=" . ($_GET['scroll_pos'] ?? 0));
    exit();
}

// ============================================================
// معالجة البائعين
// ============================================================
if (isset($_GET['toggle_disable']) && isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];
    $pdo->prepare("UPDATE users SET is_disabled = NOT is_disabled WHERE id = ? AND user_type = 'seller'")->execute([$user_id]);
    $_SESSION['message'] = "✅ تم تحديث حالة البائع";
    header("Location: admin_dashboard.php?active_tab=sellers&scroll_pos=" . ($_GET['scroll_pos'] ?? 0));
    exit();
}

if (isset($_GET['toggle_status']) && isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];
    $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ? AND user_type = 'seller'")->execute([$user_id]);
    $_SESSION['message'] = "✅ تم تحديث حالة البائع";
    header("Location: admin_dashboard.php?active_tab=sellers&scroll_pos=" . ($_GET['scroll_pos'] ?? 0));
    exit();
}

if (isset($_GET['delete_user']) && isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];
    $pdo->prepare("DELETE FROM users WHERE id = ? AND user_type = 'seller'")->execute([$user_id]);
    $_SESSION['message'] = "✅ تم حذف البائع";
    header("Location: admin_dashboard.php?active_tab=sellers&scroll_pos=" . ($_GET['scroll_pos'] ?? 0));
    exit();
}

// ============================================================
// معالجة المنتجات
// ============================================================
if (isset($_GET['delete_product']) && isset($_GET['product_id'])) {
    $product_id = $_GET['product_id'];
    $check = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE product_id = ?");
    $check->execute([$product_id]);
    $orders_count = $check->fetchColumn();
    
    if ($orders_count > 0) {
        $_SESSION['message'] = "❌ لا يمكن حذف المنتج لأنه مرتبط بـ $orders_count طلب(ات) سابقة";
    } else {
        try {
            $images = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
            $images->execute([$product_id]);
            foreach($images->fetchAll() as $img) {
                if(file_exists($img['image_path'])) unlink($img['image_path']);
            }
            $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$product_id]);
            $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$product_id]);
            $_SESSION['message'] = "✅ تم حذف المنتج";
        } catch(PDOException $e) {
            $_SESSION['message'] = "❌ خطأ: " . $e->getMessage();
        }
    }
    header("Location: admin_dashboard.php?active_tab=products&scroll_pos=" . ($_GET['scroll_pos'] ?? 0));
    exit();
}

// ============================================================
// إدارة الفئات
// ============================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_category'])) {
    $cat_name = trim($_POST['cat_name']);
    $cat_icon = trim($_POST['cat_icon']);
    $cat_active = isset($_POST['cat_active']) ? 1 : 0;
    if (!empty($cat_name)) {
        $stmt = $pdo->prepare("INSERT INTO categories (name, icon, is_active) VALUES (?, ?, ?)");
        $stmt->execute([$cat_name, $cat_icon, $cat_active]);
        $_SESSION['message'] = "✅ تم إضافة الفئة بنجاح";
    }
    header("Location: admin_dashboard.php?active_tab=categories&scroll_pos=" . ($_POST['scroll_pos'] ?? 0));
    exit();
}

if (isset($_GET['delete_category']) && isset($_GET['cat_id'])) {
    $cat_id = intval($_GET['cat_id']);
    try {
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$cat_id]);
        $_SESSION['message'] = "✅ تم حذف الفئة";
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ لا يمكن حذف الفئة لوجود منتجات مرتبطة بها";
    }
    header("Location: admin_dashboard.php?active_tab=categories&scroll_pos=" . ($_GET['scroll_pos'] ?? 0));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_category'])) {
    $cat_id = intval($_POST['cat_id']);
    $cat_name = trim($_POST['cat_name']);
    $cat_icon = trim($_POST['cat_icon']);
    $cat_active = isset($_POST['cat_active']) ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE categories SET name = ?, icon = ?, is_active = ? WHERE id = ?");
    $stmt->execute([$cat_name, $cat_icon, $cat_active, $cat_id]);
    $_SESSION['message'] = "✅ تم تعديل الفئة";
    header("Location: admin_dashboard.php?active_tab=categories&scroll_pos=" . ($_POST['scroll_pos'] ?? 0));
    exit();
}

// ============================================================
// الشكاوى
// ============================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_complaint'])) {
    $complaint_id = $_POST['complaint_id'];
    $new_status = $_POST['status'];
    $admin_response = trim($_POST['admin_response']);
    $stmt = $pdo->prepare("UPDATE complaints SET status = ?, admin_response = ? WHERE id = ?");
    $stmt->execute([$new_status, $admin_response, $complaint_id]);
    $_SESSION['message'] = "✅ تم تحديث حالة الشكوى";
    header("Location: admin_dashboard.php?active_tab=complaints&scroll_pos=" . ($_POST['scroll_pos'] ?? 0));
    exit();
}

// ============================================================
// معالجة الرد على تذاكر الدعم
// ============================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reply_ticket'])) {
    $ticket_id = intval($_POST['reply_ticket_id']);
    $reply_message = trim($_POST['reply_message']);
    $new_status = $_POST['ticket_status'];
    $admin_id = $_SESSION['user_id'];
    
    if (!empty($reply_message)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, user_type, message) VALUES (?, ?, 'admin', ?)");
            $stmt->execute([$ticket_id, $admin_id, $reply_message]);
            $stmt = $pdo->prepare("UPDATE support_tickets SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $ticket_id]);
            $stmt = $pdo->prepare("SELECT user_id FROM support_tickets WHERE id = ?");
            $stmt->execute([$ticket_id]);
            $owner = $stmt->fetch();
            if ($owner) {
                $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, '✅ تم الرد على تذكرتك', ?, ?)");
                $notif->execute([$owner['user_id'], "تم الرد على تذكرتك رقم #$ticket_id", "view_ticket.php?id=$ticket_id"]);
            }
            $pdo->commit();
            $_SESSION['message'] = "✅ تم إرسال الرد بنجاح";
        } catch(PDOException $e) {
            $pdo->rollBack();
            $_SESSION['message'] = "❌ خطأ: " . $e->getMessage();
        }
    } else {
        $_SESSION['message'] = "⚠️ الرجاء كتابة الرد";
    }
    header("Location: admin_dashboard.php?active_tab=support_tickets&scroll_pos=" . ($_POST['scroll_pos'] ?? 0));
    exit();
}

// ============================================================
// أرشيف الطلبات والفواتير
// ============================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['archive_seller_orders'])) {
    $seller_id = intval($_POST['seller_id']);
    $month_year = date('Y-m');
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmt->execute([$seller_id]);
        $seller_name = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("
            SELECT o.*, oi.total_price as item_total
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            WHERE o.seller_id = ? AND o.order_status = 'delivered_to_buyer'
            AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
        ");
        $stmt->execute([$seller_id]);
        $orders_to_archive = $stmt->fetchAll();
        
        if (count($orders_to_archive) > 0) {
            $orders_count = count($orders_to_archive);
            $total_sales = 0;
            foreach ($orders_to_archive as $order) { $total_sales += $order['item_total']; }
            
            $commission_sum_stmt = $pdo->prepare("
                SELECT SUM(commission_amount) as total_commission 
                FROM orders 
                WHERE seller_id = ? AND order_status = 'delivered_to_buyer' 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
            ");
            $commission_sum_stmt->execute([$seller_id]);
            $total_commission = $commission_sum_stmt->fetchColumn() ?? 0;
            
            $advance_sum_stmt = $pdo->prepare("
                SELECT SUM(advance_amount) as total_advance 
                FROM orders 
                WHERE seller_id = ? AND order_status = 'delivered_to_buyer' 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH) AND payment_type = 'advance'
            ");
            $advance_sum_stmt->execute([$seller_id]);
            $total_advance = $advance_sum_stmt->fetchColumn() ?? 0;
            
            $cash_collected = $total_sales - $total_advance;
            $net_amount = $total_advance - $total_commission;
            
            $stmt = $pdo->prepare("
                INSERT INTO monthly_invoices (seller_id, seller_name, month_year, orders_count, total_sales, total_commission, net_amount, total_advance, cash_collected)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                orders_count = VALUES(orders_count), total_sales = VALUES(total_sales),
                total_commission = VALUES(total_commission), net_amount = VALUES(net_amount), 
                total_advance = VALUES(total_advance), cash_collected = VALUES(cash_collected), invoice_date = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$seller_id, $seller_name, $month_year, $orders_count, $total_sales, $total_commission, $net_amount, $total_advance, $cash_collected]);
            
            foreach ($orders_to_archive as $order) {
                $stmt = $pdo->prepare("
                    INSERT INTO orders_archive (order_number, product_id, buyer_id, seller_id, quantity, total_price, 
                    advance_amount, delivery_method, delivery_address, buyer_notes, product_size, product_color, 
                    delivery_fee, shipping_company_id, pickup_point_id, payment_type, order_status, created_at, 
                    completed_at, month_year)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                ");
                $stmt->execute([
                    $order['order_number'], $order['product_id'], $order['buyer_id'], $order['seller_id'],
                    $order['quantity'], $order['total_price'], $order['advance_amount'], $order['delivery_method'],
                    $order['delivery_address'], $order['buyer_notes'], $order['product_size'], $order['product_color'],
                    $order['delivery_fee'], $order['shipping_company_id'], $order['pickup_point_id'], $order['payment_type'],
                    $order['order_status'], $order['created_at'], $month_year
                ]);
            }
            
            $stmt = $pdo->prepare("DELETE FROM orders WHERE seller_id = ? AND order_status = 'delivered_to_buyer' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)");
            $stmt->execute([$seller_id]);
            $pdo->commit();
            $_SESSION['message'] = "✅ تم أرشفة طلبات البائع " . htmlspecialchars($seller_name) . " بنجاح";
        } else {
            $_SESSION['message'] = "⚠️ لا توجد طلبات مكتملة لهذا البائع في الشهر الحالي";
        }
    } catch(PDOException $e) {
        $pdo->rollBack();
        $_SESSION['message'] = "❌ خطأ في الأرشفة: " . $e->getMessage();
    }
    header("Location: admin_dashboard.php?active_tab=archive&scroll_pos=" . ($_POST['scroll_pos'] ?? 0));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['archive_all_sellers'])) {
    $month_year = date('Y-m');
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT DISTINCT seller_id FROM orders WHERE order_status = 'delivered_to_buyer' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)");
        $stmt->execute();
        $sellers_with_orders = $stmt->fetchAll();
        $total_archived = 0;
        
        foreach ($sellers_with_orders as $seller) {
            $seller_id = $seller['seller_id'];
            $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
            $stmt->execute([$seller_id]);
            $seller_name = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("
                SELECT o.*, oi.total_price as item_total
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                WHERE o.seller_id = ? AND o.order_status = 'delivered_to_buyer'
                AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
            ");
            $stmt->execute([$seller_id]);
            $orders_to_archive = $stmt->fetchAll();
            
            if (count($orders_to_archive) > 0) {
                $orders_count = count($orders_to_archive);
                $total_sales = 0;
                foreach ($orders_to_archive as $order) { $total_sales += $order['item_total']; }
                
                $commission_sum_stmt = $pdo->prepare("
                    SELECT SUM(commission_amount) as total_commission 
                    FROM orders 
                    WHERE seller_id = ? AND order_status = 'delivered_to_buyer' 
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
                ");
                $commission_sum_stmt->execute([$seller_id]);
                $total_commission = $commission_sum_stmt->fetchColumn() ?? 0;
                
                $advance_sum_stmt = $pdo->prepare("
                    SELECT SUM(advance_amount) as total_advance 
                    FROM orders 
                    WHERE seller_id = ? AND order_status = 'delivered_to_buyer' 
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH) AND payment_type = 'advance'
                ");
                $advance_sum_stmt->execute([$seller_id]);
                $total_advance = $advance_sum_stmt->fetchColumn() ?? 0;
                
                $cash_collected = $total_sales - $total_advance;
                $net_amount = $total_advance - $total_commission;
                
                $stmt = $pdo->prepare("
                    INSERT INTO monthly_invoices (seller_id, seller_name, month_year, orders_count, total_sales, total_commission, net_amount, total_advance, cash_collected)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    orders_count = VALUES(orders_count), total_sales = VALUES(total_sales),
                    total_commission = VALUES(total_commission), net_amount = VALUES(net_amount),
                    total_advance = VALUES(total_advance), cash_collected = VALUES(cash_collected)
                ");
                $stmt->execute([$seller_id, $seller_name, $month_year, $orders_count, $total_sales, $total_commission, $net_amount, $total_advance, $cash_collected]);
                
                foreach ($orders_to_archive as $order) {
                    $stmt = $pdo->prepare("
                        INSERT INTO orders_archive (order_number, product_id, buyer_id, seller_id, quantity, total_price, 
                        advance_amount, delivery_method, delivery_address, buyer_notes, product_size, product_color, 
                        delivery_fee, shipping_company_id, pickup_point_id, payment_type, order_status, created_at, 
                        completed_at, month_year)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                    ");
                    $stmt->execute([
                        $order['order_number'], $order['product_id'], $order['buyer_id'], $order['seller_id'],
                        $order['quantity'], $order['total_price'], $order['advance_amount'], $order['delivery_method'],
                        $order['delivery_address'], $order['buyer_notes'], $order['product_size'], $order['product_color'],
                        $order['delivery_fee'], $order['shipping_company_id'], $order['pickup_point_id'], $order['payment_type'],
                        $order['order_status'], $order['created_at'], $month_year
                    ]);
                }
                
                $stmt = $pdo->prepare("DELETE FROM orders WHERE seller_id = ? AND order_status = 'delivered_to_buyer' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)");
                $stmt->execute([$seller_id]);
                $total_archived += $orders_count;
            }
        }
        $pdo->commit();
        $_SESSION['message'] = "✅ تم أرشفة جميع الطلبات المكتملة ($total_archived طلب) بنجاح";
    } catch(PDOException $e) {
        $pdo->rollBack();
        $_SESSION['message'] = "❌ خطأ في الأرشفة: " . $e->getMessage();
    }
    header("Location: admin_dashboard.php?active_tab=archive&scroll_pos=" . ($_POST['scroll_pos'] ?? 0));
    exit();
}

if (isset($_GET['delete_cancelled'])) {
    $seller_id = isset($_GET['seller_id']) ? $_GET['seller_id'] : 'all';
    try {
        if ($seller_id === 'all') {
            $pdo->prepare("DELETE FROM orders WHERE order_status = 'cancelled'")->execute();
            $_SESSION['message'] = "✅ تم حذف جميع الطلبات الملغاة بنجاح";
        } else {
            $stmt = $pdo->prepare("DELETE FROM orders WHERE order_status = 'cancelled' AND seller_id = ?");
            $stmt->execute([$seller_id]);
            $_SESSION['message'] = "✅ تم حذف الطلبات الملغاة للبائع المحدد بنجاح";
        }
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ خطأ: " . $e->getMessage();
    }
    header("Location: admin_dashboard.php?active_tab=archive&scroll_pos=" . ($_GET['scroll_pos'] ?? 0));
    exit();
}

// ============================================================
// جلب الإشعارات غير المقروءة
// ============================================================
$notifications = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll();
    $unread_count = count($notifications);
} catch(PDOException $e) {}

// ============================================================
// رسالة النظام
// ============================================================
$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);
// ============================================================
// حذف تذكرة دعم
// ============================================================
if (isset($_GET['delete_ticket']) && isset($_GET['ticket_id'])) {
    $ticket_id = intval($_GET['ticket_id']);
    try {
        $pdo->beginTransaction();
        // حذف جميع الردود المرتبطة بالتذكرة أولاً
        $stmt = $pdo->prepare("DELETE FROM ticket_replies WHERE ticket_id = ?");
        $stmt->execute([$ticket_id]);
        // حذف التذكرة نفسها
        $stmt = $pdo->prepare("DELETE FROM support_tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);
        $pdo->commit();
        $_SESSION['message'] = "✅ تم حذف التذكرة بنجاح";
    } catch(PDOException $e) {
        $pdo->rollBack();
        $_SESSION['message'] = "❌ خطأ: " . $e->getMessage();
    }
    header("Location: admin_dashboard.php?active_tab=support_tickets&scroll_pos=" . ($_GET['scroll_pos'] ?? 0));
    exit();
}
// ============================================================
// حذف إشعار فردي
// ============================================================
if (isset($_GET['delete_notif']) && isset($_GET['notif_id'])) {
    $notif_id = intval($_GET['notif_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->execute([$notif_id]);
        $_SESSION['message'] = "✅ تم حذف الإشعار بنجاح";
    } catch(PDOException $e) {
        $_SESSION['message'] = "❌ خطأ: " . $e->getMessage();
    }
    header("Location: admin_dashboard.php?active_tab=notifications&scroll_pos=" . ($_GET['scroll_pos'] ?? 0));
    exit();
}
?>