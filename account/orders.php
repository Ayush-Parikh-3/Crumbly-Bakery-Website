<?php
require_once '../includes/config.php';
$user = require_buyer();

$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = ORDERS_PER_PAGE;
$offset   = ($page - 1) * $per_page;

$orders      = [];
$total       = 0;
$total_pages = 0;
$db_error    = '';

try {
    $total = (int)(Database::fetch(
        "SELECT COUNT(*) as cnt FROM orders WHERE user_id = ?",
        [$user['id']]
    )['cnt'] ?? 0);

    $orders = Database::fetchAll(
        "SELECT o.id, o.order_number, o.order_status, o.payment_status,
                o.subtotal, o.delivery_fee, o.total_amount,
                o.created_at, o.cancellation_reason,
                s.id as shop_id, s.shop_name, s.shop_slug
         FROM orders o
         JOIN shops s ON s.id = o.shop_id
         WHERE o.user_id = ?
         ORDER BY o.created_at DESC
         LIMIT $per_page OFFSET $offset",
        [$user['id']]
    );

    $total_pages = $total > 0 ? ceil($total / $per_page) : 0;

} catch (Exception $e) {
    $db_error = 'Could not load orders: ' . $e->getMessage();
}

// Statuses where review is allowed
$reviewable = ['confirmed','preparing','ready','out_for_delivery','delivered'];
$steps      = ['pending','confirmed','preparing','ready','out_for_delivery','delivered'];
$step_icons = ['📋','✅','👨‍🍳','📦','🚴','🏠'];

$page_title = 'My Orders';
include '../includes/header.php';
?>

