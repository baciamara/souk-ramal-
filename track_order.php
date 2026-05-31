<?php
session_start();
require_once 'config.php';

if (!isset($_GET['order_id'])) {
    header("Location: index.php");
    exit();
}

$order_id = intval($_GET['order_id']);

$order = [];
$stmt = $pdo->prepare("
    SELECT o.*, oi.product_name, oi.quantity, oi.total_price as order_total,
           u.full_name as buyer_name, u.phone as buyer_phone,
           pp.point_name, pp.address as pickup_address,
           sc.name as shipping_company_name
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN users u ON o.buyer_id = u.id
    LEFT JOIN pickup_points pp ON o.pickup_point_id = pp.id
    LEFT JOIN shipping_companies sc ON o.shipping_company_id = sc.id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    header("Location: index.php");
    exit