<!-- ============================================================ -->
<!-- الفوتر الموحد لجميع صفحات الموقع -->
<!-- ============================================================ -->
<footer class="main-footer">
    <div class="footer-container">
        <!-- القسم الأول: الشعار ومعلومات الاتصال -->
        <div class="footer-section">
            <div class="footer-logo">
                <h3>🏜️ سوق الرمال الذهبية</h3>
                <p>سوق نسائي متكامل - كل ما تحتاجينه في مكان واحد</p>
            </div>
            <div class="footer-contact">
                <h4>📞 تواصل معنا</h4>
                <p><a href="tel:+213696025621"><i class="fas fa-phone-alt"></i> +213 696 02 56 21</a></p>
                <p><a href="mailto:info.soukramal@gmail.com"><i class="fas fa-envelope"></i> info.soukramal@gmail.com</a></p>
                <p><a href="https://wa.me/213696025621" target="_blank"><i class="fab fa-whatsapp"></i> واتساب: +213 696 02 56 21</a></p>
            </div>
        </div>
        
        <!-- القسم الثاني: روابط مفيدة -->
        <div class="footer-section">
            <h4>🔗 روابط مفيدة</h4>
            <ul>
                <li><a href="about.php"><i class="fas fa-book"></i> من نحن</a></li>
                <li><a href="faq.php"><i class="fas fa-question-circle"></i> الأسئلة الشائعة</a></li>
                <li><a href="contact.php"><i class="fas fa-envelope"></i> اتصل بنا</a></li>
                <li><a href="terms.php"><i class="fas fa-file-contract"></i> الشروط والأحكام</a></li>
                <li><a href="privacy.php"><i class="fas fa-lock"></i> سياسة الخصوصية</a></li>
            </ul>
        </div>
        
        <!-- القسم الثالث: وسائل التواصل الاجتماعي بألوانها الأصلية -->
        <div class="footer-section">
            <h4>📱 تابعينا</h4>
            <div class="social-links">
                <!-- فيسبوك -->
                <a href="https://www.facebook.com/share/18bqNZCUAv/" target="_blank" class="social-link facebook" title="فيسبوك">
                    <i class="fab fa-facebook-f"></i>
                    <span class="social-text">فيسبوك</span>
                </a>
                <!-- إنستغرام -->
                <a href="https://www.instagram.com/baciamara_5?igsh=MTNqcmg0Z2djNnJmMg==" target="_blank" class="social-link instagram" title="إنستغرام">
                    <i class="fab fa-instagram"></i>
                    <span class="social-text">إنستغرام</span>
                </a>
                <!-- تلغرام -->
                <a href="https://t.me/+4OOhpcZBfo43ZmNk" target="_blank" class="social-link telegram" title="تلغرام">
                    <i class="fab fa-telegram-plane"></i>
                    <span class="social-text">تلغرام</span>
                </a>
                <!-- يوتيوب -->
                <a href="https://www.youtube.com/@soukramal" target="_blank" class="social-link youtube" title="يوتيوب - شروحات وتعليمات">
                    <i class="fab fa-youtube"></i>
                    <span class="social-text">يوتيوب</span>
                </a>
                <!-- واتساب -->
                <a href="https://chat.whatsapp.com/Gpu6YDOJfjiD40U6hudgFV?mode=gi_t" target="_blank" class="social-link whatsapp" title="واتساب">
                    <i class="fab fa-whatsapp"></i>
                    <span class="social-text">واتساب</span>
                </a>
            </div>
            
            <!-- زر تحميل التطبيق -->
            <div class="app-download-section">
                <button class="app-download-btn" onclick="installApp()" id="footerInstallBtn">
                    <i class="fas fa-download"></i>
                    <span>📱 حمل التطبيق</span>
                </button>
                <p class="app-download-hint">حمل التطبيق لتجربة أسرع وأفضل</p>
            </div>
        </div>
    </div>
    
    <!-- حقوق النشر -->
    <div class="footer-bottom">
        <p>© <?php echo date('Y'); ?> سوق الرمال الذهبية - جميع الحقوق محفوظة</p>
        <p class="footer-note">سوق آمن للنساء في الجزائر - ولاية الوادي</p>
    </div>
</footer>

<style>
/* ============================================================ */
/* الفوتر الموحد */
/* ============================================================ */
.main-footer {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    color: #fff;
    margin-top: 60px;
    padding-top: 50px;
    font-family: 'Tajawal', 'Tahoma', Arial, sans-serif;
}

.footer-container {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 40px;
    padding: 0 20px 40px;
}

.footer-section h3 {
    color: #b8860b;
    font-size: 24px;
    margin-bottom: 15px;
}

