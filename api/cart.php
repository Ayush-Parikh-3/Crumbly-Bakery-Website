<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

// Only buyers can use the cart
if (is_logged_in() && $_SESSION['user_role'] === 'seller') {
    json_response(['success' => false, 'message' => 'Seller accounts cannot purchase products.'], 403);
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'get';

function get_cart_data(int $user_id): array {
    $items = Database::fetchAll("
        SELECT c.id, c.product_id, c.quantity, c.variant_id, c.customization,
               p.name, p.price, p.track_stock, p.stock_qty,
               s.shop_name, s.id as shop_id, s.delivery_fee, s.free_delivery_above,
               v.variant_name, v.variant_value, v.price_modifier,
               (SELECT image_path FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) as image
        FROM cart c
        JOIN products p ON p.id = c.product_id AND p.is_active = 1
        JOIN shops    s ON s.id = p.shop_id    AND s.is_active = 1
        LEFT JOIN product_variants v ON v.id = c.variant_id
        WHERE c.user_id = ?
        ORDER BY c.added_at DESC
    ", [$user_id]);

    $subtotal = 0;
    foreach ($items as &$item) {
        $unit = $item['price'] + ($item['price_modifier'] ?? 0);
        $item['unit_price']      = $unit;
        $item['line_total']      = $unit * $item['quantity'];
        $item['price_formatted'] = format_price($unit * $item['quantity']);
        $item['variant']         = $item['variant_name'] ? $item['variant_name'].': '.$item['variant_value'] : null;
        if ($item['image']) $item['image'] = SITE_URL . '/' . $item['image'];
        $subtotal += $item['line_total'];
    }

    $shop_id      = $items[0]['shop_id'] ?? null;
    $delivery_fee = 0;
    if ($shop_id) {
        $s = Database::fetch("SELECT delivery_fee, free_delivery_above FROM shops WHERE id=?", [$shop_id]);
        if ($s) {
            $delivery_fee = (float)$s['delivery_fee'];
            if ($s['free_delivery_above'] && $subtotal >= (float)$s['free_delivery_above']) $delivery_fee = 0;
        }
    }

    $total = $subtotal + $delivery_fee;
    return [
        'items' => $items,
        'totals' => [
            'subtotal'     => $subtotal,
            'subtotal_fmt' => format_price($subtotal),
            'delivery_fee' => $delivery_fee,
            'delivery_fmt' => format_price($delivery_fee),
            'discount'     => 0,
            'discount_fmt' => format_price(0),
            'total'        => $total,
            'total_fmt'    => format_price($total),
        ],
    ];
}

switch ($action) {
    case 'get':
        if (!is_logged_in()) { echo json_encode(['items'=>[],'totals'=>[]]); exit; }
        echo json_encode(get_cart_data($_SESSION['user_id']));
        break;

    case 'add':
        if (!is_logged_in()) { json_response(['success'=>false,'message'=>'Please login to add to cart','redirect'=>SITE_URL.'/auth/login.php']); }
        $product_id    = (int)($_POST['product_id']    ?? 0);
        $qty           = max(1, (int)($_POST['quantity'] ?? 1));
        $variant_id    = !empty($_POST['variant_id']) ? (int)$_POST['variant_id'] : null;
        $customization = substr($_POST['customization'] ?? '', 0, 500);
        if (!$product_id) json_response(['success'=>false,'message'=>'Invalid product']);
        $product = Database::fetch("SELECT * FROM products WHERE id=? AND is_active=1", [$product_id]);
        if (!$product) json_response(['success'=>false,'message'=>'Product not found']);
        if ($product['track_stock'] && $product['stock_qty'] < $qty) json_response(['success'=>false,'message'=>'Not enough stock available']);
        $existing = Database::fetch(
            "SELECT id,quantity FROM cart WHERE user_id=? AND product_id=? AND (variant_id=? OR (variant_id IS NULL AND ? IS NULL))",
            [$_SESSION['user_id'], $product_id, $variant_id, $variant_id]
        );
        if ($existing) {
            $new_qty = $existing['quantity'] + $qty;
            if ($product['track_stock'] && $new_qty > $product['stock_qty']) $new_qty = $product['stock_qty'];
            Database::query("UPDATE cart SET quantity=? WHERE id=?", [$new_qty, $existing['id']]);
        } else {
            Database::insert("INSERT INTO cart (user_id,product_id,variant_id,quantity,customization) VALUES (?,?,?,?,?)",
                [$_SESSION['user_id'], $product_id, $variant_id, $qty, $customization]);
        }
        $cart_data = get_cart_data($_SESSION['user_id']);
        $count     = array_sum(array_column($cart_data['items'], 'quantity'));
        json_response(['success'=>true,'cart'=>$cart_data,'cart_count'=>$count]);

    case 'update':
        if (!is_logged_in()) json_response(['success'=>false]);
        $cart_id  = (int)($_POST['cart_id']   ?? 0);
        $qty      = max(1, (int)($_POST['quantity'] ?? 1));
        $ci       = Database::fetch("SELECT c.*,p.track_stock,p.stock_qty FROM cart c JOIN products p ON p.id=c.product_id WHERE c.id=? AND c.user_id=?", [$cart_id,$_SESSION['user_id']]);
        if (!$ci) json_response(['success'=>false,'message'=>'Item not found']);
        if ($ci['track_stock'] && $qty > $ci['stock_qty']) $qty = $ci['stock_qty'];
        Database::query("UPDATE cart SET quantity=? WHERE id=?", [$qty,$cart_id]);
        echo json_encode(['success'=>true] + get_cart_data($_SESSION['user_id']));
        break;

    case 'remove':
        if (!is_logged_in()) json_response(['success'=>false]);
        $cart_id = (int)($_POST['cart_id'] ?? 0);
        Database::query("DELETE FROM cart WHERE id=? AND user_id=?", [$cart_id,$_SESSION['user_id']]);
        echo json_encode(['success'=>true] + get_cart_data($_SESSION['user_id']));
        break;

    case 'clear':
        if (!is_logged_in()) json_response(['success'=>false]);
        Database::query("DELETE FROM cart WHERE user_id=?", [$_SESSION['user_id']]);
        json_response(['success'=>true,'items'=>[],'totals'=>[]]);

    default:
        json_response(['error'=>'Unknown action']);
}
