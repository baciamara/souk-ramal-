<?php
header('Content-Type: application/json');
require_once 'config.php';

$company_id = intval($_GET['company_id'] ?? 0);
$commune = $_GET['commune'] ?? '';

if($company_id == 0 || empty($commune)) {
    echo json_encode([]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT id, point_name, address, delivery_fee
        FROM pickup_points
        WHERE company_id = ? AND commune = ? AND is_active = 1
        ORDER BY point_name
    ");
    $stmt->execute([$company_id, $commune]);
    $points = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($points);
} catch(PDOException $e) {
    echo json_encode([]);
}
?>