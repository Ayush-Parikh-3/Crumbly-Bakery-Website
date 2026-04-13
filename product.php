<?php
require_once 'includes/config.php';
$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('/shop.php');

$product = Database::fetch("SELECT p.*, s.shop_name, s.shop_slug, s.id as shop_id, s.rating as shop_rating, s.total_reviews as shop_reviews, c.name as cat_name, c.slug as cat_slug FROM products p JOIN shops s ON s.id=p.shop_id JOIN categories c ON c.id=p.category_id WHERE p.id=? AND p.is_active=1 AND s.is_active=1", [$id]);
if (!$product) { http_response_code(404); include __DIR__ . '/includes/404.php'; exit; }

$images = Database::fetchAll("SELECT * FROM product_images WHERE product_id=? ORDER BY is_primary DESC, sort_order", [$id]);
$variants = Database::fetchAll("SELECT * FROM product_variants WHERE product_id=? ORDER BY variant_name, price_modifier", [$id]);
$reviews = Database::fetchAll("SELECT r.*, u.full_name FROM reviews r JOIN users u ON u.id=r.user_id WHERE r.shop_id=? ORDER BY r.created_at DESC LIMIT 10", [$product['shop_id']]);

// Related products
$related = Database::fetchAll("SELECT p.*, s.shop_name, s.shop_slug, (SELECT image_path FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) as primary_image FROM products p JOIN shops s ON s.id=p.shop_id WHERE p.category_id=? AND p.id!=? AND p.is_active=1 AND s.is_active=1 ORDER BY RAND() LIMIT 4", [$product['category_id'], $id]);

$primary_img = $images ? (SITE_URL.'/'.$images[0]['image_path']) : (SITE_URL.'/assets/images/placeholder.svg');
$discount = $product['compare_price'] ? round((($product['compare_price']-$product['price'])/$product['compare_price'])*100) : 0;
$in_wishlist = is_logged_in() && Database::fetch("SELECT id FROM wishlist WHERE user_id=? AND product_id=?", [$_SESSION['user_id'], $id]);

