<?php
// tab_shipping_invoices.php - فواتير شركات التوصيل ونقاط التجميع

// جلب شركات التوصيل
$shipping_companies_list = $pdo->query("SELECT * FROM shipping_companies ORDER BY name")->fetchAll();

// جلب نقاط التجميع
$pickup_points_list = $pdo->query("SELECT p.*, sc.name as company_name FROM pickup_points p LEFT JOIN shipping_companies sc ON p.company_id = sc.id ORDER BY p.point_name")->fetchAll();

// فلترة حسب التاريخ
$filter_month = isset($_GET['filter_month']) ? $_GET['filter_month'] : date('Y-m');
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'all';

// بناء استعلام فواتير شركات التوصيل
$shipping_invoices = [];
try {
    $sql = "
        SELECT 
            o.shipping_company_id,
            sc.name as company_name,
            sc.phone as company_phone,
            DATE_FORMAT(o.created_at, '%Y-%m') as month_year,
            COUNT(o.id) as total_orders,
            COALESCE(SUM(o.delivery_fee), 0) as total_delivery_fees,
            COALESCE(SUM(CASE WHEN o.order_status = 'delivered_to_buyer' THEN o.delivery_fee ELSE 0 END), 0) as completed_fees,
            COALESCE(SUM(CASE WHEN o.order_status = 'cancelled' THEN o.delivery_fee ELSE 0 END), 0) as cancelled_fees
        FROM orders o
        JOIN shipping_companies sc ON o.shipping_company_id = sc.id
        WHERE o.shipping_company_id IS NOT NULL
    ";
    
    if ($filter_month) {
        $sql .= " AND DATE_FORMAT(o.created_at, '%Y-%m') = " . $pdo->quote($filter_month);
    }
    
    $sql .= " GROUP BY o.shipping_company_id, DATE_FORMAT(o.created_at, '%Y-%m')
              ORDER BY month_year DESC, company_name ASC";
    
    $shipping_invoices = $pdo->query($sql)->fetchAll();
} catch(PDOException $e) {}

// بناء استعلام فواتير نقاط التجميع
$pickup_invoices = [];
try {
    $sql = "
        SELECT 
            o.pickup_point_id,
            pp.point_name,
            pp.wilaya,
            pp.commune,
            pp.manager_name,
            pp.manager_phone,
            DATE_FORMAT(o.created_at, '%Y-%m') as month_year,
            COUNT(o.id) as total_orders,
            COALESCE(SUM(o.delivery_fee), 0) as total_delivery_fees,
            COALESCE(SUM(CASE WHEN o.order_status = 'delivered_to_buyer' THEN o.delivery_fee ELSE 0 END), 0) as completed_fees,
            COALESCE(SUM(CASE WHEN o.order_status = 'cancelled' THEN o.delivery_fee ELSE 0 END), 0) as cancelled_fees
        FROM orders o
        JOIN pickup_points pp ON o.pickup_point_id = pp.id
        WHERE o.pickup_point_id IS NOT NULL
    ";
    
    if ($filter_month) {
        $sql .= " AND DATE_FORMAT(o.created_at, '%Y-%m') = " . $pdo->quote($filter_month);
    }
    
    $sql .= " GROUP BY o.pickup_point_id, DATE_FORMAT(o.created_at, '%Y-%m')
              ORDER BY month_year DESC, point_name ASC";
    
    $pickup_invoices = $pdo->query($sql)->fetchAll();
} catch(PDOException $e) {}

// إجماليات سريعة
$total_shipping_completed = 0;
$total_pickup_completed = 0;
foreach ($shipping_invoices as $inv) {
    $total_shipping_completed += $inv['completed_fees'];
}
foreach ($pickup_invoices as $inv) {
    $total_pickup_completed += $inv['completed_fees'];
}
?>

