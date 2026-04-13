<?php
require_once '../includes/config.php';
$user = require_seller();
$shop = get_shop_by_user($user['id']);
if (!$shop) redirect('/seller/setup.php');
$shop_id = $shop['id'];

// Safety patch: add created_at column if it was missing in older installs
try {
    Database::query("ALTER TABLE coupons ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
} catch (Exception $e) {
    // Already exists or not supported — ignore
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $fa = $_POST['form_action'] ?? '';
    if ($fa === 'save') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $type = in_array($_POST['type']??'',['percentage','fixed','free_delivery']) ? $_POST['type'] : 'percentage';
        $value = (float)($_POST['value'] ?? 0);
        $min_order = (float)($_POST['min_order_amount'] ?? 0);
        $max_disc = !empty($_POST['max_discount']) ? (float)$_POST['max_discount'] : null;
        $usage_limit = !empty($_POST['usage_limit']) ? (int)$_POST['usage_limit'] : null;
        $per_user = max(1, (int)($_POST['per_user_limit'] ?? 1));
        $valid_until = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $edit_id = (int)($_POST['coupon_id'] ?? 0);

        if (!$code || $value <= 0) { $error = 'Code and value are required.'; goto render; }

        if ($edit_id) {
            Database::query("UPDATE coupons SET code=?,type=?,value=?,min_order_amount=?,max_discount=?,usage_limit=?,per_user_limit=?,valid_until=?,is_active=? WHERE id=? AND shop_id=?",
                [$code,$type,$value,$min_order,$max_disc,$usage_limit,$per_user,$valid_until,$is_active,$edit_id,$shop_id]);
        } else {
            $exists = Database::fetch("SELECT id FROM coupons WHERE code=?", [$code]);
            if ($exists) { $error = 'This coupon code already exists.'; goto render; }
            Database::insert("INSERT INTO coupons (shop_id,code,type,value,min_order_amount,max_discount,usage_limit,per_user_limit,valid_until,is_active) VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$shop_id,$code,$type,$value,$min_order,$max_disc,$usage_limit,$per_user,$valid_until,$is_active]);
        }
        flash_set('success', 'Coupon saved!');
        redirect('/seller/coupons.php');
    }
    if ($fa === 'delete') {
        $cid = (int)($_POST['coupon_id'] ?? 0);
        Database::query("DELETE FROM coupons WHERE id=? AND shop_id=?", [$cid, $shop_id]);
        flash_set('success', 'Coupon deleted.');
        redirect('/seller/coupons.php');
    }
    if ($fa === 'toggle') {
        $cid = (int)($_POST['coupon_id'] ?? 0);
        Database::query("UPDATE coupons SET is_active = NOT is_active WHERE id=? AND shop_id=?", [$cid, $shop_id]);
        redirect('/seller/coupons.php');
    }
}

render:
$edit_coupon = isset($_GET['edit']) ? Database::fetch("SELECT * FROM coupons WHERE id=? AND shop_id=?", [(int)$_GET['edit'], $shop_id]) : null;
$coupons = Database::fetchAll("SELECT * FROM coupons WHERE shop_id=? ORDER BY id DESC", [$shop_id]);
$page_title = 'Coupons';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Coupons — Crumbly Seller</title>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <style>body{background:var(--cream)}.coupon-card{background:var(--white);border:2px dashed var(--border-color);border-radius:var(--radius-lg);padding:var(--space-xl);transition:border-color 0.2s}.coupon-card:hover{border-color:var(--amber)}</style>
</head>
<body>
<div class="seller-layout">
  <?php include '../includes/seller_sidebar.php'; ?>
  <main class="seller-main">
    <div class="page-header">
      <div><h1 class="page-title">Coupons 🎟️</h1><p class="page-subtitle">Create discount codes for your customers</p></div>
    </div>
    <?php $fs=flash_get('success'); if($fs): ?><div class="alert alert-success" style="margin-bottom:var(--space-xl)">✅ <?= sanitize($fs) ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-error" style="margin-bottom:var(--space-xl)"><?= sanitize($error) ?></div><?php endif; ?>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:var(--space-xl);align-items:start">
      <!-- Coupon List -->
      <div>
        <?php if (empty($coupons)): ?>
          <div style="text-align:center;padding:var(--space-3xl);background:var(--white);border-radius:var(--radius-lg)">
            <span style="font-size:2.5rem">🎟️</span>
            <p style="font-family:var(--font-display);font-size:1.1rem;margin:var(--space-md) 0">No coupons yet</p>
            <p style="color:var(--text-muted)">Create your first discount code using the form →</p>
          </div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:var(--space-md)">
          <?php foreach ($coupons as $c): ?>
          <div class="coupon-card" style="<?= !$c['is_active']?'opacity:0.6':'' ?>">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--space-md)">
              <div style="display:flex;align-items:center;gap:var(--space-lg)">
                <div style="background:var(--espresso);color:var(--amber);font-family:monospace;font-size:1.2rem;font-weight:700;padding:10px 16px;border-radius:var(--radius-md);letter-spacing:0.1em"><?= sanitize($c['code']) ?></div>
                <div>
                  <p style="font-weight:700;margin-bottom:4px">
                    <?php if ($c['type']==='percentage'): ?><?= $c['value'] ?>% Off
                    <?php elseif ($c['type']==='fixed'): ?><?= format_price($c['value']) ?> Off
                    <?php else: ?>Free Delivery<?php endif; ?>
                    <?php if ($c['min_order_amount']>0): ?><span style="font-weight:400;font-size:0.8rem;color:var(--text-muted)"> on orders ≥ <?= format_price($c['min_order_amount']) ?></span><?php endif; ?>
                  </p>
                  <p style="font-size:0.78rem;color:var(--text-muted)">
                    Used <?= $c['used_count'] ?>/<?= $c['usage_limit'] ?? '∞' ?>
                    <?php if ($c['valid_until']): ?> · Expires <?= date('d M Y', strtotime($c['valid_until'])) ?><?php endif; ?>
                  </p>
                </div>
              </div>
              <div style="display:flex;gap:var(--space-sm);align-items:center">
                <span class="status-pill <?= $c['is_active']?'status-active':'status-inactive' ?>"><?= $c['is_active']?'Active':'Paused' ?></span>
                <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="form_action" value="toggle"><input type="hidden" name="coupon_id" value="<?= $c['id'] ?>"><button type="submit" class="action-btn" title="Toggle"><?= $c['is_active']?'⏸️':'▶️' ?></button></form>
                <a href="?edit=<?= $c['id'] ?>" class="action-btn edit" title="Edit">✏️</a>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this coupon?')"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="form_action" value="delete"><input type="hidden" name="coupon_id" value="<?= $c['id'] ?>"><button type="submit" class="action-btn delete" title="Delete">🗑️</button></form>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Create/Edit Form -->
      <div class="card" style="padding:var(--space-xl)">
        <h3 style="font-family:var(--font-display);font-size:1rem;margin-bottom:var(--space-xl)"><?= $edit_coupon ? 'Edit Coupon' : 'Create Coupon' ?></h3>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="form_action" value="save">
          <input type="hidden" name="coupon_id" value="<?= $edit_coupon['id'] ?? 0 ?>">
          <div class="form-group">
            <label class="form-label">Coupon Code <span class="required">*</span></label>
            <input type="text" name="code" class="form-input" required placeholder="e.g. SWEET20" style="text-transform:uppercase;font-family:monospace;font-weight:700;letter-spacing:0.1em" value="<?= sanitize($edit_coupon['code'] ?? '') ?>" oninput="this.value=this.value.toUpperCase()">
          </div>
          <div class="form-group">
            <label class="form-label">Discount Type <span class="required">*</span></label>
            <select name="type" class="form-select" id="couponType" onchange="updateValueLabel()">
              <option value="percentage" <?= ($edit_coupon['type']??'')==='percentage'?'selected':'' ?>>% Percentage Off</option>
              <option value="fixed" <?= ($edit_coupon['type']??'')==='fixed'?'selected':'' ?>>₹ Fixed Amount Off</option>
              <option value="free_delivery" <?= ($edit_coupon['type']??'')==='free_delivery'?'selected':'' ?>>🚚 Free Delivery</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" id="valueLabel">Value <span class="required">*</span></label>
            <input type="number" name="value" class="form-input" required step="0.01" min="0.01" placeholder="e.g. 20" value="<?= $edit_coupon['value'] ?? '' ?>">
          </div>
          <div class="form-group"><label class="form-label">Min. Order Amount (₹)</label><input type="number" name="min_order_amount" class="form-input" step="0.01" min="0" placeholder="0" value="<?= $edit_coupon['min_order_amount'] ?? 0 ?>"></div>
          <div class="form-group"><label class="form-label">Max Discount (₹)</label><input type="number" name="max_discount" class="form-input" step="0.01" min="0" placeholder="Leave blank = no cap" value="<?= $edit_coupon['max_discount'] ?? '' ?>"></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-md)">
            <div class="form-group"><label class="form-label">Total Uses</label><input type="number" name="usage_limit" class="form-input" min="1" placeholder="∞ unlimited" value="<?= $edit_coupon['usage_limit'] ?? '' ?>"></div>
            <div class="form-group"><label class="form-label">Per User</label><input type="number" name="per_user_limit" class="form-input" min="1" value="<?= $edit_coupon['per_user_limit'] ?? 1 ?>"></div>
          </div>
          <div class="form-group"><label class="form-label">Expiry Date</label><input type="date" name="valid_until" class="form-input" value="<?= $edit_coupon['valid_until'] ? date('Y-m-d',strtotime($edit_coupon['valid_until'])) : '' ?>"></div>
          <label class="checkbox-label" style="margin-bottom:var(--space-lg)"><input type="checkbox" name="is_active" <?= ($edit_coupon['is_active'] ?? 1) ? 'checked' : '' ?>> Active</label>
          <button type="submit" class="btn btn-amber btn-block"><?= $edit_coupon ? 'Update Coupon' : 'Create Coupon' ?></button>
          <?php if ($edit_coupon): ?><a href="?" class="btn btn-ghost btn-block" style="margin-top:var(--space-sm)">Cancel</a><?php endif; ?>
        </form>
      </div>
    </div>
  </main>
</div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
function updateValueLabel() {
  const t = document.getElementById('couponType').value;
  document.getElementById('valueLabel').innerHTML = t==='percentage' ? '% Off Value <span class="required">*</span>' : (t==='fixed' ? '₹ Amount Off <span class="required">*</span>' : 'Value (enter 0) <span class="required">*</span>');
}
updateValueLabel();
</script>
</body>
</html>