<style>
.star-picker{display:flex;flex-direction:row-reverse;gap:6px;justify-content:flex-end}
.star-picker input{display:none}
.star-picker label{font-size:2.2rem;color:#edd9b8;cursor:pointer;transition:color .15s,transform .15s;line-height:1}
.star-picker label:hover,.star-picker label:hover~label,.star-picker input:checked~label{color:#d4941e}
.star-picker label:hover{transform:scale(1.2)}
#reviewOverlay{display:none;position:fixed;inset:0;background:rgba(28,10,0,.6);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;padding:20px}
#reviewOverlay.open{display:flex}
.review-box{background:#fff;border-radius:20px;width:100%;max-width:500px;box-shadow:0 24px 64px rgba(28,10,0,.25);overflow:hidden;animation:popIn .3s cubic-bezier(.34,1.56,.64,1)}
@keyframes popIn{from{transform:scale(.85) translateY(30px);opacity:0}to{transform:scale(1) translateY(0);opacity:1}}
</style>

<div class="section" style="background:var(--cream)">
<div class="container-sm">

  <h1 style="font-family:var(--font-display);font-size:2rem;font-weight:700;margin-bottom:var(--space-2xl)">My Orders 📦</h1>

  <?php if ($db_error): ?>
    <div class="alert alert-error" style="margin-bottom:20px">⚠️ <?= sanitize($db_error) ?></div>

  <?php elseif (empty($orders)): ?>
    <div style="text-align:center;padding:60px 20px;background:#fff;border-radius:20px">
      <div style="font-size:3rem;margin-bottom:16px">📦</div>
      <h3 style="font-family:var(--font-display);margin-bottom:8px">No orders yet</h3>
      <p style="color:var(--text-muted);margin-bottom:24px">Start shopping to see your orders here.</p>
      <a href="<?= SITE_URL ?>/shop.php" class="btn btn-amber">Shop Now →</a>
    </div>

  <?php else: foreach ($orders as $o):

    // Load order items
    $items = [];
    try {
      $items = Database::fetchAll(
        "SELECT oi.product_name, oi.quantity,
                (SELECT image_path FROM product_images WHERE product_id = oi.product_id AND is_primary = 1 LIMIT 1) as image
         FROM order_items oi WHERE oi.order_id = ?",
        [$o['id']]
      );
    } catch(Exception $e){}

    // Check already reviewed
    $already_reviewed = false;
    try {
      $rev = Database::fetch(
        "SELECT id FROM reviews WHERE user_id = ? AND shop_id = ? AND order_id = ?",
        [$user['id'], $o['shop_id'], $o['id']]
      );
      $already_reviewed = !empty($rev);
    } catch(Exception $e){}

    // Trim status to remove any hidden spaces/chars from DB
    $status   = trim($o['order_status']);
    $can_review = in_array($status, $reviewable);

    // Tracker
    $cur_idx  = array_search($status, $steps);
    $step_cnt = count($steps) - 1;
    $pct      = ($cur_idx !== false && $step_cnt > 0) ? (int)round(($cur_idx / $step_cnt) * 100) : 0;
  ?>

  <div class="card" style="padding:24px;margin-bottom:20px">

    <!-- Order header -->
    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
      <div>
        <p style="font-weight:700;font-size:1rem;margin-bottom:4px"><?= sanitize($o['order_number']) ?></p>
        <p style="font-size:0.82rem;color:var(--text-muted)">
          <a href="<?= SITE_URL ?>/bakery.php?slug=<?= urlencode($o['shop_slug']) ?>" style="color:var(--rose);font-weight:600"><?= sanitize($o['shop_name']) ?></a>
          · <?= date('d M Y, g:i A', strtotime($o['created_at'])) ?>
        </p>
      </div>
      <div style="text-align:right">
        <p style="font-weight:700;font-size:1.1rem;font-family:var(--font-display)"><?= format_price($o['total_amount']) ?></p>
        <span class="status-pill status-<?= sanitize($status) ?>" style="display:inline-block;margin-top:4px">
          <?= ucwords(str_replace('_',' ',$status)) ?>
        </span>
      </div>
    </div>

    <!-- Thumbnails -->
    <?php if (!empty($items)): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
      <?php foreach (array_slice($items,0,5) as $item): ?>
        <img src="<?= !empty($item['image']) ? SITE_URL.'/'.$item['image'] : SITE_URL.'/assets/images/placeholder.svg' ?>"
          title="<?= sanitize($item['product_name']) ?>"
          style="width:50px;height:50px;object-fit:cover;border-radius:8px;border:2px solid var(--border-light)" alt="">
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Tracker (not cancelled) -->
    <?php if ($status !== 'cancelled'): ?>
    <div style="position:relative;padding:0 8px;margin-bottom:16px">
      <div style="position:absolute;top:18px;left:8px;right:8px;height:2px;background:var(--border-light);z-index:0">
        <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,#d4941e,#f0b84c)"></div>
      </div>
      <div style="display:flex;justify-content:space-between;position:relative;z-index:1">
        <?php foreach ($steps as $i => $s):
          $cls = ($cur_idx!==false && $i < $cur_idx) ? 'done' : ($status===$s ? 'current' : '');
        ?>
        <div style="display:flex;flex-direction:column;align-items:center;gap:4px;min-width:48px;text-align:center">
          <div style="width:36px;height:36px;border-radius:50%;font-size:0.85rem;display:grid;place-items:center;
            background:<?= $cls==='done'?'#1c0a00':($cls==='current'?'#d4941e':'#fff') ?>;
            border:3px solid <?= $cls==='done'?'#1c0a00':($cls==='current'?'#d4941e':'#edd9b8') ?>;
            <?= $cls==='current'?'box-shadow:0 0 0 4px rgba(212,148,30,.2)':'' ?>
          "><?= $step_icons[$i] ?></div>
          <span style="font-size:0.6rem;font-weight:<?= $cls?'700':'500' ?>;color:<?= $cls?'#1c0a00':'#a8927a' ?>;line-height:1.2">
            <?= ucwords(str_replace('_',' ',$s)) ?>
          </span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php else: ?>
    <div class="alert alert-error" style="margin-bottom:12px">
      ❌ Order cancelled<?= !empty($o['cancellation_reason']) ? ' — '.sanitize($o['cancellation_reason']) : '' ?>
    </div>
    <?php endif; ?>

    <!-- REVIEW SECTION — always shown at bottom (not inside if/else above) -->
    <div style="padding-top:14px;border-top:1px solid var(--border-light)">
      <?php if ($status === 'cancelled'): ?>
        <!-- No review for cancelled -->

      <?php elseif ($already_reviewed): ?>
        <p style="font-size:0.875rem;color:#7a9e7e;font-weight:600">✅ You've reviewed this order — thank you!</p>

      <?php elseif ($can_review): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
          <p style="font-size:0.85rem;color:var(--text-muted)">
            How was your experience with <strong><?= sanitize($o['shop_name']) ?></strong>?
          </p>
          <button
            onclick="openReview(<?= (int)$o['id'] ?>, <?= (int)$o['shop_id'] ?>, '<?= addslashes(sanitize($o['shop_name'])) ?>')"
            style="padding:8px 20px;background:transparent;border:2px solid #d4941e;color:#d4941e;border-radius:99px;font-weight:700;font-size:0.85rem;cursor:pointer;display:flex;align-items:center;gap:6px;transition:all .2s"
            onmouseover="this.style.background='#d4941e';this.style.color='#1c0a00'"
            onmouseout="this.style.background='transparent';this.style.color='#d4941e'">
            ⭐ Write a Review
          </button>
        </div>

      <?php else: ?>
        <p style="font-size:0.8rem;color:var(--text-light)">
          💬 You can leave a review once the seller confirms your order.
        </p>
      <?php endif; ?>
    </div>

  </div>
  <?php endforeach; endif; ?>

  <?php if ($total_pages > 1): ?>
  <div class="pagination">
    <?php for ($i=1;$i<=$total_pages;$i++): ?>
      <a href="?page=<?= $i ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

</div>
</div>

<!-- ===== REVIEW MODAL ===== -->
<div id="reviewOverlay">
  <div class="review-box">

    <div style="background:#1c0a00;padding:20px 28px;display:flex;align-items:center;justify-content:space-between">
      <div>
        <h3 style="font-family:var(--font-display);font-size:1.1rem;color:#fdf6ec;margin-bottom:2px">Write a Review ⭐</h3>
        <p style="font-size:0.8rem;color:rgba(253,246,236,.5)" id="rShopName"></p>
      </div>
      <button onclick="closeReview()" style="background:rgba(255,255,255,.12);border:none;color:#fff;width:32px;height:32px;border-radius:8px;cursor:pointer;font-size:1rem">✕</button>
    </div>

    <div style="padding:28px">
      <input type="hidden" id="rOrderId">
      <input type="hidden" id="rShopId">

      <div style="margin-bottom:20px">
        <p style="font-size:0.85rem;font-weight:600;color:var(--text-secondary);margin-bottom:10px">Your Rating <span style="color:#c47f6a">*</span></p>
        <div class="star-picker" id="starPicker">
          <input type="radio" name="rev_rating" id="r5" value="5"><label for="r5">★</label>
          <input type="radio" name="rev_rating" id="r4" value="4"><label for="r4">★</label>
          <input type="radio" name="rev_rating" id="r3" value="3"><label for="r3">★</label>
          <input type="radio" name="rev_rating" id="r2" value="2"><label for="r2">★</label>
          <input type="radio" name="rev_rating" id="r1" value="1"><label for="r1">★</label>
        </div>
        <p id="ratingText" style="font-size:0.82rem;font-weight:600;color:#d4941e;margin-top:8px;min-height:20px"></p>
      </div>

      <div style="margin-bottom:16px">
        <label style="font-size:0.85rem;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px">Title <span style="font-weight:400;color:var(--text-muted)">(optional)</span></label>
        <input type="text" id="rTitle" class="form-input" placeholder="e.g. Amazing chocolate cake!" maxlength="200">
      </div>

      <div style="margin-bottom:16px">
        <label style="font-size:0.85rem;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px">
          Your Review <span style="color:#c47f6a">*</span>
          <span style="font-weight:400;color:var(--text-muted)">(min 10 chars)</span>
        </label>
        <textarea id="rBody" class="form-textarea" rows="4"
          placeholder="Tell others about the quality, packaging, taste and delivery..."
          maxlength="2000" style="resize:vertical"></textarea>
        <p style="font-size:0.72rem;color:var(--text-muted);margin-top:4px"><span id="rCharCount">0</span>/2000</p>
      </div>

      <div id="rError" style="display:none;background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;padding:10px 14px;border-radius:8px;font-size:0.875rem;margin-bottom:16px"></div>

      <div style="display:flex;gap:12px">
        <button id="rSubmitBtn" onclick="submitReview()"
          style="flex:1;padding:13px;background:#1c0a00;color:#fdf6ec;border:none;border-radius:99px;font-weight:700;font-size:0.95rem;cursor:pointer">
          Submit Review
        </button>
        <button onclick="closeReview()"
          style="padding:13px 20px;background:transparent;border:2px solid #edd9b8;border-radius:99px;font-weight:600;color:#7a6050;cursor:pointer">
          Cancel
        </button>
      </div>
    </div>

  </div>
</div>

<script>
const rLabels = {1:'😞 Terrible',2:'😕 Poor',3:'😐 Okay',4:'😊 Good',5:'🤩 Excellent!'};

function openReview(orderId, shopId, shopName) {
  document.getElementById('rOrderId').value = orderId;
  document.getElementById('rShopId').value  = shopId;
  document.getElementById('rShopName').textContent = shopName;
  document.querySelectorAll('#starPicker input').forEach(i => i.checked = false);
  document.getElementById('rTitle').value   = '';
  document.getElementById('rBody').value    = '';
  document.getElementById('ratingText').textContent = '';
  document.getElementById('rCharCount').textContent = '0';
  document.getElementById('rError').style.display   = 'none';
  document.getElementById('rSubmitBtn').textContent  = 'Submit Review';
  document.getElementById('rSubmitBtn').disabled     = false;
  document.getElementById('reviewOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeReview() {
  document.getElementById('reviewOverlay').classList.remove('open');
  document.body.style.overflow = '';
}

document.getElementById('reviewOverlay').addEventListener('click', function(e){
  if (e.target === this) closeReview();
});

document.querySelectorAll('#starPicker input').forEach(function(inp){
  inp.addEventListener('change', function(){
    document.getElementById('ratingText').textContent = rLabels[this.value] || '';
  });
});

document.getElementById('rBody').addEventListener('input', function(){
  document.getElementById('rCharCount').textContent = this.value.length;
});

async function submitReview() {
  const orderId = document.getElementById('rOrderId').value;
  const shopId  = document.getElementById('rShopId').value;
  const rating  = document.querySelector('#starPicker input:checked')?.value;
  const title   = document.getElementById('rTitle').value.trim();
  const body    = document.getElementById('rBody').value.trim();
  const errEl   = document.getElementById('rError');
  const btn     = document.getElementById('rSubmitBtn');

  errEl.style.display = 'none';

  if (!rating) {
    errEl.textContent = '⭐ Please select a star rating.';
    errEl.style.display = 'block'; return;
  }
  if (body.length < 10) {
    errEl.textContent = '✏️ Please write at least 10 characters.';
    errEl.style.display = 'block'; return;
  }

  btn.textContent = 'Submitting...';
  btn.disabled    = true;

  const fd = new FormData();
  fd.append('order_id', orderId);
  fd.append('shop_id',  shopId);
  fd.append('rating',   rating);
  fd.append('title',    title);
  fd.append('body',     body);

  try {
    const res  = await fetch('<?= SITE_URL ?>/api/review.php', {method:'POST', body:fd});
    const text = await res.text();

    let data;
    try { data = JSON.parse(text); }
    catch(e) {
      errEl.innerHTML = '⚠️ Server error:<br><pre style="font-size:0.7rem;margin-top:6px;white-space:pre-wrap;max-height:150px;overflow:auto">' + text.substring(0,500) + '</pre>';
      errEl.style.display = 'block';
      btn.textContent = 'Submit Review';
      btn.disabled = false;
      return;
    }

    if (data.success) {
      closeReview();
      if (typeof showToast === 'function') showToast(data.message, 'success');
      setTimeout(() => location.reload(), 1500);
    } else {
      errEl.textContent   = '⚠️ ' + (data.message || 'Could not submit.');
      errEl.style.display = 'block';
      btn.textContent = 'Submit Review';
      btn.disabled    = false;
    }
  } catch(e) {
    errEl.textContent   = '⚠️ Network error. Please try again.';
    errEl.style.display = 'block';
    btn.textContent = 'Submit Review';
    btn.disabled    = false;
  }
}
</script>

<?php include '../includes/footer.php'; ?>
