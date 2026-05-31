<?php
header('Content-Type: application/xml');
require_once 'config.php';

$urls = [];

// الصفحات الثابتة
$static = [
    'http://soukramal.42web.io/',
    'http://soukramal.42web.io/dashboard.php',
];
$urls = array_merge($urls, $static);

// جلب المنتجات
try {
    $stmt = $pdo->query("SELECT id FROM products WHERE status = 'available'");
    while($row = $stmt->fetch()) {
        $urls[] = 'http://soukramal.42web.io/product.php?id=' . $row['id'];
    }
} catch(PDOException $e) {}

// جلب الفئات
try {
    $stmt = $pdo->query("SELECT id FROM categories WHERE is_active = 1");
    while($row = $stmt->fetch()) {
        $urls[] = 'http://soukramal.42web.io/index.php?category=' . $row['id'];
    }
} catch(PDOException $e) {}

// توليد XML
$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');
foreach($urls as $url) {
    $urlElement = $xml->addChild('url');
    $urlElement->addChild('loc', $url);
    $urlElement->addChild('changefreq', 'weekly');
    $urlElement->addChild('priority', '0.8');
}
header('Content-Type: application/xml');
echo $xml->asXML();
?>