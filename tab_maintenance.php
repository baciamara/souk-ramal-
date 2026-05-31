<div class="card">
    <div class="card-header">🔧 إعدادات وضع الصيانة</div>
    <div class="card-body">
        <div class="maintenance-settings">
            <form method="POST" onsubmit="saveScrollPosition()">
                <input type="hidden" name="scroll_pos" value="0">
                <div style="margin-bottom:20px;">
                    <label style="display:flex; align-items:center; gap:10px;">
                        <input type="checkbox" name="maintenance_mode" value="1" <?php echo $maintenance_mode ? 'checked' : ''; ?>>
                        <span>🔒 تفعيل وضع الصيانة</span>
                    </label>
                </div>
                <div style="margin-bottom:20px;">
                    <label>📝 رسالة الصيانة</label>
                    <textarea name="maintenance_message" rows="3" style="width:100%; padding:10px; border-radius:10px;"><?php echo htmlspecialchars($maintenance_message); ?></textarea>
                </div>
                <button type="submit" name="update_maintenance" class="btn-success">💾 حفظ</button>
            </form>
        </div>
    </div>
</div>