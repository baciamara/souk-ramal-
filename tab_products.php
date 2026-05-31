<?php
// جلب جميع المنتجات مرتبة من الأعلى تقييماً إلى الأدنى
$all_products = $pdo->query("SELECT p.*, u.full_name as seller_name FROM products p JOIN users u ON p.seller_id = u.id ORDER BY COALESCE(p.avg_rating, 0) DESC, p.ratings_count DESC, p.created_at DESC")->fetchAll();
?>

<style>
    .products-stars {
        color: var(--color-yellow, #ffc107);
        font-size: 14px;
        letter-spacing: 2px;
    }
    
    .products-rating-info {
        color: var(--text-muted);
        font-size: 12px;
        display: block;
        margin-top: 3px;
    }
    
    .products-no-rating {
        color: var(--text-muted);
        font-size: 14px;
        letter-spacing: 2px;
    }
</style>

<div class="card">
    <div class="card-header">📦 إدارة المنتجات</div>
    <div class="card-body">
        <?php if(count($all_products) > 0): ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>المنتج</th>
                            <th>البائع</th>
                            <th>السعر</th>
                            <th>الكمية</th>
                            <th>التقييم</th>
                            <th>الحالة</th>
                            <th>حذف</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($all_products as $product): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php echo htmlspecialchars($product['seller_name']); ?></td>
                            <td><?php echo number_format($product['price'], 2); ?> دج</td>
                            <td><?php echo $product['stock']; ?> قطعة</td>
                            <td>
                                <?php if($product['avg_rating'] > 0): ?>
                                    <div class="products-stars">
                                        <?php echo str_repeat('★', round($product['avg_rating'])); ?>
                                        <?php echo str_repeat('☆', 5 - round($product['avg_rating'])); ?>
                                    </div>
                                    <small class="products-rating-info">(<?php echo number_format($product['avg_rating'], 1); ?>/5 - <?php echo $product['ratings_count']; ?> تقييم)</small>
                                <?php else: ?>
                                    <span class="products-no-rating">☆☆☆☆☆</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $product['status'] == 'available' ? '🟢 متوفر' : '🔴 تم البيع'; ?></td>
                            <td>
                                <a href="?delete_product=1&product_id=<?php echo $product['id']; ?>&active_tab=products&scroll_pos=<?php echo $scroll_pos; ?>" class="btn-small btn-danger" onclick="return confirm('حذف المنتج؟')">🗑️ حذف</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="text-align:center; padding:30px; color: var(--text-muted);">📭 لا توجد منتجات بعد</p>
        <?php endif; ?>
    </div>
</div>