<?php
// ============================================================
// الشريط السفلي للهواتف - نسخة محسنة مع دمج الأزرار
// ============================================================
if (!isset($current_page)) {
    $current_page = basename($_SERVER['PHP_SELF']);
}
if (!isset($unread_count)) {
    $unread_count = 0;
}

if (!isset($user_notifications) && isset($_SESSION['user_id'])) {
    $user_notifications = [];
    try {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 20");
        $stmt->execute([$_SESSION['user_id']]);
        $user_notifications = $stmt->fetchAll();
        
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $count_stmt->execute([$_SESSION['user_id']]);
        $unread_count = $count_stmt->fetchColumn();
    } catch(PDOException $e) {
        $user_notifications = [];
        $unread_count = 0;
    }
}
?>

<div class="bottom-nav">
    <div class="bottom-nav-items">
        <a href="index.php" class="bottom-nav-item <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
            <span class="icon">🏠</span><span class="label">الرئيسية</span>
        </a>
        
        <?php if(isset($_SESSION['user_id'])): ?>
        
        <a href="my_orders.php" class="bottom-nav-item <?php echo $current_page == 'my_orders.php' ? 'active' : ''; ?>">
            <span class="icon">📦</span><span class="label">طلباتي</span>
        </a>
        
        <div class="bottom-nav-item" id="bottomBellIcon" onclick="toggleBottomNotifications()">
            <span class="icon">🔔</span>
            <?php if($unread_count > 0): ?>
                <span class="notif-badge-bottom"><?php echo $unread_count > 99 ? '99+' : $unread_count; ?></span>
            <?php endif; ?>
            <span class="label">إشعارات</span>
        </div>
        
        <?php if($_SESSION['user_type'] != 'buyer'): ?>
            <?php 
            $dashboard_link = '#'; $dashboard_icon = '📊'; $dashboard_text = 'لوحتي';
            if ($_SESSION['user_type'] == 'seller') { $dashboard_link = 'dashboard.php'; $dashboard_icon = '📊'; $dashboard_text = 'لوحتي'; }
            elseif ($_SESSION['user_type'] == 'admin') { $dashboard_link = 'admin_dashboard.php'; $dashboard_icon = '👑'; $dashboard_text = 'المدير'; }
            elseif ($_SESSION['user_type'] == 'shipping_company') { $dashboard_link = 'shipping_dashboard.php'; $dashboard_icon = '🚚'; $dashboard_text = 'الشحن'; }
            elseif ($_SESSION['user_type'] == 'pickup_point') { $dashboard_link = 'pickup_dashboard.php'; $dashboard_icon = '📍'; $dashboard_text = 'النقطة'; }
            ?>
            <a href="<?php echo $dashboard_link; ?>" class="bottom-nav-item <?php echo $current_page == basename($dashboard_link) ? 'active' : ''; ?>">
                <span class="icon"><?php echo $dashboard_icon; ?></span><span class="label"><?php echo $dashboard_text; ?></span>
            </a>
        <?php endif; ?>
        
        <a href="my_tickets.php" class="bottom-nav-item <?php echo $current_page == 'my_tickets.php' ? 'active' : ''; ?>">
            <span class="icon">🎫</span><span class="label">تذاكري</span>
        </a>
        
        <!-- زر مدمج: حسابي + خروج -->
        <div class="bottom-nav-item" id="bottomProfileBtn" onclick="toggleProfileMenu()">
            <span class="icon">👤</span><span class="label">حسابي</span>
        </div>
        
        <?php else: ?>
        <a href="login.php" class="bottom-nav-item"><span class="icon">🔑</span><span class="label">دخول</span></a>
        <a href="register.php" class="bottom-nav-item"><span class="icon">✨</span><span class="label">تسجيل</span></a>
        <?php endif; ?>
    </div>
</div>

<!-- قائمة منبثقة لحسابي وخروج -->
<?php if(isset($_SESSION['user_id'])): ?>
<div id="bottomProfileMenu" style="display:none; position:fixed; bottom:80px; right:10px; left:10px; background:var(--bg-notif, #ffffff); border-radius:15px; box-shadow:0 5px 15px rgba(0,0,0,0.2); max-width:250px; margin-left:auto; z-index:9999;">
    <a href="profile.php" style="display:flex; align-items:center; gap:10px; padding:12px 15px; text-decoration:none; color:var(--text-primary, #333); border-bottom:1px solid var(--notif-border, #eee);">
        <span style="font-size:18px;">👤</span> <span>حسابي</span>
    </a>
    <a href="logout.php" style="display:flex; align-items:center; gap:10px; padding:12px 15px; text-decoration:none; color:var(--logout-text, #c62828);">
        <span style="font-size:18px;">🚪</span> <span>تسجيل خروج</span>
    </a>
</div>
<?php endif; ?>

