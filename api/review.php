<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Please login first.']);
}
if ($_SESSION['user_role'] === 'seller') {
    json_response(['success' => false, 'message' => 'Seller accounts cannot submit reviews.']);
}

$shop_id = (int)($_POST['shop_id'] ?? 0);
$rating  = (int)($_POST['rating']  ?? 0);
$title   = trim(substr($_POST['title'] ?? '', 0, 200));
$body    = trim(substr($_POST['body']  ?? '', 0, 2000));

if (!$shop_id)           json_response(['success' => false, 'message' => 'Invalid shop.']);
if ($rating < 1 || $rating > 5) json_response(['success' => false, 'message' => 'Please select a rating.']);
if (strlen($body) < 5)  json_response(['success' => false, 'message' => 'Review is too short.']);

$shop = Database::fetch("SELECT id FROM shops WHERE id = ? AND is_active = 1", [$shop_id]);
if (!$shop) json_response(['success' => false, 'message' => 'Shop not found.']);

// Check already reviewed this shop (one review per user per shop, no order needed)
$existing = Database::fetch(
    "SELECT id FROM reviews WHERE user_id = ? AND shop_id = ?",
    [$_SESSION['user_id'], $shop_id]
);
if ($existing) {
    json_response(['success' => false, 'message' => 'You have already reviewed this shop.']);
}

try {
    // Drop order_id requirement — use 0 as placeholder if column required
    Database::insert(
        "INSERT INTO reviews (shop_id, user_id, order_id, rating, title, body, is_verified, created_at)
         VALUES (?, ?, 0, ?, ?, ?, 1, NOW())",
        [$shop_id, $_SESSION['user_id'], $rating, $title ?: null, $body]
    );

    // Update shop rating
    $stats = Database::fetch(
        "SELECT COUNT(*) as total, AVG(rating) as avg FROM reviews WHERE shop_id = ?",
        [$shop_id]
    );
    Database::query(
        "UPDATE shops SET rating = ?, total_reviews = ? WHERE id = ?",
        [round((float)$stats['avg'], 2), (int)$stats['total'], $shop_id]
    );

    json_response(['success' => true, 'message' => 'Review submitted! Thank you 🎉']);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
