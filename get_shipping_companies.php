<?php
header('Content-Type: application/json');
require_once 'config.php';

$wilaya = $_GET['wilaya'] ?? '';
$commune = $_GET['commune'] ?? '';
$delivery_method = $_GET['delivery_method'] ?? 'home';

if(empty($wilaya)) {
    echo json_encode([]);
    exit();
}

$fee_field = ($delivery_method === 'pickup') ? 'pickup_delivery_fee' : 'home_delivery_fee';

try {
    $sql = "
        SELECT DISTINCT 
            c.id, 
            c.name, 
            sc.$fee_field as delivery_fee,
            (SELECT COUNT(*) FROM pickup_points WHERE company_id = c.id AND commune = ?) > 0 as has_pickup_points
        FROM shipping_companies c
        JOIN shipping_coverage sc ON c.id = sc.company_id
        WHERE (sc.wilaya = ? OR (sc.commune = ? AND sc.commune IS NOT NULL))
        AND c.is_active = 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$commune, $wilaya, $commune]);
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($companies);
} catch(PDOException $e) {
    echo json_encode([]);
}
?>