.footer-section h4 {
    color: #b8860b;
    font-size: 18px;
    margin-bottom: 20px;
    border-right: 3px solid #b8860b;
    padding-right: 12px;
    display: inline-block;
}

.footer-section p {
    line-height: 1.8;
    color: #ddd;
    font-size: 14px;
    margin-bottom: 10px;
}

.footer-section ul {
    list-style: none;
    padding: 0;
}

.footer-section ul li {
    margin-bottom: 12px;
}

.footer-section ul li a {
    color: #ddd;
    text-decoration: none;
    transition: all 0.3s;
    font-size: 14px;
    display: inline-block;
}

.footer-section ul li a:hover {
    color: #b8860b;
    transform: translateX(-5px);
}

/* روابط الاتصال */
.footer-contact a {
    color: #ddd;
    text-decoration: none;
    transition: all 0.3s;
    display: inline-block;
    margin-bottom: 8px;
}

.footer-contact a:hover {
    color: #b8860b;
    transform: translateX(-5px);
}

/* ============================================================ */
/* وسائل التواصل الاجتماعي - سطر واحد لا ينكسر */
/* ============================================================ */
.social-links {
    display: flex;
    flex-wrap: nowrap;
    gap: 8px;
    margin-top: 10px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    -ms-overflow-style: none;
    padding-bottom: 4px;
}

.social-links::-webkit-scrollbar {
    display: none;
}

.social-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 30px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
    flex-shrink: 0;
    white-space: nowrap;
    min-width: fit-content;
}

.social-link i {
    font-size: 13px;
    flex-shrink: 0;
}

.social-link .social-text {
    white-space: nowrap;
}

/* فيسبوك - أزرق */
.social-link.facebook {
    background: #1877f2;
    color: white;
}
.social-link.facebook:hover {
    background: #0d5ab9;
    transform: translateY(-2px);
}

