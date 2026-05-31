<?php
// جلب الطلبات الملغاة
$cancelled_orders_list = $pdo->query("
    SELECT o.*, oi.product_name, oi.quantity, oi.total_price as order_total,
           u.full_name as buyer_name, s.full_name as seller_name
    FROM orders o JOIN order_items oi ON o.id = oi.order_id
    JOIN users u ON o.buyer_id = u.id JOIN users s ON o.seller_id = s.id
    WHERE o.order_status = 'cancelled' ORDER BY o.created_at DESC LIMIT 100
")->fetchAll();

// جلب البائعين للتصفية
$sellers_for_filter = $pdo->query("SELECT id, full_name FROM users WHERE user_type = 'seller' ORDER BY full_name")->fetchAll();
?>

<div class="card">
    <div class="card-header">❌ الطلبات الملغاة</div>
    <div class="card-body">
        <div class="filter-bar">
            <label>🔍 تصفية حسب البائع:</label>
            <select id="filterSeller" onchange="filterCancelledOrders()" style="padding:5px 10px; border-radius:10px;">
                <option value="all">-- جميع البائعين --</option>
                <?php foreach($sellers_for_filter as $seller): ?>
                    <option value="<?php echo $seller['id']; ?>"><?php echo htmlspecialchars($seller['full_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="table-wrapper">
            <table class="data-table" id="cancelledOrdersTable">
                <thead>
                    <tr>
                        <th>رقم الطلب</th>
                        <th>المنتج</th>
                        <th>البائع</th>
                        <th>المشتري</th>
                        <th>الكمية</th>
                        <th>المبلغ</th>
                        <th>طريقة الدفع</th>
                        <th>التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($cancelled_orders_list as $order): ?>
                    <tr class="order-row" data-seller-id="<?php echo $order['seller_id']; ?>">
                        <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                        <td><?php echo htmlspecialchars($order['seller_name']); ?></td>
                        <td><?php echo htmlspecialchars($order['buyer_name']); ?></td>
                        <td><?php echo $order['quantity']; ?> قطعة</td>
                        <td><?php echo number_format($order['order_total'], 2); ?> دج</td>
                        <td><?php echo getPaymentTypeText($order['payment_type'] ?? 'advance'); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($order['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>