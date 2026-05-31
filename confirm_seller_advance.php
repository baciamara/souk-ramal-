<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'seller') {
    header("Location: login.php");
    exit();
}

if (isset($_GET['order_id'])) {
    $order_id = $_GET['order_id'];
    $seller_id = $_SESSION['user_id'];
    
    try {
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET advance_confirmed_by_seller = 1, 
                advance_confirmed_by_seller_date = NOW(), 
                order_status = 'advance_confirmed_by_seller' 
            WHERE id = ? AND seller_id = ?
        ");
        $stmt->execute([$order_id, $seller_id]);
        
        header("Location: dashboard.php?msg=تم تأكيد استلام الدفعة المقدمة");
    } catch(PDOException $e) {
        header("Location: dashboard.php?error=" . urlencode($e->getMessage()));
    }
} else {
    header("Location: dashboard.php");
}
?>