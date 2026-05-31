<?php
header('Content-Type: application/json');
require_once 'config.php';

$commune = $_GET['commune'] ?? '';
if(empty($commune)) {
    echo json_encode(['fee' => 600]);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT delivery_fee FROM delivery_fees WHERE commune = ? OR (wilaya = (SELECT wilaya FROM pickup_points WHERE commune = ? LIMIT 1)) ORDER BY delivery_fee ASC LIMIT 1");
    $stmt->execute([$commune, $commune]);
    $fee = $stmt->fetchColumn();
    
    if($fee === false) {
        $fee = 600; // سعر افتراضي
    }
    
    echo json_encode(['fee' => (float)$fee]);
} catch(PDOException $e) {
    echo json_encode(['fee' => 600]);
}
?>