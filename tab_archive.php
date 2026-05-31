<?php
// جلب البائعين للتصفية
$sellers_for_archive = [];
try {
    $sellers_for_archive = $pdo->query("SELECT id, full_name, email FROM users WHERE user_type = 'seller' ORDER BY full_name")->fetchAll();
} catch(PDOException $e) {}

$sellers_for_filter = $pdo->query("SELECT id, full_name FROM users WHERE user_type = 'seller' ORDER BY full_name")->fetchAll();

// جلب الفواتير المؤرشفة
$archived_invoices = [];
try {
    $archived_invoices = $pdo->query("SELECT * FROM monthly_invoices ORDER BY month_year DESC, seller_name")->fetchAll();
} catch(PDOException $e) {}
?>

<div class="card">
    <div class="card-header">📦 أرشيف الطلبات والفواتير الشهرية</div>
    <div class="card-body">
        <div class="archive-settings">
            <form method="POST" onsubmit="saveScrollPosition()" style="display:flex; gap:15px; flex-wrap:wrap;">
                <input type="hidden" name="scroll_pos" value="0">
                <div style="flex:2; min-width:200px;">
                    <select name="seller_id" style="width:100%; padding:10px; border-radius:10px;">
                        <option value="">-- اختر البائع --</option>
                        <?php foreach($sellers_for_archive as $seller): ?>
                            <option value="<?php echo $seller['id']; ?>"><?php echo htmlspecialchars($seller['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="archive_seller_orders" class="btn-warning">📦 أرشفة طلبات البائع</button>
                <button type="submit" name="archive_all_sellers" class="btn-success">📦 أرشفة الكل</button>
            </form>
            
            <div style="background:#fff3e0; padding:15px; border-radius:15px; margin-top:20px;">
                <h4 style="color:#e65100;">🗑️ تنظيف الطلبات الملغاة</h4>
                <div style="display:flex; gap:15px; flex-wrap:wrap; margin-top:15px;">
                    <div style="flex:2; min-width:200px;">
                        <select id="deleteCancelledSeller" style="width:100%; padding:10px; border-radius:10px;">
                            <option value="all">-- جميع البائعين --</option>
                            <?php foreach($sellers_for_filter as $seller): ?>
                                <option value="<?php echo $seller['id']; ?>"><?php echo htmlspecialchars($seller['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button onclick="deleteCancelledOrders()" class="btn-danger">🗑️ حذف الطلبات الملغاة</button>
                </div>
            </div>
        </div>
        
        <div style="margin-top:25px;">
            <h4>📋 الفواتير الشهرية السابقة</h4>
            <?php if(count($archived_invoices) > 0): ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>الشهر</th>
                                <th>البائع</th>
                                <th>الطلبات</th>
                                <th>إجمالي المبيعات</th>
                                <th>العمولة</th>
                                <th>صافي المستحق</th>
                                <th>التاريخ</th>
                                <th>فاتورة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($archived_invoices as $inv): ?>
                            <tr>
                                <td><?php echo date('m/Y', strtotime($inv['month_year'] . '-01')); ?></td>
                                <td><?php echo htmlspecialchars($inv['seller_name']); ?></td>
                                <td><?php echo $inv['orders_count']; ?></td>
                                <td><?php echo number_format($inv['total_sales'], 2); ?> دج</td>
                                <td><?php echo number_format($inv['total_commission'], 2); ?> دج</td>
                                <td><strong><?php echo number_format($inv['net_amount'], 2); ?> دج</strong></td>
                                <td><?php echo date('Y-m-d', strtotime($inv['invoice_date'])); ?></td>
                                <td>
                                    <button onclick="printInvoiceArchive('<?php echo addslashes($inv['seller_name']); ?>', '<?php echo $inv['month_year']; ?>', <?php echo $inv['orders_count']; ?>, <?php echo $inv['total_sales']; ?>, <?php echo $inv['total_commission']; ?>, <?php echo $inv['net_amount']; ?>, <?php echo $inv['total_advance'] ?? 0; ?>, <?php echo $inv['cash_collected'] ?? 0; ?>)" class="btn-print">🖨️ طباعة</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>لا توجد فواتير سابقة</p>
            <?php endif; ?>
        </div>
    </div>
</div>