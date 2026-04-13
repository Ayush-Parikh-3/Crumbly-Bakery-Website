<?php
require_once '../includes/config.php';
$user = require_seller();
$shop = get_shop_by_user($user['id']);
if (!$shop) redirect('/seller/setup.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $error = 'Invalid request.'; goto render; }
    $shop_name = trim($_POST['shop_name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $delivery_fee = (float)($_POST['delivery_fee'] ?? 0);
    $min_order = (float)($_POST['min_order_amount'] ?? 0);
    $free_above = !empty($_POST['free_delivery_above']) ? (float)$_POST['free_delivery_above'] : null;
    $radius = (int)($_POST['delivery_radius_km'] ?? 20);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (!$shop_name) { $error = 'Shop name is required.'; goto render; }

    $logo_path = $shop['logo'];
    $banner_path = $shop['banner'];
    if (!empty($_FILES['logo']['name'])) {
        $p = upload_image($_FILES['logo'], 'avatars');
        if ($p) $logo_path = $p;
    }
    if (!empty($_FILES['banner']['name'])) {
        $p = upload_image($_FILES['banner'], 'products');
        if ($p) $banner_path = $p;
    }

    Database::query("UPDATE shops SET shop_name=?,description=?,address=?,city=?,state=?,pincode=?,delivery_fee=?,min_order_amount=?,free_delivery_above=?,delivery_radius_km=?,is_active=?,logo=?,banner=? WHERE id=?",
        [$shop_name,$desc,$address,$city,$state,$pincode,$delivery_fee,$min_order,$free_above,$radius,$is_active,$logo_path,$banner_path,$shop['id']]);
    $shop = get_shop_by_user($user['id']);
    flash_set('success', 'Shop settings saved! ✅');
    redirect('/seller/shop-settings.php');
}

render:
$page_title = 'Shop Settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Shop Settings — Crumbly Seller</title>
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
        <h1 class="page-title">Shop Settings</h1>
        <p class="page-subtitle">Manage your bakery profile and delivery settings</p>
      </div>
      <a href="<?= SITE_URL ?>/bakery.php?slug=<?= urlencode($shop['shop_slug']) ?>" class="btn btn-outline btn-sm" target="_blank">View Public Shop →</a>
    </div>

    <?php $fs = flash_get('success'); if ($fs): ?><div class="alert alert-success" style="margin-bottom:var(--space-xl)">✅ <?= sanitize($fs) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error" style="margin-bottom:var(--space-xl)"><?= sanitize($error) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div style="display:grid;grid-template-columns:2fr 1fr;gap:var(--space-xl);align-items:start">
        <div style="display:flex;flex-direction:column;gap:var(--space-xl)">

          <div class="card" style="padding:var(--space-2xl)">
            <h3 style="font-family:var(--font-display);margin-bottom:var(--space-xl)">Shop Identity</h3>
            <div class="form-group">
              <label class="form-label">Shop Name <span class="required">*</span></label>
              <input type="text" name="shop_name" class="form-input" required value="<?= sanitize($shop['shop_name']) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">About Your Bakery</label>
              <textarea name="description" class="form-textarea" rows="4" placeholder="Tell customers about your story, specialties, and what makes you unique..."><?= sanitize($shop['description'] ?? '') ?></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-md)">
              <div class="form-group">
                <label class="form-label">Shop Logo</label>
                <?php if ($shop['logo']): ?>
                  <img src="<?= SITE_URL.'/'.$shop['logo'] ?>" style="width:60px;height:60px;object-fit:cover;border-radius:8px;margin-bottom:8px" alt="logo">
                <?php endif; ?>
                <input type="file" name="logo" accept="image/*" class="form-input" style="padding:8px">
              </div>
              <div class="form-group">
                <label class="form-label">Banner Image</label>
                <?php if ($shop['banner']): ?>
                  <img src="<?= SITE_URL.'/'.$shop['banner'] ?>" style="width:100%;height:40px;object-fit:cover;border-radius:6px;margin-bottom:8px" alt="banner">
                <?php endif; ?>
                <input type="file" name="banner" accept="image/*" class="form-input" style="padding:8px">
              </div>
            </div>
          </div>

          <div class="card" style="padding:var(--space-2xl)">
            <h3 style="font-family:var(--font-display);margin-bottom:var(--space-xl)">Location</h3>
            <div class="form-group">
              <label class="form-label">Full Address</label>
              <textarea name="address" class="form-textarea" rows="2"><?= sanitize($shop['address'] ?? '') ?></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:var(--space-md)">
              <div class="form-group mb-0">
                <label class="form-label">City</label>
                <input type="text" name="city" class="form-input" value="<?= sanitize($shop['city'] ?? '') ?>" placeholder="City">
              </div>
              <div class="form-group mb-0">
                <label class="form-label">State</label>
                <input type="text" name="state" class="form-input" value="<?= sanitize($shop['state'] ?? '') ?>" placeholder="State">
              </div>
              <div class="form-group mb-0">
                <label class="form-label">PIN Code</label>
                <input type="text" name="pincode" class="form-input" value="<?= sanitize($shop['pincode'] ?? '') ?>" placeholder="PIN">
              </div>
            </div>
          </div>

          <div class="card" style="padding:var(--space-2xl)">
            <h3 style="font-family:var(--font-display);margin-bottom:var(--space-xl)">Delivery Settings</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-md)">
              <div class="form-group">
                <label class="form-label">Delivery Fee (₹)</label>
                <input type="number" name="delivery_fee" class="form-input" step="0.01" min="0" value="<?= $shop['delivery_fee'] ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Minimum Order (₹)</label>
                <input type="number" name="min_order_amount" class="form-input" step="0.01" min="0" value="<?= $shop['min_order_amount'] ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Free Delivery Above (₹)</label>
                <input type="number" name="free_delivery_above" class="form-input" step="0.01" min="0" placeholder="Leave blank to disable" value="<?= $shop['free_delivery_above'] ?? '' ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Delivery Radius (km)</label>
                <input type="number" name="delivery_radius_km" class="form-input" min="1" max="100" value="<?= $shop['delivery_radius_km'] ?>">
              </div>
            </div>
          </div>

        </div>
        <div style="display:flex;flex-direction:column;gap:var(--space-xl)">
          <div class="card" style="padding:var(--space-xl)">
            <h3 style="font-family:var(--font-display);font-size:1rem;margin-bottom:var(--space-lg)">Shop Status</h3>
            <label class="checkbox-label" style="margin-bottom:var(--space-xl)">
              <input type="checkbox" name="is_active" <?= $shop['is_active'] ? 'checked' : '' ?>> Shop is open & accepting orders
            </label>
            <div style="padding:var(--space-md);background:var(--cream);border-radius:var(--radius-md);font-size:0.85rem;color:var(--text-muted);margin-bottom:var(--space-xl)">
              <strong>Shop URL:</strong><br>
              <a href="<?= SITE_URL ?>/bakery.php?slug=<?= urlencode($shop['shop_slug']) ?>" style="color:var(--amber);word-break:break-all"><?= SITE_URL ?>/bakery.php?slug=<?= sanitize($shop['shop_slug']) ?></a>
            </div>
            <button type="submit" class="btn btn-amber btn-block">Save Settings</button>
          </div>

          <div class="card" style="padding:var(--space-xl)">
            <h3 style="font-family:var(--font-display);font-size:1rem;margin-bottom:var(--space-lg)">Shop Stats</h3>
            <div style="display:flex;flex-direction:column;gap:var(--space-md)">
              <div style="display:flex;justify-content:space-between;font-size:0.875rem">
                <span style="color:var(--text-muted)">Rating</span>
                <strong><?= number_format($shop['rating'],1) ?> ⭐</strong>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:0.875rem">
                <span style="color:var(--text-muted)">Total Reviews</span>
                <strong><?= $shop['total_reviews'] ?></strong>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:0.875rem">
                <span style="color:var(--text-muted)">Total Sales</span>
                <strong><?= number_format($shop['total_sales']) ?></strong>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:0.875rem">
                <span style="color:var(--text-muted)">Verified</span>
                <strong style="color:<?= $shop['is_verified']?'var(--sage)':'var(--text-muted)' ?>"><?= $shop['is_verified']?'✓ Yes':'Pending' ?></strong>
              </div>
            </div>
          </div>
        </div>
      </div>
    </form>
  </main>
</div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