<!-- قائمة الإشعارات -->
<div id="bottomNotifDropdown" style="display:none; position:fixed; bottom:80px; right:10px; left:10px; background:var(--bg-notif, #ffffff); border-radius:15px; box-shadow:0 5px 15px rgba(0,0,0,0.2); max-width:350px; margin-left:auto; z-index:9999; max-height:400px; overflow-y:auto;">
    <div style="padding:12px; border-bottom:1px solid var(--notif-border, #f0e0c0); font-weight:bold; color:var(--text-heading, #b8860b); text-align:center;">📢 الإشعارات (<?php echo $unread_count; ?>)</div>
    <?php if(isset($user_notifications) && count($user_notifications) > 0): ?>
        <?php foreach($user_notifications as $notif): ?>
            <a href="view_notification.php?id=<?php echo $notif['id']; ?>" style="display:block; padding:10px; text-decoration:none; color:var(--text-primary, #333); border-bottom:1px solid var(--notif-border, #eee);">
                <div style="font-weight:bold; font-size:13px; color:var(--text-primary, #333);"><?php echo htmlspecialchars($notif['title']); ?></div>
                <div style="font-size:11px; color:var(--text-secondary, #666);"><?php echo htmlspecialchars(mb_substr($notif['message'], 0, 60)); ?>...</div>
                <div style="font-size:10px; color:var(--text-muted, #999);"><?php echo date('H:i d/m', strtotime($notif['created_at'])); ?></div>
            </a>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="padding:20px; text-align:center; color:var(--text-muted, #999);">لا توجد إشعارات</div>
    <?php endif; ?>
</div>

<style>
/* ============================================================ */
/* شريط التنقل السفلي - متجاوب مع جميع الشاشات */
/* ============================================================ */
.bottom-nav {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--bg-card);
    box-shadow: 0 -2px 15px rgba(0,0,0,0.1);
    z-index: 1000;
    padding: 5px 3px;
    padding-bottom: max(5px, env(safe-area-inset-bottom));
}

.bottom-nav-items {
    display: flex;
    justify-content: space-around;
    align-items: center;
    flex-wrap: nowrap;
    gap: 0;
    width: 100%;
    max-width: 100%;
}

.bottom-nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    color: var(--text-muted);
    font-size: 10px;
    padding: 3px 2px;
    min-width: 0;
    flex: 1 1 0;
    position: relative;
    background: transparent;
    border: none;
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.3s;
    gap: 2px;
    -webkit-tap-highlight-color: transparent;
    overflow: hidden;
}

.bottom-nav-item .icon {
    font-size: 18px;
    transition: all 0.3s;
    position: relative;
    line-height: 1;
}

.bottom-nav-item .label {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
    font-size: 8px;
    text-align: center;
    line-height: 1.1;
}

.bottom-nav-item.active {
    color: var(--text-heading);
    background: var(--bg-header-card);
    font-weight: bold;
}

.bottom-nav-item.active .icon {
    transform: scale(1.1);
}

.bottom-nav-item:active {
    transform: scale(0.95);
    opacity: 0.8;
}

.bottom-nav-item .notif-badge-bottom {
    position: absolute;
    top: -2px;
    left: 50%;
    transform: translateX(6px);
    background: var(--notif-badge, #c62828);
    color: white;
    border-radius: 50%;
    padding: 1px 4px;
    font-size: 8px;
    min-width: 14px;
    text-align: center;
    line-height: 1.4;
    z-index: 1;
}

/* ============================================================ */
/* تجاوب لجميع أحجام الشاشات */
/* ============================================================ */

/* الأساسي - الظهور على الهواتف فقط */
@media (max-width: 768px) {
    .bottom-nav {
        display: block;
    }
    
    body {
        padding-bottom: 62px;
    }
}

/* هواتف متوسطة (481px - 768px) */
@media (max-width: 768px) and (min-width: 481px) {
    .bottom-nav {
        padding: 6px 6px;
    }
    
    .bottom-nav-item .icon {
        font-size: 20px;
    }
    
    .bottom-nav-item .label {
        font-size: 9px;
    }
}

/* هواتف صغيرة (361px - 480px) */
@media (max-width: 480px) {
    .bottom-nav {
        padding: 4px 3px;
    }
    
    .bottom-nav-item .icon {
        font-size: 17px;
    }
    
    .bottom-nav-item .label {
        font-size: 8px;
    }
    
    body {
        padding-bottom: 58px;
    }
}

/* هواتف صغيرة جداً (حتى 360px) */
@media (max-width: 360px) {
    .bottom-nav {
        padding: 3px 2px;
    }
    
    .bottom-nav-item {
        padding: 2px 1px;
        border-radius: 6px;
        gap: 1px;
    }
    
    .bottom-nav-item .icon {
        font-size: 15px;
    }
    
    .bottom-nav-item .label {
        font-size: 7px;
    }
    
    .bottom-nav-item .notif-badge-bottom {
        font-size: 7px;
        min-width: 12px;
        padding: 0px 3px;
        transform: translateX(4px);
    }
    
    body {
        padding-bottom: 52px;
    }
}

/* هواتف قديمة جداً (حتى 320px) */
@media (max-width: 320px) {
    .bottom-nav {
        padding: 2px 1px;
    }
    
    .bottom-nav-item .icon {
        font-size: 14px;
    }
    
    .bottom-nav-item .label {
        font-size: 6px;
    }
    
    body {
        padding-bottom: 46px;
    }
}

/* هواتف كبيرة (768px - 992px) */
@media (min-width: 769px) and (max-width: 992px) {
    .bottom-nav {
        display: block;
        padding: 7px 12px;
    }
    
    .bottom-nav-item .icon {
        font-size: 22px;
    }
    
    .bottom-nav-item .label {
        font-size: 10px;
    }
    
    body {
        padding-bottom: 68px;
    }
}

/* إخفاء على الشاشات الكبيرة */
@media (min-width: 993px) {
    .bottom-nav {
        display: none !important;
    }
    
    body {
        padding-bottom: 0;
    }
}

/* ============================================================ */
/* دعم الوضع الأفقي */
/* ============================================================ */
@media (orientation: landscape) and (max-height: 600px) {
    .bottom-nav {
        padding: 3px 6px;
    }
    
    .bottom-nav-item .icon {
        font-size: 15px;
    }
    
    .bottom-nav-item .label {
        font-size: 7px;
    }
    
    body {
        padding-bottom: 48px;
    }
    
    #bottomNotifDropdown, #bottomProfileMenu {
        bottom: 55px;
    }
}