/* إنستغرام - تدرج لوني */
.social-link.instagram {
    background: linear-gradient(45deg, #f09433, #d62976, #962fbf, #4f5bd5);
    color: white;
}
.social-link.instagram:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

/* تلغرام - أزرق فاتح */
.social-link.telegram {
    background: #0088cc;
    color: white;
}
.social-link.telegram:hover {
    background: #006699;
    transform: translateY(-2px);
}

/* يوتيوب - أحمر */
.social-link.youtube {
    background: #ff0000;
    color: white;
}
.social-link.youtube:hover {
    background: #cc0000;
    transform: translateY(-2px);
}

/* واتساب - أخضر */
.social-link.whatsapp {
    background: #25d366;
    color: white;
}
.social-link.whatsapp:hover {
    background: #1da15a;
    transform: translateY(-2px);
}

/* ============================================================ */
/* زر تحميل التطبيق */
/* ============================================================ */
.app-download-section {
    margin-top: 20px;
    text-align: center;
}

.app-download-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: linear-gradient(135deg, #b8860b, #d4a017);
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 30px;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(184, 134, 11, 0.3);
    font-family: 'Tajawal', 'Tahoma', Arial, sans-serif;
    width: auto;
    min-width: 200px;
}

.app-download-btn:hover {
    background: linear-gradient(135deg, #9a7209, #b8860b);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(184, 134, 11, 0.4);
}

.app-download-btn:active {
    transform: scale(0.97);
}

.app-download-btn i {
    font-size: 16px;
}

.app-download-hint {
    color: #aaa;
    font-size: 11px;
    margin-top: 8px;
    opacity: 0.8;
}

/* إخفاء الزر على الحاسوب (يظهر فقط على الهواتف) */
@media (min-width: 769px) {
    .app-download-section {
        display: none;
    }
}

/* ============================================================ */
/* القسم السفلي */
/* ============================================================ */
.footer-bottom {
    text-align: center;
    padding: 25px 20px;
    background: rgba(0, 0, 0, 0.2);
}

.footer-bottom p {
    font-size: 13px;
    color: #aaa;
    margin: 5px 0;
}

.footer-note {
    font-size: 11px;
    opacity: 0.6;
}

/* ============================================================ */
/* للهواتف والأجهزة اللوحية */
/* ============================================================ */
@media (max-width: 768px) {
    .footer-container {
        grid-template-columns: 1fr;
        gap: 30px;
        text-align: center;
        padding: 0 15px 30px;
    }
    
    .footer-section h4 {
        border-right: none;
        padding-right: 0;
        text-align: center;
        display: block;
    }
    
    .social-links {
        justify-content: flex-start;
        flex-wrap: nowrap;
        overflow-x: auto;
        gap: 6px;
    }
    
    .social-link {
        padding: 7px 12px;
        font-size: 11px;
    }
    
    .social-link i {
        font-size: 12px;
    }
    
    .footer-section ul li a:hover {
        transform: translateX(0);
    }
    
    .footer-contact a:hover {
        transform: translateX(0);
    }
    
    .app-download-btn {
        padding: 14px 30px;
        font-size: 15px;
        min-width: 220px;
    }
}

@media (max-width: 480px) {
    .social-link {
        padding: 6px 10px;
        font-size: 10px;
        gap: 5px;
    }
    
    .social-link i {
        font-size: 11px;
    }
    
    .social-link .social-text {
        font-size: 10px;
    }
    
    .social-links {
        gap: 5px;
    }
    
    .footer-section h3 {
        font-size: 20px;
    }
    
    .footer-section h4 {
        font-size: 16px;
    }
    
    .app-download-btn {
        padding: 12px 25px;
        font-size: 14px;
        min-width: 200px;
    }
    
    .app-download-section {
        margin-top: 15px;
    }
}

@media (max-width: 360px) {
    .social-link {
        padding: 5px 8px;
        font-size: 9px;
        gap: 4px;
    }
    
    .social-link i {
        font-size: 10px;
    }
    
    .social-link .social-text {
        font-size: 9px;
    }
    
    .social-links {
        gap: 4px;
    }
    
    .app-download-btn {
        padding: 10px 20px;
        font-size: 13px;
        min-width: 180px;
    }
}
</style>

<script>
// ============================================================
// دالة تحميل التطبيق
// ============================================================
function installApp() {
    // محاولة استخدام حدث beforeinstallprompt إذا كان متاحاً
    if (window.deferredPrompt) {
        window.deferredPrompt.prompt();
        window.deferredPrompt.userChoice.then(function(choiceResult) {
            if (choiceResult.outcome === 'accepted') {
                console.log('✅ تم تثبيت التطبيق');
                // إخفاء زر التحميل بعد التثبيت
                var btn = document.getElementById('footerInstallBtn');
                if (btn) {
                    btn.innerHTML = '<i class="fas fa-check"></i><span>✅ تم التثبيت</span>';
                    btn.style.background = 'linear-gradient(135deg, #2e7d32, #4caf50)';
                    btn.disabled = true;
                    btn.style.cursor = 'default';
                }
            }
            window.deferredPrompt = null;
        });
    } else {
        // إذا كان التطبيق مثبتاً بالفعل أو المتصفح لا يدعم
        var btn = document.getElementById('footerInstallBtn');
        if (btn) {
            // التحقق مما إذا كان التطبيق مثبتاً بالفعل
            if (window.matchMedia('(display-mode: standalone)').matches || 
                navigator.standalone || 
                document.referrer.includes('android-app://')) {
                btn.innerHTML = '<i class="fas fa-check"></i><span>✅ التطبيق مثبت</span>';
                btn.style.background = 'linear-gradient(135deg, #2e7d32, #4caf50)';
                btn.disabled = true;
                btn.style.cursor = 'default';
            } else {
                // إظهار تعليمات التثبيت
                showInstallInstructions();
            }
        }
    }
}

// دالة إظهار تعليمات التثبيت للمتصفحات التي لا تدعم PWA مباشرة
function showInstallInstructions() {
    var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    var isAndroid = /Android/.test(navigator.userAgent);
    var msg = '';
    
    if (isIOS) {
        msg = '📱 لتثبيت التطبيق:\n1. اضغطي على زر المشاركة (📤) في الأسفل\n2. اختاري "إضافة إلى الشاشة الرئيسية"\n3. اضغطي "إضافة"';
    } else if (isAndroid) {
        msg = '📱 لتثبيت التطبيق:\n1. اضغطي على زر القائمة (⋮) في الأعلى\n2. اختاري "تثبيت التطبيق" أو "إضافة إلى الشاشة الرئيسية"';
    } else {
        msg = '📱 يمكنك تثبيت التطبيق من خلال إعدادات المتصفح\nأو استخدام متصفح Chrome للحصول على أفضل تجربة';
    }
    
    alert(msg);
}

// تحديث حالة الزر عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('footerInstallBtn');
    if (!btn) return;
    
    // التحقق مما إذا كان التطبيق مثبتاً بالفعل
    if (window.matchMedia('(display-mode: standalone)').matches || 
        navigator.standalone || 
        document.referrer.includes('android-app://')) {
        btn.innerHTML = '<i class="fas fa-check"></i><span>✅ التطبيق مثبت</span>';
        btn.style.background = 'linear-gradient(135deg, #2e7d32, #4caf50)';
        btn.disabled = true;
        btn.style.cursor = 'default';
    }
});

// الاستماع لحدث beforeinstallprompt (من header.php)
window.addEventListener('beforeinstallprompt', function(e) {
    // حفظ الحدث للاستخدام عند النقر على زر التحميل
    window.deferredPrompt = e;
});
</script>