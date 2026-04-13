<?php
require_once '../includes/config.php';
$user = require_seller();
$shop = get_shop_by_user($user['id']);
if (!$shop) redirect('/seller/setup.php');

$order_id = (int)($_GET['id'] ?? 0);
if (!$order_id) redirect('/seller/orders.php');

$order = Database::fetch("
    SELECT o.*, u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE o.id = ? AND o.shop_id = ?
", [$order_id, $shop['id']]);

if (!$order) redirect('/seller/orders.php');

$items = Database::fetchAll("
    SELECT oi.*, (SELECT image_path FROM product_images WHERE product_id = oi.product_id AND is_primary = 1 LIMIT 1) as image
    FROM order_items oi WHERE oi.order_id = ?
", [$order_id]);

$history = Database::fetchAll("
    SELECT h.*, u.full_name as changed_by_name
    FROM order_status_history h
    LEFT JOIN users u ON u.id = h.changed_by
    WHERE h.order_id = ? ORDER BY h.created_at ASC
", [$order_id]);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $new_status = $_POST['status'] ?? '';
    $note = trim($_POST['note'] ?? '');
    $valid = ['pending','confirmed','preparing','ready','out_for_delivery','delivered','cancelled'];
    if (in_array($new_status, $valid)) {
        Database::query("UPDATE orders SET order_status = ? WHERE id = ?", [$new_status, $order_id]);
        Database::insert("INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (?,?,?,?)",
            [$order_id, $new_status, $note ?: null, $user['id']]);
        if ($new_status === 'cancelled' && !empty($_POST['cancel_reason'])) {
            Database::query("UPDATE orders SET cancellation_reason = ? WHERE id = ?", [trim($_POST['cancel_reason']), $order_id]);
        }
        flash_set('success', 'Order status updated!');
        redirect('/seller/order-detail.php?id=' . $order_id);
    }
}

$order = Database::fetch("SELECT o.*, u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ? AND o.shop_id = ?", [$order_id, $shop['id']]);
$page_title = 'Order ' . $order['order_number'];
$status_steps = ['pending','confirmed','preparing','ready','out_for_delivery','delivered'];
$cur_idx = array_search($order['order_status'], $status_steps);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= sanitize($page_title) ?> — Crumbly Seller</title>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <style>body{background:var(--cream)}</style>
</head>
<body>
<div class="seller-layout">
  <?php include '../includes/seller_sidebar.php'; ?>
  <main class="seller-main">

    <div class="page-header">
      <div>
        <a href="<?= SITE_URL ?>/seller/orders.php" style="font-size:0.875rem;color:var(--text-muted);display:inline-flex;align-items:center;gap:4px;margin-bottom:8px">← Back to Orders</a>
        <h1 class="page-title"><?= sanitize($order['order_number']) ?></h1>
        <p class="page-subtitle"><?= date('d M Y, g:i A', strtotime($order['created_at'])) ?></p>
      </div>
      <div style="display:flex;gap:var(--space-md);align-items:center">
        <span class="status-pill status-<?= $order['order_status'] ?>" style="font-size:0.85rem;padding:6px 14px"><?= ucwords(str_replace('_',' ',$order['order_status'])) ?></span>
        <span class="status-pill <?= $order['payment_status']==='paid'?'status-delivered':'status-pending' ?>" style="font-size:0.85rem;padding:6px 14px">💳 <?= ucfirst($order['payment_status']) ?></span>
      </div>
    </div>

    <?php $fs = flash_get('success'); if ($fs): ?><div class="alert alert-success" style="margin-bottom:var(--space-xl)">✅ <?= sanitize($fs) ?></div><?php endif; ?>

    <!-- Order Tracker -->
    <?php if ($order['order_status'] !== 'cancelled'): ?>
    <div class="data-table-wrap" style="padding:var(--space-2xl);margin-bottom:var(--space-xl)">
      <h3 style="font-family:var(--font-display);font-size:1rem;margin-bottom:var(--space-xl)">Order Progress</h3>
      <div class="order-tracker">
        <div class="tracker-steps">
          <div class="tracker-line">
            <div class="tracker-line-fill" style="width:<?= $cur_idx !== false ? ($cur_idx/(count($status_steps)-1))*100 : 0 ?>%"></div>
          </div>
          <?php $icons=['📋','✅','👨‍🍳','📦','🚴','🏠']; foreach ($status_steps as $i => $s):
            $cls = ($cur_idx !== false && $i < $cur_idx) ? 'done' : ($order['order_status']===$s ? 'current' : '');
          ?>
          <div class="tracker-step <?= $cls ?>">
            <div class="tracker-dot"><?= $icons[$i] ?></div>
            <div class="tracker-step-label"><?= ucwords(str_replace('_',' ',$s)) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div class="alert alert-error" style="margin-bottom:var(--space-xl)">
      ❌ <strong>Order Cancelled</strong><?= $order['cancellation_reason'] ? ' — ' . sanitize($order['cancellation_reason']) : '' ?>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:var(--space-xl);align-items:start">
      <div style="display:flex;flex-direction:column;gap:var(--space-xl)">

        <!-- Items -->
        <div class="data-table-wrap">
          <div class="table-toolbar"><span class="table-title">Order Items</span></div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Total</th></tr></thead>
              <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                  <td>
                    <div style="display:flex;align-items:center;gap:var(--space-md)">
                      <img src="<?= $item['image']?SITE_URL.'/'.$item['image']:SITE_URL.'/assets/images/placeholder.svg' ?>" class="product-td-img" alt="">
                      <div>
                        <p style="font-weight:600"><?= sanitize($item['product_name']) ?></p>
                        <?php if ($item['variant_info']): ?><p style="font-size:0.75rem;color:var(--text-muted)"><?= sanitize($item['variant_info']) ?></p><?php endif; ?>
                        <?php if ($item['customization']): ?><p style="font-size:0.75rem;color:var(--rose)">Note: <?= sanitize($item['customization']) ?></p><?php endif; ?>
                      </div>
                    </div>
                  </td>
                  <td><?= format_price($item['price']) ?></td>
                  <td><?= $item['quantity'] ?></td>
                  <td><strong><?= format_price($item['total']) ?></strong></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div style="padding:var(--space-lg) var(--space-xl)">
            <div style="display:flex;justify-content:space-between;font-size:0.9rem;color:var(--text-muted);margin-bottom:6px"><span>Subtotal</span><span><?= format_price($order['subtotal']) ?></span></div>
            <?php if ($order['delivery_fee'] > 0): ?><div style="display:flex;justify-content:space-between;font-size:0.9rem;color:var(--text-muted);margin-bottom:6px"><span>Delivery Fee</span><span><?= format_price($order['delivery_fee']) ?></span></div><?php endif; ?>
            <?php if ($order['discount_amount'] > 0): ?><div style="display:flex;justify-content:space-between;font-size:0.9rem;color:var(--sage);margin-bottom:6px"><span>Discount</span><span>−<?= format_price($order['discount_amount']) ?></span></div><?php endif; ?>
            <div style="display:flex;justify-content:space-between;font-weight:700;font-size:1.1rem;padding-top:var(--space-md);border-top:2px solid var(--border-light)"><span>Total</span><span style="color:var(--amber)"><?= format_price($order['total_amount']) ?></span></div>
          </div>
        </div>

        <!-- Status History -->
        <?php if (!empty($history)): ?>
        <div class="data-table-wrap">
          <div class="table-toolbar"><span class="table-title">Status History</span></div>
          <div style="padding:var(--space-lg)">
            <?php foreach ($history as $h): ?>
            <div style="display:flex;gap:var(--space-md);padding:var(--space-md) 0;border-bottom:1px solid var(--border-light)">
              <div style="width:8px;height:8px;border-radius:50%;background:var(--amber);margin-top:6px;flex-shrink:0"></div>
              <div>
                <p style="font-weight:600;font-size:0.875rem"><?= ucwords(str_replace('_',' ',$h['status'])) ?></p>
                <?php if ($h['note']): ?><p style="font-size:0.8rem;color:var(--text-muted)"><?= sanitize($h['note']) ?></p><?php endif; ?>
                <p style="font-size:0.75rem;color:var(--text-muted)"><?= date('d M Y, g:i A', strtotime($h['created_at'])) ?></p>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <div style="display:flex;flex-direction:column;gap:var(--space-xl)">

        <!-- Update Status -->
        <?php if ($order['order_status'] !== 'delivered' && $order['order_status'] !== 'cancelled'): ?>
        <div class="card" style="padding:var(--space-xl)">
          <h3 style="font-family:var(--font-display);font-size:1rem;margin-bottom:var(--space-lg)">Update Status</h3>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="form-group">
              <label class="form-label">New Status</label>
              <select name="status" class="form-select">
                <?php foreach (['pending','confirmed','preparing','ready','out_for_delivery','delivered','cancelled'] as $s): ?>
                  <option value="<?= $s ?>" <?= $order['order_status']===$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" id="cancelReasonWrap" style="display:none">
              <label class="form-label">Cancellation Reason</label>
              <textarea name="cancel_reason" class="form-textarea" rows="2" placeholder="Reason for cancellation..."></textarea>
            </div>
            <div class="form-group" style="margin-bottom:var(--space-lg)">
              <label class="form-label">Note (optional)</label>
              <input type="text" name="note" class="form-input" placeholder="e.g. Out for delivery with Rahul">
            </div>
            <button type="submit" class="btn btn-amber btn-block">Update Status</button>
          </form>
        </div>
        <?php endif; ?>

        <!-- Customer Info -->
        <div class="card" style="padding:var(--space-xl)">
          <h3 style="font-family:var(--font-display);font-size:1rem;margin-bottom:var(--space-lg)">Customer</h3>
          <div style="display:flex;align-items:center;gap:var(--space-md);margin-bottom:var(--space-lg)">
            <div style="width:40px;height:40px;border-radius:50%;background:var(--grad-warm);display:grid;place-items:center;color:var(--cream);font-weight:700"><?= strtoupper(substr($order['customer_name'],0,1)) ?></div>
            <div>
              <p style="font-weight:700"><?= sanitize($order['customer_name']) ?></p>
              <p style="font-size:0.8rem;color:var(--text-muted)"><?= sanitize($order['customer_email']) ?></p>
            </div>
          </div>
          <?php if ($order['customer_phone']): ?><p style="font-size:0.875rem;margin-bottom:6px">📞 <?= sanitize($order['customer_phone']) ?></p><?php endif; ?>
        </div>

        <!-- Delivery Info -->
        <?php $addr = $order['delivery_address'] ? json_decode($order['delivery_address'], true) : null; ?>
        <?php if ($addr): ?>
        <div class="card" style="padding:var(--space-xl)">
          <h3 style="font-family:var(--font-display);font-size:1rem;margin-bottom:var(--space-lg)">Delivery Address</h3>
          <p style="font-weight:600;margin-bottom:4px"><?= sanitize($addr['full_name'] ?? '') ?></p>
          <p style="font-size:0.875rem;color:var(--text-muted);line-height:1.6"><?= sanitize($addr['address_line1'] ?? '') ?><?= !empty($addr['address_line2'])?', '.sanitize($addr['address_line2']):'' ?>,<br><?= sanitize($addr['city'] ?? '') ?>, <?= sanitize($addr['state'] ?? '') ?> — <?= sanitize($addr['pincode'] ?? '') ?></p>
          <?php if (!empty($addr['phone'])): ?><p style="font-size:0.875rem;margin-top:6px">📞 <?= sanitize($addr['phone']) ?></p><?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($order['special_instructions']): ?>
        <div class="card" style="padding:var(--space-xl)">
          <h3 style="font-family:var(--font-display);font-size:1rem;margin-bottom:var(--space-md)">Special Instructions</h3>
          <p style="font-size:0.875rem;color:var(--text-secondary);line-height:1.6"><?= sanitize($order['special_instructions']) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($order['delivery_date']): ?>
        <div class="card" style="padding:var(--space-xl)">
          <h3 style="font-family:var(--font-display);font-size:1rem;margin-bottom:6px">Preferred Delivery Date</h3>
          <p style="color:var(--amber);font-weight:700"><?= date('d M Y', strtotime($order['delivery_date'])) ?></p>
        </div>
        <?php endif; ?>

      </div>
    </div>
  </main>
</div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
document.querySelector('select[name="status"]')?.addEventListener('change', function() {
  document.getElementById('cancelReasonWrap').style.display = this.value === 'cancelled' ? 'block' : 'none';
});
</script>
</body>
</html>
