<?php
// Product card partial - expects $product array
if (!isset($product)) return;
$img = $product['primary_image'] ? SITE_URL . '/' . $product['primary_image'] : SITE_URL . '/assets/images/placeholder.svg';
$discount = isset($product['compare_price']) && $product['compare_price'] > 0 ? round((($product['compare_price'] - $product['price']) / $product['compare_price']) * 100) : 0;
$in_wishlist = false;
if (is_logged_in()) {
  $wl = Database::fetch("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?", [$_SESSION['user_id'], $product['id']]);
  $in_wishlist = !empty($wl);
}
?>
<div class="product-card fade-in" data-product-id="<?= $product['id'] ?>">
  <div class="product-card-image">
    <a href="<?= SITE_URL ?>/product.php?id=<?= $product['id'] ?>">
      <img src="<?= $img ?>" alt="<?= sanitize($product['name']) ?>" loading="lazy">
    </a>
    <div class="product-card-badges">
      <?php if ($discount >= 5): ?>
        <span class="badge badge-sale"><?= $discount ?>% OFF</span>
      <?php elseif (strtotime($product['created_at']) > strtotime('-7 days')): ?>
        <span class="badge badge-new">NEW</span>
      <?php elseif ($product['is_bestseller']): ?>
        <span class="badge badge-hot">🔥 HOT</span>
      <?php endif; ?>
      <?php if ($product['is_vegan']): ?>
        <span class="badge badge-vegan">🌿 Vegan</span>
      <?php endif; ?>
      <?php if ($product['is_gluten_free']): ?>
        <span class="badge badge-gf">GF</span>
      <?php endif; ?>
    </div>
    <button
      class="product-card-wishlist <?= $in_wishlist ? 'active' : '' ?>"
      onclick="crumbly.toggleWishlist(<?= $product['id'] ?>, this)"
      title="<?= $in_wishlist ? 'Remove from wishlist' : 'Add to wishlist' ?>"
      aria-label="Wishlist"
    ><?= $in_wishlist ? '❤️' : '🤍' ?></button>
  </div>
  <div class="product-card-body">
    <?php if (isset($product['shop_name'])): ?>
      <a href="<?= SITE_URL ?>/bakery.php?slug=<?= urlencode($product['shop_slug'] ?? '') ?>" class="product-shop-link">
        <?= sanitize($product['shop_name']) ?>
      </a>
    <?php endif; ?>
    <a href="<?= SITE_URL ?>/product.php?id=<?= $product['id'] ?>">
      <h3 class="product-name"><?= sanitize($product['name']) ?></h3>
    </a>
    <?php if (($product['rating'] ?? 0) > 0): ?>
    <div class="product-rating">
      <?= get_star_rating($product['rating']) ?>
      <span class="rating-count">(<?= $product['review_count'] ?? 0 ?>)</span>
    </div>
    <?php endif; ?>
    <div class="product-footer">
      <div class="product-price">
        <span class="price-current"><?= format_price($product['price']) ?></span>
        <?php if ($product['compare_price'] && $product['compare_price'] > $product['price']): ?>
          <span class="price-original"><?= format_price($product['compare_price']) ?></span>
        <?php endif; ?>
      </div>
      <?php if (($product['stock_qty'] ?? 1) > 0 || !($product['track_stock'] ?? 0)): ?>
        <button
          class="btn-add-cart"
          onclick="crumbly.addToCart(<?= $product['id'] ?>, 1)"
          title="Add to cart"
          aria-label="Add to cart"
        >+</button>
      <?php else: ?>
        <span style="font-size:0.75rem;color:var(--rose);font-weight:600">Sold Out</span>
      <?php endif; ?>
    </div>
  </div>
</div>