@media (orientation: landscape) and (max-height: 400px) {
    .bottom-nav {
        padding: 2px 8px;
    }
    
    .bottom-nav-item .icon {
        font-size: 13px;
    }
    
    .bottom-nav-item .label {
        display: none;
    }
    
    .bottom-nav-item {
        padding: 2px 1px;
        gap: 0;
    }
    
    body {
        padding-bottom: 32px;
    }
    
    #bottomNotifDropdown, #bottomProfileMenu {
        bottom: 38px;
    }
}

/* ============================================================ */
/* دعم الشاشات الحديثة (النوتش والجزيرة) */
/* ============================================================ */
@supports (padding-bottom: env(safe-area-inset-bottom)) {
    .bottom-nav {
        padding-bottom: calc(5px + env(safe-area-inset-bottom));
    }
    
    body {
        padding-bottom: calc(62px + env(safe-area-inset-bottom));
    }
    
    @media (max-width: 480px) {
        body {
            padding-bottom: calc(58px + env(safe-area-inset-bottom));
        }
    }
    
    @media (max-width: 360px) {
        body {
            padding-bottom: calc(52px + env(safe-area-inset-bottom));
        }
    }
}

/* ============================================================ */
/* دعم تفضيلات الحركة */
/* ============================================================ */
@media (prefers-reduced-motion: reduce) {
    .bottom-nav-item {
        transition: none;
    }
    
    .bottom-nav-item:active {
        transform: none;
    }
}
</style>

<script>
// تبديل قائمة الإشعارات
function toggleBottomNotifications() {
    var dropdown = document.getElementById('bottomNotifDropdown');
    var profileMenu = document.getElementById('bottomProfileMenu');
    
    // إغلاق قائمة الحساب إذا كانت مفتوحة
    if (profileMenu) profileMenu.style.display = 'none';
    
    if (dropdown) {
        dropdown.style.display = (dropdown.style.display === 'none' || dropdown.style.display === '') ? 'block' : 'none';
    }
}

// تبديل قائمة الحساب
function toggleProfileMenu() {
    var menu = document.getElementById('bottomProfileMenu');
    var notifDropdown = document.getElementById('bottomNotifDropdown');
    
    // إغلاق قائمة الإشعارات إذا كانت مفتوحة
    if (notifDropdown) notifDropdown.style.display = 'none';
    
    if (menu) {
        menu.style.display = (menu.style.display === 'none' || menu.style.display === '') ? 'block' : 'none';
    }
}

// إغلاق جميع القوائم عند النقر خارجها
document.addEventListener('click', function(e) {
    var bellIcon = document.getElementById('bottomBellIcon');
    var profileBtn = document.getElementById('bottomProfileBtn');
    var notifDropdown = document.getElementById('bottomNotifDropdown');
    var profileMenu = document.getElementById('bottomProfileMenu');
    
    if (bellIcon && notifDropdown && !bellIcon.contains(e.target) && !notifDropdown.contains(e.target)) {
        notifDropdown.style.display = 'none';
    }
    
    if (profileBtn && profileMenu && !profileBtn.contains(e.target) && !profileMenu.contains(e.target)) {
        profileMenu.style.display = 'none';
    }
});

// إغلاق القوائم عند العودة للصفحة
window.addEventListener('pageshow', function() {
    var notifDropdown = document.getElementById('bottomNotifDropdown');
    var profileMenu = document.getElementById('bottomProfileMenu');
    if (notifDropdown) notifDropdown.style.display = 'none';
    if (profileMenu) profileMenu.style.display = 'none';
});

// إغلاق القوائم عند التمرير
window.addEventListener('scroll', function() {
    var notifDropdown = document.getElementById('bottomNotifDropdown');
    var profileMenu = document.getElementById('bottomProfileMenu');
    if (notifDropdown && notifDropdown.style.display === 'block') notifDropdown.style.display = 'none';
    if (profileMenu && profileMenu.style.display === 'block') profileMenu.style.display = 'none';
});
</script>