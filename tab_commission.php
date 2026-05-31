<?php
// تقرير العمولات حسب البائع
$seller_commission_report = [];
$sellers_data = $pdo->query("
    SELECT u.id, u.full_name as seller_name, u.email as seller_email, u.phone as seller_phone,
           COUNT(DISTINCT o.id) as orders_count, 
           SUM(oi.quantity) as total_items_sold,
           SUM(oi.total_price) as products_total
    FROM users u
    LEFT JOIN orders o ON u.id = o.seller_id AND o.order_status = 'delivered_to_buyer'
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE u.user_type = 'seller'
    GROUP BY u.id
")->fetchAll();

foreach($sellers_data as $seller) {
    $products_total = $seller['products_total'] ?? 0;
    
    $commission_stmt = $pdo->prepare("
        SELECT SUM(commission_amount) as total_commission, AVG(commission_rate) as avg_rate
        FROM orders 
        WHERE seller_id = ? AND order_status = 'delivered_to_buyer'
    ");
    $commission_stmt->execute([$seller['id']]);
    $commission_data = $commission_stmt->fetch();
    $commission_amount = $commission_data['total_commission'] ?? 0;
    $avg_commission_rate = round($commission_data['avg_rate'] ?? 0, 1);
    
    $advance_stmt = $pdo->prepare("
        SELECT SUM(advance_amount) as total_advance 
        FROM orders 
        WHERE seller_id = ? AND order_status = 'delivered_to_buyer' AND payment_type = 'advance'
    ");
    $advance_stmt->execute([$seller['id']]);
    $total_advance = $advance_stmt->fetchColumn() ?? 0;
    
    $cash_collected = $products_total - $total_advance;
    $net_amount = $total_advance - $commission_amount;
    
    $seller_commission_report[] = [
        'id' => $seller['id'],
        'seller_name' => $seller['seller_name'],
        'seller_email' => $seller['seller_email'],
        'seller_phone' => $seller['seller_phone'],
        'orders_count' => $seller['orders_count'],
        'total_items_sold' => $seller['total_items_sold'],
        'products_total' => $products_total,
        'total_advance' => $total_advance,
        'cash_collected' => $cash_collected,
        'commission_rate' => $avg_commission_rate,
        'commission_amount' => $commission_amount,
        'net_amount' => $net_amount
    ];
}

usort($seller_commission_report, function($a, $b) {
    return $b['net_amount'] <=> $a['net_amount'];
});
?>

<div class="card">
    <div class="card-header">📊 تقرير العمولات حسب البائع (بناءً على العمولات المخزنة)</div>
    <div class="card-body">
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>البائع</th>
                        <th>البريد</th>
                        <th>الهاتف</th>
                        <th>الطلبات</th>
                        <th>القطع المباعة</th>
                        <th>إجمالي المبيعات</th>
                        <th>الدفعة المقدمة</th>
                        <th>المقبوض مباشرة</th>
                        <th>معدل العمولة (%)</th>
                        <th>قيمة العمولة</th>
                        <th>المستحق</th>
                        <th>فاتورة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $counter = 1; foreach($seller_commission_report as $s): ?>
                    <tr>
                        <td><?php echo $counter++; ?></td>
                        <td><?php echo htmlspecialchars($s['seller_name']); ?></td>
                        <td><?php echo htmlspecialchars($s['seller_email']); ?></td>
                        <td><?php echo htmlspecialchars($s['seller_phone'] ?? '—'); ?></td>
                        <td><?php echo $s['orders_count'] ?? 0; ?></td>
                        <td><?php echo $s['total_items_sold'] ?? 0; ?></td>
                        <td><?php echo number_format($s['products_total'], 2); ?> دج</td>
                        <td><?php echo number_format($s['total_advance'], 2); ?> دج</td>
                        <td><?php echo number_format($s['cash_collected'], 2); ?> دج</td>
                        <td><strong><?php echo $s['commission_rate']; ?>%</strong></td>
                        <td><?php echo number_format($s['commission_amount'], 2); ?> دج</td>
                        <td><strong style="color:#2e7d32;"><?php echo number_format($s['net_amount'], 2); ?> دج</strong></td>
                        <td>
                            <button onclick="printSellerInvoice(<?php echo $s['id']; ?>, '<?php echo addslashes($s['seller_name']); ?>', '<?php echo addslashes($s['seller_email']); ?>', '<?php echo addslashes($s['seller_phone'] ?? ''); ?>', <?php echo $s['orders_count'] ?? 0; ?>, <?php echo $s['total_items_sold'] ?? 0; ?>, <?php echo $s['products_total']; ?>, <?php echo $s['total_advance']; ?>, <?php echo $s['cash_collected']; ?>, <?php echo $s['net_amount']; ?>, <?php echo $s['commission_rate']; ?>, '<?php echo date('F'); ?>', <?php echo date('Y'); ?>)" class="btn-print">🖨️ فاتورة</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($seller_commission_report)): ?>
                    <tr><td colspan="13" style="text-align:center;">لا توجد بيانات للعمولات</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>