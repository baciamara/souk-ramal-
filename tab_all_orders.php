<?php
// جلب جميع الطلبات
$all_orders_list = $pdo->query("
    SELECT o.*, oi.product_name, oi.quantity, oi.total_price as order_total,
           u.full_name as buyer_name, s.full_name as seller_name
    FROM orders o JOIN order_items oi ON o.id = oi.order_id
    JOIN users u ON o.buyer_id = u.id JOIN users s ON o.seller_id = s.id
    ORDER BY o.created_at DESC LIMIT 100
")->fetchAll();
?>

<div class="card">
    <div class="card-header">📋 جميع الطلبات</div>
    <div class="card-body">
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>رقم الطلب</th>
                        <th>المنتج</th>
                        <th>البائع</th>
                        <th>المشتري</th>
                        <th>الكمية</th>
                        <th>المبلغ</th>
                        <th>طريقة الدفع</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($all_orders_list as $order): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                        <td><?php echo htmlspecialchars($order['seller_name']); ?></td>
                        <td><?php echo htmlspecialchars($order['buyer_name']); ?></td>
                        <td><?php echo $order['quantity']; ?> قطعة</td>
                        <td><?php echo number_format($order['order_total'], 2); ?> دج</td>
                        <td><?php echo getPaymentTypeText($order['payment_type'] ?? 'advance'); ?></td>
                        <td><?php echo translateOrderStatus($order['order_status']); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($order['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>