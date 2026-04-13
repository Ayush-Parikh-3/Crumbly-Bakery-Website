<?php
require_once '../includes/config.php';
$user = require_seller();
$shop = get_shop_by_user($user['id']);
if (!$shop) redirect('/seller/setup.php');
$shop_id = $shop['id'];

// Handle bulk stock update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $updates = $_POST['stock'] ?? [];
    foreach ($updates as $product_id => $qty) {
        $product_id = (int)$product_id;
        $qty = max(0, (int)$qty);
        Database::query("UPDATE products SET stock_qty=? WHERE id=? AND shop_id=?", [$qty, $product_id, $shop_id]);
    }
    flash_set('success', 'Inventory updated!');
    redirect('/seller/inventory.php');
}

$search = sanitize($_GET['search'] ?? '');
$filter = sanitize($_GET['filter'] ?? '');
$where = ["p.shop_id = $shop_id", "p.track_stock = 1"];
$params = [];
if ($search) { $where[] = "p.name LIKE ?"; $params[] = "%$search%"; }
if ($filter === 'low') $where[] = "p.stock_qty <= p.low_stock_threshold AND p.is_active=1";
elseif ($filter === 'out') $where[] = "p.stock_qty = 0";
$where_sql = 'WHERE '.implode(' AND ',$where);
$products = Database::fetchAll("SELECT p.*, c.name as cat_name, (SELECT image_path FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) as image FROM products p LEFT JOIN categories c ON c.id=p.category_id $where_sql ORDER BY p.stock_qty ASC", $params);

$page_title = 'Inventory';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Inventory — Crumbly Seller</title>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <style>body{background:var(--cream)}.stock-input{width:80px;padding:6px 10px;border:1.5px solid var(--border-color);border-radius:var(--radius-sm);font-size:0.875rem;text-align:center}.stock-input:focus{outline:none;border-color:var(--amber)}</style>
</head>
<body>
<div class="seller-layout">
  <?php include '../includes/seller_sidebar.php'; ?>
  <main class="seller-main">
    <div class="page-header">
      <div><h1 class="page-title">Inventory 📦</h1><p class="page-subtitle">Manage stock levels for your products</p></div>
      <button type="submit" form="inventoryForm" class="btn btn-amber">💾 Save All Changes</button>
    </div>

    <?php $fs = flash_get('success'); if ($fs): ?><div class="alert alert-success" style="margin-bottom:var(--space-xl)">✅ <?= sanitize($fs) ?></div><?php endif; ?>

    <!-- Filters -->
    <div class="data-table-wrap" style="margin-bottom:var(--space-xl)">
      <form method="GET" style="display:flex;gap:var(--space-md);padding:var(--space-lg) var(--space-xl);flex-wrap:wrap;align-items:flex-end">
        <div><label class="form-label" style="margin-bottom:4px">Search</label><input type="text" name="search" value="<?= sanitize($search) ?>" class="form-input" placeholder="Product name..." style="width:220px"></div>
        <div>
          <label class="form-label" style="margin-bottom:4px">Filter</label>
          <select name="filter" class="form-select" style="width:160px">
            <option value="">All Products</option>
            <option value="low" <?= $filter==='low'?'selected':'' ?>>⚠️ Low Stock</option>
            <option value="out" <?= $filter==='out'?'selected':'' ?>>❌ Out of Stock</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="?" class="btn btn-ghost btn-sm">Reset</a>
      </form>
    </div>

    <form method="POST" id="inventoryForm">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="data-table-wrap">
        <?php if (empty($products)): ?>
          <div style="text-align:center;padding:var(--space-3xl);color:var(--text-muted)">
            <span style="font-size:2.5rem">📦</span>
            <p style="margin-top:var(--space-md)">No tracked products found. <a href="<?= SITE_URL ?>/seller/products.php" style="color:var(--amber)">Add products →</a></p>
          </div>
        <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Current Stock</th><th>Alert At</th><th>Status</th><th>Update Stock</th></tr></thead>
            <tbody>
              <?php foreach ($products as $p):
                $is_low = $p['stock_qty'] <= $p['low_stock_threshold'] && $p['stock_qty'] > 0;
                $is_out = $p['stock_qty'] === 0;
              ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:var(--space-md)">
                    <img src="<?= $p['image']?SITE_URL.'/'.$p['image']:SITE_URL.'/assets/images/placeholder.svg' ?>" class="product-td-img" alt="">
                    <div>
                      <p style="font-weight:600"><?= sanitize($p['name']) ?></p>
                      <?php if (!$p['is_active']): ?><span style="font-size:0.7rem;color:var(--text-muted)">(Hidden)</span><?php endif; ?>
                    </div>
                  </div>
                </td>
                <td style="color:var(--text-muted)"><?= sanitize($p['cat_name'] ?? '') ?></td>
                <td><?= format_price($p['price']) ?></td>
                <td>
                  <span style="font-size:1.1rem;font-weight:700;color:<?= $is_out?'var(--rose)':($is_low?'#F59E0B':'var(--sage)') ?>">
                    <?= $p['stock_qty'] ?>
                  </span>
                </td>
                <td style="color:var(--text-muted)"><?= $p['low_stock_threshold'] ?></td>
                <td>
                  <?php if ($is_out): ?>
                    <span class="status-pill status-cancelled">Out of Stock</span>
                  <?php elseif ($is_low): ?>
                    <span class="status-pill status-preparing">⚠️ Low Stock</span>
                  <?php else: ?>
                    <span class="status-pill status-delivered">In Stock</span>
                  <?php endif; ?>
                </td>
                <td>
                  <input type="number" name="stock[<?= $p['id'] ?>]" value="<?= $p['stock_qty'] ?>" min="0" class="stock-input">
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div style="padding:var(--space-lg) var(--space-xl);display:flex;justify-content:flex-end">
          <button type="submit" class="btn btn-amber">💾 Save All Changes</button>
        </div>
        <?php endif; ?>
      </div>
    </form>
  </main>
</div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
