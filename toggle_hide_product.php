<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'seller') {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: my-products.php?error=معرف المنتج غير موجود");
    exit();
}

$product_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    // جلب الحالة الحالية
    $stmt = $pdo->prepare("SELECT is_hidden FROM products WHERE id = ? AND seller_id = ?");
    $stmt->execute([$product_id, $user_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        header("Location: my-products.php?error=المنتج غير موجود");
        exit();
    }
    
    // تبديل القيمة (0 يصبح 1، و1 يصبح 0)
    $new_value = ($product['is_hidden'] == 1) ? 0 : 1;
    
    $update = $pdo->prepare("UPDATE products SET is_hidden = ? WHERE id = ? AND seller_id = ?");
    $update->execute([$new_value, $product_id, $user_id]);
    
    $msg = ($new_value == 1) ? "✅ تم إخفاء المنتج" : "✅ تم استعادة المنتج";
    header("Location: my-products.php?msg=" . urlencode($msg));
    
} catch(Exception $e) {
    header("Location: my-products.php?error=" . urlencode($e->getMessage()));
}
?>