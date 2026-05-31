<?php
header('Content-Type: application/json');
require_once 'config.php';

$wilaya = $_GET['wilaya'] ?? '';
if(empty($wilaya)) {
    echo json_encode([]);
    exit();
}

try {
    // جلب البلديات من جدول communes الجديد
    $stmt = $pdo->prepare("
        SELECT c.name 
        FROM communes c
        JOIN wilayas w ON c.wilaya_id = w.id
        WHERE w.name = ?
        ORDER BY c.name
    ");
    $stmt->execute([$wilaya]);
    $communes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($communes);
} catch(PDOException $e) {
    echo json_encode([]);
}
?>