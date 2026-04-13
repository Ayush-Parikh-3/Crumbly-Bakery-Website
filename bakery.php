<?php
require_once 'includes/config.php';
$slug = sanitize($_GET['slug'] ?? '');
if (!$slug) redirect('/sellers.php');

$shop = Database::fetch(
    "SELECT s.*, u.full_name as owner_name FROM shops s JOIN users u ON u.id=s.user_id WHERE s.shop_slug=? AND s.is_active=1",
    [$slug]
);
if (!$shop) { http_response_code(404); echo '<h1>Bakery not found</h1>'; exit; }

$cat_f  = sanitize($_GET['cat']  ?? '');
$sort_f = sanitize($_GET['sort'] ?? 'popular');
$page   = max(1,(int)($_GET['page'] ?? 1));
$per_page = PRODUCTS_PER_PAGE;
$offset   = ($page-1)*$per_page;

$where  = ["p.shop_id={$shop['id']}", "p.is_active=1"];
$params = [];
if ($cat_f) { $where[] = "c.slug=?"; $params[] = $cat_f; }
$order_sql = match($sort_f) {'newest'=>'p.created_at DESC','price_asc'=>'p.price ASC','price_desc'=>'p.price DESC',default=>'p.total_sold DESC'};
$ws = 'WHERE '.implode(' AND ',$where);

$total    = (int)Database::fetch("SELECT COUNT(*) as cnt FROM products p LEFT JOIN categories c ON c.id=p.category_id $ws",$params)['cnt'];
$products = Database::fetchAll("SELECT p.*, s.shop_name, s.shop_slug, c.name as cat_name, (SELECT image_path FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) as primary_image FROM products p JOIN shops s ON s.id=p.shop_id LEFT JOIN categories c ON c.id=p.category_id $ws ORDER BY $order_sql LIMIT $per_page OFFSET $offset",$params);
$total_pages = ceil($total/$per_page);

$shop_cats = Database::fetchAll("SELECT DISTINCT c.id,c.name,c.slug,c.icon,COUNT(p.id) as cnt FROM products p JOIN categories c ON c.id=p.category_id WHERE p.shop_id=? AND p.is_active=1 GROUP BY c.id ORDER BY c.sort_order",[$shop['id']]);

// Reviews
$reviews = Database::fetchAll("SELECT r.*, u.full_name FROM reviews r JOIN users u ON u.id=r.user_id WHERE r.shop_id=? ORDER BY r.created_at DESC LIMIT 20",[$shop['id']]);
$rating_summary = Database::fetch("SELECT COUNT(*) as total, ROUND(AVG(rating),1) as avg, SUM(rating=5) as five, SUM(rating=4) as four, SUM(rating=3) as three, SUM(rating=2) as two, SUM(rating=1) as one FROM reviews WHERE shop_id=?",[$shop['id']]);

// Has current user reviewed this shop?
$user_reviewed = false;
if (is_logged_in() && !is_seller()) {
    $ur = Database::fetch("SELECT id FROM reviews WHERE user_id=? AND shop_id=?",[$_SESSION['user_id'],$shop['id']]);
    $user_reviewed = !empty($ur);
}

$banner_img = $shop['banner'] ? (SITE_URL.'/'.$shop['banner']) : null;
$logo_img   = $shop['logo']   ? (SITE_URL.'/'.$shop['logo'])   : null;
$page_title = $shop['shop_name'];
include 'includes/header.php';
?>

<!-- SHOP HERO -->
<div style="background:var(--grad-hero);position:relative;overflow:hidden">
  <?php if($banner_img): ?><img src="<?=$banner_img?>" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.2"><?php endif; ?>
  <div style="position:absolute;inset:0;opacity:.04;background-image:radial-gradient(circle,#fff 1px,transparent 1px);background-size:32px 32px"></div>
  <div class="container" style="position:relative;z-index:2;padding:var(--space-3xl) var(--space-xl)">
    <div style="display:flex;align-items:flex-end;gap:var(--space-xl);flex-wrap:wrap">
      <div style="width:80px;height:80px;border-radius:var(--radius-lg);border:3px solid rgba(255,255,255,.3);overflow:hidden;flex-shrink:0;background:rgba(255,255,255,.1)">
        <?php if($logo_img): ?><img src="<?=$logo_img?>" alt="" style="width:100%;height:100%;object-fit:cover"><?php else: ?><div style="width:100%;height:100%;display:grid;place-items:center;font-size:2rem">🥐</div><?php endif; ?>
      </div>
      <div style="flex:1">
        <h1 style="font-family:var(--font-display);font-size:2.2rem;font-weight:700;color:var(--cream);margin-bottom:8px"><?=sanitize($shop['shop_name'])?></h1>
        <?php if($shop['city']): ?><p style="color:rgba(253,246,236,.7);margin-bottom:var(--space-md)">📍 <?=sanitize($shop['city'])?><?=$shop['state']?', '.sanitize($shop['state']):''?></p><?php endif; ?>
        <div style="display:flex;gap:var(--space-xl);flex-wrap:wrap">
          <div><p style="font-weight:700;color:var(--cream);font-size:1.2rem"><?=number_format($shop['rating'],1)?> ⭐</p><p style="font-size:.75rem;color:rgba(253,246,236,.6)"><?=$shop['total_reviews']?> Reviews</p></div>
          <div><p style="font-weight:700;color:var(--cream);font-size:1.2rem"><?=$total?>+</p><p style="font-size:.75rem;color:rgba(253,246,236,.6)">Products</p></div>
          <div><p style="font-weight:700;color:var(--cream);font-size:1.2rem"><?=number_format($shop['total_sales'])?></p><p style="font-size:.75rem;color:rgba(253,246,236,.6)">Orders</p></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="section-sm" style="background:var(--cream)">
