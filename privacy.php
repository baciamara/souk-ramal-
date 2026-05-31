<?php
session_start();
require_once 'config.php';

if (isset($_GET['read_notif'])) {
    $notif_id = intval($_GET['read_notif']);
    try {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")->execute([$notif_id]);
    } catch(PDOException $e) {}
    $current_url = strtok($_SERVER['REQUEST_URI'], '?');
    header("Location: " . $current_url);
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
$page_title = "سياسة الخصوصية";

$user_notifications = [];
$unread_count = 0;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
        $stmt->execute([$_SESSION['user_id']]);
        $user_notifications = $stmt->fetchAll();
        $unread_count = count($user_notifications);
    } catch(PDOException $e) {}
}

include_once 'header.php';
?>

<style>
    .privacy-container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
    
    .privacy-card { 
        background: var(--bg-card); 
        border-radius: 20px; 
        padding: 35px; 
        box-shadow: 0 5px 20px rgba(0,0,0,0.08); 
    }
    
    .privacy-card h1 { 
        color: var(--text-heading); 
        margin-bottom: 25px; 
        font-size: 32px; 
    }
    
    .privacy-card h3 { 
        color: var(--text-heading); 
        margin: 25px 0 15px; 
    }
    
    .privacy-card p { 
        line-height: 1.8; 
        color: var(--text-primary); 
        margin-bottom: 20px; 
    }
    
    .privacy-card ul { 
        margin-right: 30px; 
        margin-bottom: 20px; 
        color: var(--text-primary);
        line-height: 1.8;
    }
    
    .privacy-card ul li { 
        margin-bottom: 5px; 
    }
    
    .privacy-card a { 
        color: var(--text-heading); 
        text-decoration: none;
        transition: opacity 0.3s;
    }
    
    .privacy-card a:hover { 
        opacity: 0.8; 
    }
    
    .update-box { 
        background: var(--bg-header-card); 
        padding: 20px; 
        border-radius: 15px; 
        margin-top: 30px; 
    }
    
    .update-box p { 
        margin: 0; 
        color: var(--text-primary); 
    }
    
    @media (max-width: 768px) {
        .privacy-container { margin: 20px auto; }
        .privacy-card { padding: 20px; }
        .privacy-card h1 { font-size: 24px; }
        .privacy-card h3 { font-size: 16px; }
    }
</style>

<div class="privacy-container">
    <div class="privacy-card">
        <h1>🔒 سياسة الخصوصية</h1>
        
        <div>
            <p>نحن في <strong>سوق الرمال الذهبية</strong> نولي خصوصية مستخدمينا أهمية بالغة. توضح هذه السياسة كيفية جمع واستخدام وحماية معلوماتك الشخصية عند استخدام موقعنا.</p>
            
            <h3>📊 المعلومات التي نجمعها</h3>
            <p>عند التسجيل في موقعنا، قد نجمع المعلومات التالية:</p>
            <ul>
                <li>الاسم الكامل</li>
                <li>البريد الإلكتروني</li>
                <li>رقم الهاتف</li>
                <li>عنوان التوصيل</li>
                <li>سجل الطلبات والتقييمات</li>
            </ul>
            
            <h3>🔐 كيف نستخدم معلوماتك</h3>
            <ul>
                <li>تجهيز ومعالجة طلباتك</li>
                <li>تحسين تجربتك على الموقع</li>
                <li>إرسال تحديثات عن طلباتك</li>
                <li>التواصل معك بخصوص العروض والخدمات</li>
            </ul>
            
            <h3>🛡️ حماية معلوماتك</h3>
            <p>نحن نلتزم بحماية معلوماتك الشخصية باستخدام أحدث تقنيات التشفير وبروتوكولات الأمان. لا نقوم ببيع أو مشاركة معلوماتك مع أطراف ثالثة دون موافقتك.</p>
            
            <h3>🍪 ملفات تعريف الارتباط (Cookies)</h3>
            <p>يستخدم موقعنا ملفات تعريف الارتباط لتحسين أداء الموقع وتخصيص تجربتك. يمكنك تعطيل ملفات الارتباط من خلال إعدادات المتصفح الخاص بك.</p>
            
            <h3>📞 حقوق المستخدم</h3>
            <p>لديك الحق في الاطلاع على معلوماتك الشخصية وتعديلها أو حذفها في أي وقت. للقيام بذلك، يرجى التواصل معنا عبر البريد الإلكتروني: <a href="mailto:info@souk-ramal.com">info@souk-ramal.com</a></p>
            
            <div class="update-box">
                <p>📅 <strong>آخر تحديث:</strong> <?php echo date('Y-m-d'); ?></p>
            </div>
        </div>
    </div>
</div>

<?php include_once 'footer.php'; ?>
<?php include_once 'bottom_nav.php'; ?>
</body>
</html>