<?php
// checkout.php
require_once 'includes/config.php';
$user = require_buyer();

$cart = Database::fetchAll("SELECT c.*, p.name, p.price, p.track_stock, p.stock_qty, s.shop_name, s.id as shop_id, s.delivery_fee, s.free_delivery_above, s.min_order_amount, (SELECT image_path FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) as image FROM cart c JOIN products p ON p.id=c.product_id JOIN shops s ON s.id=p.shop_id WHERE c.user_id=?", [$user['id']]);
if (empty($cart)) redirect('/index.php');

$addresses = Database::fetchAll("SELECT * FROM addresses WHERE user_id=? ORDER BY is_default DESC", [$user['id']]);
$subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cart));
$shop = $cart[0];
$delivery_fee = $shop['delivery_fee'];
if ($shop['free_delivery_above'] && $subtotal >= $shop['free_delivery_above']) $delivery_fee = 0;
$total = $subtotal + $delivery_fee;

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $error='Invalid request'; goto render; }
    $addr_id = (int)($_POST['address_id'] ?? 0);
    $payment = in_array($_POST['payment_method']??'cod',['cod','online']) ? $_POST['payment_method'] : 'cod';
    $instructions = substr(trim($_POST['instructions']??''),0,500);
    $delivery_date = $_POST['delivery_date'] ?? null;

    $address = Database::fetch("SELECT * FROM addresses WHERE id=? AND user_id=?", [$addr_id, $user['id']]);
    if (!$address) { $error='Please select a delivery address'; goto render; }

    Database::beginTransaction();
    try {
        $order_num = generate_order_number();
        $order_id = Database::insert("INSERT INTO orders (order_number,user_id,shop_id,address_id,delivery_address,subtotal,delivery_fee,total_amount,payment_method,payment_status,order_status,special_instructions,delivery_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$order_num, $user['id'], $shop['shop_id'], $addr_id, json_encode($address), $subtotal, $delivery_fee, $total, $payment, $payment==='cod'?'pending':'paid', 'pending', $instructions, $delivery_date]);
        foreach ($cart as $item) {
            Database::insert("INSERT INTO order_items (order_id,product_id,product_name,price,quantity,total) VALUES (?,?,?,?,?,?)",
                [$order_id, $item['product_id'], $item['name'], $item['price'], $item['quantity'], $item['price']*$item['quantity']]);
            if ($item['track_stock']) Database::query("UPDATE products SET stock_qty=stock_qty-?, total_sold=total_sold+? WHERE id=?", [$item['quantity'],$item['quantity'],$item['product_id']]);
        }
        Database::query("DELETE FROM cart WHERE user_id=?", [$user['id']]);
        Database::query("UPDATE shops SET total_sales=total_sales+1 WHERE id=?", [$shop['shop_id']]);
        Database::commit();
        flash_set('success', "Order placed! #$order_num 🎉");
        redirect('/account/orders.php');
    } catch (Exception $e) {
        Database::rollback();
        $error = 'Failed to place order. Please try again.';
    }
}

