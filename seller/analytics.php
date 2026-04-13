<?php
require_once '../includes/config.php';
$user = require_seller();
$shop = get_shop_by_user($user['id']);
if (!$shop) redirect('/seller/setup.php');
$shop_id = $shop['id'];

// Date range
$range = sanitize($_GET['range'] ?? '30');
$days = in_array($range, ['7','30','90','365']) ? (int)$range : 30;

// Revenue by day
$revenue_chart = Database::fetchAll("
    SELECT DATE(created_at) as date, COALESCE(SUM(total_amount),0) as revenue, COUNT(*) as orders
    FROM orders WHERE shop_id=? AND payment_status='paid' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    GROUP BY DATE(created_at) ORDER BY date ASC
", [$shop_id, $days]);

// Top products
$top_products = Database::fetchAll("
    SELECT p.name, SUM(oi.quantity) as units_sold, SUM(oi.total) as revenue
    FROM order_items oi JOIN products p ON p.id=oi.product_id
    JOIN orders o ON o.id=oi.order_id
    WHERE p.shop_id=? AND o.payment_status='paid' AND o.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    GROUP BY p.id ORDER BY revenue DESC LIMIT 10
", [$shop_id, $days]);

// Category breakdown
$cat_breakdown = Database::fetchAll("
    SELECT c.name, c.icon, COUNT(DISTINCT p.id) as products, SUM(oi.quantity) as sold
    FROM products p JOIN categories c ON c.id=p.category_id
    LEFT JOIN order_items oi ON oi.product_id=p.id
    LEFT JOIN orders o ON o.id=oi.order_id AND o.payment_status='paid'
    WHERE p.shop_id=?
    GROUP BY c.id ORDER BY sold DESC
", [$shop_id]);

// Summary stats
$stats = Database::fetch("
    SELECT
        COALESCE(SUM(total_amount),0) as total_revenue,
        COUNT(*) as total_orders,
        COALESCE(AVG(total_amount),0) as avg_order_value
    FROM orders WHERE shop_id=? AND payment_status='paid' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
", [$shop_id, $days]);

$page_title = 'Analytics';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Analytics — Crumbly Seller</title>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>body{background:var(--cream)}.chart-wrap{position:relative;height:280px}</style>
</head>
<body>
<div class="seller-layout">
  <?php include '../includes/seller_sidebar.php'; ?>
  <main class="seller-main">
    <div class="page-header">
      <div><h1 class="page-title">Analytics 📈</h1><p class="page-subtitle">Track your shop's performance</p></div>
      <div style="display:flex;gap:4px">
        <?php foreach (['7'=>'7D','30'=>'30D','90'=>'90D','365'=>'1Y'] as $d=>$l): ?>
          <a href="?range=<?= $d ?>" class="btn btn-sm <?= $range===$d?'btn-primary':'btn-outline' ?>"><?= $l ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Summary Stats -->
    <div class="stats-grid" style="margin-bottom:var(--space-2xl)">
      <div class="stat-card"><div class="stat-icon amber">💰</div><div class="stat-info"><div class="stat-label">Revenue</div><div class="stat-value"><?= format_price($stats['total_revenue']) ?></div><div class="stat-change up">Last <?= $days ?> days</div></div></div>
      <div class="stat-card"><div class="stat-icon rose">📦</div><div class="stat-info"><div class="stat-label">Orders</div><div class="stat-value"><?= number_format($stats['total_orders']) ?></div></div></div>
      <div class="stat-card"><div class="stat-icon sage">📊</div><div class="stat-info"><div class="stat-label">Avg. Order Value</div><div class="stat-value"><?= format_price($stats['avg_order_value']) ?></div></div></div>
    </div>

    <!-- Revenue Chart -->
    <div class="data-table-wrap" style="padding:var(--space-xl);margin-bottom:var(--space-xl)">
      <h3 style="font-family:var(--font-display);font-size:1rem;margin-bottom:var(--space-xl)">Revenue Trend</h3>
      <div class="chart-wrap"><canvas id="revenueChart"></canvas></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-xl)">
      <!-- Top Products -->
      <div class="data-table-wrap">
        <div class="table-toolbar"><span class="table-title">Top Products</span></div>
        <?php if (empty($top_products)): ?>
          <div style="text-align:center;padding:var(--space-2xl);color:var(--text-muted)">No sales data yet</div>
        <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>#</th><th>Product</th><th>Units</th><th>Revenue</th></tr></thead>
            <tbody>
              <?php foreach ($top_products as $i=>$p): ?>
              <tr>
                <td style="color:var(--text-muted)"><?= $i+1 ?></td>
                <td style="font-weight:600"><?= sanitize($p['name']) ?></td>
                <td><?= $p['units_sold'] ?></td>
                <td><strong><?= format_price($p['revenue']) ?></strong></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <!-- Category Breakdown -->
      <div class="data-table-wrap">
        <div class="table-toolbar"><span class="table-title">By Category</span></div>
        <div style="padding:var(--space-lg)">
          <?php foreach ($cat_breakdown as $c): ?>
          <div style="display:flex;align-items:center;gap:var(--space-md);padding:10px 0;border-bottom:1px solid var(--border-light)">
            <span style="font-size:1.3rem"><?= $c['icon'] ?></span>
            <div style="flex:1">
              <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                <span style="font-weight:600;font-size:0.875rem"><?= sanitize($c['name']) ?></span>
                <span style="font-size:0.75rem;color:var(--text-muted)"><?= $c['products'] ?> products · <?= $c['sold'] ?? 0 ?> sold</span>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
const raw  = <?= json_encode($revenue_chart) ?>;
const days = <?= $days ?>;
const ctx  = document.getElementById('revenueChart')?.getContext('2d');

if (ctx) {
  // Build a full date range with 0 for missing days
  // Use string-based date to avoid timezone shifts (never parse YYYY-MM-DD with new Date())
  function makeDateLabel(offsetFromToday) {
    const d = new Date();
    d.setDate(d.getDate() - offsetFromToday);
    const yyyy = d.getFullYear();
    const mm   = String(d.getMonth() + 1).padStart(2, '0');
    const dd   = String(d.getDate()).padStart(2, '0');
    return {
      key:   `${yyyy}-${mm}-${dd}`,
      label: d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })
    };
  }

  // Build full range from oldest → today
  const fullRange = [];
  for (let i = days - 1; i >= 0; i--) {
    fullRange.push(makeDateLabel(i));
  }

  // Map DB data by date key
  const dataMap = {};
  raw.forEach(r => { dataMap[r.date] = parseFloat(r.revenue); });

  const labels   = fullRange.map(d => d.label);
  const revenues = fullRange.map(d => dataMap[d.key] ?? 0);

  new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Revenue (₹)',
        data: revenues,
        borderColor: 'rgba(212,148,30,1)',
        backgroundColor: 'rgba(212,148,30,0.08)',
        fill: true,
        tension: 0.4,
        pointBackgroundColor: revenues.map(v => v > 0 ? 'rgba(212,148,30,1)' : 'transparent'),
        pointBorderColor:     revenues.map(v => v > 0 ? 'rgba(212,148,30,1)' : 'transparent'),
        pointRadius: revenues.map(v => v > 0 ? 5 : 0),
        pointHoverRadius: 6,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
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
          grid: { color: 'rgba(0,0,0,0.04)' },
          ticks: {
            callback: v => '₹' + (v >= 1000 ? (v/1000).toFixed(1)+'k' : v),
            font: { size: 11 }
          }
        },
        x: {
          grid: { display: false },
          ticks: {
            // Show fewer labels when range is large to avoid crowding
            maxTicksLimit: days <= 7 ? 7 : days <= 30 ? 10 : days <= 90 ? 12 : 13,
            font: { size: 11 }
          }
        }
      }
    }
  });
}
</script>
</body>
</html>
