<?php
require_once '../includes/config.php';
$user = require_buyer();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $fa = $_POST['form_action'] ?? '';
    if ($fa === 'save') {
        $aid = (int)($_POST['address_id'] ?? 0);
        $label = sanitize($_POST['label'] ?? 'Home');
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $line1 = trim($_POST['address_line1'] ?? '');
        $line2 = trim($_POST['address_line2'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $pincode = trim($_POST['pincode'] ?? '');
        $is_default = isset($_POST['is_default']) ? 1 : 0;

        if (!$full_name || !$phone || !$line1 || !$city || !$state || !$pincode) { $error = 'All required fields must be filled.'; goto render; }

        if ($is_default) Database::query("UPDATE addresses SET is_default=0 WHERE user_id=?", [$user['id']]);

        if ($aid) {
            Database::query("UPDATE addresses SET label=?,full_name=?,phone=?,address_line1=?,address_line2=?,city=?,state=?,pincode=?,is_default=? WHERE id=? AND user_id=?",
                [$label,$full_name,$phone,$line1,$line2,$city,$state,$pincode,$is_default,$aid,$user['id']]);
        } else {
            Database::insert("INSERT INTO addresses (user_id,label,full_name,phone,address_line1,address_line2,city,state,pincode,is_default) VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$user['id'],$label,$full_name,$phone,$line1,$line2,$city,$state,$pincode,$is_default]);
        }
        flash_set('success', 'Address saved!');
        redirect('/account/addresses.php');
    }
    if ($fa === 'delete') {
        $aid = (int)($_POST['address_id'] ?? 0);
        Database::query("DELETE FROM addresses WHERE id=? AND user_id=?", [$aid, $user['id']]);
        flash_set('success', 'Address removed.');
        redirect('/account/addresses.php');
    }
}

render:
$addresses = Database::fetchAll("SELECT * FROM addresses WHERE user_id=? ORDER BY is_default DESC, id DESC", [$user['id']]);
$edit_addr = isset($_GET['edit']) ? Database::fetch("SELECT * FROM addresses WHERE id=? AND user_id=?", [(int)$_GET['edit'], $user['id']]) : null;
$page_title = 'Manage Addresses';
include '../includes/header.php';
?>
<div class="section" style="background:var(--cream)">
<div class="container-sm">
  <h1 style="font-family:var(--font-display);font-size:2rem;font-weight:700;margin-bottom:var(--space-2xl)">My Addresses 📍</h1>
  <?php $fs = flash_get('success'); if ($fs): ?><div class="alert alert-success" style="margin-bottom:var(--space-xl)">✅ <?= sanitize($fs) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error" style="margin-bottom:var(--space-xl)"><?= sanitize($error) ?></div><?php endif; ?>

  <!-- Address list -->
  <div style="display:grid;gap:var(--space-md);margin-bottom:var(--space-2xl)">
    <?php foreach ($addresses as $a): ?>
    <div class="card" style="padding:var(--space-xl);display:flex;gap:var(--space-lg);align-items:flex-start">
      <div style="flex:1">
        <div style="display:flex;align-items:center;gap:var(--space-sm);margin-bottom:6px">
          <span style="font-weight:700"><?= sanitize($a['label']) ?></span>
          <?php if ($a['is_default']): ?><span class="badge badge-hot" style="font-size:0.65rem">Default</span><?php endif; ?>
        </div>
        <p style="font-weight:600"><?= sanitize($a['full_name']) ?> · <?= sanitize($a['phone']) ?></p>
        <p style="font-size:0.875rem;color:var(--text-muted)"><?= sanitize($a['address_line1']) ?><?= $a['address_line2']?', '.sanitize($a['address_line2']):'' ?>, <?= sanitize($a['city']) ?>, <?= sanitize($a['state']) ?> - <?= sanitize($a['pincode']) ?></p>
      </div>
      <div style="display:flex;gap:var(--space-sm)">
        <a href="?edit=<?= $a['id'] ?>" class="action-btn edit" title="Edit">✏️</a>
        <form method="POST" onsubmit="return confirm('Remove this address?')">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="form_action" value="delete">
          <input type="hidden" name="address_id" value="<?= $a['id'] ?>">
          <button type="submit" class="action-btn delete" title="Delete">🗑️</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Add/Edit form -->
  <div class="card" style="padding:var(--space-2xl)">
    <h3 style="font-family:var(--font-display);margin-bottom:var(--space-xl)"><?= $edit_addr ? 'Edit Address' : 'Add New Address' ?></h3>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="form_action" value="save">
      <input type="hidden" name="address_id" value="<?= $edit_addr['id'] ?? 0 ?>">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-md)">
        <div class="form-group"><label class="form-label">Label</label>
          <select name="label" class="form-select"><option value="Home" <?= ($edit_addr['label']??'Home')==='Home'?'selected':'' ?>>🏠 Home</option><option value="Work" <?= ($edit_addr['label']??'')==='Work'?'selected':'' ?>>🏢 Work</option><option value="Other" <?= ($edit_addr['label']??'')==='Other'?'selected':'' ?>>📍 Other</option></select>
        </div>
        <div class="form-group"><label class="form-label">Full Name <span class="required">*</span></label><input type="text" name="full_name" class="form-input" required value="<?= sanitize($edit_addr['full_name'] ?? $user['full_name']) ?>"></div>
        <div class="form-group"><label class="form-label">Phone <span class="required">*</span></label><input type="tel" name="phone" class="form-input" required value="<?= sanitize($edit_addr['phone'] ?? $user['phone'] ?? '') ?>"></div>
      </div>
      <div class="form-group"><label class="form-label">Address Line 1 <span class="required">*</span></label><input type="text" name="address_line1" class="form-input" required placeholder="House no., Street, Area" value="<?= sanitize($edit_addr['address_line1'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Address Line 2</label><input type="text" name="address_line2" class="form-input" placeholder="Landmark (optional)" value="<?= sanitize($edit_addr['address_line2'] ?? '') ?>"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:var(--space-md)">
        <div class="form-group mb-0"><label class="form-label">City <span class="required">*</span></label><input type="text" name="city" class="form-input" required value="<?= sanitize($edit_addr['city'] ?? '') ?>"></div>
        <div class="form-group mb-0"><label class="form-label">State <span class="required">*</span></label><input type="text" name="state" class="form-input" required value="<?= sanitize($edit_addr['state'] ?? '') ?>"></div>
        <div class="form-group mb-0"><label class="form-label">PIN Code <span class="required">*</span></label><input type="text" name="pincode" class="form-input" required pattern="[0-9]{6}" value="<?= sanitize($edit_addr['pincode'] ?? '') ?>"></div>
      </div>
      <label class="checkbox-label" style="margin:var(--space-xl) 0"><input type="checkbox" name="is_default" <?= ($edit_addr['is_default'] ?? 0) ? 'checked' : '' ?>> Set as default address</label>
      <button type="submit" class="btn btn-amber"><?= $edit_addr ? 'Update Address' : 'Save Address' ?></button>
      <?php if ($edit_addr): ?><a href="<?= SITE_URL ?>/account/addresses.php" class="btn btn-ghost">Cancel</a><?php endif; ?>
    </form>
  </div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
