<?php
// ============================================================
// ملف الهيدر الموحد - يتم استدعاؤه في جميع الصفحات
// ============================================================

// نتحقق من وجود المتغيرات الضرورية
if (!isset($current_page)) {
    $current_page = basename($_SERVER['PHP_SELF']);
}
if (!isset($user_notifications)) {
    $user_notifications = [];
    $unread_count = 0;
    if (isset($_SESSION['user_id'])) {
        try {
            global $pdo;
            $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 20");
            $stmt->execute([$_SESSION['user_id']]);
            $user_notifications = $stmt->fetchAll();
            
            $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $count_stmt->execute([$_SESSION['user_id']]);
            $unread_count = $count_stmt->fetchColumn();
        } catch(PDOException $e) {
            $user_notifications = [];
            $unread_count = 0;
        }
    }
}

if (isset($_GET['read_notif'])) {
    $notif_id = intval($_GET['read_notif']);
    try {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$notif_id, $_SESSION['user_id']]);
    } catch(PDOException $e) {}
    header("Location: view_notification.php?id=" . $notif_id);
    exit();
}

$mobile_user_name = $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? '';
$mobile_user_type = $_SESSION['user_type'] ?? '';

$mobile_icon = '👤';
if ($mobile_user_type == 'admin') $mobile_icon = '👑';
elseif ($mobile_user_type == 'seller') $mobile_icon = '🛍️';
elseif ($mobile_user_type == 'buyer') $mobile_icon = '🛒';
elseif ($mobile_user_type == 'shipping_company') $mobile_icon = '🚚';
elseif ($mobile_user_type == 'pickup_point') $mobile_icon = '📍';

$mobile_type_text = '';
if ($mobile_user_type == 'admin') $mobile_type_text = 'مدير النظام';
elseif ($mobile_user_type == 'seller') $mobile_type_text = 'بائع';
elseif ($mobile_user_type == 'buyer') $mobile_type_text = 'مشتري';
elseif ($mobile_user_type == 'shipping_company') $mobile_type_text = 'شركة توصيل';
elseif ($mobile_user_type == 'pickup_point') $mobile_type_text = 'نقطة تجميع';

$mobile_dashboard_link = '';
$mobile_dashboard_name = '';
if ($mobile_user_type == 'admin') {
    $mobile_dashboard_link = 'admin_dashboard.php';
    $mobile_dashboard_name = 'لوحة المدير';
} elseif ($mobile_user_type == 'seller') {
    $mobile_dashboard_link = 'dashboard.php';
    $mobile_dashboard_name = 'لوحة البائع';
} elseif ($mobile_user_type == 'shipping_company') {
    $mobile_dashboard_link = 'shipping_dashboard.php';
    $mobile_dashboard_name = 'لوحة الشحن';
} elseif ($mobile_user_type == 'pickup_point') {
    $mobile_dashboard_link = 'pickup_dashboard.php';
    $mobile_dashboard_name = 'لوحة التجميع';
}

