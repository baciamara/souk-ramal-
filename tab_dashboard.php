<div class="card">
    <div class="card-header">📊 مرحباً <?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
    <div class="card-body">
        <p>مرحباً بك في لوحة تحكم المدير. يمكنك التنقل بين التبويبات لإدارة الموقع.</p>
        <p style="margin-top: 15px;">👥 <strong>إجمالي المستخدمين:</strong> <?php echo array_sum(array_column($user_stats, 'count')); ?></p>
        <p>🛍️ <strong>إجمالي البائعين:</strong> <?php $sc = 0; foreach($user_stats as $s) if($s['user_type']=='seller') $sc=$s['count']; echo $sc; ?></p>
        <p>📦 <strong>إجمالي المنتجات:</strong> <?php echo $products_count; ?></p>
        <p>✅ <strong>الطلبات المكتملة:</strong> <?php echo $orders_count; ?></p>
        <p>💰 <strong>إجمالي المبيعات:</strong> <?php echo number_format($total_sales, 2); ?> دج</p>
        <p>💸 <strong>عمولات الموقع:</strong> <?php echo number_format($total_commission, 2); ?> دج</p>
    </div>
</div>