<div class="container">

  <?php if($shop['description']): ?>
  <div style="background:var(--white);border-radius:var(--radius-lg);padding:var(--space-xl);margin-bottom:var(--space-xl);border:1px solid var(--border-light)">
    <p style="color:var(--text-secondary);line-height:1.7;font-size:.95rem"><?=nl2br(sanitize($shop['description']))?></p>
  </div>
  <?php endif; ?>

  <!-- Category tabs -->
  <?php if(!empty($shop_cats)): ?>
  <div style="overflow-x:auto;margin-bottom:var(--space-xl)"><div style="display:flex;gap:12px;padding-bottom:4px">
    <a href="?slug=<?=urlencode($slug)?>" class="cat-pill <?=!$cat_f?'active':''?>"><span class="cat-pill-icon">🛍️</span><span class="cat-pill-name">All</span></a>
    <?php foreach($shop_cats as $sc): ?>
    <a href="?slug=<?=urlencode($slug)?>&cat=<?=urlencode($sc['slug'])?>" class="cat-pill <?=$cat_f===$sc['slug']?'active':''?>">
      <span class="cat-pill-icon"><?=$sc['icon']?></span><span class="cat-pill-name"><?=sanitize($sc['name'])?></span>
    </a>
    <?php endforeach; ?></div></div>
  <?php endif; ?>

  <!-- Products -->
  <?php if(empty($products)): ?>
    <div style="text-align:center;padding:60px;background:var(--white);border-radius:var(--radius-lg)"><span style="font-size:3rem">🧁</span><h3 style="font-family:var(--font-display);margin:16px 0">No products yet</h3></div>
  <?php else: ?>
  <div class="products-grid">
    <?php foreach($products as $product): include 'includes/product_card.php'; endforeach; ?>
  </div>
  <?php if($total_pages>1): ?><div class="pagination"><?php for($i=1;$i<=$total_pages;$i++): ?><a href="?slug=<?=urlencode($slug)?>&page=<?=$i?>" class="page-btn <?=$i===$page?'active':''?>"><?=$i?></a><?php endfor; ?></div><?php endif; ?>
  <?php endif; ?>

  <!-- ===== REVIEWS SECTION ===== -->
  <div style="margin-top:var(--space-3xl)">

    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:24px">
      <h2 style="font-family:var(--font-display);font-size:1.8rem;font-weight:700">Customer Reviews ⭐</h2>

      <?php if(is_logged_in() && !is_seller()): ?>
        <?php if($user_reviewed): ?>
          <span style="background:#d1fae5;color:#065f46;padding:8px 18px;border-radius:99px;font-size:.85rem;font-weight:700">✅ You've reviewed this shop</span>
        <?php else: ?>
          <button onclick="document.getElementById('reviewBox').style.display='block';window.scrollTo({top:document.getElementById('reviewBox').offsetTop-80,behavior:'smooth'})"
            style="padding:10px 24px;background:#1c0a00;color:#fdf6ec;border:none;border-radius:99px;font-weight:700;font-size:.9rem;cursor:pointer">
            ⭐ Write a Review
          </button>
        <?php endif; ?>
      <?php elseif(!is_logged_in()): ?>
        <a href="<?=SITE_URL?>/auth/login.php?next=<?=urlencode($_SERVER['REQUEST_URI'])?>"
           style="padding:10px 24px;background:#1c0a00;color:#fdf6ec;border-radius:99px;font-weight:700;font-size:.9rem;text-decoration:none">
          Login to Review
        </a>
      <?php endif; ?>
    </div>

    <!-- Rating summary -->
    <?php if($rating_summary && $rating_summary['total'] > 0): ?>
    <div style="background:#fff;border:1px solid var(--border-light);border-radius:var(--radius-lg);padding:24px;margin-bottom:24px;display:grid;grid-template-columns:auto 1fr;gap:32px;align-items:center">
      <div style="text-align:center;min-width:90px">
        <div style="font-size:3.5rem;font-weight:900;font-family:var(--font-display);line-height:1;color:#1c0a00"><?=$rating_summary['avg']?></div>
        <div style="color:#d4941e;font-size:1.3rem;margin:6px 0"><?=str_repeat('★',round($rating_summary['avg']))?><?=str_repeat('☆',5-round($rating_summary['avg']))?></div>
        <div style="font-size:.78rem;color:var(--text-muted)"><?=$rating_summary['total']?> reviews</div>
      </div>
      <div style="display:flex;flex-direction:column;gap:7px">
        <?php foreach([5=>'five',4=>'four',3=>'three',2=>'two',1=>'one'] as $star=>$key):
          $cnt = (int)($rating_summary[$key]??0);
          $pct = $rating_summary['total']>0 ? round(($cnt/$rating_summary['total'])*100) : 0;
        ?>
        <div style="display:flex;align-items:center;gap:10px">
          <span style="font-size:.8rem;font-weight:600;color:var(--text-muted);min-width:12px"><?=$star?></span>
          <span style="color:#d4941e;font-size:.85rem">★</span>
          <div style="flex:1;height:8px;background:var(--border-light);border-radius:4px;overflow:hidden">
            <div style="height:100%;width:<?=$pct?>%;background:#d4941e;border-radius:4px"></div>
          </div>
          <span style="font-size:.75rem;color:var(--text-muted);min-width:24px;text-align:right"><?=$cnt?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- WRITE REVIEW FORM (inline, always visible when logged in) -->
    <?php if(is_logged_in() && !is_seller() && !$user_reviewed): ?>
    <div id="reviewBox" style="background:#fff;border:2px solid #d4941e;border-radius:20px;padding:28px;margin-bottom:28px">
      <h3 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:20px">Share Your Experience 🎂</h3>

      <!-- Stars -->
      <p style="font-size:.85rem;font-weight:600;color:var(--text-secondary);margin-bottom:8px">Your Rating *</p>
      <div style="display:flex;gap:8px;margin-bottom:6px" id="starRow">
        <?php for($s=1;$s<=5;$s++): ?>
        <span data-val="<?=$s?>" onclick="setStar(<?=$s?>)"
          style="font-size:2.2rem;cursor:pointer;color:#edd9b8;transition:color .1s,transform .15s;line-height:1"
          onmouseover="hoverStar(<?=$s?>)" onmouseout="hoverStar(0)">★</span>
        <?php endfor; ?>
      </div>
      <p id="starLabel" style="font-size:.82rem;font-weight:600;color:#d4941e;min-height:20px;margin-bottom:16px"></p>
      <input type="hidden" id="shopReviewRating" value="0">

      <!-- Title -->
      <div style="margin-bottom:14px">
        <label style="font-size:.85rem;font-weight:600;display:block;margin-bottom:6px">Title <span style="font-weight:400;color:var(--text-muted)">(optional)</span></label>
        <input type="text" id="shopReviewTitle" class="form-input" placeholder="e.g. Best croissants in the city!" maxlength="200">
      </div>

      <!-- Body -->
      <div style="margin-bottom:14px">
        <label style="font-size:.85rem;font-weight:600;display:block;margin-bottom:6px">Review * <span style="font-weight:400;color:var(--text-muted)">(min 5 chars)</span></label>
        <textarea id="shopReviewBody" class="form-textarea" rows="4"
          placeholder="Tell others about the taste, quality, packaging, delivery..." maxlength="2000"></textarea>
      </div>

      <!-- Error / Success -->
      <div id="shopReviewError"   style="display:none;background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;padding:10px 14px;border-radius:8px;font-size:.875rem;margin-bottom:14px"></div>
      <div id="shopReviewSuccess" style="display:none;background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;padding:10px 14px;border-radius:8px;font-size:.875rem;margin-bottom:14px"></div>

      <button onclick="submitShopReview(<?=(int)$shop['id']?>)"
        id="shopReviewBtn"
        style="padding:12px 32px;background:#1c0a00;color:#fdf6ec;border:none;border-radius:99px;font-weight:700;font-size:.95rem;cursor:pointer">
        Submit Review
      </button>
    </div>
    <?php endif; ?>

    <!-- Reviews list -->
    <?php if(empty($reviews)): ?>
      <div style="text-align:center;padding:40px;background:#fff;border-radius:var(--radius-lg);border:1px solid var(--border-light)">
        <span style="font-size:2.5rem">⭐</span>
        <p style="font-family:var(--font-display);font-size:1.1rem;margin:12px 0">No reviews yet</p>
        <p style="color:var(--text-muted);font-size:.875rem">Be the first to review this bakery!</p>
      </div>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
      <?php foreach($reviews as $r): ?>
      <div class="card" style="padding:20px">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
          <div style="width:40px;height:40px;border-radius:50%;background:var(--grad-warm);display:grid;place-items:center;color:#fdf6ec;font-weight:700;font-size:.85rem;flex-shrink:0">
            <?=strtoupper(substr($r['full_name'],0,1))?>
          </div>
          <div>
            <p style="font-weight:700;font-size:.9rem"><?=sanitize($r['full_name'])?></p>
            <div style="display:flex;align-items:center;gap:6px">
              <span style="color:#d4941e;letter-spacing:-1px"><?=str_repeat('★',$r['rating'])?><span style="color:#edd9b8"><?=str_repeat('★',5-$r['rating'])?></span></span>
              <span style="font-size:.72rem;color:var(--text-muted)"><?=time_ago($r['created_at'])?></span>
            </div>
          </div>
          <span style="margin-left:auto;font-size:.68rem;background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:99px;font-weight:700;white-space:nowrap">✓ Verified</span>
        </div>
        <?php if(!empty($r['title'])): ?><p style="font-weight:700;font-size:.9rem;margin-bottom:6px"><?=sanitize($r['title'])?></p><?php endif; ?>
        <p style="font-size:.875rem;color:var(--text-secondary);line-height:1.7"><?=sanitize($r['body']??'')?></p>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

