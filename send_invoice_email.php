<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    echo "غير مصرح";
    exit();
}

$seller_id = intval($_POST['seller_id'] ?? 0);
$seller_email = $_POST['seller_email'] ?? '';
$seller_name = $_POST['seller_name'] ?? '';
$month_year = $_POST['month_year'] ?? '';
$orders_count = intval($_POST['orders_count'] ?? 0);
$total_items = intval($_POST['total_items'] ?? 0);
$total_sales = floatval($_POST['total_sales'] ?? 0);
$net_amount = floatval($_POST['net_amount'] ?? 0);

if (empty($seller_email)) {
    echo "البريد الإلكتروني مطلوب";
    exit();
}

$advance = $total_sales * 0.25;
$commission = $total_sales * 0.07;

$subject = "فاتورة العمولة الشهرية - سوق الرمال - " . $month_year;

$html = '
<!DOCTYPE html>
<html dir="rtl">
<head><meta charset="UTF-8"></head>
<body style="font-family: Tahoma, Arial, sans-serif; background: #f5f5f5; padding: 20px;">
    <div style="max-width: 450px; margin: auto; background: white; border-radius: 20px; padding: 25px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
        <div style="text-align: center; border-bottom: 2px solid #b8860b; padding-bottom: 15px;">
            <h1 style="color: #b8860b;">🏜️ سوق الرمال الذهبية</h1>
            <p style="color: #666;">فاتورة العمولة الشهرية</p>
        </div>
        
        <div style="background: #f8e1b0; padding: 15px; border-radius: 15px; margin: 20px 0; text-align: center;">
            <strong>' . htmlspecialchars($month_year) . '</strong>
        </div>
        
        <div style="margin-bottom: 15px;">
            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee;">
                <span>👤 اسم البائع:</span>
                <span><strong>' . htmlspecialchars($seller_name) . '</strong></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee;">
                <span>📧 البريد الإلكتروني:</span>
                <span>' . htmlspecialchars($seller_email) . '</span>
            </div>
        </div>
        
        <div style="background: #f8f8f8; padding: 15px; border-radius: 15px; margin: 15px 0;">
            <div style="display: flex; justify-content: space-between; padding: 5px 0;">
                <span>📋 عدد الطلبات:</span>
                <span><strong>' . $orders_count . '</strong></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 5px 0;">
                <span>📦 عدد القطع المباعة:</span>
                <span><strong>' . $total_items . '</strong></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 5px 0;">
                <span>💰 إجمالي المبيعات:</span>
                <span><strong>' . number_format($total_sales, 2) . ' دج</strong></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 5px 0;">
                <span>💳 الدفعة المقدمة (25%):</span>
                <span>' . number_format($advance, 2) . ' دج</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 5px 0;">
                <span>💸 عمولة الموقع (7%):</span>
                <span>' . number_format($commission, 2) . ' دج</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 10px 0; background: #e8f5e9; margin-top: 10px; padding: 10px; border-radius: 10px;">
                <span>💰 المبلغ المستحق:</span>
                <span><strong style="color: #2e7d32;">' . number_format($net_amount, 2) . ' دج</strong></span>
            </div>
        </div>
        
        <div style="margin-top: 20px; text-align: center; font-size: 12px; color: #999;">
            <p>🔔 سيتم تحويل المبلغ المستحق إلى حساب CCP الخاص بك خلال 7 أيام</p>
            <p>شكراً لتعاونك معنا 💐</p>
        </div>
    </div>
</body>
</html>';

$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$headers .= "From: سوق الرمال <noreply@souk-ramal.com>" . "\r\n";

if (mail($seller_email, $subject, $html, $headers)) {
    echo "✅ تم إرسال الفاتورة بنجاح";
} else {
    echo "❌ فشل إرسال البريد";
}
?>