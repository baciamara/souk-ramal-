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
$page_title = "الأسئلة الشائعة";

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
    .faq-container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
    
    .faq-card { 
        background: var(--bg-card); 
        border-radius: 20px; 
        padding: 35px; 
        box-shadow: 0 5px 20px rgba(0,0,0,0.08); 
    }
    
    .faq-card h1 { 
        color: var(--text-heading); 
        margin-bottom: 25px; 
        font-size: 32px; 
    }
    
    .faq-item { 
        margin-bottom: 25px; 
        border-bottom: 1px solid var(--border-light); 
        padding-bottom: 15px; 
    }
    
    .faq-item:last-child { 
        border-bottom: none; 
        margin-bottom: 0; 
        padding-bottom: 0; 
    }
    
    .faq-item h3 { 
        color: var(--text-heading); 
        margin-bottom: 10px; 
    }
    
    .faq-item p { 
        color: var(--text-secondary); 
        line-height: 1.8; 
    }
    
    @media (max-width: 768px) {
        .faq-container { margin: 20px auto; }
        .faq-card { padding: 20px; }
        .faq-card h1 { font-size: 24px; }
        .faq-item h3 { font-size: 16px; }
    }
</style>

<div class="faq-container">
    <div class="faq-card">
        <h1>❓ الأسئلة الشائعة</h1>
        
        <div>
            <div class="faq-item">
                <h3>🤔 كيف يمكنني التسجيل في الموقع؟</h3>
                <p>يمكنك التسجيل بالضغط على زر "تسجيل جديد" في أعلى الصفحة، ثم ملء البيانات المطلوبة (الاسم، البريد الإلكتروني، رقم الهاتف، كلمة المرور).</p>
            </div>
            
            <div class="faq-item">
                <h3>🛍️ كيف أشتري منتجاً؟</h3>
                <p>ابحثي عن المنتج المناسب، اضغطي على "طلب المنتج"، ثم قومي بتعبئة معلومات التوصيل وطريقة الدفع، وبعدها سيتم إرسال طلبك إلى البائع.</p>
            </div>
            
            <div class="faq-item">
                <h3>🚚 ما هي طرق التوصيل المتاحة؟</h3>
                <p>نوفر خيارين للتوصيل: التوصيل إلى المنزل عبر شركات الشحن، أو الاستلام من أقرب نقطة تجميع لك.</p>
            </div>
            
            <div class="faq-item">
                <h3>💰 ما هي طرق الدفع؟</h3>
                <p>نوفر طريقتين للدفع: الدفع عند الاستلام (نقداً) أو دفع عربون بنسبة 25% من قيمة المنتج والباقي عند الاستلام.</p>
            </div>
            
            <div class="faq-item">
                <h3>🔄 كيف يمكنني إلغاء طلبي؟</h3>
                <p>يمكنك إلغاء الطلب من خلال التواصل مع البائع مباشرة، أو من خلال صفحة الطلب إذا كانت حالة الطلب تسمح بذلك.</p>
            </div>
            
            <div class="faq-item">
                <h3>⭐ كيف يمكنني تقييم منتج اشتريته؟</h3>
                <p>بعد استلام المنتج وتأكيد التسليم، ستتاح لك الفرصة لتقييم المنتج وترك تعليق على صفحة المنتج.</p>
            </div>
        </div>
    </div>
</div>

<?php include_once 'footer.php'; ?>
<?php include_once 'bottom_nav.php'; ?>
</body>
</html>