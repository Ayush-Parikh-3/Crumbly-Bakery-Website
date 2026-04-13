<?php
require_once '../includes/config.php';
if (is_logged_in()) {
    $u = crumbly_get_current_user();
    redirect($u && $u['role'] === 'seller' ? '/seller/dashboard.php' : '/index.php');
}

$error = '';
$next  = sanitize($_GET['next'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $error = 'Invalid request. Please try again.'; goto render; }
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';
    if (!$email || !$password)                           { $error = 'Please fill in all fields.'; goto render; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))      { $error = 'Invalid email address.'; goto render; }
    $user = Database::fetch("SELECT * FROM users WHERE email = ? AND is_active = 1", [$email]);
    if (!$user || !password_verify($password, $user['password_hash'])) { $error = 'Invalid email or password.'; goto render; }

    login_user($user);
    flash_set('success', 'Welcome back, ' . $user['full_name'] . '! 👋');

    if ($user['role'] === 'seller') redirect('/seller/dashboard.php');
    if ($next)                      { header("Location: $next"); exit; }
    redirect('/index.php');
}

render:
$page_title = 'Sign In';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Sign In — Crumbly 🥐</title>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
</head>
<body>
<div class="auth-page">
  <div class="auth-visual">
    <div class="auth-visual-bg"></div>
    <div class="auth-visual-content">
      <span class="auth-visual-icon">🥐</span>
      <h2 class="auth-visual-title">Welcome Back to<br><em>Crumbly</em></h2>
      <p class="auth-visual-desc">Your favourite artisan bakeries are waiting. Fresh bakes, delivered with love.</p>
      <div class="auth-testimonial">
        <p class="auth-testimonial-text">"I order from Crumbly every week. The quality is unmatched — fresh bakery goods with the convenience of online shopping."</p>
        <p class="auth-testimonial-author">— Ananya S., Mumbai</p>
      </div>
    </div>
  </div>

  <div class="auth-form-side">
    <a href="<?= SITE_URL ?>/index.php" class="auth-back-link">← Back to Crumbly</a>
    <div style="max-width:420px">
      <h1 class="auth-title">Sign In</h1>
      <p class="auth-subtitle">Don't have an account? <a href="<?= SITE_URL ?>/auth/signup.php">Create one free →</a></p>

      <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom:var(--space-xl)">⚠️ <?= sanitize($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <?php if ($next): ?><input type="hidden" name="next" value="<?= sanitize($next) ?>"><?php endif; ?>

        <div class="form-group">
          <label class="form-label" for="email">Email Address <span class="required">*</span></label>
          <div class="input-icon-wrap">
            <span class="input-icon">✉️</span>
            <input type="email" id="email" name="email" class="form-input"
              placeholder="you@example.com" value="<?= sanitize($_POST['email'] ?? '') ?>"
              required autocomplete="email">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password <span class="required">*</span></label>
          <div class="input-icon-wrap">
            <span class="input-icon">🔒</span>
            <input type="password" id="password" name="password" class="form-input"
              placeholder="Your password" required autocomplete="current-password">
            <button type="button" id="togglePwd" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:transparent;border:none;cursor:pointer;font-size:1rem;color:var(--text-muted)">👁️</button>
          </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--space-xl)">
          <label class="checkbox-label"><input type="checkbox" name="remember"> Remember me</label>
          <a href="#" style="font-size:0.875rem;color:var(--amber);font-weight:600">Forgot password?</a>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg">Sign In →</button>
      </form>
    </div>
  </div>
</div>
<script>
document.getElementById('togglePwd')?.addEventListener('click', function() {
  const p = document.getElementById('password');
  p.type = p.type === 'password' ? 'text' : 'password';
  this.textContent = p.type === 'password' ? '👁️' : '🙈';
});
</script>
</body>
</html>
