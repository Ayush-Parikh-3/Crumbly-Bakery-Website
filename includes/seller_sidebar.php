<?php
// expects $user and optionally $shop to already be set
if (!isset($shop)) {
    $shop = isset($user) ? get_shop_by_user($user['id']) : null;
}
$cur = basename($_SERVER['PHP_SELF'], '.php');

$pending_orders = 0;
if ($shop) {
    $row = Database::fetch("SELECT COUNT(*) as cnt FROM orders WHERE shop_id=? AND order_status='pending'", [$shop['id']]);
    $pending_orders = (int)($row['cnt'] ?? 0);
}
?>
<aside class="seller-sidebar">

  <div class="seller-sidebar-logo">
    <a href="<?= SITE_URL ?>/seller/dashboard.php" style="display:flex;align-items:center;gap:10px;text-decoration:none">
      <div class="logo-mark">🥐</div>
      <span class="logo-text" style="color:var(--cream)">Crumb<span style="color:var(--amber)">ly</span></span>
    </a>
  </div>

  <?php if ($shop): ?>
  <div class="sidebar-shop-info">
    <div class="sidebar-shop-name"><?= sanitize($shop['shop_name']) ?></div>
    <div class="sidebar-shop-status">
      <span class="dot" style="background:<?= $shop['is_active'] ? '#6EE7B7' : '#F87171' ?>"></span>
      <?= $shop['is_active'] ? 'Shop is Open' : 'Shop Closed' ?>
    </div>
  </div>
  <?php endif; ?>

  <nav class="seller-nav">

    <div class="seller-nav-group">
      <div class="seller-nav-group-title">Overview</div>
      <a href="<?= SITE_URL ?>/seller/dashboard.php" class="seller-nav-link <?= $cur==='dashboard'?'active':'' ?>">
        <span class="nav-icon">📊</span> Dashboard
      </a>
      <a href="<?= SITE_URL ?>/seller/analytics.php" class="seller-nav-link <?= $cur==='analytics'?'active':'' ?>">
        <span class="nav-icon">📈</span> Analytics
      </a>
    </div>

    <div class="seller-nav-group">
      <div class="seller-nav-group-title">Products</div>
      <a href="<?= SITE_URL ?>/seller/products.php" class="seller-nav-link <?= $cur==='products'?'active':'' ?>">
        <span class="nav-icon">🧁</span> All Products
      </a>
      <a href="<?= SITE_URL ?>/seller/products.php?action=add" class="seller-nav-link">
        <span class="nav-icon">➕</span> Add Product
      </a>
      <a href="<?= SITE_URL ?>/seller/inventory.php" class="seller-nav-link <?= $cur==='inventory'?'active':'' ?>">
        <span class="nav-icon">📦</span> Inventory
      </a>
    </div>

    <div class="seller-nav-group">
      <div class="seller-nav-group-title">Orders</div>
      <a href="<?= SITE_URL ?>/seller/orders.php" class="seller-nav-link <?= $cur==='orders'?'active':'' ?>">
        <span class="nav-icon">🛒</span> All Orders
        <?php if ($pending_orders > 0): ?>
          <span class="nav-badge"><?= $pending_orders ?></span>
        <?php endif; ?>
      </a>
      <a href="<?= SITE_URL ?>/seller/orders.php?status=pending" class="seller-nav-link">
        <span class="nav-icon">⏳</span> Pending
      </a>
      <a href="<?= SITE_URL ?>/seller/reviews.php" class="seller-nav-link <?= $cur==='reviews'?'active':'' ?>">
        <span class="nav-icon">⭐</span> Reviews
      </a>
    </div>

    <div class="seller-nav-group">
      <div class="seller-nav-group-title">My Shop</div>
      <a href="<?= SITE_URL ?>/seller/shop-settings.php" class="seller-nav-link <?= $cur==='shop-settings'?'active':'' ?>">
        <span class="nav-icon">🏪</span> Shop Settings
      </a>
      <?php if ($shop): ?>
      <a href="<?= SITE_URL ?>/bakery.php?slug=<?= urlencode($shop['shop_slug']) ?>" class="seller-nav-link" target="_blank">
        <span class="nav-icon">🔗</span> Public Shop Page
      </a>
      <?php endif; ?>
    </div>

    <div class="seller-nav-group" style="margin-top:auto;padding-top:var(--space-lg);border-top:1px solid rgba(255,255,255,0.06)">
      <a href="<?= SITE_URL ?>/auth/logout.php" class="seller-nav-link" style="color:rgba(248,113,113,0.8)">
        <span class="nav-icon">🚪</span> Sign Out
      </a>
    </div>

  </nav>
</aside>
