<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Please login first.'], 401);
}

if ($_SESSION['user_role'] === 'seller') {
    json_response(['success' => false, 'message' => 'Seller accounts cannot use coupons.']);
}

$code = strtoupper(trim($_POST['code'] ?? ''));
if (!$code) json_response(['success' => false, 'message' => 'Enter a coupon code.']);

// Get cart to determine shop & subtotal
$cart = Database::fetchAll("
    SELECT c.quantity, p.price, p.shop_id
    FROM cart c JOIN products p ON p.id = c.product_id
    WHERE c.user_id = ?
", [$_SESSION['user_id']]);

if (empty($cart)) json_response(['success' => false, 'message' => 'Your cart is empty.']);

$subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cart));
$shop_id = $cart[0]['shop_id'];

// Find coupon (shop-specific or platform-wide)
$coupon = Database::fetch("
    SELECT * FROM coupons
    WHERE code = ? AND is_active = 1
    AND (shop_id = ? OR shop_id IS NULL)
    AND (valid_until IS NULL OR valid_until >= NOW())
    AND (usage_limit IS NULL OR used_count < usage_limit)
", [$code, $shop_id]);

if (!$coupon) json_response(['success' => false, 'message' => 'Invalid or expired coupon code.']);

// Min order check
if ($subtotal < $coupon['min_order_amount']) {
    json_response(['success' => false, 'message' => 'Minimum order of ' . format_price($coupon['min_order_amount']) . ' required for this coupon.']);
}

// Per-user usage check
$user_usage = Database::fetch("
    SELECT COUNT(*) as cnt FROM orders
    WHERE user_id = ? AND coupon_code = ? AND order_status != 'cancelled'
", [$_SESSION['user_id'], $code]);

if ($user_usage['cnt'] >= $coupon['per_user_limit']) {
    json_response(['success' => false, 'message' => 'You have already used this coupon.']);
}

// Calculate discount
$discount = 0;
if ($coupon['type'] === 'percentage') {
    $discount = ($subtotal * $coupon['value']) / 100;
    if ($coupon['max_discount'] && $discount > $coupon['max_discount']) {
        $discount = $coupon['max_discount'];
    }
} elseif ($coupon['type'] === 'fixed') {
    $discount = min($coupon['value'], $subtotal);
} elseif ($coupon['type'] === 'free_delivery') {
    // The discount value is the delivery fee saved
    $shop = Database::fetch("SELECT delivery_fee FROM shops WHERE id = ?", [$shop_id]);
    $discount = $shop['delivery_fee'] ?? 0;
}

// Store in session for checkout
$_SESSION['applied_coupon'] = [
    'id'           => $coupon['id'],
    'code'         => $coupon['code'],
    'type'         => $coupon['type'],
    'discount'     => $discount,
];

json_response([
    'success'      => true,
    'message'      => 'Coupon applied!',
    'code'         => $coupon['code'],
    'discount'     => $discount,
    'discount_fmt' => format_price($discount),
    'type'         => $coupon['type'],
]);
