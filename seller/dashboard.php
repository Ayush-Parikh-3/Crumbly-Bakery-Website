<?php
require_once '../includes/config.php';
$user = require_seller();
$shop = get_shop_by_user($user['id']);
if (!$shop) redirect('/seller/setup.php');
$sid = $shop['id'];

$stats = Database::fetch("
    SELECT
      (SELECT COUNT(*) FROM products WHERE shop_id=? AND is_active=1)            AS active_products,
      (SELECT COUNT(*) FROM products WHERE shop_id=?)                             AS total_products,
      (SELECT COUNT(*) FROM orders   WHERE shop_id=? AND order_status!='cancelled') AS total_orders,
      (SELECT COUNT(*) FROM orders   WHERE shop_id=? AND order_status='pending')  AS pending_orders,
      (SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE shop_id=? AND payment_status='paid') AS total_revenue,
      (SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE shop_id=? AND payment_status='paid' AND MONTH(created_at)=MONTH(NOW())) AS month_revenue,
      (SELECT COUNT(*) FROM orders WHERE shop_id=? AND order_status='delivered')  AS completed_orders
", array_fill(0,7,$sid));

$recent_orders = Database::fetchAll("
    SELECT o.*, u.full_name AS customer_name, u.phone AS customer_phone
    FROM orders o JOIN users u ON u.id=o.user_id
    WHERE o.shop_id=? ORDER BY o.created_at DESC LIMIT 8
", [$sid]);

$revenue_data = Database::fetchAll("
    SELECT DATE(created_at) AS date, COALESCE(SUM(total_amount),0) AS revenue, COUNT(*) AS orders
    FROM orders WHERE shop_id=? AND payment_status='paid' AND created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)
    GROUP BY DATE(created_at) ORDER BY date ASC
", [$sid]);

$low_stock = Database::fetchAll("
    SELECT p.*, (SELECT image_path FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image
    FROM products p WHERE p.shop_id=? AND p.track_stock=1 AND p.stock_qty<=p.low_stock_threshold AND p.is_active=1
    ORDER BY p.stock_qty ASC LIMIT 5
", [$sid]);

$page_title = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard — Crumbly Seller</title>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>body{background:var(--cream)}.chart-container{position:relative;height:240px}</style>
</head>
<body>
<div class="seller-layout">
  <?php include '../includes/seller_sidebar.php'; ?>
  <main class="seller-main">

    <?php $fs=flash_get('success'); if($fs): ?>
    <div class="alert alert-success" style="margin-bottom:var(--space-xl)">✅ <?= sanitize($fs) ?></div>
    <?php endif; ?>

    <div class="page-header">
      <div>
        <h1 class="page-title">Hello, <?= sanitize(explode(' ',$user['full_name'])[0]) ?> 👋</h1>
        <p class="page-subtitle">Here's what's happening at <strong><?= sanitize($shop['shop_name']) ?></strong> today.</p>
      </div>
      <div style="display:flex;gap:var(--space-md)">
        <a href="<?= SITE_URL ?>/bakery.php?slug=<?= urlencode($shop['shop_slug']) ?>" class="btn btn-outline btn-sm" target="_blank">View Public Shop</a>
        <a href="<?= SITE_URL ?>/seller/products.php?action=add" class="btn btn-amber btn-sm">+ Add Product</a>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon amber">💰</div>
        <div class="stat-info">
          <div class="stat-label">Total Revenue</div>
          <div class="stat-value"><?= format_price($stats['total_revenue']) ?></div>
          <div class="stat-change up"><?= format_price($stats['month_revenue']) ?> this month</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon rose">📦</div>
        <div class="stat-info">
          <div class="stat-label">Total Orders</div>
          <div class="stat-value"><?= number_format($stats['total_orders']) ?></div>
          <div class="stat-change <?= $stats['pending_orders']>0?'down':'up' ?>"><?= $stats['pending_orders'] ?> pending</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon sage">🧁</div>
        <div class="stat-info">
          <div class="stat-label">Active Products</div>
          <div class="stat-value"><?= $stats['active_products'] ?></div>
          <div class="stat-change up"><?= $stats['total_products'] ?> total</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon blue">⭐</div>
        <div class="stat-info">
          <div class="stat-label">Shop Rating</div>
          <div class="stat-value"><?= number_format($shop['rating'],1) ?>/5</div>
          <div class="stat-change up"><?= $shop['total_reviews'] ?> reviews</div>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:var(--space-xl);margin-bottom:var(--space-xl)">
      <!-- Chart -->
      <div class="data-table-wrap">
        <div class="table-toolbar">
          <span class="table-title">Revenue — Last 7 Days</span>
          <a href="<?= SITE_URL ?>/seller/analytics.php" class="btn btn-ghost btn-sm">Full Analytics →</a>
        </div>
        <div style="padding:var(--space-xl)">
          <div class="chart-container"><canvas id="revenueChart"></canvas></div>
        </div>
      </div>
      <!-- Low Stock -->
      <div class="data-table-wrap">
        <div class="table-toolbar">
          <span class="table-title">⚠️ Low Stock</span>
          <a href="<?= SITE_URL ?>/seller/inventory.php" class="btn btn-ghost btn-sm">Manage →</a>
        </div>
        <div style="padding:var(--space-md)">
          <?php if (empty($low_stock)): ?>
            <div style="text-align:center;padding:var(--space-xl);color:var(--text-muted)"><span style="font-size:2rem">✅</span><p style="margin-top:8px;font-size:0.875rem">All products well stocked</p></div>
          <?php else: ?>
            <?php foreach ($low_stock as $p): ?>
            <div style="display:flex;align-items:center;gap:var(--space-md);padding:10px;border-bottom:1px solid var(--border-light)">
              <img src="<?= $p['image']?SITE_URL.'/'.$p['image']:SITE_URL.'/assets/images/placeholder.svg' ?>" class="product-td-img" alt="">
              <div style="flex:1;min-width:0">
                <p style="font-size:0.875rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= sanitize($p['name']) ?></p>
                <p style="font-size:0.75rem;color:var(--rose);font-weight:600"><?= $p['stock_qty'] ?> left</p>
              </div>
              <a href="<?= SITE_URL ?>/seller/products.php?action=edit&id=<?= $p['id'] ?>" class="action-btn edit">✏️</a>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Recent Orders -->
    <div class="data-table-wrap">
      <div class="table-toolbar">
        <span class="table-title">Recent Orders</span>
        <a href="<?= SITE_URL ?>/seller/orders.php" class="btn btn-ghost btn-sm">View All →</a>
      </div>
      <?php if (empty($recent_orders)): ?>
        <div style="text-align:center;padding:var(--space-3xl);color:var(--text-muted)">
          <span style="font-size:2.5rem">📭</span>
          <p style="font-family:var(--font-display);font-size:1.1rem;margin-top:var(--space-md)">No orders yet</p>
          <p style="font-size:0.875rem;margin-top:4px">Orders will appear here once customers purchase your products.</p>
        </div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Order #</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($recent_orders as $o): ?>
            <tr>
              <td><strong><?= sanitize($o['order_number']) ?></strong></td>
              <td>
                <p style="font-weight:600"><?= sanitize($o['customer_name']) ?></p>
                <p style="font-size:0.75rem;color:var(--text-muted)"><?= sanitize($o['customer_phone']??'') ?></p>
              </td>
              <td><strong><?= format_price($o['total_amount']) ?></strong></td>
              <td>
                <select class="update-order-status" data-order-id="<?= $o['id'] ?>"
                  style="border:none;background:transparent;cursor:pointer;font-size:0.72rem;font-weight:700;text-transform:uppercase;padding:3px 8px;border-radius:99px;background:<?= [
                    'pending'=>'#FEF3C7','confirmed'=>'#EFF6FF','preparing'=>'#FEF9C3',
                    'ready'=>'#F0FDF4','out_for_delivery'=>'#F0F9FF','delivered'=>'#D1FAE5','cancelled'=>'#FEE2E2'
                  ][$o['order_status']]??'#F3F4F6' ?>">
                  <?php foreach (['pending','confirmed','preparing','ready','out_for_delivery','delivered','cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= $o['order_status']===$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td style="color:var(--text-muted);font-size:0.82rem"><?= time_ago($o['created_at']) ?></td>
              <td><a href="<?= SITE_URL ?>/seller/order-detail.php?id=<?= $o['id'] ?>" class="action-btn view">👁️</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </main>
</div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
const ctx = document.getElementById('revenueChart')?.getContext('2d');
if (ctx) {
  const raw = <?= json_encode($revenue_data) ?>;

  // Build last 7 days using local date strings — avoids UTC timezone shift
  const last7 = [];
  for (let i = 6; i >= 0; i--) {
    const d  = new Date();
    d.setDate(d.getDate() - i);
    const yyyy = d.getFullYear();
    const mm   = String(d.getMonth() + 1).padStart(2, '0');
    const dd   = String(d.getDate()).padStart(2, '0');
    const key  = `${yyyy}-${mm}-${dd}`;
    const found = raw.find(r => r.date === key);
    last7.push({
      label:   d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric' }),
      revenue: found ? parseFloat(found.revenue) : 0
    });
  }

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: last7.map(d => d.label),
      datasets: [{
        label: 'Revenue (₹)',
        data: last7.map(d => d.revenue),
        backgroundColor: 'rgba(212,148,30,0.3)',
        borderColor: 'rgba(212,148,30,1)',
        borderWidth: 2,
        borderRadius: 8,
        borderSkipped: false,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => ' ₹' + ctx.parsed.y.toLocaleString('en-IN', { minimumFractionDigits: 2 })
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          grid: { color: 'rgba(0,0,0,0.05)' },
          ticks: { callback: v => '₹' + (v >= 1000 ? (v/1000).toFixed(1)+'k' : v), font: { size: 11 } }
        },
        x: { grid: { display: false }, ticks: { font: { size: 11 } } }
      }
    }
  });
}
</script>
</body>
</html>