<style>
    .invoices-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .invoice-stat-box {
        background: var(--bg-header-card);
        padding: 18px 15px;
        border-radius: 15px;
        text-align: center;
        box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        border: 1px solid #e8d49a;
    }
    
    .invoice-stat-number {
        font-size: 24px;
        font-weight: bold;
        color: var(--text-welcome);
        margin-bottom: 3px;
    }
    
    .invoice-stat-label {
        color: var(--text-primary);
        font-size: 12px;
        font-weight: 500;
        opacity: 0.8;
    }
    
    .filter-bar {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
        margin-bottom: 20px;
        padding: 15px;
        background: var(--bg-card-alt);
        border-radius: 12px;
    }
    
    .filter-bar select,
    .filter-bar input {
        padding: 8px 12px;
        border-radius: 8px;
        border: 1px solid var(--border-input);
        background: var(--bg-input);
        color: var(--text-primary);
        font-family: inherit;
    }
    
    .filter-bar button {
        padding: 8px 20px;
        border-radius: 25px;
        border: none;
        cursor: pointer;
        font-weight: bold;
        background: var(--btn-primary);
        color: var(--text-white);
        transition: 0.3s;
    }
    
    .filter-bar button:hover {
        background: var(--btn-primary-hover);
    }
    
    .invoice-section-title {
        color: var(--text-heading);
        margin: 25px 0 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--text-heading);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .invoice-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: bold;
    }
    
    .invoice-badge-completed {
        background: var(--badge-success-bg);
        color: var(--badge-success-text);
    }
    
    .invoice-badge-pending {
        background: var(--badge-pending-bg);
        color: var(--badge-pending-text);
    }
    
    .invoice-badge-cancelled {
        background: var(--badge-danger-bg);
        color: var(--badge-danger-text);
    }
    
    .btn-invoice-print {
        background: var(--btn-info, #2196f3);
        color: var(--text-white);
        border: none;
        padding: 5px 12px;
        border-radius: 20px;
        cursor: pointer;
        font-size: 11px;
        font-weight: bold;
        transition: 0.3s;
    }
    
    .btn-invoice-print:hover {
        background: var(--btn-info-hover, #1976d2);
    }
</style>

<div class="card">
    <div class="card-header">📄 فواتير شركات التوصيل ونقاط التجميع</div>
    <div class="card-body">
        <!-- إحصائيات سريعة -->
        <div class="invoices-stats">
            <div class="invoice-stat-box">
                <div class="invoice-stat-number"><?php echo count($shipping_invoices); ?></div>
                <div class="invoice-stat-label">🚚 فواتير شركات التوصيل</div>
            </div>
            <div class="invoice-stat-box">
                <div class="invoice-stat-number"><?php echo count($pickup_invoices); ?></div>
                <div class="invoice-stat-label">📍 فواتير نقاط التجميع</div>
            </div>
            <div class="invoice-stat-box">
                <div class="invoice-stat-number"><?php echo number_format($total_shipping_completed, 2); ?> دج</div>
                <div class="invoice-stat-label">💰 مستحقات شركات التوصيل</div>
            </div>
            <div class="invoice-stat-box">
                <div class="invoice-stat-number"><?php echo number_format($total_pickup_completed, 2); ?> دج</div>
                <div class="invoice-stat-label">💰 مستحقات نقاط التجميع</div>
            </div>
        </div>
        
        <!-- شريط التصفية -->
        <div class="filter-bar">
            <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                <input type="hidden" name="active_tab" value="shipping_invoices">
                <input type="hidden" name="scroll_pos" value="<?php echo $scroll_pos; ?>">
                
                <label style="font-weight: bold; color: var(--text-secondary);">📅 الشهر:</label>
                <input type="month" name="filter_month" value="<?php echo $filter_month; ?>">
                
                <button type="submit">🔍 تصفية</button>
                
                <?php if($filter_month): ?>
                    <a href="?active_tab=shipping_invoices&scroll_pos=<?php echo $scroll_pos; ?>" class="btn-small btn-danger">❌ إلغاء التصفية</a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- فواتير شركات التوصيل -->
        <h3 class="invoice-section-title">🚚 فواتير شركات التوصيل</h3>
        
        <?php if(count($shipping_invoices) > 0): ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>الشهر</th>
                            <th>شركة التوصيل</th>
                            <th>الهاتف</th>
                            <th>عدد الطلبات</th>
                            <th>إجمالي المستحقات</th>
                            <th>المكتملة</th>
                            <th>الملغاة</th>
                            <th>فاتورة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($shipping_invoices as $invoice): 
                            $month_name = date('F Y', strtotime($invoice['month_year'] . '-01'));
                        ?>
                            <tr>
                                <td><strong><?php echo $month_name; ?></strong></td>
                                <td><?php echo htmlspecialchars($invoice['company_name']); ?></td>
                                <td><?php echo htmlspecialchars($invoice['company_phone'] ?? '—'); ?></td>
                                <td><?php echo $invoice['total_orders']; ?> طلب</td>
                                <td><strong><?php echo number_format($invoice['total_delivery_fees'], 2); ?> دج</strong></td>
                                <td>
                                    <span class="invoice-badge invoice-badge-completed">
                                        <?php echo number_format($invoice['completed_fees'], 2); ?> دج
                                    </span>
                                </td>
                                <td>
                                    <span class="invoice-badge invoice-badge-cancelled">
                                        <?php echo number_format($invoice['cancelled_fees'], 2); ?> دج
                                    </span>
                                </td>
                                <td>
                                    <button onclick="printShippingInvoice(
                                        '<?php echo addslashes($invoice['company_name']); ?>',
                                        '<?php echo addslashes($invoice['company_phone'] ?? ''); ?>',
                                        '<?php echo $month_name; ?>',
                                        <?php echo $invoice['total_orders']; ?>,
                                        <?php echo $invoice['total_delivery_fees']; ?>,
                                        <?php echo $invoice['completed_fees']; ?>,
                                        <?php echo $invoice['cancelled_fees']; ?>
                                    )" class="btn-invoice-print">
                                        🖨️ طباعة
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="text-align:center; padding:30px; color: var(--text-muted);">📭 لا توجد فواتير لشركات التوصيل في هذه الفترة</p>
        <?php endif; ?>
        
        <!-- فواتير نقاط التجميع -->
        <h3 class="invoice-section-title">📍 فواتير نقاط التجميع</h3>
        
        <?php if(count($pickup_invoices) > 0): ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>الشهر</th>
                            <th>نقطة التجميع</th>
                            <th>الموقع</th>
                            <th>المسؤول</th>
                            <th>عدد الطلبات</th>
                            <th>إجمالي المستحقات</th>
                            <th>المكتملة</th>
                            <th>الملغاة</th>
                            <th>فاتورة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pickup_invoices as $invoice): 
                            $month_name = date('F Y', strtotime($invoice['month_year'] . '-01'));
                        ?>
                            <tr>
                                <td><strong><?php echo $month_name; ?></strong></td>
                                <td><?php echo htmlspecialchars($invoice['point_name']); ?></td>
                                <td><?php echo htmlspecialchars($invoice['wilaya'] . ' / ' . $invoice['commune']); ?></td>
                                <td><?php echo htmlspecialchars($invoice['manager_name'] ?? '—'); ?></td>
                                <td><?php echo $invoice['total_orders']; ?> طلب</td>
                                <td><strong><?php echo number_format($invoice['total_delivery_fees'], 2); ?> دج</strong></td>
                                <td>
                                    <span class="invoice-badge invoice-badge-completed">
                                        <?php echo number_format($invoice['completed_fees'], 2); ?> دج
                                    </span>
                                </td>
                                <td>
                                    <span class="invoice-badge invoice-badge-cancelled">
                                        <?php echo number_format($invoice['cancelled_fees'], 2); ?> دج
                                    </span>
                                </td>
                                <td>
                                    <button onclick="printPickupInvoice(
                                        '<?php echo addslashes($invoice['point_name']); ?>',
                                        '<?php echo addslashes($invoice['wilaya'] . ' / ' . $invoice['commune']); ?>',
                                        '<?php echo addslashes($invoice['manager_name'] ?? ''); ?>',
                                        '<?php echo addslashes($invoice['manager_phone'] ?? ''); ?>',
                                        '<?php echo $month_name; ?>',
                                        <?php echo $invoice['total_orders']; ?>,
                                        <?php echo $invoice['total_delivery_fees']; ?>,
                                        <?php echo $invoice['completed_fees']; ?>,
                                        <?php echo $invoice['cancelled_fees']; ?>
                                    )" class="btn-invoice-print">
                                        🖨️ طباعة
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="text-align:center; padding:30px; color: var(--text-muted);">📭 لا توجد فواتير لنقاط التجميع في هذه الفترة</p>
        <?php endif; ?>
    </div>
