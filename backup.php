<?php
// backup.php - إدارة النسخ الاحتياطي لقاعدة البيانات
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: login.php");
    exit();
}

// ============================================================
// المتغيرات المطلوبة للهيدر والشريط السفلي
// ============================================================
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = "النسخ الاحتياطي";

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

// إعدادات النسخ الاحتياطي
$backup_dir = 'backups/';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// حذف النسخ القديمة (أقدم من 30 يوم)
$old_files = glob($backup_dir . '*.sql.gz');
foreach ($old_files as $file) {
    if (filemtime($file) < time() - 30 * 86400) {
        unlink($file);
    }
}

// إنشاء نسخة احتياطية جديدة
if (isset($_GET['create'])) {
    $filename = $backup_dir . 'backup_' . date('Y-m-d_H-i-s') . '.sql.gz';
    
    // الحصول على جميع الجداول
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    $output = "-- سوق الرمال - نسخة احتياطية\n";
    $output .= "-- التاريخ: " . date('Y-m-d H:i:s') . "\n";
    $output .= "-- قاعدة البيانات: " . $pdo->query("SELECT DATABASE()")->fetchColumn() . "\n\n";
    
    foreach ($tables as $table) {
        // هيكل الجدول
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $output .= "\n-- جدول: $table\n";
        $output .= "DROP TABLE IF EXISTS `$table`;\n";
        $output .= $row['Create Table'] . ";\n\n";
        
        // بيانات الجدول
        $stmt = $pdo->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 0) {
            $output .= "-- بيانات جدول $table\n";
            foreach ($rows as $row) {
                $values = array_map(function($val) use ($pdo) {
                    if ($val === null) return 'NULL';
                    return $pdo->quote($val);
                }, array_values($row));
                $output .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
            }
            $output .= "\n";
        }
    }
    
    // ضغط الملف
    $gz = gzopen($filename, 'w9');
    gzwrite($gz, $output);
    gzclose($gz);
    
    $_SESSION['message'] = "✅ تم إنشاء النسخة الاحتياطية بنجاح: " . basename($filename);
    header("Location: backup.php");
    exit();
}

// تحميل نسخة احتياطية
if (isset($_GET['download'])) {
    $file = basename($_GET['download']);
    $filepath = $backup_dir . $file;
    if (file_exists($filepath) && pathinfo($filepath, PATHINFO_EXTENSION) == 'gz') {
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit();
    }
}

// حذف نسخة احتياطية
if (isset($_GET['delete'])) {
    $file = basename($_GET['delete']);
    $filepath = $backup_dir . $file;
    if (file_exists($filepath)) {
        unlink($filepath);
        $_SESSION['message'] = "✅ تم حذف النسخة الاحتياطية: " . $file;
    }
    header("Location: backup.php");
    exit();
}

// قائمة النسخ الاحتياطية
$backup_files = glob($backup_dir . '*.sql.gz');
rsort($backup_files);

$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);

// تضمين الهيدر
include_once 'header.php';
?>

