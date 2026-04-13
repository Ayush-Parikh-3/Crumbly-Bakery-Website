<?php
require_once '../includes/config.php';
if (is_logged_in()) {
    $u = crumbly_get_current_user();
    redirect($u && $u['role'] === 'seller' ? '/seller/dashboard.php' : '/index.php');
}

$error        = '';
$default_role = in_array($_GET['role'] ?? '', ['buyer','seller']) ? $_GET['role'] : 'buyer';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $error = 'Invalid request.'; goto render; }
    $full_name        = trim($_POST['full_name']        ?? '');
    $email            = trim($_POST['email']            ?? '');
    $phone            = trim($_POST['phone']            ?? '');
    $password         = $_POST['password']              ?? '';
    $confirm_password = $_POST['confirm_password']      ?? '';
    $role             = in_array($_POST['role'] ?? '', ['buyer','seller']) ? $_POST['role'] : 'buyer';
    $agree            = $_POST['agree']                 ?? '';

    if (!$full_name || !$email || !$password || !$confirm_password) { $error = 'Please fill all required fields.'; goto render; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))                  { $error = 'Please enter a valid email address.'; goto render; }
    if (strlen($password) < 8)                                       { $error = 'Password must be at least 8 characters.'; goto render; }
    if ($password !== $confirm_password)                             { $error = 'Passwords do not match.'; goto render; }
    if (!$agree)                                                     { $error = 'You must agree to the Terms of Service.'; goto render; }
    if (Database::fetch("SELECT id FROM users WHERE email = ?", [$email])) { $error = 'An account with this email already exists.'; goto render; }

    Database::beginTransaction();
    try {
        $user_id = Database::insert(
            "INSERT INTO users (email, password_hash, role, full_name, phone, email_verified) VALUES (?,?,?,?,?,1)",
            [$email, password_hash($password, PASSWORD_DEFAULT), $role, $full_name, $phone]
        );

        if ($role === 'seller') {
            $shop_name = trim($_POST['shop_name'] ?? ($full_name . "'s Bakery"));
            $city      = trim($_POST['city']      ?? '');
            $slug      = generate_slug($shop_name);
            $cnt       = Database::fetch("SELECT COUNT(*) as c FROM shops WHERE shop_slug LIKE ?", [$slug . '%'])['c'];
            if ($cnt > 0) $slug .= '-' . $user_id;
            Database::insert(
                "INSERT INTO shops (user_id, shop_name, shop_slug, city) VALUES (?,?,?,?)",
                [$user_id, $shop_name, $slug, $city]
            );
        }

        Database::commit();
        $user = Database::fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
        login_user($user);
        flash_set('success', "Welcome to Crumbly, {$full_name}! 🎉");
        redirect($role === 'seller' ? '/seller/dashboard.php' : '/index.php');
    } catch (Exception $e) {
        Database::rollback();
        error_log($e->getMessage());
        $error = 'Something went wrong. Please try again.';
    }
}