</div>

<script>
function printShippingInvoice(companyName, companyPhone, monthName, totalOrders, totalFees, completedFees, cancelledFees) {
    var w = window.open('', '_blank');
    var d = w.document;
    
    d.write('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"><title>فاتورة شركة توصيل</title><style>');
    d.write('*{margin:0;padding:0;box-sizing:border-box}');
    d.write('body{font-family:"Tahoma","Arial",sans-serif;background:#f5f5f5;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;padding:20px}');
    d.write('@media print{body{background:white;padding:0;margin:0}.print-buttons{display:none!important}.invoice-card{box-shadow:none;border:1px solid #ddd}}');
    d.write('.invoice-container{max-width:700px;width:100%;margin:0 auto}');
    d.write('.invoice-card{background:white;border-radius:20px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.1)}');
    d.write('.header{background:#b8860b;color:white;padding:25px 20px;text-align:center}');
    d.write('.header h1{font-size:26px;margin-bottom:5px}');
    d.write('.content{padding:20px}');
    d.write('.section-title{background:#f8e1b0;padding:10px 15px;margin:15px 0;font-weight:bold;color:#b8860b;border-radius:8px}');
    d.write('.info-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #eee}');
    d.write('.info-label{font-weight:bold;color:#555}');
    d.write('.info-value{color:#333;font-weight:500}');
    d.write('.total-box{background:#e8f5e9;padding:15px;border-radius:12px;margin-top:15px}');
    d.write('.total-amount{font-size:20px;font-weight:bold;color:#2e7d32}');
    d.write('.footer{background:#f8e1b0;padding:15px;text-align:center;border-top:2px solid #b8860b;margin-top:15px}');
    d.write('.print-buttons{display:flex;justify-content:center;gap:20px;padding:15px;background:#f5f5f5}');
    d.write('.btn{padding:8px 25px;border:none;border-radius:30px;cursor:pointer;font-size:13px;font-weight:bold}');
    d.write('.btn-print{background:#b8860b;color:white}.btn-close{background:#757575;color:white}');
    d.write('</style></head><body>');
    d.write('<div class="invoice-container"><div class="invoice-card">');
    d.write('<div class="header"><h1>🏜️ سوق الرمال الذهبية</h1><p>فاتورة شركة توصيل</p></div>');
    d.write('<div class="content">');
    d.write('<div class="section-title">📋 معلومات الشركة</div>');
    d.write('<div class="info-row"><span class="info-label">🚚 اسم الشركة:</span><span class="info-value">' + companyName + '</span></div>');
    d.write('<div class="info-row"><span class="info-label">📞 الهاتف:</span><span class="info-value">' + (companyPhone || '—') + '</span></div>');
    d.write('<div class="info-row"><span class="info-label">📅 الشهر:</span><span class="info-value">' + monthName + '</span></div>');
    d.write('<div class="section-title">📊 ملخص المستحقات</div>');
    d.write('<div class="info-row"><span class="info-label">📦 عدد الطلبات:</span><span class="info-value">' + totalOrders + ' طلب</span></div>');
    d.write('<div class="info-row"><span class="info-label">💰 إجمالي المستحقات:</span><span class="info-value">' + parseFloat(totalFees).toFixed(2) + ' دج</span></div>');
    d.write('<div class="info-row"><span class="info-label">✅ المكتملة:</span><span class="info-value" style="color:#2e7d32;">' + parseFloat(completedFees).toFixed(2) + ' دج</span></div>');
    d.write('<div class="info-row"><span class="info-label">❌ الملغاة:</span><span class="info-value" style="color:#c62828;">' + parseFloat(cancelledFees).toFixed(2) + ' دج</span></div>');
    d.write('<div class="total-box"><div class="info-row"><span class="info-label">💰 صافي المستحقات:</span><span class="total-amount">' + parseFloat(completedFees).toFixed(2) + ' دج</span></div></div>');
    d.write('</div>');
    d.write('<div class="footer"><p>🙏 شكراً لتعاونكم معنا</p><p>سوق الرمال - سوق آمن للنساء</p></div>');
    d.write('</div>');
    d.write('<div class="print-buttons"><button onclick="window.print()" class="btn btn-print">🖨️ طباعة</button><button onclick="window.close()" class="btn btn-close">✖️ إغلاق</button></div>');
    d.write('</div></body></html>');
    d.close();
}