</div>
</div>

<script>
let selectedStar = 0;

function setStar(val) {
  selectedStar = val;
  document.getElementById('shopReviewRating').value = val;
  const labels = {1:'😞 Terrible',2:'😕 Poor',3:'😐 Okay',4:'😊 Good',5:'🤩 Excellent!'};
  document.getElementById('starLabel').textContent = labels[val] || '';
  colorStars(val);
}

function hoverStar(val) {
  colorStars(val || selectedStar);
}

function colorStars(val) {
  document.querySelectorAll('#starRow span').forEach(function(s, i) {
    s.style.color = (i < val) ? '#d4941e' : '#edd9b8';
    s.style.transform = (i < val) ? 'scale(1.1)' : 'scale(1)';
  });
}

async function submitShopReview(shopId) {
  const rating = parseInt(document.getElementById('shopReviewRating').value);
  const title  = document.getElementById('shopReviewTitle').value.trim();
  const body   = document.getElementById('shopReviewBody').value.trim();
  const errEl  = document.getElementById('shopReviewError');
  const sucEl  = document.getElementById('shopReviewSuccess');
  const btn    = document.getElementById('shopReviewBtn');

  errEl.style.display = 'none';
  sucEl.style.display = 'none';

  if (!rating || rating < 1) {
    errEl.textContent = '⭐ Please select a star rating.';
    errEl.style.display = 'block'; return;
  }
  if (body.length < 5) {
    errEl.textContent = '✏️ Please write at least 5 characters.';
    errEl.style.display = 'block'; return;
  }

  btn.textContent = 'Submitting...';
  btn.disabled = true;

  const fd = new FormData();
  fd.append('shop_id', shopId);
  fd.append('rating',  rating);
  fd.append('title',   title);
  fd.append('body',    body);

  try {
    const res  = await fetch('<?=SITE_URL?>/api/review.php', {method:'POST', body:fd});
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); }
    catch(e) {
      errEl.innerHTML = 'Server error: <pre style="font-size:.7rem;margin-top:6px;white-space:pre-wrap">' + text.substring(0,400) + '</pre>';
      errEl.style.display = 'block';
      btn.textContent = 'Submit Review'; btn.disabled = false; return;
    }

    if (data.success) {
      sucEl.textContent = '🎉 ' + data.message;
      sucEl.style.display = 'block';
      document.getElementById('shopReviewTitle').value = '';
      document.getElementById('shopReviewBody').value  = '';
      selectedStar = 0; colorStars(0);
      document.getElementById('starLabel').textContent = '';
      btn.textContent = 'Review Submitted ✅';
      setTimeout(() => location.reload(), 1500);
    } else {
      errEl.textContent = '⚠️ ' + (data.message || 'Could not submit.');
      errEl.style.display = 'block';
      btn.textContent = 'Submit Review'; btn.disabled = false;
    }
  } catch(e) {
    errEl.textContent = '⚠️ Network error. Please try again.';
    errEl.style.display = 'block';
    btn.textContent = 'Submit Review'; btn.disabled = false;
  }
}
</script>

<?php include 'includes/footer.php'; ?>
