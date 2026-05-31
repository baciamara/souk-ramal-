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
$page_title = "الشروط والأحكام";

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
    .terms-container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
    
    .terms-card { 
        background: var(--bg-card); 
        border-radius: 20px; 
        padding: 35px; 
        box-shadow: 0 5px 20px rgba(0,0,0,0.08); 
    }
    
    .terms-card h1 { 
        color: var(--text-heading); 
        margin-bottom: 25px; 
        font-size: 32px; 
    }
    
    .terms-card h3 { 
        color: var(--text-heading); 
        margin: 25px 0 15px; 
    }
    
    .terms-card p { 
        line-height: 1.8; 
        color: var(--text-primary); 
        margin-bottom: 20px; 
    }
    
    .terms-card a { 
        color: var(--text-heading); 
        text-decoration: none;
        transition: opacity 0.3s;
    }
    
    .terms-card a:hover { 
        opacity: 0.8; 
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
    
    .update-date { 
        margin-top: 10px; 
        color: var(--text-secondary); 
    }
    
    @media (max-width: 768px) {
        .terms-container { margin: 20px auto; }
        .terms-card { padding: 20px; }
        .terms-card h1 { font-size: 24px; }
        .terms-card h3 { font-size: 16px; }
    }
</style>

<div class="terms-container">
    <div class="terms-card">
        <h1>📜 الشروط والأحكام</h1>
        
        <div>
            <p>مرحباً بك في <strong>سوق الرمال الذهبية</strong>. باستخدامك لهذا الموقع (بصفة مشتري أو بائع أو شركة توصيل أو نقطة تجميع)، فإنك توافق على الالتزام بهذه الشروط والأحكام.</p>
            
            <h3>1. التسجيل والحساب</h3>
            <p>1.1 يجب أن تكون عمرك 18 سنة على الأقل للتسجيل في الموقع.<br>
            1.2 أنت مسؤول عن الحفاظ على سرية كلمة المرور الخاصة بك وجميع الأنشطة التي تحدث في حسابك.<br>
            1.3 يحق للإدارة حذف أو تعليق أي حساب يخالف قواعد الموقع دون إشعار مسبق.<br>
            1.4 البائعون وشركات التوصيل ونقاط التجميع بحاجة إلى موافقة المدير قبل تفعيل حساباتهم.</p>
            
            <h3>2. المنتجات والطلبات (للمشتري)</h3>
            <p>2.1 جميع المنتجات المعروضة هي من بائعين مستقلين. الموقع يعمل كوسيط بين البائع والمشتري.<br>
            2.2 الصور المعروضة للمنتجات هي لأغراض توضيحية، وقد تختلف الألوان قليلاً حسب شاشة العرض.<br>
            2.3 الأسعار المعروضة شاملة جميع الضرائب والرسوم (ما عدا رسوم التوصيل).<br>
            2.4 بعد تأكيد الطلب، لا يمكن إلغاؤه بعد تجهيزه للشحن إلا بموافقة البائع.</p>
            
            <h3>3. طرق الدفع</h3>
            <p>3.1 <strong>الدفع عند الاستلام:</strong> يتم دفع كامل المبلغ نقداً عند استلام المنتج.<br>
            3.2 <strong>دفع عربون (25%):</strong> يتم دفع 25% من قيمة الطلب مقدماً، والباقي عند الاستلام.<br>
            3.3 في حالة الدفع بالعربون، يتم تحويل المبلغ إلى حساب البائع بعد تأكيد وصول المنتج.<br>
            3.4 إذا تم إلغاء الطلب من قبل المشتري بعد تأكيد الدفع، يتم استرداد المبلغ حسب سياسة الاسترجاع.</p>
            
            <h3>4. التوصيل (للمشتري)</h3>
            <p>4.1 نوفر خيارين للتوصيل: التوصيل إلى المنزل (عبر شركات الشحن) أو الاستلام من نقطة تجميع قريبة.<br>
            4.2 رسوم التوصيل تُحتسب بناءً على الموقع الجغرافي وطريقة التوصيل المختارة.<br>
            4.3 يتم إشعارك برقم تتبع الطلب عند شحنه، ويمكنك متابعة حالة الطلب عبر حسابك.<br>
            4.4 في حالة تأخر الطلب عن الموعد المحدد، يرجى التواصل مع خدمة العملاء.</p>
            
            <h3>5. الإلغاء والاسترجاع (للمشتري)</h3>
            <p>5.1 يمكن إلغاء الطلب قبل تجهيزه للشحن من خلال صفحة الطلب أو بالتواصل مع البائع.<br>
            5.2 في حال استلام منتج تالف أو معيب أو خاطئ، يرجى التواصل مع البائع خلال 7 أيام من تاريخ الاستلام.<br>
            5.3 في حالة عدم حل المشكلة مع البائع، يمكنك تقديم شكوى للإدارة عبر نظام التذاكر.<br>
            5.4 لا يتم قبول استرجاع المنتجات بعد فتحها أو استخدامها (ما عدا المنتجات التالفة).</p>
            
            <h3>6. مسؤوليات البائع</h3>
            <p>6.1 يجب على البائع تقديم وصف دقيق للمنتج وصور حقيقية له.<br>
            6.2 يلتزم البائع بشحن الطلب خلال المدة المحددة بعد تأكيده.<br>
            6.3 يتحمل البائع مسؤولية جودة المنتج ومطابقته للوصف.<br>
            6.4 عمولة الموقع هي 7% من قيمة المنتج، وتُخصم من أرباح البائع.<br>
            6.5 يحق للإدارة إزالة أي منتج يخالف قوانين الموقع أو يتلقى شكاوى متكررة.</p>
            
            <h3>7. مسؤوليات شركات التوصيل ونقاط التجميع</h3>
            <p>7.1 تلتزم شركات التوصيل بتسليم الطلبات في الوقت المحدد وبحالة جيدة.<br>
            7.2 تلتزم نقاط التجميع باستلام الطلبات وتسليمها للعملاء بدقة وأمان.<br>
            7.3 في حالة تلف الطلب أثناء النقل، تتحمل شركة التوصيل أو نقطة التجميع المسؤولية حسب الاتفاق.</p>
            
            <h3>8. سياسة الخصوصية</h3>
            <p>8.1 نحن نلتزم بحماية معلوماتك الشخصية وعدم مشاركتها مع أطراف ثالثة دون موافقتك.<br>
            8.2 يتم استخدام معلوماتك فقط لتجهيز طلباتك وتحسين تجربتك على الموقع.<br>
            8.3 يمكنك الاطلاع على <a href="privacy.php">سياسة الخصوصية كاملة</a> لمزيد من التفاصيل.</p>
            
            <h3>9. التواصل وحل النزاعات</h3>
            <p>9.1 في حالة وجود أي نزاع، يرجى التواصل معنا عبر <a href="contact.php">صفحة اتصل بنا</a> أو عبر نظام التذاكر.<br>
            9.2 نحن نسعى لحل جميع النزاعات بطريقة ودية وعادلة لجميع الأطراف.<br>
            9.3 إذا لم يتم حل النزاع، يمكن الرجوع إلى القوانين الجزائرية النافذة.</p>
            
            <h3>10. تعديل الشروط</h3>
            <p>10.1 نحتفظ بالحق في تعديل هذه الشروط في أي وقت.<br>
            10.2 سيتم إخطار المستخدمين بأي تغييرات جوهرية عبر البريد الإلكتروني أو الإشعارات داخل الموقع.<br>
            10.3 استمرار استخدام الموقع بعد التعديل يعني موافقتك على الشروط الجديدة.</p>
            
            <div class="contact-box">
                <p><strong>📞 للتواصل معنا:</strong><br>
                البريد الإلكتروني: <a href="mailto:info@souk-ramal.com">info@souk-ramal.com</a><br>
                الهاتف: <a href="tel:+213555000000">+213 555 00 00 00</a></p>
                <p class="update-date">📅 <strong>آخر تحديث:</strong> <?php echo date('Y-m-d'); ?></p>
            </div>
        </div>
    </div>
</div>

<?php include_once 'footer.php'; ?>
<?php include_once 'bottom_nav.php'; ?>
</body>
</html>