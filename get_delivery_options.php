<?php
header('Content-Type: application/json');
require_once 'config.php';

$wilaya = $_GET['wilaya'] ?? '';
$commune = $_GET['commune'] ?? '';

if(empty($wilaya) || empty($commune)) {
    echo json_encode(['home_available' => false, 'pickup_available' => false]);
    exit();
}

$response = [
    'home_available' => false,
    'pickup_available' => false,
    'home_fee' => 0,
    'pickup_fee' => 0,
    'home_company_id' => 0,
    'pickup_company_id' => 0,
    'pickup_points' => []
];

// التحقق من وجود توصيل للمنزل
try {
    $stmt = $pdo->prepare("
        SELECT sc.company_id, sc.home_delivery_fee as fee 
        FROM shipping_coverage sc
        JOIN shipping_companies c ON sc.company_id = c.id
        WHERE (sc.wilaya = ? AND (sc.commune = ? OR sc.commune IS NULL)) AND c.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$wilaya, $commune]);
    $home = $stmt->fetch();
    if($home) {
        $response['home_available'] = true;
        $response['home_fee'] = $home['fee'];
        $response['home_company_id'] = $home['company_id'];
    }
} catch(PDOException $e) {}

// جلب نقاط التجميع في هذه البلدية
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.point_name, p.address, p.delivery_fee as extra_fee, 
               sc.pickup_delivery_fee as base_fee, sc.company_id
        FROM pickup_points p
        JOIN shipping_coverage sc ON p.company_id = sc.company_id
        WHERE p.commune = ? AND p.is_active = 1 
        AND (sc.wilaya = ? AND (sc.commune = ? OR sc.commune IS NULL))
    ");
    $stmt->execute([$commune, $wilaya, $commune]);
    $pickup = $stmt->fetch();
    if($pickup) {
        $response['pickup_available'] = true;
        $response['pickup_fee'] = $pickup['base_fee'] + ($pickup['extra_fee'] ?? 0);
        $response['pickup_company_id'] = $pickup['company_id'];  // هذا هو المفتاح!
        $response['pickup_points'] = [
            'id' => $pickup['id'],
            'name' => $pickup['point_name'],
            'address' => $pickup['address'],
            'total_fee' => $response['pickup_fee']
        ];
    }
} catch(PDOException $e) {}

echo json_encode($response);
?>