<style>
    .backup-container { 
        max-width: 900px; 
        margin: 30px auto; 
        padding: 0 20px; 
    }
    .backup-card { 
        background: var(--bg-card); 
        border-radius: 20px; 
        padding: 30px; 
        box-shadow: 0 5px 20px rgba(0,0,0,0.1); 
    }
    .backup-title { 
        color: var(--text-heading); 
        text-align: center; 
        margin-bottom: 25px; 
        font-size: 24px;
    }
    .btn { 
        background: var(--btn-primary); 
        color: var(--text-white); 
        border: none; 
        padding: 10px 25px; 
        border-radius: 30px; 
        cursor: pointer; 
        text-decoration: none; 
        display: inline-block; 
        margin: 5px; 
        font-size: 14px; 
        transition: 0.3s; 
        font-weight: bold;
    }
    .btn:hover { 
        transform: translateY(-2px); 
        background: var(--btn-primary-hover);
    }
    .btn-danger { 
        background: var(--btn-danger); 
    }
    .btn-danger:hover { 
        background: var(--btn-danger-hover); 
    }
    .btn-success { 
        background: var(--btn-success); 
    }
    .btn-success:hover { 
        background: var(--btn-success-hover); 
    }
    .btn-primary-btn { 
        background: var(--btn-info, #2196f3); 
    }
    .btn-primary-btn:hover { 
        background: var(--btn-info-hover, #1976d2); 
    }
    
    .backup-message { 
        background: var(--success-bg); 
        color: var(--success-text); 
        padding: 15px; 
        border-radius: 12px; 
        margin-bottom: 20px; 
        text-align: center; 
    }
    
    .backup-table { 
        width: 100%; 
        border-collapse: collapse; 
        margin-top: 20px; 
    }
    .backup-table th, .backup-table td { 
        padding: 12px; 
        text-align: center; 
        border-bottom: 1px solid var(--border-color); 
        color: var(--text-primary);
    }
    .backup-table th { 
        background: var(--bg-header-card); 
        color: var(--text-heading); 
        font-weight: bold; 
    }
    .backup-table tr:hover { background: var(--hover-row-bg); }
    
    .file-size { 
        font-size: 12px; 
        color: var(--text-muted); 
    }
    
    .info-box { 
        background: var(--bg-card-alt); 
        padding: 20px; 
        border-radius: 12px; 
        margin-top: 25px; 
    }
    .info-box h4 { 
        color: var(--text-heading); 
        margin-bottom: 10px; 
    }
    .info-box ol { 
        margin-right: 20px; 
        line-height: 1.8; 
        color: var(--text-primary);
    }
    
    .empty-state {
        text-align: center;
        padding: 40px;
        color: var(--text-muted);
    }
    .warning-text {
        margin-top: 15px;
        font-size: 13px;
        color: var(--badge-danger-text);
    }
    
    .retention-note {
        margin-top: 15px; 
        text-align: center; 
        font-size: 12px; 
        color: var(--text-muted);
    }
    
    @media (max-width: 768px) { 
        .backup-card { padding: 20px; } 
        .backup-table th, .backup-table td { padding: 8px; font-size: 12px; } 
        .btn { padding: 6px 12px; font-size: 11px; } 
        .backup-container { margin: 20px auto; }
    }
</style>

<div class="backup-container">
    <div class="backup-card">
        <h1 class="backup-title">💾 النسخ الاحتياطي لقاعدة البيانات</h1>
        
        <?php if(isset($message)): ?>
            <div class="backup-message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-bottom: 25px;">
            <a href="?create=1" class="btn btn-success" onclick="return confirm('إنشاء نسخة احتياطية جديدة؟ قد يستغرق ذلك بضع ثوانٍ.')">➕ إنشاء نسخة احتياطية</a>
        </div>
        
        <?php if(count($backup_files) > 0): ?>
            <div style="overflow-x: auto;">
                <table class="backup-table">
                    <thead>
                        <tr>
                            <th>اسم الملف</th>
                            <th>الحجم</th>
                            <th>التاريخ</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($backup_files as $file): 
                            $filename = basename($file);
                            $size = round(filesize($file) / 1024, 2);
                            $date = date('Y-m-d H:i:s', filemtime($file));
                        ?>
                            <tr>
                                <td><?php echo $filename; ?></td>
                                <td><?php echo $size; ?> ك.ب</td>
                                <td><?php echo $date; ?></td>
                                <td>
                                    <a href="?download=<?php echo urlencode($filename); ?>" class="btn btn-primary-btn" style="padding:5px 12px; font-size:11px;">📥 تحميل</a>
                                    <a href="?delete=<?php echo urlencode($filename); ?>" class="btn btn-danger" style="padding:5px 12px; font-size:11px;" onclick="return confirm('حذف النسخة الاحتياطية؟')">🗑️ حذف</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="retention-note">
                📌 يتم الاحتفاظ بالنسخ الاحتياطية لمدة 30 يوماً فقط
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p style="font-size: 40px;">📭</p>
                <p style="margin-top:10px;">لا توجد نسخ احتياطية بعد</p>
                <p style="margin-top:10px;">انقر على "إنشاء نسخة احتياطية" لإنشاء أول نسخة</p>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h4>📌 تعليمات استعادة النسخة الاحتياطية:</h4>
            <ol>
                <li>قم بتحميل ملف النسخة الاحتياطي (ملف .sql.gz)</li>
                <li>افتح phpMyAdmin من لوحة التحكم الخاصة باستضافتك</li>
                <li>اختر قاعدة البيانات</li>
                <li>اذهب إلى تبويب "استيراد" (Import)</li>
                <li>اختر الملف الذي حملته</li>
                <li>اضغط "تنفيذ" (Go)</li>
            </ol>
            <p class="warning-text">⚠️ تحذير: استعادة النسخة الاحتياطية ستؤدي إلى استبدال جميع البيانات الحالية</p>
        </div>
    </div>
</div>

<?php
// تضمين الفوتر والشريط السفلي
include_once 'footer.php';
include_once 'bottom_nav.php';
?>
</body>
</html>