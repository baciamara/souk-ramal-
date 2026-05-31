<?php
// جلب الشكاوى
$complaints = [];
try {
    $complaints = $pdo->query("
        SELECT c.*, o.order_number, p.name as product_name, comp.full_name as complainer_name, acc.full_name as accused_name
        FROM complaints c
        JOIN orders o ON c.order_id = o.id
        JOIN products p ON o.product_id = p.id
        JOIN users comp ON c.complainer_id = comp.id
        JOIN users acc ON c.accused_id = acc.id
        ORDER BY c.created_at DESC
    ")->fetchAll();
} catch(PDOException $e) {}
?>

<div class="card">
    <div class="card-header">⚠️ إدارة شكاوى المستخدمين</div>
    <div class="card-body">
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>رقم الطلب</th>
                        <th>المنتج</th>
                        <th>مقدم الشكوى</th>
                        <th>المشكو بحقه</th>
                        <th>النوع</th>
                        <th>العنوان</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th>إجراء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($complaints as $c): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($c['order_number']); ?></td>
                        <td><?php echo htmlspecialchars($c['product_name']); ?></td>
                        <td><?php echo htmlspecialchars($c['complainer_name']); ?></td>
                        <td><?php echo htmlspecialchars($c['accused_name']); ?></td>
                        <td><?php echo translateComplaintType($c['complaint_type']); ?></td>
                        <td><?php echo htmlspecialchars($c['subject']); ?></td>
                        <td><?php echo translateComplaintStatus($c['status']); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($c['created_at'])); ?></td>
                        <td>
                            <button onclick="showComplaintModal(<?php echo htmlspecialchars(json_encode($c)); ?>)" class="btn-small btn-warning">عرض</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>