render:
$page_title = 'Checkout';
include 'includes/header.php';
?>
<div class="section" style="background:var(--cream)">
<div class="container-sm">
  <div class="breadcrumb"><a href="<?= SITE_URL ?>">Home</a><span class="breadcrumb-sep">›</span><span>Checkout</span></div>
  <h1 style="font-family:var(--font-display);font-size:2rem;font-weight:700;margin-bottom:var(--space-2xl)">Checkout</h1>
  <?php if ($error): ?><div class="alert alert-error" style="margin-bottom:var(--space-xl)"><?= sanitize($error) ?></div><?php endif; ?>
  <div style="display:grid;grid-template-columns:3fr 2fr;gap:var(--space-2xl);align-items:start">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <!-- Delivery Address -->
      <div class="card" style="padding:var(--space-2xl);margin-bottom:var(--space-xl)">
        <h3 style="font-family:var(--font-display);margin-bottom:var(--space-xl)">Delivery Address</h3>
        <?php if (empty($addresses)): ?>
          <p style="color:var(--text-muted);margin-bottom:var(--space-lg)">No saved addresses. <a href="<?= SITE_URL ?>/account/addresses.php" style="color:var(--amber)">Add one →</a></p>
        <?php else: ?>
          <?php foreach ($addresses as $a): ?>
          <label style="display:flex;gap:var(--space-md);padding:var(--space-lg);border:2px solid var(--border-light);border-radius:var(--radius-md);margin-bottom:var(--space-md);cursor:pointer;transition:border-color 0.2s" onclick="this.style.borderColor='var(--amber)'">
            <input type="radio" name="address_id" value="<?= $a['id'] ?>" <?= $a['is_default']?'checked':'' ?> style="accent-color:var(--amber);flex-shrink:0;margin-top:2px">
            <div>
              <p style="font-weight:700"><?= sanitize($a['label']) ?> — <?= sanitize($a['full_name']) ?></p>
              <p style="font-size:0.875rem;color:var(--text-muted)"><?= sanitize($a['address_line1']) ?>, <?= sanitize($a['city']) ?>, <?= sanitize($a['state']) ?> - <?= sanitize($a['pincode']) ?></p>
              <p style="font-size:0.875rem;color:var(--text-muted)"><?= sanitize($a['phone']) ?></p>
            </div>
          </label>
          <?php endforeach; ?>
          <a href="<?= SITE_URL ?>/account/addresses.php" style="font-size:0.875rem;color:var(--amber)">+ Add new address</a>
        <?php endif; ?>
      </div>
      <!-- Payment -->
      <div class="card" style="padding:var(--space-2xl);margin-bottom:var(--space-xl)">
        <h3 style="font-family:var(--font-display);margin-bottom:var(--space-xl)">Payment Method</h3>
        <label style="display:flex;align-items:center;gap:var(--space-md);padding:var(--space-lg);border:2px solid var(--amber);border-radius:var(--radius-md);margin-bottom:var(--space-md);cursor:pointer">
          <input type="radio" name="payment_method" value="cod" checked style="accent-color:var(--amber)">
          <span>💵 Cash on Delivery</span>
        </label>
        <label style="display:flex;align-items:center;gap:var(--space-md);padding:var(--space-lg);border:2px solid var(--border-light);border-radius:var(--radius-md);cursor:pointer">
          <input type="radio" name="payment_method" value="online" style="accent-color:var(--amber)">
          <span>💳 Pay Online</span>
        </label>
      </div>
      <!-- Special Instructions -->
      <div class="card" style="padding:var(--space-2xl);margin-bottom:var(--space-xl)">
        <h3 style="font-family:var(--font-display);margin-bottom:var(--space-lg)">Delivery Preferences</h3>
        <div class="form-group">
          <label class="form-label">Preferred Delivery Date</label>
          <input type="date" name="delivery_date" class="form-input" min="<?= date('Y-m-d',strtotime('+1 day')) ?>">
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Special Instructions</label>
          <textarea name="instructions" class="form-textarea" rows="2" placeholder="Any special requests for the baker?"></textarea>
        </div>
      </div>
      <button type="submit" class="btn btn-amber btn-block btn-lg">🛍️ Place Order — <?= format_price($total) ?></button>
    </form>
    <!-- Order Summary -->
    <div class="card" style="padding:var(--space-xl);position:sticky;top:90px">
      <h3 style="font-family:var(--font-display);margin-bottom:var(--space-xl)">Order Summary</h3>
      <?php foreach ($cart as $item): ?>
      <div style="display:flex;gap:var(--space-md);margin-bottom:var(--space-md);padding-bottom:var(--space-md);border-bottom:1px solid var(--border-light)">
        <img src="<?= $item['image']?SITE_URL.'/'.$item['image']:SITE_URL.'/assets/images/placeholder.svg' ?>" style="width:52px;height:52px;object-fit:cover;border-radius:8px;flex-shrink:0" alt="">
        <div style="flex:1">
          <p style="font-size:0.875rem;font-weight:600"><?= sanitize($item['name']) ?></p>
          <p style="font-size:0.8rem;color:var(--text-muted)">Qty: <?= $item['quantity'] ?></p>
        </div>
        <p style="font-weight:700"><?= format_price($item['price']*$item['quantity']) ?></p>
      </div>
      <?php endforeach; ?>
      <div style="display:flex;justify-content:space-between;font-size:0.9rem;color:var(--text-muted);margin-top:var(--space-md)"><span>Subtotal</span><span><?= format_price($subtotal) ?></span></div>
      <div style="display:flex;justify-content:space-between;font-size:0.9rem;color:var(--text-muted);margin-top:6px"><span>Delivery</span><span><?= $delivery_fee>0?format_price($delivery_fee):'FREE' ?></span></div>
      <div style="display:flex;justify-content:space-between;font-weight:700;font-size:1.1rem;margin-top:var(--space-md);padding-top:var(--space-md);border-top:2px solid var(--border-light)"><span>Total</span><span style="color:var(--amber)"><?= format_price($total) ?></span></div>
    </div>
  </div>
</div>
</div>
<?php include 'includes/footer.php'; ?>
