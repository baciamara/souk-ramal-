<?php
header('Content-Type: application/json');
require_once 'config.php';

$commune = $_GET['commune'] ?? '';
if(empty($commune)) {
    echo json_encode([]);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT id, point_name, address, delivery_fee FROM pickup_points WHERE commune = ? AND is_active = 1");
    $stmt->execute([$commune]);
    $points = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($points);
} catch(PDOException $e) {
    echo json_encode([]);
}
?>