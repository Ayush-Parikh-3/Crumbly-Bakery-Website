<?php
// account/profile.php
require_once '../includes/config.php';
$user = require_buyer();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $error = 'Invalid request'; goto render; }
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    if (!$full_name) { $error = 'Name is required'; goto render; }

    $avatar = $user['avatar'];
    if (!empty($_FILES['avatar']['name'])) {
        $p = upload_image($_FILES['avatar'], 'avatars');
        if ($p) $avatar = $p;
    }

    $new_pwd = $_POST['new_password'] ?? '';
    $pwd_hash = $user['password_hash'];
    if ($new_pwd) {
        $cur_pwd = $_POST['current_password'] ?? '';
        if (!password_verify($cur_pwd, $user['password_hash'])) { $error = 'Current password is incorrect'; goto render; }
        if (strlen($new_pwd) < 8) { $error = 'New password must be at least 8 characters'; goto render; }
        $pwd_hash = password_hash($new_pwd, PASSWORD_DEFAULT);
    }

    Database::query("UPDATE users SET full_name=?, phone=?, avatar=?, password_hash=? WHERE id=?", [$full_name, $phone, $avatar, $pwd_hash, $user['id']]);
    $user = crumbly_get_current_user();
    $_SESSION['user_name'] = $user['full_name'];
    flash_set('success', 'Profile updated!');
    redirect('/account/profile.php');
}

render:
$page_title = 'My Profile';
include '../includes/header.php';
?>
<div class="section" style="background:var(--cream)">
<div class="container-sm">
  <h1 style="font-family:var(--font-display);font-size:2rem;font-weight:700;margin-bottom:var(--space-2xl)">My Profile</h1>
  <?php $fs = flash_get('success'); if ($fs): ?><div class="alert alert-success" style="margin-bottom:var(--space-xl)">✅ <?= sanitize($fs) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error" style="margin-bottom:var(--space-xl)"><?= sanitize($error) ?></div><?php endif; ?>
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <div class="card" style="padding:var(--space-2xl);margin-bottom:var(--space-xl)">
      <h3 style="font-family:var(--font-display);margin-bottom:var(--space-xl)">Personal Information</h3>
      <div style="display:flex;align-items:center;gap:var(--space-xl);margin-bottom:var(--space-xl)">
        <?php if ($user['avatar']): ?>
          <img src="<?= SITE_URL.'/'.$user['avatar'] ?>" id="avatarPreview" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid var(--border-light)" alt="">
        <?php else: ?>
          <div id="avatarPreview" style="width:80px;height:80px;border-radius:50%;background:var(--grad-warm);display:grid;place-items:center;color:var(--cream);font-weight:700;font-size:1.5rem;flex-shrink:0"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
        <?php endif; ?>
        <div>
          <label class="btn btn-outline btn-sm" style="cursor:pointer;margin-bottom:4px">📸 Change Photo<input type="file" name="avatar" accept="image/*" style="display:none" data-preview="avatarPreview"></label>
          <p style="font-size:0.75rem;color:var(--text-muted)">JPG, PNG or WebP. Max 5MB.</p>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-md)">
        <div class="form-group"><label class="form-label">Full Name <span class="required">*</span></label><input type="text" name="full_name" class="form-input" required value="<?= sanitize($user['full_name']) ?>"></div>
        <div class="form-group"><label class="form-label">Phone</label><input type="tel" name="phone" class="form-input" value="<?= sanitize($user['phone']??'') ?>"></div>
      </div>
      <div class="form-group"><label class="form-label">Email</label><input type="email" class="form-input" value="<?= sanitize($user['email']) ?>" disabled style="opacity:0.6"><p class="form-hint">Email cannot be changed</p></div>
    </div>
    <div class="card" style="padding:var(--space-2xl);margin-bottom:var(--space-xl)">
      <h3 style="font-family:var(--font-display);margin-bottom:var(--space-xl)">Change Password</h3>
      <div class="form-group"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-input" placeholder="Enter current password"></div>
      <div class="form-group"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-input" placeholder="Min. 8 characters"></div>
    </div>
    <button type="submit" class="btn btn-amber btn-lg">Save Changes</button>
    <a href="<?= SITE_URL ?>/account/orders.php" class="btn btn-outline btn-lg" style="margin-left:var(--space-md)">View Orders</a>
  </form>
</div>
</div>
<?php include '../includes/footer.php'; ?>
