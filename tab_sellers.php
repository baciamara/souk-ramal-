<?php
// جلب بيانات البائعين
$sellers = $pdo->query("SELECT * FROM users WHERE user_type = 'seller' ORDER BY created_at DESC")->fetchAll();

// جلب العمولة الافتراضية وقائمة البائعين للعمولات
$default_rate = getDefaultCommission($pdo);
$sellers_list = $pdo->query("SELECT u.id, u.full_name, u.custom_commission_rate, u.commission_note FROM users u WHERE u.user_type = 'seller' ORDER BY u.full_name")->fetchAll();
?>

<div class="card">
    <div class="card-header">🛍️ إدارة البائعين</div>
    <div class="card-body">
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>البريد</th>
                        <th>الهاتف</th>
                        <th>التقييم</th>
                        <th>الحالة</th>
                        <th>مسجل منذ</th>
                        <th>تعطيل/تفعيل</th>
                        <th>تفعيل الحساب</th>
                        <th>حذف</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($sellers as $seller): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($seller['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($seller['email']); ?></td>
                        <td><?php echo htmlspecialchars($seller['phone'] ?? '—'); ?></td>
                        <td><?php echo number_format($seller['seller_avg_rating'] ?? 0, 1); ?>/5</td>
                        <td><?php echo $seller['is_disabled'] ? '⛔ معطل' : '✅ نشط'; ?></td>
                        <td><?php echo date('Y-m-d', strtotime($seller['created_at'])); ?></td>
                        <td>
                            <a href="?toggle_disable=1&user_id=<?php echo $seller['id']; ?>&active_tab=sellers&scroll_pos=<?php echo $scroll_pos; ?>" class="btn-small <?php echo $seller['is_disabled'] ? 'btn-success' : 'btn-warning'; ?>">
                                <?php echo $seller['is_disabled'] ? 'تفعيل' : 'تعطيل'; ?>
                            </a>
                        </td>
                        <td>
                            <a href="?toggle_status=1&user_id=<?php echo $seller['id']; ?>&active_tab=sellers&scroll_pos=<?php echo $scroll_pos; ?>" class="btn-small btn-success">تفعيل</a>
                        </td>
                        <td>
                            <a href="?delete_user=1&user_id=<?php echo $seller['id']; ?>&active_tab=sellers&scroll_pos=<?php echo $scroll_pos; ?>" class="btn-small btn-danger" onclick="return confirm('حذف البائع نهائياً؟')">حذف</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 30px;">
    <div class="card-header">⚙️ إدارة عمولات البائعين</div>
    <div class="card-body">
        <form method="POST" class="commission-form">
            <div class="commission-form-row">
                <div class="commission-form-field">
                    <label class="commission-label">🏷️ نسبة العمولة الافتراضية (%)</label>
                    <input type="number" name="default_commission" step="0.5" min="0" max="100" value="<?php echo $default_rate; ?>" class="commission-input">
                </div>
                <div>
                    <button type="submit" name="update_default_commission" class="btn-success" style="padding: 10px 20px;">💾 حفظ</button>
                </div>
            </div>
        </form>
        
        <h4 class="commission-subtitle">📊 العمولات المخصصة للبائعين</h4>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>البائع</th>
                        <th>العمولة الافتراضية</th>
                        <th>العمولة المخصصة</th>
                        <th>العمولة الفعلية</th>
                        <th>السبب / ملاحظة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($sellers_list as $seller): ?>
                    <?php 
                        $custom_rate = $seller['custom_commission_rate'];
                        $actual_rate = ($custom_rate !== null) ? floatval($custom_rate) : $default_rate;
                        $badge_class = 'commission-default';
                        if ($custom_rate === 0) {
                            $badge_class = 'commission-free';
                        } elseif ($custom_rate !== null && $custom_rate < $default_rate) {
                            $badge_class = 'commission-reduced';
                        } elseif ($custom_rate !== null && $custom_rate > $default_rate) {
                            $badge_class = 'commission-increased';
                        }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($seller['full_name']); ?></td>
                        <td><?php echo $default_rate; ?>%</td>
                        <td>
                            <?php if($custom_rate !== null): ?>
                                <span class="<?php echo $badge_class; ?>"><?php echo $custom_rate; ?>%</span>
                            <?php else: ?>
                                <span class="commission-none">—</span>
                            <?php endif; ?>
                        </td>
                        <td><strong class="<?php echo $badge_class; ?>"><?php echo $actual_rate; ?>%</strong></td>
                        <td><?php echo htmlspecialchars($seller['commission_note'] ?? '—'); ?></td>
                        <td>
                            <button onclick="openCommissionModal(<?php echo $seller['id']; ?>, '<?php echo addslashes($seller['full_name']); ?>', <?php echo $custom_rate !== null ? $custom_rate : 'null'; ?>, '<?php echo addslashes($seller['commission_note'] ?? ''); ?>')" class="btn-small btn-warning">✏️ تعديل</button>
                            <?php if($custom_rate !== null): ?>
                                <a href="?reset_commission=<?php echo $seller['id']; ?>&active_tab=sellers&scroll_pos=<?php echo $scroll_pos; ?>" class="btn-small btn-danger" onclick="return confirm('هل تريد إعادة تعيين العمولة للقيمة الافتراضية؟')">🔄 إعادة تعيين</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    /* نموذج العمولة الافتراضية */
    .commission-form {
        margin-bottom: 25px; 
        padding: 15px; 
        background: var(--bg-card-alt); 
        border-radius: 15px;
    }
    
    .commission-form-row {
        display: flex; 
        gap: 15px; 
        flex-wrap: wrap; 
        align-items: flex-end;
    }
    
    .commission-form-field {
        flex: 1;
    }
    
    .commission-label {
        font-weight: bold; 
        color: var(--text-secondary);
        display: block;
        margin-bottom: 5px;
    }
    
    .commission-input {
        width: 100%; 
        padding: 10px; 
        border-radius: 10px; 
        border: 1px solid var(--border-input, #ddd); 
        background: var(--bg-input); 
        color: var(--text-primary);
        transition: border-color 0.3s;
    }
    
    .commission-input:focus {
        outline: none; 
        border-color: var(--text-heading);
    }
    
    .commission-subtitle {
        margin: 20px 0 15px; 
        color: var(--text-heading);
    }
    
    /* ألوان العمولات */
    .commission-default {
        color: var(--text-primary);
    }
    
    .commission-free {
        color: var(--success-text); 
        font-weight: bold;
    }
    
    .commission-reduced {
        color: var(--badge-info-text); 
        font-weight: bold;
    }
    
    .commission-increased {
        color: var(--badge-danger-text); 
        font-weight: bold;
    }
    
    .commission-none {
        color: var(--text-muted);
    }
</style>