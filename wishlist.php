<?php
// wishlist.php
require_once 'includes/config.php';
$user = require_buyer();

$products = Database::fetchAll("SELECT p.*, s.shop_name, s.shop_slug, (SELECT image_path FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) as primary_image FROM wishlist w JOIN products p ON p.id=w.product_id JOIN shops s ON s.id=p.shop_id WHERE w.user_id=? AND p.is_active=1 AND s.is_active=1 ORDER BY w.created_at DESC", [$user['id']]);

$page_title = 'My Wishlist';
include 'includes/header.php';
?>
<div class="section" style="background:var(--cream)">
<div class="container">
  <h1 style="font-family:var(--font-display);font-size:2rem;font-weight:700;margin-bottom:var(--space-2xl)">My Wishlist ❤️</h1>
  <?php if (empty($products)): ?>
    <div style="text-align:center;padding:var(--space-4xl);background:var(--white);border-radius:var(--radius-lg)">
      <span style="font-size:3rem">🤍</span>
      <h3 style="font-family:var(--font-display);margin:var(--space-md) 0">Your wishlist is empty</h3>
      <p style="color:var(--text-muted);margin-bottom:var(--space-xl)">Save products you love to come back to them later.</p>
      <a href="<?= SITE_URL ?>/shop.php" class="btn btn-amber">Explore Products →</a>
    </div>
  <?php else: ?>
    <div class="products-grid">
      <?php foreach ($products as $product): include 'includes/product_card.php'; endforeach; ?>
    </div>
  <?php endif; ?>
</div>
</div>
<?php include 'includes/footer.php'; ?>
