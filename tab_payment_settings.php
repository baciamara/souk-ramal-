<div class="card">
    <div class="card-header">⚙️ إعدادات نظام الدفع</div>
    <div class="card-body">
        <div class="payment-settings">
            <form method="POST" onsubmit="saveScrollPosition()">
                <input type="hidden" name="scroll_pos" value="0">
                <div id="paymentOptionBoth" class="payment-option <?php echo $system_payment_type == 'both' ? 'selected' : ''; ?>" onclick="selectPaymentSetting('both')">
                    🔄 كلتا الطريقتين
                </div>
                <div id="paymentOptionAdvance" class="payment-option <?php echo $system_payment_type == 'advance_only' ? 'selected' : ''; ?>" onclick="selectPaymentSetting('advance_only')">
                    💳 العربون فقط
                </div>
                <div id="paymentOptionCash" class="payment-option <?php echo $system_payment_type == 'cash_only' ? 'selected' : ''; ?>" onclick="selectPaymentSetting('cash_only')">
                    💵 الدفع عند الاستلام فقط
                </div>
                <input type="hidden" name="payment_type" id="paymentTypeSetting" value="<?php echo $system_payment_type; ?>">
                <button type="submit" name="update_payment_settings" class="btn-success">💾 حفظ</button>
            </form>
        </div>
    </div>
</div>