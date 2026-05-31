<?php
session_start();
require_once 'config.php';

// معالجة قراءة الإشعار
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
$page_title = "من نحن";

// جلب الإشعارات
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
    .about-container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
    
    .about-card { 
        background: var(--bg-card); 
        border-radius: 20px; 
        padding: 35px; 
        box-shadow: 0 5px 20px rgba(0,0,0,0.08); 
    }
    
    .about-card h1 { 
        color: var(--text-heading); 
        margin-bottom: 25px; 
        font-size: 32px; 
    }
    
    .about-card h3 { 
        color: var(--text-heading); 
        margin: 25px 0 15px; 
    }
    
    .about-card p { 
        line-height: 1.8; 
        color: var(--text-primary); 
        margin-bottom: 20px; 
    }
    
    .about-card ul { 
        margin-right: 30px; 
        margin-bottom: 20px; 
        color: var(--text-primary);
    }
    
    .about-card ul li { 
        margin-bottom: 10px; 
    }
    
    .contact-box { 
        background: var(--bg-header-card); 
        padding: 20px; 
        border-radius: 15px; 
        margin-top: 30px; 
    }
    
    .contact-box p { 
        margin: 0; 
        color: var(--text-primary); 
    }
    
    .contact-box a { 
        color: var(--text-heading); 
        text-decoration: none;
        transition: opacity 0.3s;
    }
    
    .contact-box a:hover { 
        opacity: 0.8; 
    }
    
    @media (max-width: 768px) {
        .about-container { margin: 20px auto; }
        .about-card { padding: 20px; }
        .about-card h1 { font-size: 24px; }
    }
</style>

<div class="about-container">
    <div class="about-card">
        <h1>📖 من نحن</h1>
        
        <div>
            <p>🏜️ <strong>سوق الرمال الذهبية</strong> هو أول سوق إلكتروني نسائي متكامل في الجزائر، يهدف إلى توفير تجربة تسوق آمنة ومريحة للنساء في جميع أنحاء الوطن.</p>
            
            <h3>🎯 رؤيتنا</h3>
            <p>نسعى لأن نكون المنصة الأولى والموثوقة للتسوق الإلكتروني للنساء في الجزائر، من خلال توفير منتجات عالية الجودة وأسعار تنافسية وخدمة توصيل سريعة وآمنة.</p>
            
            <h3>💎 رسالتنا</h3>
            <p>تمكين النساء من التسوق بكل ثقة وأمان، وخلق فرص للبائعات لعرض منتجاتهن والوصول إلى شريحة واسعة من العملاء.</p>
            
            <h3>✅ لماذا تختارين سوق الرمال؟</h3>
            <ul>
                <li>🛡️ <strong>بيئة آمنة:</strong> منصة مخصصة للنساء فقط.</li>
                <li>🚚 <strong>توصيل سريع:</strong> خدمة توصيل إلى جميع ولايات الجزائر.</li>
                <li>💰 <strong>أسعار تنافسية:</strong> أفضل الأسعار في السوق.</li>
                <li>🤝 <strong>دعم مستمر:</strong> فريق دعم متواجد لمساعدتك على مدار الساعة.</li>
            </ul>
            
            <div class="contact-box">
                <p>📞 <strong>للتواصل معنا:</strong><br>
                الهاتف: <a href="tel:+213555000000">+213 555 00 00 00</a><br>
                البريد الإلكتروني: <a href="mailto:info@souk-ramal.com">info@souk-ramal.com</a></p>
            </div>
        </div>
    </div>
</div>

<?php include_once 'footer.php'; ?>
<?php include_once 'bottom_nav.php'; ?>
</body>
</html>