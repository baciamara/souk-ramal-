<?php
// جلب أحدث التقييمات
$latest_ratings = $pdo->query("
    SELECT r.*, p.name as product_name, u.full_name as buyer_name, s.full_name as seller_name
    FROM ratings r JOIN products p ON r.product_id = p.id
    JOIN users u ON r.buyer_id = u.id JOIN users s ON r.seller_id = s.id
    ORDER BY r.rating DESC, r.created_at DESC
")->fetchAll();
?>

<style>
    .ratings-stars {
        color: var(--color-yellow, #ffc107);
        font-size: 14px;
        letter-spacing: 2px;
    }
    
    .ratings-value {
        color: var(--text-muted);
        font-size: 12px;
        display: block;
        margin-top: 3px;
    }
    
    .ratings-comment {
        color: var(--text-primary);
    }
</style>

<div class="card">
    <div class="card-header">⭐ التقييمات</div>
    <div class="card-body">
        <?php if(count($latest_ratings) > 0): ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>المنتج</th>
                            <th>البائع</th>
                            <th>المشتري</th>
                            <th>التقييم</th>
                            <th>التعليق</th>
                            <th>التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($latest_ratings as $r): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['product_name']); ?></td>
                            <td><?php echo htmlspecialchars($r['seller_name']); ?></td>
                            <td><?php echo htmlspecialchars($r['buyer_name']); ?></td>
                            <td>
                                <div class="ratings-stars">
                                    <?php echo str_repeat('★', round($r['rating'])); ?>
                                    <?php echo str_repeat('☆', 5 - round($r['rating'])); ?>
                                </div>
                                <small class="ratings-value">(<?php echo number_format($r['rating'], 1); ?>/5)</small>
                            </td>
                            <td class="ratings-comment"><?php echo nl2br(htmlspecialchars($r['comment'] ?: '—')); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($r['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="text-align:center; padding:30px; color: var(--text-muted);">📭 لا توجد تقييمات بعد</p>
        <?php endif; ?>
    </div>
</div>