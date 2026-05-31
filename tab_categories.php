<?php
// جلب جميع الفئات
$all_categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
?>

<div class="card">
    <div class="card-header">
        📁 إدارة الفئات
        <button onclick="openAddModal()" class="btn-small btn-success">➕ إضافة فئة</button>
    </div>
    <div class="card-body">
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>اسم الفئة</th>
                        <th>أيقونة</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $cat_counter = 1; foreach($all_categories as $cat): ?>
                    <tr>
                        <td><?php echo $cat_counter++; ?></td>
                        <td><?php echo htmlspecialchars($cat['name']); ?></td>
                        <td><?php echo $cat['icon'] ?? '—'; ?></td>
                        <td><?php echo $cat['is_active'] ? '🟢 مفعل' : '🔴 غير مفعل'; ?></td>
                        <td>
                            <button onclick="openEditModal(<?php echo $cat['id']; ?>, '<?php echo addslashes($cat['name']); ?>', '<?php echo addslashes($cat['icon'] ?? ''); ?>', <?php echo $cat['is_active']; ?>)" class="btn-small btn-warning">✏️ تعديل</button>
                            <a href="?delete_category=1&cat_id=<?php echo $cat['id']; ?>&active_tab=categories&scroll_pos=<?php echo $scroll_pos; ?>" class="btn-small btn-danger" onclick="return confirm('حذف الفئة؟')">🗑️ حذف</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>