$hide_back_button_pages = [
    'index.php',
    'admin_dashboard.php',
    'dashboard.php',
    'shipping_dashboard.php',
    'pickup_dashboard.php',
    'login.php',
    'register.php'
];
$show_back_button = !in_array($current_page, $hide_back_button_pages);
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
    
    <!-- تطبيق الوضع الليلي فوراً بدون وميض -->
    <script>
        if (localStorage.getItem('darkMode') === 'enabled') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>
    
    <!-- ============================================================ -->
    <!-- PWA - تطبيق الويب التقدمي -->
    <!-- ============================================================ -->
    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="سوق الرمال">
    <link rel="apple-touch-icon" href="/uploads/icon-512.png">
    <meta name="theme-color" content="#b8860b">
    <meta name="mobile-web-app-capable" content="yes">
    
    <!-- ============================================================ -->
    <!-- نظام الألوان الموحد باستخدام CSS Variables -->
    <!-- ============================================================ -->
    <style>
        /* === متغيرات الوضع النهاري (الافتراضي) === */
        :root {
            --bg-body: #fff8f0;
            --bg-card: #ffffff;
            --bg-card-alt: #f8f8f8;
            --bg-header-card: #f8e1b0;
            --bg-input: #ffffff;
            --bg-nav: #ffffff;
            --bg-notif: #ffffff;
            --bg-modal: #ffffff;
            --bg-welcome: linear-gradient(135deg, #ffffff, #fdf5e6, #f8e1b0);
            --bg-back-bar: #ffffff;
            --bg-tab: #f8e1b0;
            --bg-tab-active: #b8860b;
            --bg-badge: #f8f8f8;
            --bg-rib: #f8e1b0;
            --bg-footer: #333333;
            
            --text-primary: #333333;
            --text-secondary: #666666;
            --text-muted: #999999;
            --text-heading: #b8860b;
            --text-tab: #8B4513;
            --text-tab-active: #ffffff;
            --text-link: #b8860b;
            --text-welcome: #5d4037;
            --text-welcome-type: #8d6e63;
            --text-white: #ffffff;
            
            --border-color: #dddddd;
            --border-light: #f0e0c0;
            --border-gold: #d4a017;
            --border-input: #dddddd;
            
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow-md: 0 2px 10px rgba(0,0,0,0.1);
            --shadow-lg: 0 5px 20px rgba(0,0,0,0.1);
            --shadow-nav: 0 2px 10px rgba(0,0,0,0.1);
            
            --btn-primary: #b8860b;
            --btn-primary-hover: #9a6f0b;
            --btn-danger: #c62828;
            --btn-danger-hover: #b71c1c;
            --btn-success: #2e7d32;
            --btn-success-hover: #1b5e20;
            --btn-info: #2196f3;
            --btn-info-hover: #1976d2;
            --btn-warning: #e65100;
            --btn-warning-hover: #bf360c;
            --btn-edit: #2196f3;
            --btn-print: #2196f3;
            
            --badge-pending-bg: #fff3e0;
            --badge-pending-text: #e65100;
            --badge-success-bg: #e8f5e9;
            --badge-success-text: #2e7d32;
            --badge-danger-bg: #ffebee;
            --badge-danger-text: #c62828;
            --badge-info-bg: #e3f2fd;
            --badge-info-text: #1565c0;
            --badge-warning-bg: #fff8e1;
            --badge-warning-text: #f57f17;
            
            --success-bg: #e8f5e9;
            --success-text: #2e7d32;
            --error-bg: #ffebee;
            --error-text: #c62828;
            
            --notif-badge: #c62828;
            --notif-border: #eeeeee;
            --notif-hover: #fff8f0;
            
            --table-header-bg: #b8860b;
            --table-header-text: #ffffff;
            --table-border: #f0e0c0;
            --table-hover: #fff8f0;
            
            --star-color: #ffc107;
            --user-name-bg: #f8e1b0;
            --user-name-text: #b8860b;
            --logout-bg: #ffebee;
            --logout-text: #c62828;
            --dark-toggle-bg: #f0e0c0;
            --dark-toggle-text: #333333;
            --welcome-icon-bg: #ffffff;
            --welcome-icon-border: #d4a017;
            --welcome-type-bg: rgba(255,255,255,0.7);
            --welcome-type-border: #e8d49a;
            --back-btn-bg: #f8e1b0;
            --back-btn-text: #8B4513;
            --back-btn-border: #d4a017;
            --mobile-dark-bg: #f0e0c0;
            --mobile-dark-text: #8B4513;
            --mobile-dark-border: #d4a017;
            
            --hover-row-bg: #fff8f0;
        }
        
        /* === متغيرات الوضع الليلي === */
        html.dark-mode {
            --bg-body: #1a1a2e;
            --bg-card: #2d2d44;
            --bg-card-alt: #3d3d5c;
            --bg-header-card: #3d3d5c;
            --bg-input: #3d3d5c;
            --bg-nav: #2d2d44;
            --bg-notif: #2d2d44;
            --bg-modal: #2d2d44;
            --bg-welcome: linear-gradient(135deg, #2d2d44, #3d3d5c);
            --bg-back-bar: #2d2d44;
            --bg-tab: #3d3d5c;
            --bg-tab-active: #b8860b;
            --bg-badge: #3d3d5c;
            --bg-rib: #3d3d5c;
            --bg-footer: #111111;
            
            --text-primary: #e0e0e0;
            --text-secondary: #b0b0b0;
            --text-muted: #888888;
            --text-heading: #f0e0c0;
            --text-tab: #e0e0e0;
            --text-tab-active: #ffffff;
            --text-link: #f0e0c0;
            --text-welcome: #f0e0c0;
            --text-welcome-type: #cccccc;
            --text-white: #ffffff;
            
            --border-color: #444444;
            --border-light: #444444;
            --border-gold: #b8860b;
            --border-input: #555555;
            
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.2);
            --shadow-md: 0 2px 10px rgba(0,0,0,0.3);
            --shadow-lg: 0 5px 20px rgba(0,0,0,0.3);
            --shadow-nav: 0 2px 10px rgba(0,0,0,0.3);
            
            --btn-primary: #b8860b;
            --btn-primary-hover: #9a6f0b;
            --btn-danger: #c62828;
            --btn-danger-hover: #b71c1c;
            --btn-success: #2e7d32;
            --btn-success-hover: #1b5e20;
            --btn-info: #2196f3;
            --btn-info-hover: #1976d2;
            --btn-warning: #e65100;
            --btn-warning-hover: #bf360c;
            --btn-edit: #2196f3;
            --btn-print: #2196f3;
            
            --badge-pending-bg: #3d3520;
            --badge-pending-text: #ffb74d;
            --badge-success-bg: #1a3a1a;
            --badge-success-text: #81c784;
            --badge-danger-bg: #3a1a1a;
            --badge-danger-text: #ef9a9a;
            --badge-info-bg: #1a2a3a;
            --badge-info-text: #64b5f6;
            --badge-warning-bg: #3d3d20;
            --badge-warning-text: #fff176;
            
            --success-bg: #1a3a1a;
            --success-text: #81c784;
            --error-bg: #3a1a1a;
            --error-text: #ef9a9a;
            
            --notif-badge: #c62828;
            --notif-border: #444444;
            --notif-hover: #3d3d5c;
            
            --table-header-bg: #b8860b;
            --table-header-text: #ffffff;
            --table-border: #444444;
            --table-hover: #3d3d5c;
            
            --star-color: #ffc107;
            --user-name-bg: #3d3d5c;
            --user-name-text: #f0e0c0;
            --logout-bg: #3a1a1a;
            --logout-text: #ef9a9a;
            --dark-toggle-bg: #3d3d5c;
            --dark-toggle-text: #f0e0c0;
            --welcome-icon-bg: #3d3d5c;
            --welcome-icon-border: #b8860b;
            --welcome-type-bg: rgba(255,255,255,0.1);
            --welcome-type-border: #555555;
            --back-btn-bg: #3d3d5c;
            --back-btn-text: #f0e0c0;
            --back-btn-border: #b8860b;
            --mobile-dark-bg: #3d3d5c;
            --mobile-dark-text: #f0e0c0;
            --mobile-dark-border: #b8860b;
            
            --hover-row-bg: #3d3d5c;
        }
        
        /* === تطبيق المتغيرات === */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: var(--bg-body) !important;
            color: var(--text-primary) !important;
            font-family: 'Tajawal', 'Tahoma', Arial, sans-serif;
        }
        
        header {
            background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('uploads/header-bg.jpg');
            background-size: cover; background-position: center; color: white;
            padding: 60px 20px; text-align: center;
        }
        header h1 { font-size: 36px; margin-bottom: 10px; }
        header p { font-size: 18px; }
        
        .mobile-sticky-header { display: none; position: -webkit-sticky; position: sticky; top: 0; z-index: 1000; }
        .mobile-welcome-bar { background: var(--bg-welcome); border-bottom: 2px solid var(--border-gold); padding: 10px 15px; }
        .mobile-welcome-content { display: flex; align-items: center; justify-content: space-between; }
        .mobile-welcome-left { display: flex; align-items: center; gap: 10px; }
        .mobile-welcome-icon {
            font-size: 22px; background: var(--welcome-icon-bg); width: 38px; height: 38px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            box-shadow: var(--shadow-sm); border: 2px solid var(--welcome-icon-border);
        }
        .mobile-welcome-name { font-size: 14px; font-weight: bold; color: var(--text-welcome); }
        .mobile-welcome-type {
            font-size: 10px; color: var(--text-welcome-type); background: var(--welcome-type-bg);
            padding: 2px 10px; border-radius: 12px; display: inline-block; margin-top: 2px;
            border: 1px solid var(--welcome-type-border);
        }
        .mobile-welcome-right { display: flex; align-items: center; }
        .mobile-welcome-dashboard-btn {
            background: var(--btn-primary); color: white; text-decoration: none; padding: 6px 14px;
            border-radius: 20px; font-size: 11px; font-weight: bold; transition: 0.3s; white-space: nowrap;
        }
        .mobile-welcome-dashboard-btn:hover { background: var(--btn-primary-hover); }
        
        .back-button-bar {
            background: var(--bg-back-bar); border-bottom: 1px solid var(--border-light);
            padding: 6px 15px; display: flex; justify-content: space-between; align-items: center;
        }
        .back-button {
            background: var(--back-btn-bg); color: var(--back-btn-text); border: 1px solid var(--back-btn-border);
            padding: 5px 14px; border-radius: 20px; cursor: pointer; font-size: 12px; font-weight: bold;
            transition: 0.3s; font-family: 'Tajawal', 'Tahoma', Arial, sans-serif;
        }
        .mobile-dark-toggle {
            background: var(--mobile-dark-bg); color: var(--mobile-dark-text); border: 1px solid var(--mobile-dark-border);
            padding: 5px 12px; border-radius: 20px; cursor: pointer; font-size: 12px; font-weight: bold;
            transition: 0.3s; font-family: 'Tajawal', 'Tahoma', Arial, sans-serif; text-decoration: none;
        }
        
        nav {
            background: var(--bg-nav); box-shadow: var(--shadow-nav);
            padding: 15px 30px; display: flex; justify-content: space-between;
            align-items: center; flex-wrap: wrap; gap: 10px;
        }
        .logo { font-size: 24px; font-weight: bold; color: var(--text-heading); }
        .nav-links { display: flex; flex-wrap: wrap; gap: 5px; align-items: center; }
        .nav-links a {
            color: var(--text-primary); text-decoration: none; padding: 8px 15px;
            border-radius: 25px; transition: all 0.3s;
        }
        .nav-links a:hover { background: var(--border-light); }
        .btn-nav { background: var(--btn-primary); color: white !important; }
        .nav-links a.active-page,
        .nav-links a.active-page:active { background: #8B4513 !important; color: white !important; box-shadow: 0 2px 5px var(--shadow-md); }
        .user-name { background: var(--user-name-bg); color: var(--user-name-text) !important; font-weight: bold; }
        .logout-btn { background: var(--logout-bg); color: var(--logout-text) !important; }
        .dark-mode-toggle { background: var(--dark-toggle-bg); color: var(--dark-toggle-text); }
        
        .notif-icon { position: relative; display: inline-block; cursor: pointer; }
        .notif-badge {
            position: absolute; top: -8px; right: -8px; background: var(--notif-badge); color: white;
            border-radius: 50%; padding: 2px 6px; font-size: 10px; min-width: 18px; text-align: center;
        }
        .notif-dropdown {
            display: none; position: absolute; left: 0; top: 35px;
            background: var(--bg-notif); border-radius: 10px;
            box-shadow: 0 5px 15px var(--shadow-lg); width: 300px; z-index: 1000;
            max-height: 400px; overflow-y: auto;
        }
        .notif-item {
            display: block; padding: 10px; text-decoration: none;
            color: var(--text-primary); border-bottom: 1px solid var(--notif-border);
        }
        .notif-item:hover { background: var(--notif-hover); }
        .notif-title { font-weight: bold; margin-bottom: 5px; font-size: 13px; color: var(--text-primary); }
        .notif-message { font-size: 11px; color: var(--text-secondary); }
        .notif-time { font-size: 10px; color: var(--text-muted); margin-top: 5px; }
        
        /* ============================================================ */
        /* أنماط عامة مشتركة لجميع الصفحات */
        /* ============================================================ */
        
        /* حاويات الصفحات */
        .admin-container, .shipping-container, .pickup-container,
        .dashboard-container, .my-products-container, .add-product-container,
        .edit-product-container, .backup-container, .delivery-container,
        .about-container, .faq-container, .privacy-container,
        .terms-container, .contact-container, .ticket-container { 
            max-width: 1400px; margin: 30px auto; padding: 0 20px; 
        }
        
        .card { 
            background: var(--bg-card); border-radius: 15px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 30px; overflow: hidden; 
        }
        .card-header { 
            background: var(--bg-header-card); padding: 15px 20px; font-size: 18px; 
            font-weight: bold; color: var(--text-heading); border-bottom: 2px solid var(--text-heading); 
        }
        .card-body { padding: 20px; overflow-x: auto; }
        
        /* شبكة الإحصائيات - تتمدد لملء الفراغ */
        .stats-grid { 
            display: grid; grid-template-columns: repeat(auto-fit, minmax(155px, 1fr)); 
            gap: 15px; margin-bottom: 25px; 
        }
        .stat-box { 
            background: var(--bg-header-card); padding: 18px 15px; border-radius: 15px; 
            text-align: center; box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            transition: 0.3s; border: 1px solid #e8d49a;
        }
        .stat-box:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.12); }
        .stat-number { font-size: 24px; font-weight: bold; color: var(--text-welcome); margin-bottom: 3px; }
        .stat-label { color: var(--text-primary); margin-top: 5px; font-size: 12px; font-weight: 500; opacity: 0.8; }
        
        /* رسائل */
        .success-msg, .success, .message { 
            background: var(--success-bg); color: var(--success-text); 
            padding: 12px; border-radius: 10px; margin-bottom: 20px; text-align: center; 
        }
        .error-msg, .error { 
            background: var(--error-bg); color: var(--error-text); 
            padding: 12px; border-radius: 10px; margin-bottom: 20px; text-align: center; 
        }
        
        /* أزرار صغيرة */
        .btn-small { 
            padding: 5px 12px; border-radius: 20px; text-decoration: none; font-size: 11px; 
            display: inline-flex; align-items: center; justify-content: center; 
            margin: 2px; cursor: pointer; border: none; font-weight: bold; transition: 0.3s;
            color: var(--text-white, white);
        }
        .btn-danger { background: var(--btn-danger); } .btn-danger:hover { background: var(--btn-danger-hover); }
        .btn-warning { background: var(--btn-warning); } .btn-warning:hover { background: var(--btn-warning-hover); }
        .btn-success { background: var(--btn-success); } .btn-success:hover { background: var(--btn-success-hover); }
        .btn-edit { background: var(--btn-info); } .btn-edit:hover { background: var(--btn-info-hover); }
        .btn-print { background: var(--btn-info); border: none; padding: 5px 12px; border-radius: 20px; cursor: pointer; font-size: 12px; transition: 0.3s; }
        .btn-print:hover { background: var(--btn-info-hover); }
        
        /* أزرار الإجراءات - flex: 1 1 0 ليتوسط النص */
        .btn-delivery { 
            background: var(--btn-primary); color: var(--text-white, white); 
            padding: 8px 20px; border-radius: 25px; text-decoration: none; 
            display: inline-flex; align-items: center; justify-content: center; gap: 5px;
            margin: 0; transition: 0.3s; flex: 1 1 0; min-width: fit-content; font-weight: bold;
        }
        .btn-delivery:hover { background: var(--btn-primary-hover); }
        
        /* التبويبات - flex: 1 1 0 ليتوسط النص */
        .tabs { 
            display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; 
            border-bottom: 2px solid var(--text-heading); padding-bottom: 10px; 
        }
        .tab-btn { 
            padding: 10px 16px; background: var(--bg-header-card); border: none; 
            border-radius: 25px; cursor: pointer; color: var(--text-primary);
            transition: 0.3s; font-weight: 500; flex: 1 1 0; min-width: fit-content;
            text-align: center; white-space: nowrap; font-size: 13px;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .tab-btn:hover { background: var(--btn-primary); color: var(--text-white, white); opacity: 0.8; }
        .tab-btn.active { 
            background: var(--btn-primary); color: var(--text-white, white); font-weight: bold;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2); transform: translateY(-1px);
        }
        .tab-content { display: none; } .tab-content.active { display: block; }
        
        /* جداول */
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; min-width: 600px; }
        .data-table th, .data-table td { 
            padding: 12px 15px; text-align: center; border-bottom: 1px solid var(--border-color); 
            white-space: nowrap; color: var(--text-primary);
        }
        .data-table th { background: var(--btn-primary); color: var(--text-white, white); font-weight: bold; }
        .data-table tr:hover { background: var(--hover-row-bg); }
        .table-wrapper { overflow-x: auto; width: 100%; }
        
        /* شارات */
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; display: inline-block; font-weight: bold; }
        .badge-pending { background: var(--badge-pending-bg); color: var(--badge-pending-text); }
        .badge-paid { background: var(--badge-pending-bg); color: var(--badge-pending-text); }
        .badge-picked { background: var(--badge-info-bg); color: var(--badge-info-text); }
        .badge-transit { background: var(--badge-warning-bg); color: var(--badge-warning-text); }
        .badge-arrived { background: var(--badge-info-bg); color: var(--badge-info-text); }
        .badge-delivered, .badge-confirmed { background: var(--badge-success-bg); color: var(--badge-success-text); }
        .badge-cancelled { background: var(--badge-danger-bg); color: var(--badge-danger-text); }
        .badge-ready, .badge-shipped { background: var(--badge-info-bg); color: var(--badge-info-text); }
        
        /* حقول إدخال */
        select, input[type="text"], input[type="number"], input[type="email"], input[type="password"], textarea { 
            background: var(--bg-input); color: var(--text-primary); 
            border: 1px solid var(--border-input); border-radius: 10px; padding: 10px; font-family: inherit;
        }
        select:focus, input:focus, textarea:focus { outline: none; border-color: var(--text-heading); }
        small { color: var(--text-muted); }
        
        /* ============================================================ */
        /* تجاوب */
        /* ============================================================ */
        @media (max-width: 768px) {
            nav { display: none !important; }
            .mobile-sticky-header { display: block !important; }
            header { padding: 40px 20px; } header h1 { font-size: 28px; }
            .card-body { overflow-x: auto; } .data-table { min-width: 600px; }
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); }
            .admin-container, .shipping-container, .pickup-container,
            .dashboard-container, .my-products-container, .add-product-container,
            .edit-product-container, .backup-container, .delivery-container,
            .about-container, .faq-container, .privacy-container,
            .terms-container, .contact-container, .ticket-container { margin: 20px auto; }
            .tab-btn { padding: 10px 14px; font-size: 12px; flex: 1 1 0; min-width: fit-content; }
            .btn-delivery { flex: 1 1 0; padding: 10px 15px; font-size: 13px; min-width: fit-content; }
        }
        @media (max-width: 480px) {
            .tab-btn { padding: 10px 12px; font-size: 11px; }
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); }
        }
        @media (min-width: 769px) { .mobile-sticky-header { display: none !important; } }
    </style>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?>سوق الرمال</title>