function printPickupInvoice(pointName, location, managerName, managerPhone, monthName, totalOrders, totalFees, completedFees, cancelledFees) {
    var w = window.open('', '_blank');
    var d = w.document;
    
    d.write('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"><title>فاتورة نقطة تجميع</title><style>');
    d.write('*{margin:0;padding:0;box-sizing:border-box}');
    d.write('body{font-family:"Tahoma","Arial",sans-serif;background:#f5f5f5;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;padding:20px}');
    d.write('@media print{body{background:white;padding:0;margin:0}.print-buttons{display:none!important}.invoice-card{box-shadow:none;border:1px solid #ddd}}');
    d.write('.invoice-container{max-width:700px;width:100%;margin:0 auto}');
    d.write('.invoice-card{background:white;border-radius:20px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.1)}');
    d.write('.header{background:#b8860b;color:white;padding:25px 20px;text-align:center}');
    d.write('.header h1{font-size:26px;margin-bottom:5px}');
    d.write('.content{padding:20px}');
    d.write('.section-title{background:#f8e1b0;padding:10px 15px;margin:15px 0;font-weight:bold;color:#b8860b;border-radius:8px}');
    d.write('.info-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #eee}');
    d.write('.info-label{font-weight:bold;color:#555}');
    d.write('.info-value{color:#333;font-weight:500}');
    d.write('.total-box{background:#e8f5e9;padding:15px;border-radius:12px;margin-top:15px}');
    d.write('.total-amount{font-size:20px;font-weight:bold;color:#2e7d32}');
    d.write('.footer{background:#f8e1b0;padding:15px;text-align:center;border-top:2px solid #b8860b;margin-top:15px}');
    d.write('.print-buttons{display:flex;justify-content:center;gap:20px;padding:15px;background:#f5f5f5}');
    d.write('.btn{padding:8px 25px;border:none;border-radius:30px;cursor:pointer;font-size:13px;font-weight:bold}');
    d.write('.btn-print{background:#b8860b;color:white}.btn-close{background:#757575;color:white}');
    d.write('</style></head><body>');
    d.write('<div class="invoice-container"><div class="invoice-card">');
    d.write('<div class="header"><h1>🏜️ سوق الرمال الذهبية</h1><p>فاتورة نقطة تجميع</p></div>');
    d.write('<div class="content">');
    d.write('<div class="section-title">📋 معلومات النقطة</div>');
    d.write('<div class="info-row"><span class="info-label">📍 اسم النقطة:</span><span class="info-value">' + pointName + '</span></div>');
    d.write('<div class="info-row"><span class="info-label">🗺️ الموقع:</span><span class="info-value">' + location + '</span></div>');
    d.write('<div class="info-row"><span class="info-label">👤 المسؤول:</span><span class="info-value">' + (managerName || '—') + '</span></div>');
    d.write('<div class="info-row"><span class="info-label">📞 الهاتف:</span><span class="info-value">' + (managerPhone || '—') + '</span></div>');
    d.write('<div class="info-row"><span class="info-label">📅 الشهر:</span><span class="info-value">' + monthName + '</span></div>');
    d.write('<div class="section-title">📊 ملخص المستحقات</div>');
    d.write('<div class="info-row"><span class="info-label">📦 عدد الطلبات:</span><span class="info-value">' + totalOrders + ' طلب</span></div>');
    d.write('<div class="info-row"><span class="info-label">💰 إجمالي المستحقات:</span><span class="info-value">' + parseFloat(totalFees).toFixed(2) + ' دج</span></div>');
    d.write('<div class="info-row"><span class="info-label">✅ المكتملة:</span><span class="info-value" style="color:#2e7d32;">' + parseFloat(completedFees).toFixed(2) + ' دج</span></div>');
    d.write('<div class="info-row"><span class="info-label">❌ الملغاة:</span><span class="info-value" style="color:#c62828;">' + parseFloat(cancelledFees).toFixed(2) + ' دج</span></div>');
    d.write('<div class="total-box"><div class="info-row"><span class="info-label">💰 صافي المستحقات:</span><span class="total-amount">' + parseFloat(completedFees).toFixed(2) + ' دج</span></div></div>');
    d.write('</div>');
    d.write('<div class="footer"><p>🙏 شكراً لتعاونكم معنا</p><p>سوق الرمال - سوق آمن للنساء</p></div>');
    d.write('</div>');
    d.write('<div class="print-buttons"><button onclick="window.print()" class="btn btn-print">🖨️ طباعة</button><button onclick="window.close()" class="btn btn-close">✖️ إغلاق</button></div>');
    d.write('</div></body></html>');
    d.close();
}
</script>