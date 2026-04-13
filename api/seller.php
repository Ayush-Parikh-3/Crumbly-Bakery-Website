<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

if (!is_logged_in() || $_SESSION['user_role'] !== 'seller') {
    json_response(['success' => false, 'message' => 'Unauthorized'], 401);
}

$shop = get_shop_by_user($_SESSION['user_id']);
if (!$shop) json_response(['success' => false, 'message' => 'Shop not found'], 404);

$action = $_GET['action'] ?? '';

if ($action === 'toggle_product') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $is_active = (int)($_POST['is_active'] ?? 0);
    $product = Database::fetch("SELECT id FROM products WHERE id=? AND shop_id=?", [$product_id, $shop['id']]);
    if (!$product) json_response(['success' => false, 'message' => 'Product not found'], 404);
    Database::query("UPDATE products SET is_active=? WHERE id=?", [$is_active, $product_id]);
    json_response(['success' => true]);
}

if ($action === 'update_order_status') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $valid = ['pending','confirmed','preparing','ready','out_for_delivery','delivered','cancelled'];
    if (!in_array($status, $valid)) json_response(['success' => false, 'message' => 'Invalid status']);
    $order = Database::fetch("SELECT id FROM orders WHERE id=? AND shop_id=?", [$order_id, $shop['id']]);
    if (!$order) json_response(['success' => false, 'message' => 'Order not found'], 404);
    Database::query("UPDATE orders SET order_status=? WHERE id=?", [$status, $order_id]);
    Database::insert("INSERT INTO order_status_history (order_id, status, changed_by) VALUES (?,?,?)", [$order_id, $status, $_SESSION['user_id']]);
    json_response(['success' => true]);
}

json_response(['success' => false, 'message' => 'Unknown action'], 400);