$page_title = $product['name'];
$page_meta_desc = substr(strip_tags($product['description'] ?? ''), 0, 160);
include 'includes/header.php';
?>
<div class="section-sm" style="background:var(--cream)">
<div class="container">
  <div class="breadcrumb">
    <a href="<?= SITE_URL ?>">Home</a><span class="breadcrumb-sep">›</span>
    <a href="<?= SITE_URL ?>/shop.php?cat=<?= urlencode($product['cat_slug']) ?>"><?= sanitize($product['cat_name']) ?></a>
    <span class="breadcrumb-sep">›</span>
    <span><?= sanitize($product['name']) ?></span>
  </div>

  <div class="product-detail-grid">
    <!-- Gallery -->
    <div class="product-gallery">
      <div class="gallery-main">
        <img src="<?= $primary_img ?>" alt="<?= sanitize($product['name']) ?>" id="mainImg">
      </div>
      <?php if (count($images) > 1): ?>
      <div class="gallery-thumbs">
        <?php foreach ($images as $i => $img): ?>
        <div class="gallery-thumb <?= $i===0?'active':'' ?>" data-src="<?= SITE_URL.'/'.$img['image_path'] ?>" onclick="document.getElementById('mainImg').src=this.dataset.src;document.querySelectorAll('.gallery-thumb').forEach(t=>t.classList.remove('active'));this.classList.add('active')">
          <img src="<?= SITE_URL.'/'.$img['image_path'] ?>" alt="">
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Info -->
    <div class="product-detail-info">
      <p class="product-detail-brand"><a href="<?= SITE_URL ?>/bakery.php?slug=<?= urlencode($product['shop_slug']) ?>" style="color:var(--rose)"><?= sanitize($product['shop_name']) ?></a></p>
      <h1 class="product-detail-name"><?= sanitize($product['name']) ?></h1>

      <div class="product-detail-rating">
        <?= get_star_rating($product['rating']) ?>
        <span style="font-weight:700"><?= number_format($product['rating'],1) ?></span>
        <span style="color:var(--text-muted)">(<?= $product['review_count'] ?> reviews)</span>
        <span style="color:var(--text-muted)">·</span>
        <span style="color:var(--sage);font-weight:600"><?= $product['total_sold'] ?> sold</span>
      </div>

      <div class="product-detail-price">
        <span class="detail-price-current"><?= format_price($product['price']) ?></span>
        <?php if ($product['compare_price'] > $product['price']): ?>
          <span class="detail-price-original"><?= format_price($product['compare_price']) ?></span>
          <span class="detail-price-save"><?= $discount ?>% OFF</span>
        <?php endif; ?>
      </div>

      <?php if (!empty($variants)): ?>
      <div class="variant-picker" id="variantPicker">
        <?php $v_groups = []; foreach ($variants as $v) $v_groups[$v['variant_name']][] = $v; ?>
        <?php foreach ($v_groups as $vname => $opts): ?>
        <div style="margin-bottom:var(--space-lg)">
          <p class="variant-title"><?= sanitize($vname) ?></p>
          <div class="variant-options">
            <?php foreach ($opts as $opt): ?>
            <button type="button" class="variant-option" data-variant-id="<?= $opt['id'] ?>" data-modifier="<?= $opt['price_modifier'] ?>" onclick="selectVariant(this)">
              <?= sanitize($opt['variant_value']) ?>
              <?php if ($opt['price_modifier'] > 0): ?> (+<?= format_price($opt['price_modifier']) ?>)<?php endif; ?>
            </button>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="qty-selector">
        <span class="qty-label">Quantity:</span>
        <div class="qty-control">
          <button type="button" class="qty-large-btn" data-qty-dec="qtyInput">−</button>
          <input type="number" id="qtyInput" value="1" min="1" max="<?= $product['stock_qty'] ?: 99 ?>" data-max="<?= $product['stock_qty'] ?: 99 ?>" style="width:50px;text-align:center;border:none;font-weight:700;font-size:1.1rem;background:transparent">
          <button type="button" class="qty-large-btn" data-qty-inc="qtyInput">+</button>
        </div>
        <?php if ($product['track_stock']): ?>
          <span style="font-size:0.82rem;color:<?= $product['stock_qty'] <= $product['low_stock_threshold']?'var(--rose)':'var(--sage)' ?>;font-weight:600">
            <?= $product['stock_qty'] ?> in stock
          </span>
        <?php endif; ?>
      </div>

      <div class="product-actions">
        <?php if ($product['stock_qty'] > 0 || !$product['track_stock']): ?>
          <button class="btn btn-primary btn-lg" style="flex:1" id="addCartBtn" onclick="addToCartFromDetail()">
            🛒 Add to Cart
          </button>
        <?php else: ?>
          <button class="btn btn-outline btn-lg" style="flex:1" disabled>Sold Out</button>
        <?php endif; ?>
        <button class="btn btn-icon btn-outline <?= $in_wishlist?'btn-rose':'' ?>" id="wishlistBtn" onclick="crumbly.toggleWishlist(<?= $id ?>, this)" title="Wishlist" style="font-size:1.3rem;padding:12px 16px">
          <?= $in_wishlist ? '❤️' : '🤍' ?>
        </button>
      </div>

      <!-- Dietary badges -->
      <?php if ($product['is_vegan'] || $product['is_gluten_free'] || $product['is_nut_free']): ?>
      <div style="display:flex;gap:var(--space-sm);flex-wrap:wrap;margin-bottom:var(--space-xl)">
        <?php if ($product['is_vegan']): ?><span class="badge badge-vegan">🌿 Vegan</span><?php endif; ?>
        <?php if ($product['is_gluten_free']): ?><span class="badge badge-gf">🌾 Gluten-Free</span><?php endif; ?>
        <?php if ($product['is_nut_free']): ?><span class="badge" style="background:#FEF3C7;color:#92400E">🥜 Nut-Free</span><?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Product meta -->
      <div style="background:var(--cream);border-radius:var(--radius-md);padding:var(--space-lg);margin-bottom:var(--space-xl)">
        <?php if ($product['weight_grams']): ?><p style="font-size:0.875rem;color:var(--text-muted);margin-bottom:6px">⚖️ Weight: <strong><?= $product['weight_grams'] ?>g</strong></p><?php endif; ?>
        <?php if ($product['serving_size']): ?><p style="font-size:0.875rem;color:var(--text-muted);margin-bottom:6px">🍽️ Serves: <strong><?= sanitize($product['serving_size']) ?></strong></p><?php endif; ?>
        <p style="font-size:0.875rem;color:var(--text-muted)">🏪 Sold by: <a href="<?= SITE_URL ?>/bakery.php?slug=<?= urlencode($product['shop_slug']) ?>" style="color:var(--rose);font-weight:600"><?= sanitize($product['shop_name']) ?></a></p>
      </div>

      <!-- Tabs -->
      <div class="product-tabs">
        <div class="tab-nav">
          <button class="tab-btn active" data-tab="tab-desc">Description</button>
          <?php if ($product['ingredients']): ?><button class="tab-btn" data-tab="tab-ing">Ingredients</button><?php endif; ?>
          <?php if ($product['allergen_info']): ?><button class="tab-btn" data-tab="tab-allergy">Allergens</button><?php endif; ?>
          <button class="tab-btn" data-tab="tab-reviews">Reviews (<?= $product['review_count'] ?>)</button>
        </div>
        <div id="tab-desc" class="tab-content active" style="font-size:0.95rem;line-height:1.8;color:var(--text-secondary)">
          <?= nl2br(sanitize($product['description'] ?: 'No description available.')) ?>
        </div>
        <?php if ($product['ingredients']): ?>
        <div id="tab-ing" class="tab-content" style="font-size:0.95rem;line-height:1.8;color:var(--text-secondary)">
          <?= nl2br(sanitize($product['ingredients'])) ?>
        </div>
        <?php endif; ?>
        <?php if ($product['allergen_info']): ?>
        <div id="tab-allergy" class="tab-content">
          <div class="alert alert-warning">⚠️ <?= sanitize($product['allergen_info']) ?></div>
        </div>
        <?php endif; ?>
        <div id="tab-reviews" class="tab-content">
          <?php if (empty($reviews)): ?>
            <p style="color:var(--text-muted);text-align:center;padding:var(--space-xl)">No reviews yet. Be the first to review!</p>
          <?php else: ?>
            <?php foreach ($reviews as $r): ?>
            <div style="padding:var(--space-lg) 0;border-bottom:1px solid var(--border-light)">
              <div style="display:flex;align-items:center;gap:var(--space-md);margin-bottom:var(--space-sm)">
                <div style="width:36px;height:36px;border-radius:50%;background:var(--grad-warm);display:grid;place-items:center;color:var(--cream);font-weight:700;font-size:0.8rem;flex-shrink:0"><?= strtoupper(substr($r['full_name'],0,1)) ?></div>
                <div>
                  <p style="font-weight:600;font-size:0.9rem"><?= sanitize($r['full_name']) ?></p>
                  <div style="display:flex;align-items:center;gap:6px"><?= get_star_rating($r['rating']) ?> <span style="font-size:0.75rem;color:var(--text-muted)"><?= time_ago($r['created_at']) ?></span></div>
                </div>
              </div>
              <?php if ($r['title']): ?><p style="font-weight:600;margin-bottom:4px"><?= sanitize($r['title']) ?></p><?php endif; ?>
              <p style="font-size:0.9rem;color:var(--text-secondary);line-height:1.6"><?= sanitize($r['body'] ?? '') ?></p>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Related Products -->
  <?php if (!empty($related)): ?>
  <div style="margin-top:var(--space-4xl)">
    <h2 style="font-family:var(--font-display);font-size:1.8rem;font-weight:700;margin-bottom:var(--space-xl)">You Might Also Like</h2>
    <div class="products-grid">
      <?php foreach ($related as $product): include 'includes/product_card.php'; endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div>
</div>

<script>
let selectedVariantId = null;
const basePrice = <?= $product['price'] ?>;

function selectVariant(btn) {
  btn.closest('.variant-options').querySelectorAll('.variant-option').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
  selectedVariantId = btn.dataset.variantId;
  const modifier = parseFloat(btn.dataset.modifier) || 0;
  document.querySelector('.detail-price-current').textContent = '₹' + (basePrice + modifier).toFixed(2);
}

function addToCartFromDetail() {
  const qty = parseInt(document.getElementById('qtyInput').value) || 1;
  crumbly.addToCart(<?= $product['id'] ?>, qty, selectedVariantId);
}
</script>
<?php include 'includes/footer.php'; ?>
