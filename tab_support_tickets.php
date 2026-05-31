<?php
// جلب تذاكر الدعم مع ردودها
$support_tickets = [];
$tickets_with_replies = [];

try {
    // جلب التذاكر مع البريد الإلكتروني للمستخدم
    $stmt = $pdo->query("
        SELECT st.*, u.full_name as user_name, u.user_type, u.email 
        FROM support_tickets st 
        JOIN users u ON st.user_id = u.id 
        ORDER BY st.created_at DESC
    ");
    $support_tickets = $stmt->fetchAll();

    // جلب ردود جميع التذاكر دفعة واحدة
    if (count($support_tickets) > 0) {
        $ticket_ids = array_column($support_tickets, 'id');
        $placeholders = implode(',', array_fill(0, count($ticket_ids), '?'));
        $replies_stmt = $pdo->prepare("
            SELECT tr.*, u.full_name as replier_name, u.user_type as replier_type
            FROM ticket_replies tr
            JOIN users u ON tr.user_id = u.id
            WHERE tr.ticket_id IN ($placeholders)
            ORDER BY tr.created_at ASC
        ");
        $replies_stmt->execute($ticket_ids);
        $all_replies = $replies_stmt->fetchAll();

        foreach ($all_replies as $reply) {
            $tickets_with_replies[$reply['ticket_id']][] = $reply;
        }
    }
} catch(PDOException $e) {
    $support_tickets = [];
}

$tickets_json = json_encode($support_tickets);
$replies_json = json_encode($tickets_with_replies);

function translateTicketStatus($status) {
    $statuses = [
        'open' => '🔓 مفتوحة',
        'in_progress' => '⏳ قيد المعالجة',
        'closed' => '🔒 مغلقة'
    ];
    return $statuses[$status] ?? $status;
}

function translateTicketPriority($priority) {
    $priorities = [
        'low' => '🟢 منخفضة',
        'medium' => '🟡 متوسطة',
        'high' => '🔴 عالية'
    ];
    return $priorities[$priority] ?? $priority;
}

// دالة مساعدة لترجمة نوع المستخدم في PHP
function getUserTypeLabelPHP($type) {
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
    <div class="card-header">🎫 تذاكر الدعم الفني</div>
    <div class="card-body">
        <?php if(count($support_tickets) > 0): ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>المستخدم</th>
                            <th>نوع المستخدم</th>
                            <th>الموضوع</th>
                            <th>الأولوية</th>
                            <th>الحالة</th>
                            <th>التاريخ</th>
                            <th>عرض</th>
                            <th>حذف</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($support_tickets as $ticket): ?>
                        <tr>
                            <td><strong>#<?php echo $ticket['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($ticket['user_name']); ?></td>
                            <td><?php echo getUserTypeLabelPHP($ticket['user_type']); ?></td>
                            <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                            <td><?php echo translateTicketPriority($ticket['priority'] ?? 'medium'); ?></td>
                            <td><?php echo translateTicketStatus($ticket['status']); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($ticket['created_at'])); ?></td>
                            <td>
                                <button onclick="openTicketModal(<?php echo $ticket['id']; ?>)" class="btn-small btn-warning">👁️ عرض</button>
                            </td>
                            <td>
                                <a href="?delete_ticket=1&ticket_id=<?php echo $ticket['id']; ?>&active_tab=support_tickets&scroll_pos=<?php echo $scroll_pos; ?>" 
                                   class="btn-small btn-danger" 
                                   onclick="return confirm('هل أنت متأكد من حذف التذكرة #<?php echo $ticket['id']; ?>؟\nسيتم حذف جميع الردود المرتبطة أيضاً.')">
                                   🗑️ حذف
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="text-align:center; padding:20px; color: var(--text-muted);">🎉 لا توجد تذاكر دعم حالياً</p>
        <?php endif; ?>
    </div>
</div>

<!-- نافذة عرض تفاصيل التذكرة والرد -->
<div id="ticketModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:10000; align-items:center; justify-content:center;">
    <div style="background: var(--bg-card); width:650px; max-width:95%; border-radius:25px; padding:25px; max-height:85vh; overflow-y:auto;">
        <h3 style="color: var(--text-heading); margin-bottom:15px;">🎫 تفاصيل التذكرة <span id="modalTicketId"></span></h3>
        
        <div id="ticketDetails"></div>
        <div id="ticketReplies" style="margin:15px 0;"></div>
        
        <div style="background: var(--bg-header-card); padding:15px; border-radius:15px; margin-top:20px;">
            <h4 style="color: var(--text-heading); margin-bottom:10px;">✍️ الرد على التذكرة</h4>
            <form method="POST" onsubmit="saveScrollPosition()">
                <input type="hidden" name="reply_ticket_id" id="reply_ticket_id">
                <input type="hidden" name="scroll_pos" value="<?php echo $scroll_pos; ?>">
                <div style="margin-bottom:10px;">
                    <textarea name="reply_message" rows="3" required placeholder="اكتب ردك هنا..." style="width:100%; padding:10px; border-radius:10px; border:1px solid var(--border-input, #ddd); background: var(--bg-input); color: var(--text-primary);"></textarea>
                </div>
                <div style="margin-bottom:10px;">
                    <label style="color: var(--text-secondary);">📊 تحديث حالة التذكرة:</label>
                    <select name="ticket_status" style="width:100%; padding:10px; border-radius:10px; border:1px solid var(--border-input, #ddd); background: var(--bg-input); color: var(--text-primary);">
                        <option value="open">🔓 مفتوحة</option>
                        <option value="in_progress">⏳ قيد المعالجة</option>
                        <option value="closed">🔒 إغلاق</option>
                    </select>
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="submit" name="reply_ticket" class="btn-success" style="flex:1;">📨 إرسال الرد</button>
                    <button type="button" onclick="closeTicketModal()" class="btn-danger" style="flex:1;">✖️ إلغاء</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var ticketsData = <?php echo $tickets_json ?: '[]'; ?>;
var repliesData = <?php echo $replies_json ?: '{}'; ?>;

function openTicketModal(ticketId) {
    var ticket = ticketsData.find(function(t) { return t.id == ticketId; });
    if (!ticket) return;

    var detailsHtml = '';
    detailsHtml += '<div style="background: var(--bg-card-alt); padding:15px; border-radius:10px; margin-bottom:10px;">';
    detailsHtml += '<p style="color: var(--text-primary);"><strong>👤 المستخدم:</strong> ' + escapeHtml(ticket.user_name) + ' (' + getUserTypeLabel(ticket.user_type) + ')</p>';
    detailsHtml += '<p style="color: var(--text-primary);"><strong>📧 البريد:</strong> ' + escapeHtml(ticket.email || 'غير متوفر') + '</p>';
    detailsHtml += '<p style="color: var(--text-primary);"><strong>📌 الموضوع:</strong> ' + escapeHtml(ticket.subject) + '</p>';
    detailsHtml += '<p style="color: var(--text-primary);"><strong>📝 الرسالة:</strong><br>' + escapeHtml(ticket.message).replace(/\n/g, '<br>') + '</p>';
    detailsHtml += '<p style="color: var(--text-primary);"><strong>🚦 الأولوية:</strong> ' + getPriorityLabel(ticket.priority) + '</p>';
    detailsHtml += '<p style="color: var(--text-primary);"><strong>📊 الحالة:</strong> ' + getStatusLabel(ticket.status) + '</p>';
    detailsHtml += '<p style="color: var(--text-primary);"><strong>📅 التاريخ:</strong> ' + formatDate(ticket.created_at) + '</p>';
    detailsHtml += '</div>';
    document.getElementById('ticketDetails').innerHTML = detailsHtml;
    document.getElementById('modalTicketId').innerText = '#' + ticket.id;
    document.getElementById('reply_ticket_id').value = ticket.id;

    var replies = repliesData[ticketId] || [];
    var repliesHtml = '';
    if (replies.length > 0) {
        repliesHtml += '<h4 style="color: var(--text-heading);">💬 الردود (' + replies.length + ')</h4>';
        replies.forEach(function(reply) {
            var bgColor = reply.user_type === 'admin' ? 'var(--success-bg)' : 'var(--bg-card-alt)';
            var borderColor = reply.user_type === 'admin' ? 'var(--success-text)' : 'var(--text-heading)';
            repliesHtml += '<div style="background:' + bgColor + '; padding:10px; border-radius:8px; margin-bottom:8px; border-right:3px solid ' + borderColor + ';">';
            repliesHtml += '<p style="color: var(--text-primary);"><strong>' + escapeHtml(reply.replier_name) + '</strong> (' + getUserTypeLabel(reply.user_type) + ') - ' + formatDate(reply.created_at) + '</p>';
            repliesHtml += '<p style="color: var(--text-primary);">' + escapeHtml(reply.message).replace(/\n/g, '<br>') + '</p>';
            repliesHtml += '</div>';
        });
    } else {
        repliesHtml = '<p style="color: var(--text-muted);">لا توجد ردود بعد.</p>';
    }
    document.getElementById('ticketReplies').innerHTML = repliesHtml;

    document.getElementById('ticketModal').style.display = 'flex';
}

function closeTicketModal() {
    document.getElementById('ticketModal').style.display = 'none';
}

function escapeHtml(text) {
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return (text || '').toString().replace(/[&<>"']/g, function(m) { return map[m]; });
}

function getUserTypeLabel(type) {
    var labels = {
        'seller': '🛍️ بائع',
        'buyer': '🛒 مشتري',
        'shipping_company': '🚚 شركة توصيل',
        'pickup_point': '📍 نقطة تجميع',
        'admin': '👑 إدارة'
    };
    return labels[type] || '👤 ' + type;
}

function getPriorityLabel(priority) {
    if (priority === 'low') return '🟢 منخفضة';
    if (priority === 'high') return '🔴 عالية';
    return '🟡 متوسطة';
}

function getStatusLabel(status) {
    if (status === 'open') return '🔓 مفتوحة';
    if (status === 'in_progress') return '⏳ قيد المعالجة';
    if (status === 'closed') return '🔒 مغلقة';
    return status;
}

function formatDate(dateString) {
    if (!dateString) return '';
    var d = new Date(dateString);
    return d.getFullYear() + '-' + 
           ('0' + (d.getMonth() + 1)).slice(-2) + '-' + 
           ('0' + d.getDate()).slice(-2) + ' ' +
           ('0' + d.getHours()).slice(-2) + ':' +
           ('0' + d.getMinutes()).slice(-2);
}

document.getElementById('ticketModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeTicketModal();
    }
});
</script>