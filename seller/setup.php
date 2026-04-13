<?php
// seller/setup.php - Create shop for new seller
require_once '../includes/config.php';
$user = require_seller();

// Check if shop already exists
$existing = get_shop_by_user($user['id']);
if ($existing) redirect('/seller/dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $error = 'Invalid request'; goto render; }
    $shop_name = trim($_POST['shop_name'] ?? '');
    $city = trim($_POST['city'] ?? '');
    if (!$shop_name) { $error = 'Shop name is required'; goto render; }

    $slug = generate_slug($shop_name);
    $sc = Database::fetch("SELECT COUNT(*) as c FROM shops WHERE shop_slug LIKE ?", [$slug.'%']);
    if ($sc['c'] > 0) $slug .= '-' . time();

    Database::insert("INSERT INTO shops (user_id, shop_name, shop_slug, city) VALUES (?,?,?,?)", [$user['id'], $shop_name, $slug, $city]);
    flash_set('success', 'Your shop is live! 🎉');
    redirect('/seller/dashboard.php');
}

render:
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Setup Your Shop — Crumbly</title>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
</head>
<body style="background:var(--cream);min-height:100vh;display:grid;place-items:center;padding:var(--space-xl)">
  <div style="max-width:480px;width:100%">
    <div style="text-align:center;margin-bottom:var(--space-2xl)">
      <span style="font-size:4rem;display:block;margin-bottom:var(--space-md)">🏪</span>
      <h1 style="font-family:var(--font-display);font-size:2rem;font-weight:700">Set Up Your Bakery</h1>
      <p style="color:var(--text-muted)">You're one step away from selling on Crumbly!</p>
    </div>
    <?php if ($error): ?><div class="alert alert-error" style="margin-bottom:var(--space-xl)"><?= sanitize($error) ?></div><?php endif; ?>
    <form method="POST" class="card" style="padding:var(--space-2xl)">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="form-group">
        <label class="form-label">Bakery Name <span class="required">*</span></label>
        <input type="text" name="shop_name" class="form-input" required placeholder="e.g. Sweet Crust Bakery" autofocus>
      </div>
      <div class="form-group">
        <label class="form-label">City</label>
        <input type="text" name="city" class="form-input" placeholder="e.g. Mumbai">
      </div>
      <button type="submit" class="btn btn-amber btn-block btn-lg">Launch My Shop 🚀</button>
    </form>
  </div>
  <script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
