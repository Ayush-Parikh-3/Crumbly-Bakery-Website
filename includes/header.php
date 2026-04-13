<?php
if (!defined('CRUMBLY_VERSION')) {
    require_once __DIR__ . '/config.php';
}
$_current_user = crumbly_get_current_user();
$_is_seller    = $_current_user && $_current_user['role'] === 'seller';
$_is_buyer     = $_current_user && $_current_user['role'] !== 'seller';
$_cart_count   = ($_is_buyer && is_logged_in()) ? get_cart_count() : 0;
$_categories   = Database::fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order");
$page_title    = $page_title ?? 'Fresh Baked. Delivered Fast.';

// Hard block: sellers must not access buyer pages
if ($_is_seller) {
    $current_path = $_SERVER['PHP_SELF'];
    $in_allowed   = str_contains($current_path, '/seller/')
                 || str_contains($current_path, '/auth/')
                 || str_contains($current_path, '/api/');
    if (!$in_allowed) {
        header('Location: ' . SITE_URL . '/seller/dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= sanitize($page_meta_desc ?? 'Crumbly — Discover artisan bakeries. Fresh cakes, bread and pastries delivered.') ?>">
  <title><?= sanitize($page_title) ?> — Crumbly 🥐</title>
  <link rel="icon" href="<?= SITE_URL ?>/assets/images/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
</head>
<body>

<nav class="navbar" id="navbar">
  <div class="container">
    <div class="navbar-inner">

      <a href="<?= SITE_URL ?>/index.php" class="navbar-logo">
        <div class="logo-mark">🥐</div>
        <span class="logo-text">Crumb<span>ly</span></span>
      </a>

      <?php if (!$_is_seller): ?>
      <div class="navbar-search">
        <form method="GET" action="<?= SITE_URL ?>/shop.php" class="search-wrap">
          <input type="search" name="q" class="search-input"
            placeholder="Search cakes, bread, pastries..."
            value="<?= sanitize($_GET['q'] ?? '') ?>" autocomplete="off">
          <button type="submit" class="search-btn" aria-label="Search">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
            </svg>
          </button>
        </form>
      </div>
      <?php endif; ?>

      <div class="navbar-actions">
        <?php if (!$_current_user): ?>
          <a href="<?= SITE_URL ?>/auth/login.php"  class="btn-nav-login">Sign In</a>
          <a href="<?= SITE_URL ?>/auth/signup.php" class="btn-nav-start">Get Started</a>

        <?php elseif ($_is_buyer): ?>
          <a href="<?= SITE_URL ?>/wishlist.php" class="nav-icon-btn" title="Wishlist">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
            </svg>
          </a>
          <button class="nav-icon-btn" id="cartToggle" title="Cart">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <span class="nav-badge" id="cartBadge" <?= $_cart_count < 1 ? 'style="display:none"' : '' ?>><?= $_cart_count ?></span>
          </button>
          <div class="nav-divider"></div>
          <div class="user-menu">
            <div class="user-avatar-btn" tabindex="0">
              <?php if ($_current_user['avatar']): ?>
                <img src="<?= SITE_URL.'/'.sanitize($_current_user['avatar']) ?>" alt="">
              <?php else: ?>
                <div class="user-avatar-initials"><?= strtoupper(substr($_current_user['full_name'],0,1)) ?></div>
              <?php endif; ?>
            </div>
            <div class="user-dropdown">
              <div class="dropdown-header">
                <div class="dropdown-name"><?= sanitize($_current_user['full_name']) ?></div>
                <div class="dropdown-role">Buyer</div>
              </div>
              <a href="<?= SITE_URL ?>/account/profile.php"   class="dropdown-item">👤 My Profile</a>
              <a href="<?= SITE_URL ?>/account/orders.php"    class="dropdown-item">📦 My Orders</a>
              <a href="<?= SITE_URL ?>/wishlist.php"          class="dropdown-item">❤️ Wishlist</a>
              <a href="<?= SITE_URL ?>/account/addresses.php" class="dropdown-item">📍 Addresses</a>
              <div class="dropdown-divider"></div>
              <a href="<?= SITE_URL ?>/auth/logout.php" class="dropdown-item danger">🚪 Sign Out</a>
            </div>
          </div>

        <?php else: /* seller */ ?>
          <a href="<?= SITE_URL ?>/seller/dashboard.php" class="btn-nav-start">Seller Dashboard</a>
          <div class="nav-divider"></div>
          <div class="user-menu">
            <div class="user-avatar-btn" tabindex="0">
              <?php if ($_current_user['avatar']): ?>
                <img src="<?= SITE_URL.'/'.sanitize($_current_user['avatar']) ?>" alt="">
              <?php else: ?>
                <div class="user-avatar-initials"><?= strtoupper(substr($_current_user['full_name'],0,1)) ?></div>
              <?php endif; ?>
            </div>
            <div class="user-dropdown">
              <div class="dropdown-header">
                <div class="dropdown-name"><?= sanitize($_current_user['full_name']) ?></div>
                <div class="dropdown-role">Seller</div>
              </div>
              <a href="<?= SITE_URL ?>/seller/dashboard.php"     class="dropdown-item">📊 Dashboard</a>
              <a href="<?= SITE_URL ?>/seller/products.php"      class="dropdown-item">🧁 My Products</a>
              <a href="<?= SITE_URL ?>/seller/orders.php"        class="dropdown-item">📦 Orders</a>
              <a href="<?= SITE_URL ?>/seller/shop-settings.php" class="dropdown-item">🏪 Shop Settings</a>
              <div class="dropdown-divider"></div>
              <a href="<?= SITE_URL ?>/auth/logout.php" class="dropdown-item danger">🚪 Sign Out</a>
            </div>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>

  <?php if (!$_is_seller): ?>
  <div class="cat-bar">
    <div class="cat-bar-inner">
      <a href="<?= SITE_URL ?>/shop.php" class="cat-link <?= empty($_GET['cat']) ? 'active' : '' ?>">🛍️ All</a>
      <?php foreach ($_categories as $cat): ?>
        <a href="<?= SITE_URL ?>/shop.php?cat=<?= urlencode($cat['slug']) ?>"
           class="cat-link <?= (($_GET['cat'] ?? '') === $cat['slug']) ? 'active' : '' ?>">
          <?= $cat['icon'] ?> <?= sanitize($cat['name']) ?>
        </a>
      <?php endforeach; ?>
      <a href="<?= SITE_URL ?>/sellers.php" class="cat-link">🏪 Bakeries</a>
    </div>
  </div>
  <?php endif; ?>
</nav>

<?php if ($_is_buyer || !$_current_user): ?>
<div class="cart-overlay" id="cartOverlay"></div>
<div class="cart-drawer" id="cartDrawer">
  <div class="cart-header">
    <h2 class="cart-title">Your Cart 🛒</h2>
    <button class="cart-close" id="cartClose">✕</button>
  </div>
  <div class="cart-items" id="cartItems">
    <div class="cart-empty" id="cartEmpty">
      <div class="cart-empty-icon">🧺</div>
      <p class="cart-empty-text">Your cart is empty</p>
      <p>Add some delicious treats!</p>
    </div>
  </div>
  <div class="cart-footer" id="cartFooter" style="display:none">
    <div class="cart-summary" id="cartSummary"></div>
    <a href="<?= SITE_URL ?>/checkout.php" class="btn btn-primary btn-block btn-lg">Proceed to Checkout →</a>
  </div>
</div>
<?php endif; ?>

<div class="toast-container" id="toastContainer"></div>
<?php
$flash_success = flash_get('success');
$flash_error   = flash_get('error');
if ($flash_success): ?><script>document.addEventListener('DOMContentLoaded',()=>showToast(<?= json_encode($flash_success) ?>,'success'));</script><?php endif;
if ($flash_error):   ?><script>document.addEventListener('DOMContentLoaded',()=>showToast(<?= json_encode($flash_error)   ?>,'error'));</script><?php endif;
?>
