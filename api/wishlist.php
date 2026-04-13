<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

if (!is_logged_in())
    json_response(['success'=>false,'redirect'=>SITE_URL.'/auth/login.php']);

if ($_SESSION['user_role'] === 'seller')
    json_response(['success'=>false,'message'=>'Seller accounts cannot use the wishlist.'], 403);

$product_id = (int)($_POST['product_id'] ?? 0);
if (!$product_id) json_response(['success'=>false]);

$existing = Database::fetch("SELECT id FROM wishlist WHERE user_id=? AND product_id=?", [$_SESSION['user_id'],$product_id]);
if ($existing) {
    Database::query("DELETE FROM wishlist WHERE user_id=? AND product_id=?", [$_SESSION['user_id'],$product_id]);
    json_response(['success'=>true,'added'=>false]);
} else {
    $product = Database::fetch("SELECT id FROM products WHERE id=? AND is_active=1", [$product_id]);
    if (!$product) json_response(['success'=>false,'message'=>'Product not found']);
    Database::insert("INSERT INTO wishlist (user_id,product_id) VALUES (?,?)", [$_SESSION['user_id'],$product_id]);
    json_response(['success'=>true,'added'=>true]);
}
