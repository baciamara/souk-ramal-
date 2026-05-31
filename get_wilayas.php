<?php
header('Content-Type: application/json');
require_once 'config.php';

try {
    // جلب الولايات من جدول wilayas الجديد
    $stmt = $pdo->query("SELECT name FROM wilayas ORDER BY name");
    $wilayas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($wilayas);
} catch(PDOException $e) {
    echo json_encode([]);
}
?>