</head>
<body>
    <header>
        <h1>🏜️ سوق الرمال الذهبية</h1>
        <p>سوق نسائي متكامل - كل ما تحتاجينه في مكان واحد</p>
    </header>
    
    <div class="mobile-sticky-header">
        <?php if(isset($_SESSION['user_id'])): ?>
        <div class="mobile-welcome-bar">
            <div class="mobile-welcome-content">
                <div class="mobile-welcome-left">
                    <span class="mobile-welcome-icon"><?php echo $mobile_icon; ?></span>
                    <div>
                        <div class="mobile-welcome-name">👋 مرحباً، <?php echo htmlspecialchars($mobile_user_name); ?></div>
                        <?php if($mobile_type_text): ?>
                            <span class="mobile-welcome-type"><?php echo $mobile_icon; ?> <?php echo $mobile_type_text; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mobile-welcome-right">
                    <?php if($mobile_dashboard_link && $mobile_user_type != 'buyer'): ?>
                        <a href="<?php echo $mobile_dashboard_link; ?>" class="mobile-welcome-dashboard-btn">📊 <?php echo $mobile_dashboard_name; ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="back-button-bar">
            <div>
                <?php if($show_back_button): ?>
                <button onclick="goBack()" class="back-button" title="العودة للصفحة السابقة">➡️ رجوع</button>
                <?php endif; ?>
            </div>
            <a href="#" class="mobile-dark-toggle" onclick="toggleDarkMode()" title="تبديل الوضع الليلي"><span id="mobileDarkIcon">🌙</span></a>
        </div>
    </div>
    
    <nav>
        <div class="logo">سوق الرمال</div>
        <div class="nav-links">
            <a href="index.php" class="<?php echo ($current_page == 'index.php') ? 'btn-nav active-page' : ''; ?>"><i class="fas fa-home"></i> الرئيسية</a>
            <a href="profile.php" class="<?php echo ($current_page == 'profile.php') ? 'btn-nav active-page' : ''; ?>"><i class="fas fa-user"></i> حسابي</a>
            
            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="my_orders.php" class="<?php echo ($current_page == 'my_orders.php') ? 'btn-nav active-page' : ''; ?>"><i class="fas fa-shopping-bag"></i> طلباتي</a>
                
                <?php if($_SESSION['user_type'] == 'admin'): ?>
                    <a href="admin_dashboard.php" class="<?php echo ($current_page == 'admin_dashboard.php') ? 'btn-nav active-page' : ''; ?>"><i class="fas fa-tachometer-alt"></i> لوحة التحكم</a>
                <?php elseif($_SESSION['user_type'] == 'seller'): ?>
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> لوحة التحكم</a>
                    <a href="add-product.php"><i class="fas fa-plus-circle"></i> إضافة منتج</a>
                    <a href="my_products.php"><i class="fas fa-boxes"></i> منتجاتي</a>
                <?php elseif($_SESSION['user_type'] == 'shipping_company'): ?>
                    <a href="shipping_dashboard.php"><i class="fas fa-truck"></i> لوحة التحكم</a>
                <?php elseif($_SESSION['user_type'] == 'pickup_point'): ?>
                    <a href="pickup_dashboard.php"><i class="fas fa-map-marker-alt"></i> لوحة التحكم</a>
                <?php endif; ?>
                
                <a href="my_tickets.php"><i class="fas fa-ticket-alt"></i> تذاكري</a>
                
                <div class="notif-icon" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <?php if($unread_count > 0): ?><span class="notif-badge"><?php echo $unread_count; ?></span><?php endif; ?>
                    <div class="notif-dropdown" id="notifDropdown">
                        <div style="padding:10px; border-bottom:1px solid var(--notif-border); font-weight:bold; color:var(--text-heading);">📢 الإشعارات (<?php echo $unread_count; ?>)</div>
                        <?php if(count($user_notifications) > 0): ?>
                            <?php foreach($user_notifications as $notif): ?>
                                <a href="view_notification.php?id=<?php echo $notif['id']; ?>" class="notif-item">
                                    <div class="notif-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                    <div class="notif-message"><?php echo htmlspecialchars(mb_substr($notif['message'], 0, 60)); ?>...</div>
                                    <div class="notif-time"><?php echo date('H:i d/m', strtotime($notif['created_at'])); ?></div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="padding:20px; text-align:center; color:var(--text-muted);">لا توجد إشعارات جديدة</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <a href="#" class="user-name"><i class="fas fa-user-circle"></i> مرحباً <?php echo htmlspecialchars($_SESSION['user_name']); ?></a>
                <a href="#" class="dark-mode-toggle" onclick="toggleDarkMode()" title="تبديل الوضع الليلي" style="text-decoration:none; padding:8px 12px; border-radius:25px;"><span id="desktopDarkIcon">🌙</span></a>
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> تسجيل خروج</a>
            <?php else: ?>
                <a href="login.php">🔑 دخول</a>
                <a href="register.php" class="btn-nav">✨ تسجيل جديد</a>
            <?php endif; ?>
        </div>
    </nav>

    <script>
        function goBack() {
            if (document.referrer && document.referrer !== window.location.href) {
                window.location.href = document.referrer;
            } else {
                window.location.href = 'index.php';
            }
        }
        function toggleNotifications() {
            var dropdown = document.getElementById('notifDropdown');
            if(dropdown) dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        }
        document.addEventListener('click', function(e) {
            var icon = document.querySelector('.notif-icon');
            var dropdown = document.getElementById('notifDropdown');
            if(icon && dropdown && !icon.contains(e.target)) dropdown.style.display = 'none';
        });
        window.addEventListener('pageshow', function() {
            var dropdown = document.getElementById('notifDropdown');
            var bottomDropdown = document.getElementById('bottomNotifDropdown');
            if (dropdown) dropdown.style.display = 'none';
            if (bottomDropdown) bottomDropdown.style.display = 'none';
        });
        function toggleDarkMode() {
            var isDark = !document.documentElement.classList.contains('dark-mode');
            if (isDark) {
                document.documentElement.classList.add('dark-mode');
                localStorage.setItem('darkMode', 'enabled');
            } else {
                document.documentElement.classList.remove('dark-mode');
                localStorage.setItem('darkMode', 'disabled');
            }
            updateDarkModeIcons(isDark);
        }
        function updateDarkModeIcons(isDark) {
            var desktopIcon = document.getElementById('desktopDarkIcon');
            var mobileIcon = document.getElementById('mobileDarkIcon');
            var icon = isDark ? '☀️' : '🌙';
            if (desktopIcon) desktopIcon.innerHTML = icon;
            if (mobileIcon) mobileIcon.innerHTML = icon;
        }
        document.addEventListener('DOMContentLoaded', function() {
            var isDark = document.documentElement.classList.contains('dark-mode');
            updateDarkModeIcons(isDark);
        });

        // ============================================================
        // PWA - Service Worker وتسجيله
        // ============================================================
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js').then(function(registration) {
                    console.log('✅ Service Worker مسجل بنجاح');
                }).catch(function(err) {
                    console.log('❌ فشل تسجيل Service Worker:', err);
                });
            });
        }

        // متغير لتخزين حدث التثبيت (مشترك مع footer.php)
        let deferredPrompt;

        window.addEventListener('beforeinstallprompt', function(e) {
            e.preventDefault();
            // حفظ الحدث للاستخدام في footer.php
            window.deferredPrompt = e;
        });

        // اكتشاف تثبيت التطبيق
        window.addEventListener('appinstalled', function() {
            console.log('✅ تم تثبيت التطبيق بنجاح');
            window.deferredPrompt = null;
            // تحديث زر التحميل في الفوتر إذا كان موجوداً
            var btn = document.getElementById('footerInstallBtn');
            if (btn) {
                btn.innerHTML = '<i class="fas fa-check"></i><span>✅ التطبيق مثبت</span>';
                btn.style.background = 'linear-gradient(135deg, #2e7d32, #4caf50)';
                btn.disabled = true;
                btn.style.cursor = 'default';
            }
        });
    </script>