<?php
if (!isset($pdo)) {
    header('HTTP/1.0 403 Forbidden');
    exit('لا يمكن الوصول مباشرة');
}

$tickets = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.*, u.full_name as user_name, u.email as user_email
        FROM support_tickets t
        JOIN users u ON t.user_id = u.id
        ORDER BY 
            CASE WHEN t.status = 'open' THEN 1 WHEN t.status = 'in_progress' THEN 2 ELSE 3 END,
            t.created_at DESC
    ");
    $stmt->execute();
    $tickets = $stmt->fetchAll();
} catch(PDOException $e) {
    echo '<div class="error">⚠️ جدول التذاكر غير موجود: ' . $e->getMessage() . '</div>';
}
?>

<div class="card">
    <div class="card-header">🎫 إدارة تذاكر الدعم الفني</div>
    <div class="card-body">
        <?php if (!empty($tickets)): ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>العميل</th>
                        <th>الموضوع</th>
                        <th>الأولوية</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($tickets as $ticket): ?>
                    <tr id="ticket-row-<?php echo $ticket['id']; ?>">
                        <td><?php echo $ticket['id']; ?></td>
                        <td><?php echo htmlspecialchars($ticket['user_name']); ?><br><small><?php echo htmlspecialchars($ticket['user_email']); ?></small></td>
                        <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                        <td>
                            <?php
                            if ($ticket['priority'] == 'high') echo '<span class="badge" style="background:#c62828;">🔴 عالية</span>';
                            elseif ($ticket['priority'] == 'medium') echo '<span class="badge" style="background:#e65100;">🟠 متوسطة</span>';
                            else echo '<span class="badge" style="background:#2e7d32;">🟢 منخفضة</span>';
                            ?>
                        </td>
                        <td>
                            <?php
                            if ($ticket['status'] == 'open') echo '<span class="status-open">🟡 مفتوحة</span>';
                            elseif ($ticket['status'] == 'in_progress') echo '<span class="status-progress">🔵 قيد المعالجة</span>';
                            else echo '<span class="status-closed">🟢 مغلقة</span>';
                            ?>
                        </td>
                        <td><?php echo date('Y-m-d H:i', strtotime($ticket['created_at'])); ?></td>
                        <td>
                            <button class="btn-small btn-warning" onclick="openReplyModal(<?php echo $ticket['id']; ?>, '<?php echo addslashes($ticket['subject']); ?>')">💬 رد</button>
                            <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn-small btn-success" target="_blank">👁️ عرض</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p>📭 لا توجد تذاكر دعم بعد.</p>
        <?php endif; ?>
    </div>
</div>

<!-- نافذة الرد المنبثقة -->
<div id="replyTicketModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:white; width:500px; max-width:95%; border-radius:25px; padding:20px;">
        <h3 style="color:#b8860b;">💬 الرد على التذكرة</h3>
        <form method="POST" action="admin_dashboard.php" onsubmit="saveScrollPosition()">
            <input type="hidden" name="reply_ticket_id" id="reply_ticket_id" value="">
            <input type="hidden" name="scroll_pos" value="<?php echo $scroll_pos ?? 0; ?>">
            <div style="margin-bottom:15px;">
                <label>الموضوع:</label>
                <input type="text" id="ticket_subject" readonly style="width:100%; padding:8px; background:#f5f5f5; border:1px solid #ddd; border-radius:10px;">
            </div>
            <div style="margin-bottom:15px;">
                <label>الرد:</label>
                <textarea name="reply_message" rows="4" style="width:100%; padding:10px; border-radius:10px; border:1px solid #ddd;" required></textarea>
            </div>
            <div style="margin-bottom:15px;">
                <label>تغيير الحالة:</label>
                <select name="ticket_status" style="width:100%; padding:8px; border-radius:10px; border:1px solid #ddd;">
                    <option value="open">🟡 مفتوحة</option>
                    <option value="in_progress">🔵 قيد المعالجة</option>
                    <option value="closed">🟢 مغلقة</option>
                </select>
            </div>
            <div style="display:flex; gap:10px;">
                <button type="submit" name="reply_ticket" class="btn-success" style="flex:1;">📨 إرسال الرد</button>
                <button type="button" onclick="closeReplyModal()" class="btn-danger" style="flex:1;">✖️ إلغاء</button>
            </div>
        </form>
    </div>
</div>

<script>
function openReplyModal(ticketId, subject) {
    document.getElementById('reply_ticket_id').value = ticketId;
    document.getElementById('ticket_subject').value = subject;
    document.getElementById('replyTicketModal').style.display = 'flex';
}
function closeReplyModal() {
    document.getElementById('replyTicketModal').style.display = 'none';
}
</script>

<style>
.badge { display: inline-block; padding: 3px 10px; border-radius: 20px; color: white; font-size: 12px; }
.status-open { background: #fff3e0; color: #e65100; padding: 3px 10px; border-radius: 20px; display: inline-block; }
.status-progress { background: #e3f2fd; color: #1565c0; padding: 3px 10px; border-radius: 20px; display: inline-block; }
.status-closed { background: #e8f5e9; color: #2e7d32; padding: 3px 10px; border-radius: 20px; display: inline-block; }
.btn-warning { background: #e65100; color: white; border: none; cursor: pointer; padding: 4px 10px; border-radius: 20px; }
.btn-success { background: #2e7d32; color: white; padding: 4px 10px; border-radius: 20px; text-decoration: none; display: inline-block; }
</style>