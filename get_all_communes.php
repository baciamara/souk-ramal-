<?php
header('Content-Type: application/json');
require_once 'config.php';

try {
    $stmt = $pdo->query("
        SELECT c.name, w.name as wilaya 
        FROM communes c
        JOIN wilayas w ON c.wilaya_id = w.id
        ORDER BY w.name, c.name
    ");
    $communes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($communes);
} catch(PDOException $e) {
    echo json_encode([]);
}
?>