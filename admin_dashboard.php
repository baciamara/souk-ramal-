<?php
// استدعاء جميع المنطق والإعدادات
require_once 'admin_config.php';
// استدعاء الهيدر الموحد
include_once 'header.php';

// ============================================================
// الإحصائيات العامة
// ============================================================
$user_stats = $pdo->query("SELECT user_type, COUNT(*) as count FROM users GROUP BY user_type")->fetchAll();
$products_count = $pdo->query("SELECT COUNT(*) as total FROM products")->fetch()['total'];
$orders_count = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE order_status = 'delivered_to_buyer'")->fetch()['total'];
$cancelled_orders_count = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE order_status = 'cancelled'")->fetch()['total'];
$total_sales = $pdo->query("SELECT SUM(oi.total_price) as total FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE o.order_status = 'delivered_to_buyer'")->fetch()['total'] ?? 0;
$total_commission = $pdo->query("SELECT SUM(commission_amount) as total_commission FROM orders WHERE order_status = 'delivered_to_buyer'")->fetchColumn() ?? 0;
?>

<style>
    .admin-container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
    
    .card { 
        background: var(--bg-card); 
        border-radius: 15px; 
        box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        margin-bottom: 30px; 
        overflow: hidden; 
    }
    .card-header { 
        background: var(--bg-header-card); 
        padding: 15px 20px; 
        font-size: 18px; 
        font-weight: bold; 
        color: var(--text-heading); 
        border-bottom: 2px solid var(--text-heading); 
    }
    .card-body { padding: 20px; overflow-x: auto; }
    
    /* ============================================================ */
    /* شبكة الإحصائيات - تتمدد لملء الفراغ */
    /* ============================================================ */
    .stats-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(155px, 1fr)); 
        gap: 15px; 
        margin-bottom: 25px; 
    }
    .stat-box { 
        background: var(--bg-header-card);
        padding: 18px 15px; 
        border-radius: 15px; 
        text-align: center; 
        box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        transition: 0.3s;
        border: 1px solid #e8d49a;
    }
    .stat-box:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.12);
    }
    .stat-number { 
        font-size: 24px; 
        font-weight: bold; 
        color: var(--text-welcome);
        margin-bottom: 3px;
    }
    .stat-label { 
        color: var(--text-primary); 
        margin-top: 5px; 
        font-size: 12px; 
        font-weight: 500;
        opacity: 0.8;
    }
    
    .message { 
        background: var(--success-bg); 
        color: var(--success-text); 
        padding: 12px; 
        border-radius: 10px; 
        margin-bottom: 20px; 
        text-align: center;
    }
    
    .btn-small { 
        padding: 5px 12px; 
        border-radius: 20px; 
        text-decoration: none; 
        font-size: 11px; 
        display: inline-block; 
        margin: 2px; 
        cursor: pointer; 
        border: none; 
        font-weight: bold;
        transition: 0.3s;
    }
    .btn-danger { background: var(--btn-danger); color: var(--text-white); }
    .btn-danger:hover { background: var(--btn-danger-hover); }
    .btn-warning { background: var(--btn-warning, #e65100); color: var(--text-white); }
    .btn-warning:hover { background: var(--btn-warning-hover, #bf360c); }
    .btn-success { background: var(--btn-success); color: var(--text-white); }
    .btn-success:hover { background: var(--btn-success-hover); }
    .btn-print { 
        background: var(--btn-info, #2196f3); 
        color: var(--text-white); 
        border: none; 
        padding: 5px 12px; 
        border-radius: 20px; 
        cursor: pointer; 
        font-size: 12px; 
        transition: 0.3s;
    }
    .btn-print:hover { background: var(--btn-info-hover, #1976d2); }
    .btn-delivery { 
        background: var(--btn-primary); 
        color: var(--text-white); 
        padding: 8px 20px; 
        border-radius: 25px; 
        text-decoration: none; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        gap: 5px;
        margin: 0; 
        transition: 0.3s;
        flex: 1 1 auto;
        min-width: max-content;
    }
    .btn-delivery:hover { background: var(--btn-primary-hover); }
    
    /* ============================================================ */
    /* التبويبات - تتمدد لملء الفراغات */
    /* ============================================================ */
    .tabs { 
        display: flex; 
        gap: 8px; 
        margin-bottom: 20px; 
        flex-wrap: wrap; 
        border-bottom: 2px solid var(--text-heading); 
        padding-bottom: 10px; 
    }
    .tab-btn { 
        padding: 8px 14px; 
        background: var(--bg-header-card); 
        border: none; 
        border-radius: 25px; 
        cursor: pointer; 
        color: var(--text-primary);
        transition: 0.3s;
        font-weight: 500;
        flex: 1 1 auto;
        min-width: max-content;
        text-align: center;
        white-space: nowrap;
        font-size: 13px;
    }
    .tab-btn:hover {
        background: var(--btn-primary);
        color: var(--text-white);
        opacity: 0.8;
    }
    .tab-btn.active { 
        background: var(--btn-primary); 
        color: var(--text-white); 
        font-weight: bold;
        box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        transform: translateY(-1px);
    }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    
    .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; min-width: 600px; }
    .data-table th, .data-table td { 
        padding: 12px 15px; 
        text-align: center; 
        border-bottom: 1px solid var(--border-color); 
        white-space: nowrap; 
        color: var(--text-primary);
    }
    .data-table th { 
        background: var(--btn-primary); 
        color: var(--text-white); 
        font-weight: bold; 
    }
    .data-table tr:hover { background: var(--hover-row-bg); }
    .table-wrapper { overflow-x: auto; width: 100%; }
    
    .rating-stars { color: var(--color-yellow); font-size: 14px; letter-spacing: 2px; }
    
    .payment-settings, .maintenance-settings, .notification-settings, .archive-settings { 
        background: var(--bg-header-card); 
        padding: 20px; 
        border-radius: 15px; 
        margin-bottom: 20px; 
    }
    .payment-option, .maintenance-option { 
        margin: 10px 0; 
        padding: 10px; 
        border: 2px solid var(--border-color); 
        border-radius: 10px; 
        cursor: pointer; 
        background: var(--bg-card);
        color: var(--text-primary);
        transition: 0.3s;
    }
    .payment-option.selected, .maintenance-option.selected { 
        border-color: var(--text-heading); 
        background: var(--hover-row-bg); 
    }
    
    .filter-bar { 
        margin-bottom: 15px; 
        display: flex; 
        gap: 10px; 
        align-items: center; 
        flex-wrap: wrap; 
    }
    
    select, input[type="text"], input[type="number"], textarea { 
        background: var(--bg-input); 
        color: var(--text-primary); 
        border: 1px solid var(--border-input, #ddd); 
        border-radius: 10px; 
        padding: 10px; 
        font-family: inherit;
    }
    select:focus, input:focus, textarea:focus { 
        outline: none; 
        border-color: var(--text-heading); 
    }
    
    small { color: var(--text-muted); }
    
    @media (max-width: 768px) { 
        .card-body { overflow-x: auto; } 
        .data-table { min-width: 600px; }
        .stats-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
        .admin-container { margin: 20px auto; }
        .tab-btn { 
            padding: 10px 12px; 
            font-size: 12px; 
            flex: 1 1 calc(33.333% - 8px);
            min-width: auto;
        }
        .btn-delivery {
            flex: 1 1 calc(50% - 8px);
            padding: 10px 15px;
            font-size: 13px;
        }
    }
    
    @media (max-width: 480px) {
        .tab-btn { 
            flex: 1 1 calc(50% - 8px);
            padding: 8px 10px; 
            font-size: 11px; 
        }
        .btn-delivery {
            flex: 1 1 100%;
        }
        .stats-grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); }
    }
</style>

<div class="admin-container" id="mainContainer">
    <?php if(isset($message)): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <!-- الإحصائيات -->
    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-number"><?php echo array_sum(array_column($user_stats, 'count')); ?></div>
            <div class="stat-label">👥 المستخدمين</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php $sc = 0; foreach($user_stats as $s) if($s['user_type']=='seller') $sc=$s['count']; echo $sc; ?></div>
            <div class="stat-label">🛍️ البائعين</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $products_count; ?></div>
            <div class="stat-label">📦 المنتجات</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $orders_count; ?></div>
            <div class="stat-label">✅ الطلبات المكتملة</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $cancelled_orders_count; ?></div>
            <div class="stat-label">❌ الطلبات الملغاة</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo number_format($total_sales, 2); ?> دج</div>
            <div class="stat-label">💰 إجمالي المبيعات</div>
        </div>
        <div class="stat-box">
            <div class="stat-number" style="color: var(--success-text);"><?php echo number_format($total_commission, 2); ?> دج</div>
            <div class="stat-label">💸 عمولات الموقع</div>
        </div>
    </div>
    
    <!-- التبويبات -->
    <div class="tabs">
        <button class="tab-btn <?php echo $active_tab == 'dashboard' ? 'active' : ''; ?>" onclick="showTab('dashboard')">📊 نظرة عامة</button>
        <button class="tab-btn <?php echo $active_tab == 'notifications' ? 'active' : ''; ?>" onclick="showTab('notifications')">📢 إشعارات</button>
        <button class="tab-btn <?php echo $active_tab == 'maintenance' ? 'active' : ''; ?>" onclick="showTab('maintenance')">🔧 الصيانة</button>
        <button class="tab-btn <?php echo $active_tab == 'payment_settings' ? 'active' : ''; ?>" onclick="showTab('payment_settings')">⚙️ الدفع</button>
        <button class="tab-btn <?php echo $active_tab == 'archive' ? 'active' : ''; ?>" onclick="showTab('archive')">📦 أرشيف الفواتير</button>
        <button class="tab-btn <?php echo $active_tab == 'shipping_invoices' ? 'active' : ''; ?>" onclick="showTab('shipping_invoices')">📄 فواتير التوصيل</button>
        <button class="tab-btn <?php echo $active_tab == 'all_orders' ? 'active' : ''; ?>" onclick="showTab('all_orders')">📋 جميع الطلبات</button>
        <button class="tab-btn <?php echo $active_tab == 'orders' ? 'active' : ''; ?>" onclick="showTab('orders')">✅ الطلبات المكتملة</button>
        <button class="tab-btn <?php echo $active_tab == 'cancelled_orders' ? 'active' : ''; ?>" onclick="showTab('cancelled_orders')">❌ الطلبات الملغاة</button>
        <button class="tab-btn <?php echo $active_tab == 'commission' ? 'active' : ''; ?>" onclick="showTab('commission')">💰 العمولات</button>
        <button class="tab-btn <?php echo $active_tab == 'sellers' ? 'active' : ''; ?>" onclick="showTab('sellers')">🛍️ البائعين</button>
        <button class="tab-btn <?php echo $active_tab == 'products' ? 'active' : ''; ?>" onclick="showTab('products')">📦 المنتجات</button>
        <button class="tab-btn <?php echo $active_tab == 'categories' ? 'active' : ''; ?>" onclick="showTab('categories')">🏷️ الفئات</button>
        <button class="tab-btn <?php echo $active_tab == 'complaints' ? 'active' : ''; ?>" onclick="showTab('complaints')">⚠️ الشكاوى</button>
        <button class="tab-btn <?php echo $active_tab == 'support_tickets' ? 'active' : ''; ?>" onclick="showTab('support_tickets')">🎫 تذاكر الدعم</button>
        <button class="tab-btn <?php echo $active_tab == 'ratings' ? 'active' : ''; ?>" onclick="showTab('ratings')">⭐ التقييمات</button>
        <a href="admin_delivery.php" class="btn-delivery">🚚 إدارة التوصيل</a>
        <a href="backup.php" class="btn-delivery" style="background: var(--btn-info, #2196f3);">💾 النسخ الاحتياطي</a>
    </div>
    
    <!-- تضمين ملفات التبويبات -->
    <div id="tab-dashboard" class="tab-content <?php echo $active_tab == 'dashboard' ? 'active' : ''; ?>">
        <?php include 'tab_dashboard.php'; ?>
    </div>
    <div id="tab-notifications" class="tab-content <?php echo $active_tab == 'notifications' ? 'active' : ''; ?>">
        <?php include 'tab_notifications.php'; ?>
    </div>
    <div id="tab-maintenance" class="tab-content <?php echo $active_tab == 'maintenance' ? 'active' : ''; ?>">
        <?php include 'tab_maintenance.php'; ?>
    </div>
    <div id="tab-payment_settings" class="tab-content <?php echo $active_tab == 'payment_settings' ? 'active' : ''; ?>">
        <?php include 'tab_payment_settings.php'; ?>
    </div>
    <div id="tab-archive" class="tab-content <?php echo $active_tab == 'archive' ? 'active' : ''; ?>">
        <?php include 'tab_archive.php'; ?>
    </div>
    <div id="tab-shipping_invoices" class="tab-content <?php echo $active_tab == 'shipping_invoices' ? 'active' : ''; ?>">
        <?php include 'tab_shipping_invoices.php'; ?>
    </div>
    <div id="tab-all_orders" class="tab-content <?php echo $active_tab == 'all_orders' ? 'active' : ''; ?>">
        <?php include 'tab_all_orders.php'; ?>
    </div>
    <div id="tab-orders" class="tab-content <?php echo $active_tab == 'orders' ? 'active' : ''; ?>">
        <?php include 'tab_orders.php'; ?>
    </div>
    <div id="tab-cancelled_orders" class="tab-content <?php echo $active_tab == 'cancelled_orders' ? 'active' : ''; ?>">
        <?php include 'tab_cancelled_orders.php'; ?>
    </div>
    <div id="tab-commission" class="tab-content <?php echo $active_tab == 'commission' ? 'active' : ''; ?>">
        <?php include 'tab_commission.php'; ?>
    </div>
    <div id="tab-sellers" class="tab-content <?php echo $active_tab == 'sellers' ? 'active' : ''; ?>">
        <?php include 'tab_sellers.php'; ?>
    </div>
    <div id="tab-products" class="tab-content <?php echo $active_tab == 'products' ? 'active' : ''; ?>">
        <?php include 'tab_products.php'; ?>
    </div>
    <div id="tab-categories" class="tab-content <?php echo $active_tab == 'categories' ? 'active' : ''; ?>">
        <?php include 'tab_categories.php'; ?>
    </div>
    <div id="tab-complaints" class="tab-content <?php echo $active_tab == 'complaints' ? 'active' : ''; ?>">
        <?php include 'tab_complaints.php'; ?>
    </div>
    <div id="tab-support_tickets" class="tab-content <?php echo $active_tab == 'support_tickets' ? 'active' : ''; ?>">
        <?php include 'tab_support_tickets.php'; ?>
    </div>
    <div id="tab-ratings" class="tab-content <?php echo $active_tab == 'ratings' ? 'active' : ''; ?>">
        <?php include 'tab_ratings.php'; ?>
    </div>
</div>

<!-- النوافذ المنبثقة -->
<div id="categoryModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:1000; align-items:center; justify-content:center;">
    <div style="background: var(--bg-card); width:450px; max-width:90%; border-radius:25px; padding:20px;">
        <h3 id="modalTitle" style="color: var(--text-heading);">➕ إضافة فئة جديدة</h3>
        <form method="POST" onsubmit="saveScrollPosition()">
            <input type="hidden" name="scroll_pos" value="<?php echo $scroll_pos; ?>">
            <input type="hidden" name="cat_id" id="cat_id" value="">
            <div><input type="text" name="cat_name" id="cat_name" placeholder="اسم الفئة" required style="width:100%; padding:10px; margin-bottom:10px; border-radius:10px;"></div>
            <div><input type="text" name="cat_icon" id="cat_icon" placeholder="الأيقونة (مثال: 👗)" style="width:100%; padding:10px; margin-bottom:10px; border-radius:10px;"></div>
            <div><label style="color: var(--text-primary);"><input type="checkbox" name="cat_active" id="cat_active" value="1" checked> مفعل</label></div>
            <div style="display:flex; gap:10px; margin-top:15px;"><button type="submit" id="modalSubmitBtn" name="add_category" class="btn-success" style="flex:1;">💾 حفظ</button><button type="button" onclick="closeModal()" class="btn-danger" style="flex:1;">✖️ إلغاء</button></div>
        </form>
    </div>
</div>

<div id="complaintModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div style="background: var(--bg-card); width:500px; max-width:95%; padding:20px; border-radius:15px;">
        <h3 style="color: var(--text-heading);">⚠️ تفاصيل الشكوى</h3>
        <form method="POST">
            <input type="hidden" name="complaint_id" id="complaint_id">
            <input type="hidden" name="scroll_pos" value="<?php echo $scroll_pos; ?>">
            <p style="color: var(--text-primary);"><strong>رقم الطلب:</strong> <span id="complaint_order_number"></span></p>
            <p style="color: var(--text-primary);"><strong>المنتج:</strong> <span id="complaint_product"></span></p>
            <p style="color: var(--text-primary);"><strong>مقدم الشكوى:</strong> <span id="complaint_complainer"></span></p>
            <p style="color: var(--text-primary);"><strong>المشكو بحقه:</strong> <span id="complaint_accused"></span></p>
            <p style="color: var(--text-primary);"><strong>العنوان:</strong> <span id="complaint_subject"></span></p>
            <p style="color: var(--text-primary);"><strong>الرسالة:</strong> <span id="complaint_message"></span></p>
            <label style="color: var(--text-secondary);">الحالة:</label><select name="status" id="complaint_status" style="width:100%; padding:8px; border-radius:10px; margin-bottom:10px;"><option value="pending">⏳ قيد الانتظار</option><option value="reviewing">🔍 قيد المراجعة</option><option value="resolved">✅ تم الحل</option><option value="rejected">❌ مرفوض</option></select>
            <label style="color: var(--text-secondary);">رد الإدارة:</label><textarea name="admin_response" id="complaint_response" rows="3" style="width:100%; padding:8px; border-radius:10px;"></textarea>
            <div style="margin-top:15px; display:flex; gap:10px;"><button type="submit" name="update_complaint" class="btn-success" style="flex:1;">💾 حفظ</button><button type="button" onclick="closeComplaintModal()" class="btn-danger" style="flex:1;">✖️ إلغاء</button></div>
        </form>
    </div>
</div>

<div id="commissionModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:10000; align-items:center; justify-content:center;">
    <div style="background: var(--bg-card); width:450px; max-width:95%; border-radius:25px; padding:25px;">
        <h3 style="color: var(--text-heading);">⚙️ تعديل عمولة البائع</h3>
        <form method="POST" onsubmit="saveScrollPosition()">
            <input type="hidden" name="seller_id" id="commission_seller_id">
            <input type="hidden" name="scroll_pos" value="<?php echo $scroll_pos; ?>">
            <div><label style="color: var(--text-secondary);">👤 البائع:</label><input type="text" id="commission_seller_name" readonly style="width:100%; padding:10px; border-radius:10px;"></div>
            <div><label style="color: var(--text-secondary);">💰 نسبة العمولة (%)</label><input type="number" name="custom_rate" id="commission_rate" step="0.5" min="0" max="100" placeholder="اتركه فارغاً للقيمة الافتراضية (<?php echo getDefaultCommission($pdo); ?>%)" style="width:100%; padding:10px; border-radius:10px;"><small style="display:block; margin-top:5px;">✅ 0% = إعفاء من العمولة | فارغ = العمولة الافتراضية</small></div>
            <div><label style="color: var(--text-secondary);">📝 سبب التعديل (اختياري)</label><textarea name="commission_note" id="commission_note" rows="3" style="width:100%; padding:10px; border-radius:10px;" placeholder="مثال: بائع مميز، خصم خاص، إلخ..."></textarea></div>
            <div style="display:flex; gap:10px; margin-top:15px;"><button type="submit" name="set_custom_commission" class="btn-success" style="flex:1;">💾 حفظ</button><button type="button" onclick="closeCommissionModal()" class="btn-danger" style="flex:1;">✖️ إلغاء</button></div>
        </form>
    </div>
</div>

<!-- استدعاء الفوتر والشريط السفلي -->
<?php include_once 'footer.php'; ?>
<?php include_once 'bottom_nav.php'; ?>

<script>
    let currentScrollPos = <?php echo $scroll_pos; ?>;
    
    function restoreScrollPosition() { 
        if (currentScrollPos > 0) window.scrollTo(0, currentScrollPos); 
    }
    
    function saveScrollPosition() { 
        currentScrollPos = window.scrollY; 
    }
    
    function showTab(tabName) {
        saveScrollPosition();
        document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.getElementById('tab-' + tabName).classList.add('active');
        if (event && event.target) event.target.classList.add('active');
        let url = new URL(window.location.href);
        url.searchParams.set('active_tab', tabName);
        url.searchParams.set('scroll_pos', currentScrollPos);
        window.history.pushState({}, '', url);
    }
    
    function toggleSpecificUser() {
        let targetType = document.getElementById('targetType');
        let specificUserDiv = document.getElementById('specificUserDiv');
        if (targetType && specificUserDiv) {
            specificUserDiv.style.display = targetType.value === 'specific_user' ? 'block' : 'none';
        }
    }
    
    function selectPaymentSetting(type) {
        document.getElementById('paymentTypeSetting').value = type;
        document.querySelectorAll('.payment-option').forEach(opt => opt.classList.remove('selected'));
        if (type === 'both') document.getElementById('paymentOptionBoth').classList.add('selected');
        else if (type === 'advance_only') document.getElementById('paymentOptionAdvance').classList.add('selected');
        else if (type === 'cash_only') document.getElementById('paymentOptionCash').classList.add('selected');
    }
    
    function filterCancelledOrders() {
        var sellerId = document.getElementById('filterSeller').value;
        var rows = document.querySelectorAll('#cancelledOrdersTable .order-row');
        rows.forEach(row => {
            row.style.display = (sellerId === 'all' || row.getAttribute('data-seller-id') == sellerId) ? '' : 'none';
        });
    }
    
    function deleteCancelledOrders() {
        var sellerId = document.getElementById('deleteCancelledSeller').value;
        var msg = (sellerId === 'all') ? 'حذف جميع الطلبات الملغاة؟' : 'حذف الطلبات الملغاة لهذا البائع؟';
        if (confirm(msg)) {
            window.location.href = '?delete_cancelled=1&seller_id=' + sellerId + '&active_tab=archive&scroll_pos=' + currentScrollPos;
        }
    }
    
    function openAddModal() {
        document.getElementById('modalTitle').innerHTML = '➕ إضافة فئة جديدة';
        document.getElementById('cat_id').value = '';
        document.getElementById('cat_name').value = '';
        document.getElementById('cat_icon').value = '';
        document.getElementById('cat_active').checked = true;
        document.getElementById('modalSubmitBtn').name = 'add_category';
        document.getElementById('categoryModal').style.display = 'flex';
    }
    
    function openEditModal(id, name, icon, isActive) {
        document.getElementById('modalTitle').innerHTML = '✏️ تعديل فئة';
        document.getElementById('cat_id').value = id;
        document.getElementById('cat_name').value = name;
        document.getElementById('cat_icon').value = icon;
        document.getElementById('cat_active').checked = (isActive == 1);
        document.getElementById('modalSubmitBtn').name = 'edit_category';
        document.getElementById('categoryModal').style.display = 'flex';
    }
    
    function closeModal() { 
        document.getElementById('categoryModal').style.display = 'none'; 
    }
    
    function closeComplaintModal() { 
        document.getElementById('complaintModal').style.display = 'none'; 
    }
    
    function showComplaintModal(complaint) {
        document.getElementById('complaint_id').value = complaint.id;
        document.getElementById('complaint_order_number').innerText = complaint.order_number;
        document.getElementById('complaint_product').innerText = complaint.product_name;
        document.getElementById('complaint_complainer').innerText = complaint.complainer_name;
        document.getElementById('complaint_accused').innerText = complaint.accused_name;
        document.getElementById('complaint_subject').innerText = complaint.subject;
        document.getElementById('complaint_message').innerText = complaint.message;
        document.getElementById('complaint_status').value = complaint.status;
        document.getElementById('complaint_response').value = complaint.admin_response || '';
        document.getElementById('complaintModal').style.display = 'flex';
    }
    
    function openCommissionModal(sellerId, sellerName, currentRate, note) {
        document.getElementById('commission_seller_id').value = sellerId;
        document.getElementById('commission_seller_name').value = sellerName;
        document.getElementById('commission_rate').value = (currentRate !== null) ? currentRate : '';
        document.getElementById('commission_note').value = note || '';
        document.getElementById('commissionModal').style.display = 'flex';
    }
    
    function closeCommissionModal() {
        document.getElementById('commissionModal').style.display = 'none';
    }
    
    // دوال طباعة الفواتير
    function printSellerInvoice(sellerId, sellerName, sellerEmail, sellerPhone, ordersCount, totalItemsSold, productsTotal, totalAdvance, cashCollected, netAmount, commissionRate, monthName, year) {
        var w = window.open('', '_blank');
        var d = w.document;
        var productsTotalValue = parseFloat(productsTotal) || 0;
        var totalAdvanceValue = parseFloat(totalAdvance) || 0;
        var cashCollectedValue = parseFloat(cashCollected) || 0;
        var netAmountValue = parseFloat(netAmount) || 0;
        var commissionRateValue = parseFloat(commissionRate) || 7;
        var commissionAmount = productsTotalValue * (commissionRateValue / 100);
        
        d.write('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"><title>فاتورة العمولات الشهرية</title><style>');
        d.write('*{margin:0;padding:0;box-sizing:border-box}');
        d.write('body{font-family:"Tahoma","Arial",sans-serif;background:#f5f5f5;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;padding:20px}');
        d.write('@media print{body{background:white;padding:0;margin:0}.print-buttons{display:none!important}.invoice-card{box-shadow:none;border:1px solid #ddd;margin:0;page-break-inside:avoid}}');
        d.write('.invoice-container{max-width:700px;width:100%;margin:0 auto}');
        d.write('.invoice-card{background:white;border-radius:20px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.1)}');
        d.write('.header{background:#b8860b;color:white;padding:25px 20px;text-align:center}');
        d.write('.header h1{font-size:26px;margin-bottom:5px}');
        d.write('.header p{font-size:13px;opacity:0.95}');
        d.write('.sub-header{background:#f8e1b0;padding:10px 20px;text-align:center;border-bottom:2px solid #b8860b}');
        d.write('.sub-header h3{color:#b8860b;font-size:18px}');
        d.write('.content{padding:20px}');
        d.write('.info-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:20px}');
        d.write('.info-card{background:#f9f9f9;padding:8px 15px;border-radius:10px;border-right:3px solid #b8860b}');
        d.write('.info-label{font-weight:bold;color:#555;font-size:12px;margin-bottom:3px}');
        d.write('.info-value{color:#2c3e50;font-size:14px;font-weight:bold}');
        d.write('.section-title{background:#f8e1b0;padding:8px 15px;margin:15px 0 12px 0;font-weight:bold;color:#b8860b;font-size:16px;border-radius:8px}');
        d.write('.invoice-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eee}');
        d.write('.invoice-label{font-weight:bold;color:#555;font-size:13px}');
        d.write('.invoice-value{color:#333;font-weight:500;font-size:13px}');
        d.write('.total-box{background:#e8f5e9;border-radius:12px;padding:15px;margin:15px 0;border:1px solid #c8e6c9}');
        d.write('.total-row{display:flex;justify-content:space-between;padding:5px 0}');
        d.write('.total-amount{font-size:20px;font-weight:bold;color:#2e7d32}');
        d.write('.footer{background:#f8e1b0;padding:15px;text-align:center;border-top:2px solid #b8860b}');
        d.write('.print-buttons{display:flex;justify-content:center;gap:20px;padding:15px;background:#f5f5f5}');
        d.write('.btn{padding:8px 25px;border:none;border-radius:30px;cursor:pointer;font-size:13px;font-weight:bold}');
        d.write('.btn-print{background:#b8860b;color:white}.btn-close{background:#757575;color:white}');
        d.write('.note-text{font-size:11px;color:#666;margin-top:8px;padding:6px;background:#fff3e0;border-radius:6px}');
        d.write('</style></head><body>');
        d.write('<div class="invoice-container"><div class="invoice-card">');
        d.write('<div class="header"><h1>🏜️ سوق الرمال الذهبية</h1><p>سوق نسائي متكامل - ولاية الوادي</p></div>');
        d.write('<div class="sub-header"><h3>📄 فاتورة العمولات الشهرية</h3></div>');
        d.write('<div class="content">');
        d.write('<div class="info-grid">');
        d.write('<div class="info-card"><div class="info-label">👤 اسم البائع</div><div class="info-value">' + sellerName + '</div></div>');
        d.write('<div class="info-card"><div class="info-label">📧 البريد الإلكتروني</div><div class="info-value">' + sellerEmail + '</div></div>');
        d.write('<div class="info-card"><div class="info-label">📞 رقم الهاتف</div><div class="info-value">' + sellerPhone + '</div></div>');
        d.write('<div class="info-card"><div class="info-label">🗓️ الشهر</div><div class="info-value">' + monthName + ' ' + year + '</div></div>');
        d.write('</div>');
        d.write('<div class="section-title">📊 تفاصيل العمولة</div>');
        d.write('<div class="invoice-row"><span class="invoice-label">📋 عدد الطلبات المكتملة</span><span class="invoice-value">' + ordersCount + '</span></div>');
        d.write('<div class="invoice-row"><span class="invoice-label">📦 عدد القطع المباعة</span><span class="invoice-value">' + totalItemsSold + '</span></div>');
        d.write('<div class="invoice-row"><span class="invoice-label">💰 إجمالي المبيعات</span><span class="invoice-value">' + productsTotalValue.toFixed(2) + ' دج</span></div>');
        d.write('<div class="invoice-row"><span class="invoice-label">💳 الدفعة المقدمة</span><span class="invoice-value">' + totalAdvanceValue.toFixed(2) + ' دج</span></div>');
        d.write('<div class="invoice-row"><span class="invoice-label">💰 المقبوض مباشرة</span><span class="invoice-value">' + cashCollectedValue.toFixed(2) + ' دج</span></div>');
        d.write('<div class="invoice-row"><span class="invoice-label">💸 عمولة الموقع (' + commissionRateValue + '%)</span><span class="invoice-value">' + commissionAmount.toFixed(2) + ' دج</span></div>');
        d.write('<div class="total-box"><div class="total-row"><span class="invoice-label">💰 المبلغ المستحق للبائع</span><span class="total-amount">' + netAmountValue.toFixed(2) + ' دج</span></div></div>');
        d.write('</div>');
        d.write('<div class="footer"><p>🙏 شكراً لثقتكم بنا</p><p>سوق الرمال - سوق آمن للنساء</p></div>');
        d.write('</div>');
        d.write('<div class="print-buttons"><button onclick="window.print()" class="btn btn-print">🖨️ طباعة</button><button onclick="window.close()" class="btn btn-close">✖️ إغلاق</button></div>');
        d.write('</div></body></html>');
        d.close();
    }
    
    function printInvoiceArchive(sellerName, monthYear, ordersCount, totalSales, commission, netAmount, totalAdvance, cashCollected) {
        var w = window.open('', '_blank');
        var d = w.document;
        var monthName = new Date(monthYear + '-01').toLocaleDateString('ar', { month: 'long', year: 'numeric' });
        var totalSalesValue = parseFloat(totalSales) || 0;
        var commissionValue = parseFloat(commission) || 0;
        var netAmountValue = parseFloat(netAmount) || 0;
        var totalAdvanceValue = parseFloat(totalAdvance) || 0;
        var cashCollectedValue = parseFloat(cashCollected) || 0;
        
        d.write('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"><title>فاتورة تصفية شهرية</title><style>');
        d.write('*{margin:0;padding:0;box-sizing:border-box}');
        d.write('body{font-family:"Tahoma","Arial",sans-serif;background:#f5f5f5;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;padding:20px}');
        d.write('@media print{body{background:white;padding:0;margin:0}.print-buttons{display:none!important}.invoice-card{box-shadow:none;border:1px solid #ddd;margin:0;page-break-inside:avoid}}');
        d.write('.invoice-container{max-width:700px;width:100%;margin:0 auto}');
        d.write('.invoice-card{background:white;border-radius:20px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.1)}');
        d.write('.header{background:#b8860b;color:white;padding:25px 20px;text-align:center}');
        d.write('.header h1{font-size:26px;margin-bottom:5px}');
        d.write('.header p{font-size:13px;opacity:0.95}');
        d.write('.sub-header{background:#f8e1b0;padding:10px 20px;text-align:center;border-bottom:2px solid #b8860b}');
        d.write('.sub-header h3{color:#b8860b;font-size:18px}');
        d.write('.content{padding:20px}');
        d.write('.info-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:20px}');
        d.write('.info-card{background:#f9f9f9;padding:8px 15px;border-radius:10px;border-right:3px solid #b8860b}');
        d.write('.info-label{font-weight:bold;color:#555;font-size:12px;margin-bottom:3px}');
        d.write('.info-value{color:#2c3e50;font-size:14px;font-weight:bold}');
        d.write('.section-title{background:#f8e1b0;padding:8px 15px;margin:15px 0 12px 0;font-weight:bold;color:#b8860b;font-size:16px;border-radius:8px}');
        d.write('.invoice-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eee}');
        d.write('.invoice-label{font-weight:bold;color:#555;font-size:13px}');
        d.write('.invoice-value{color:#333;font-weight:500;font-size:13px}');
        d.write('.total-box{background:#e8f5e9;border-radius:12px;padding:15px;margin:15px 0;border:1px solid #c8e6c9}');
        d.write('.total-row{display:flex;justify-content:space-between;padding:5px 0}');
        d.write('.total-amount{font-size:20px;font-weight:bold;color:#2e7d32}');
        d.write('.footer{background:#f8e1b0;padding:15px;text-align:center;border-top:2px solid #b8860b}');
        d.write('.print-buttons{display:flex;justify-content:center;gap:20px;padding:15px;background:#f5f5f5}');
        d.write('.btn{padding:8px 25px;border:none;border-radius:30px;cursor:pointer;font-size:13px;font-weight:bold}');
        d.write('.btn-print{background:#b8860b;color:white}.btn-close{background:#757575;color:white}');
        d.write('.note-text{font-size:11px;color:#666;margin-top:8px;padding:6px;background:#fff3e0;border-radius:6px}');
        d.write('</style></head><body>');
        d.write('<div class="invoice-container"><div class="invoice-card">');
        d.write('<div class="header"><h1>🏜️ سوق الرمال الذهبية</h1><p>سوق نسائي متكامل - ولاية الوادي</p></div>');
        d.write('<div class="sub-header"><h3>📄 فاتورة التصفية الشهرية</h3></div>');
        d.write('<div class="content">');
        d.write('<div class="info-grid">');
        d.write('<div class="info-card"><div class="info-label">🗓️ الشهر</div><div class="info-value">' + monthName + '</div></div>');
        d.write('<div class="info-card"><div class="info-label">👤 اسم البائع</div><div class="info-value">' + sellerName + '</div></div>');
        d.write('</div>');
        d.write('<div class="section-title">📊 ملخص التصفية</div>');
        d.write('<div class="invoice-row"><span class="invoice-label">📋 عدد الطلبات</span><span class="invoice-value">' + ordersCount + '</span></div>');
        d.write('<div class="invoice-row"><span class="invoice-label">💰 إجمالي المبيعات</span><span class="invoice-value">' + totalSalesValue.toFixed(2) + ' دج</span></div>');
        d.write('<div class="invoice-row"><span class="invoice-label">💳 الدفعة المقدمة</span><span class="invoice-value">' + totalAdvanceValue.toFixed(2) + ' دج</span></div>');
        d.write('<div class="invoice-row"><span class="invoice-label">💰 المقبوض مباشرة</span><span class="invoice-value">' + cashCollectedValue.toFixed(2) + ' دج</span></div>');
        d.write('<div class="invoice-row"><span class="invoice-label">💸 عمولة الموقع</span><span class="invoice-value">' + commissionValue.toFixed(2) + ' دج</span></div>');
        d.write('<div class="total-box"><div class="total-row"><span class="invoice-label">💰 صافي المستحق</span><span class="total-amount">' + netAmountValue.toFixed(2) + ' دج</span></div></div>');
        d.write('</div>');
        d.write('<div class="footer"><p>🙏 شكراً لثقتكم بنا</p><p>سوق الرمال - سوق آمن للنساء</p></div>');
        d.write('</div>');
        d.write('<div class="print-buttons"><button onclick="window.print()" class="btn btn-print">🖨️ طباعة</button><button onclick="window.close()" class="btn btn-close">✖️ إغلاق</button></div>');
        d.write('</div></body></html>');
        d.close();
    }
    
    window.addEventListener('beforeunload', function() { saveScrollPosition(); });
    window.onload = function() { restoreScrollPosition(); };
</script>
</body>
</html>