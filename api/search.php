<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

$q = sanitize($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode(['results' => []]); exit; }

$results = Database::fetchAll("
    SELECT p.id, p.name, p.price, s.shop_name,
        (SELECT image_path FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) as image
    FROM products p
    JOIN shops s ON s.id=p.shop_id AND s.is_active=1
    WHERE p.is_active=1 AND (p.name LIKE ? OR p.description LIKE ?)
    ORDER BY p.total_sold DESC LIMIT 8
", ["%$q%", "%$q%"]);

foreach ($results as &$r) {
    $r['price_fmt'] = format_price($r['price']);
    if ($r['image']) $r['image'] = SITE_URL . '/' . $r['image'];
}

echo json_encode(['results' => $results]);
