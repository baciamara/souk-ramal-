<?php
// جلب آخر الإشعارات المرسلة مع اسم المستخدم ونوعه
$recent_notifications = [];
try {
    $recent_notifications = $pdo->query("
        SELECT n.*, u.full_name as user_name, u.user_type 
        FROM notifications n 
        JOIN users u ON n.user_id = u.id 
        ORDER BY n.created_at DESC 
        LIMIT 50
    ")->fetchAll();
} catch(PDOException $e) {}

// دالة ترجمة نوع المستخدم للعرض
function getUserTypeForNotification($type) {
    $labels = [
        'seller' => '🛍️ بائع',
        'buyer' => '🛒 مشتري',
        'shipping_company' => '🚚 شركة توصيل',
        'pickup_point' => '📍 نقطة تجميع',
        'admin' => '👑 مدير'
    ];
    return $labels[$type] ?? '👤 ' . htmlspecialchars($type);
}
?>

<div class="card">
    <div class="card-header">📢 إرسال إشعار للمستخدمين</div>
    <div class="card-body">
        <div class="notification-settings">
            <form method="POST" onsubmit="saveScrollPosition()">
                <input type="hidden" name="scroll_pos" value="0">
                <div>
                    <label>👥 إرسال إلى:</label>
                    <select name="target_type" id="targetType" onchange="toggleSpecificUser()" style="width:100%; padding:10px;">
                        <option value="all">📢 جميع المستخدمين</option>
                        <option value="buyers">🛒 المشترين</option>
                        <option value="sellers">🛍️ البائعين</option>
                        <option value="specific_user">👤 مستخدم محدد</option>
                    </select>
                </div>
                <div id="specificUserDiv" style="display:none;">
                    <label>👤 اختر المستخدم:</label>
                    <select name="specific_user_id" style="width:100%; padding:10px;">
                        <option value="">-- اختر المستخدم --</option>
                        <?php foreach($users_list as $user): ?>
                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?> (<?php echo getUserTypeForNotification($user['user_type']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>📌 عنوان الإشعار:</label>
                    <input type="text" name="title" required style="width:100%; padding:10px;">
                </div>
                <div>
                    <label>📝 نص الإشعار:</label>
                    <textarea name="message" rows="3" required style="width:100%; padding:10px;"></textarea>
                </div>
                <div>
                    <label>🔗 رابط (اختياري):</label>
                    <input type="text" name="link" style="width:100%; padding:10px;">
                </div>
                <button type="submit" name="send_notification" class="btn-success">📨 إرسال</button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">📋 سجل الإشعارات المرسلة</div>
    <div class="card-body">
        <?php if(count($recent_notifications) > 0): ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>إلى (المستخدم)</th>
                            <th>النوع</th>
                            <th>العنوان</th>
                            <th>الرسالة</th>
                            <th>الحالة</th>
                            <th>التاريخ</th>
                            <th>حذف</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_notifications as $notif): ?>
                        <tr>
                            <td><?php echo $notif['id']; ?></td>
                            <td><?php echo htmlspecialchars($notif['user_name']); ?></td>
                            <td><?php echo getUserTypeForNotification($notif['user_type']); ?></td>
                            <td><?php echo htmlspecialchars($notif['title']); ?></td>
                            <td><?php echo htmlspecialchars(mb_substr($notif['message'], 0, 60)); ?>...</td>
                            <td><?php echo $notif['is_read'] ? '✅ مقروء' : '⏳ غير مقروء'; ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($notif['created_at'])); ?></td>
                            <td>
                                <a href="?delete_notif=1&notif_id=<?php echo $notif['id']; ?>&active_tab=notifications&scroll_pos=<?php echo $scroll_pos; ?>" 
                                   class="btn-small btn-danger" 
                                   onclick="return confirm('هل أنت متأكد من حذف هذا الإشعار؟')">
                                   🗑️ حذف
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>لا توجد إشعارات مرسلة بعد</p>
        <?php endif; ?>
    </div>
</div>