render:
$page_title = 'Create Account';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Create Account — Crumbly 🥐</title>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <style>.seller-fields{display:none}.seller-fields.show{display:block}</style>
</head>
<body>
<div class="auth-page">
  <div class="auth-visual">
    <div class="auth-visual-bg"></div>
    <div class="auth-visual-content">
      <span class="auth-visual-icon">🎂</span>
      <h2 class="auth-visual-title">Join <em>Crumbly</em> Today</h2>
      <p class="auth-visual-desc">Whether you're a food lover or an artisan baker, Crumbly has a place for you.</p>
      <div style="display:grid;gap:var(--space-md);margin-top:var(--space-2xl)">
        <?php foreach(['🎁 Free to join, no monthly fees','🚀 Sell or shop in minutes','💳 Secure payments & fast payouts','⭐ Join 10,000+ happy members'] as $p): ?>
        <div style="display:flex;align-items:center;gap:var(--space-md);padding:var(--space-md) var(--space-lg);background:rgba(255,255,255,0.06);border-radius:var(--radius-md);border:1px solid rgba(255,255,255,0.08)">
          <span style="font-size:1.1rem"><?= explode(' ',$p)[0] ?></span>
          <span style="font-size:0.9rem;color:rgba(253,246,236,0.8)"><?= implode(' ',array_slice(explode(' ',$p),1)) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="auth-form-side">
    <a href="<?= SITE_URL ?>/index.php" class="auth-back-link">← Back to Crumbly</a>
    <div style="max-width:460px">
      <h1 class="auth-title">Create Account</h1>
      <p class="auth-subtitle">Already have an account? <a href="<?= SITE_URL ?>/auth/login.php">Sign in →</a></p>

      <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom:var(--space-xl)">⚠️ <?= sanitize($error) ?></div>
      <?php endif; ?>

      <form method="POST" id="signupForm">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="role" id="roleInput" value="<?= $default_role ?>">

        <!-- Role Selector -->
        <div class="form-group">
          <label class="form-label">I want to... <span class="required">*</span></label>
          <div class="role-selector">
            <div class="role-card <?= $default_role==='buyer'?'selected':'' ?>" data-role="buyer" onclick="selectRole('buyer')">
              <div class="role-check">✓</div>
              <div class="role-icon">🛒</div>
              <div class="role-name">Shop</div>
              <div class="role-desc">Buy fresh bakery products</div>
            </div>
            <div class="role-card <?= $default_role==='seller'?'selected':'' ?>" data-role="seller" onclick="selectRole('seller')">
              <div class="role-check">✓</div>
              <div class="role-icon">🏪</div>
              <div class="role-name">Sell</div>
              <div class="role-desc">List &amp; sell my baked goods</div>
            </div>
          </div>
        </div>

        <!-- Seller extra fields -->
        <div class="seller-fields <?= $default_role==='seller'?'show':'' ?>" id="sellerFields">
          <div style="background:var(--amber-pale);border:1px solid var(--amber-light);border-radius:var(--radius-md);padding:var(--space-md) var(--space-lg);margin-bottom:var(--space-lg);font-size:0.875rem;color:var(--espresso)">
            🏪 You're signing up as a <strong>Seller</strong>. You'll get a seller dashboard to manage your shop.
          </div>
          <div class="form-group">
            <label class="form-label">Bakery / Shop Name <span class="required">*</span></label>
            <input type="text" id="shop_name" name="shop_name" class="form-input" placeholder="e.g. Sweet Crust Bakery" value="<?= sanitize($_POST['shop_name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">City</label>
            <input type="text" name="city" class="form-input" placeholder="e.g. Mumbai" value="<?= sanitize($_POST['city'] ?? '') ?>">
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-md)">
          <div class="form-group">
            <label class="form-label">Full Name <span class="required">*</span></label>
            <input type="text" name="full_name" class="form-input" placeholder="Your name" value="<?= sanitize($_POST['full_name'] ?? '') ?>" required autocomplete="name">
          </div>
          <div class="form-group">
            <label class="form-label">Phone</label>
            <input type="tel" name="phone" class="form-input" placeholder="+91 9876543210" value="<?= sanitize($_POST['phone'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Email Address <span class="required">*</span></label>
          <div class="input-icon-wrap">
            <span class="input-icon">✉️</span>
            <input type="email" name="email" class="form-input" placeholder="you@example.com" value="<?= sanitize($_POST['email'] ?? '') ?>" required autocomplete="email">
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-md)">
          <div class="form-group">
            <label class="form-label">Password <span class="required">*</span></label>
            <input type="password" id="password" name="password" class="form-input" placeholder="Min. 8 chars" required autocomplete="new-password">
          </div>
          <div class="form-group">
            <label class="form-label">Confirm Password <span class="required">*</span></label>
            <input type="password" name="confirm_password" class="form-input" placeholder="Repeat password" required>
          </div>
        </div>

        <!-- Password strength -->
        <div id="pwdStrength" style="margin-top:-12px;margin-bottom:var(--space-lg);display:none">
          <div style="height:3px;background:var(--border-light);border-radius:2px;overflow:hidden;margin-bottom:4px">
            <div id="pwdBar" style="height:100%;border-radius:2px;transition:all 0.3s;width:0"></div>
          </div>
          <span id="pwdLabel" style="font-size:0.72rem;font-weight:600"></span>
        </div>

        <div class="form-group" style="margin-bottom:var(--space-xl)">
          <label class="checkbox-label" style="align-items:flex-start;gap:var(--space-md)">
            <input type="checkbox" name="agree" id="agree" style="flex-shrink:0;margin-top:2px">
            <span>I agree to Crumbly's <a href="#" style="color:var(--amber)">Terms of Service</a> and <a href="#" style="color:var(--amber)">Privacy Policy</a></span>
          </label>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg" id="submitBtn">Create My Account →</button>
      </form>
    </div>
  </div>
</div>

<script>
function selectRole(role) {
  document.getElementById('roleInput').value = role;
  document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
  document.querySelector('.role-card[data-role="' + role + '"]').classList.add('selected');
  document.getElementById('sellerFields').classList.toggle('show', role === 'seller');
}

const pwdInput = document.getElementById('password');
pwdInput?.addEventListener('input', function() {
  const s = document.getElementById('pwdStrength');
  const b = document.getElementById('pwdBar');
  const l = document.getElementById('pwdLabel');
  s.style.display = this.value ? 'block' : 'none';
  let score = 0;
  if (this.value.length >= 8) score++;
  if (this.value.length >= 12) score++;
  if (/[A-Z]/.test(this.value)) score++;
  if (/[0-9]/.test(this.value)) score++;
  if (/[^A-Za-z0-9]/.test(this.value)) score++;
  const levels = [
    {pct:'20%',color:'#EF4444',label:'Very Weak'},
    {pct:'40%',color:'#F97316',label:'Weak'},
    {pct:'60%',color:'#EAB308',label:'Fair'},
    {pct:'80%',color:'#84CC16',label:'Good'},
    {pct:'100%',color:'#22C55E',label:'Strong'},
  ];
  const lv = levels[Math.min(score-1,4)] || levels[0];
  b.style.width = lv.pct; b.style.background = lv.color;
  l.textContent = lv.label; l.style.color = lv.color;
});

document.getElementById('signupForm')?.addEventListener('submit', function(e) {
  const role = document.getElementById('roleInput').value;
  if (role === 'seller' && !document.getElementById('shop_name').value.trim()) {
    e.preventDefault(); alert('Please enter your bakery name.'); return;
  }
  if (!document.getElementById('agree').checked) {
    e.preventDefault(); alert('Please agree to the Terms of Service.'); return;
  }
  document.getElementById('submitBtn').textContent = 'Creating Account...';
  document.getElementById('submitBtn').disabled = true;
});
</script>
</body>
</html>
