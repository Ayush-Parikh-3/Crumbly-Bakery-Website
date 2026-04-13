<?php
require_once 'includes/config.php';
$page_title = 'Fresh Baked. Delivered Fast.';

// Fetch featured products (from real sellers, no demo)
$featured_products = Database::fetchAll("
  SELECT p.*, s.shop_name, s.shop_slug,
    (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
    ROUND(((p.compare_price - p.price) / p.compare_price) * 100) as discount_pct
  FROM products p
  JOIN shops s ON s.id = p.shop_id AND s.is_active = 1
  WHERE p.is_active = 1 AND p.is_featured = 1
  ORDER BY p.total_sold DESC LIMIT 8
");

// Best sellers
$bestsellers = Database::fetchAll("
  SELECT p.*, s.shop_name, s.shop_slug,
    (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
  FROM products p
  JOIN shops s ON s.id = p.shop_id AND s.is_active = 1
  WHERE p.is_active = 1 AND p.is_bestseller = 1
  ORDER BY p.total_sold DESC LIMIT 8
");

// New arrivals
$new_arrivals = Database::fetchAll("
  SELECT p.*, s.shop_name, s.shop_slug,
    (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
  FROM products p
  JOIN shops s ON s.id = p.shop_id AND s.is_active = 1
  WHERE p.is_active = 1
  ORDER BY p.created_at DESC LIMIT 8
");

// Top shops
$top_shops = Database::fetchAll("
  SELECT s.*, u.full_name as owner_name,
    COUNT(DISTINCT p.id) as product_count
  FROM shops s
  JOIN users u ON u.id = s.user_id
  LEFT JOIN products p ON p.shop_id = s.id AND p.is_active = 1
  WHERE s.is_active = 1
  GROUP BY s.id
  ORDER BY s.rating DESC, s.total_sales DESC
  LIMIT 6
");

// Stats
$stats = Database::fetch("
  SELECT
    (SELECT COUNT(*) FROM products WHERE is_active = 1) as product_count,
    (SELECT COUNT(*) FROM shops WHERE is_active = 1) as shop_count,
    (SELECT COUNT(*) FROM users WHERE role = 'buyer') as buyer_count,
    (SELECT COUNT(*) FROM orders WHERE order_status = 'delivered') as delivered_count
");

$categories = Database::fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order");

include 'includes/header.php';
?>

<!-- HERO SECTION -->
<section class="hero">
  <div class="hero-bg-pattern"></div>
  <div class="hero-orbs">
    <div class="hero-orb hero-orb-1"></div>
    <div class="hero-orb hero-orb-2"></div>
  </div>
  <div class="container" style="position:relative;z-index:2;width:100%;padding:var(--space-4xl) var(--space-xl)">
    <div class="hero-content">
      <div class="hero-tag">
        <div class="hero-tag-dot"></div>
        Fresh • Artisan • Delivered
      </div>
      <h1 class="hero-title">
        Life is Short,<br>
        Eat <em>Great</em><br>
        Baked Goods
      </h1>
      <p class="hero-desc">Discover handcrafted cakes, breads, pastries and more from local artisan bakeries. Real bakers, real ingredients, real love.</p>
      <div class="hero-actions">
        <a href="<?= SITE_URL ?>/shop.php" class="btn btn-amber btn-lg">
          Shop Now →
        </a>
        <a href="<?= SITE_URL ?>/sellers.php" class="btn btn-outline btn-lg" style="border-color:rgba(253,246,236,0.3);color:var(--cream)">
          Browse Bakeries
        </a>
      </div>
      <div class="hero-stats">
        <div class="hero-stat">
          <div class="hero-stat-num"><?= number_format($stats['shop_count'] ?? 0) ?>+</div>
          <div class="hero-stat-label">Bakeries</div>
        </div>
        <div class="hero-stat">
          <div class="hero-stat-num"><?= number_format($stats['product_count'] ?? 0) ?>+</div>
          <div class="hero-stat-label">Products</div>
        </div>
        <div class="hero-stat">
          <div class="hero-stat-num"><?= number_format($stats['delivered_count'] ?? 0) ?>+</div>
          <div class="hero-stat-label">Delivered</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- TRUST BADGES -->
<div style="background:var(--white);border-bottom:1px solid var(--border-light)">
  <div class="container">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0;padding:var(--space-lg) 0">
      <?php
      $badges = [
        ['🚀', 'Fast Delivery', '2-4 hours to your door'],
        ['🧁', 'Fresh Daily', 'Baked fresh every morning'],
        ['🔒', 'Secure Payment', '100% safe checkout'],
        ['⭐', 'Verified Sellers', 'Quality guaranteed'],
      ];
      foreach ($badges as $i => $b): ?>
      <div style="display:flex;align-items:center;gap:var(--space-md);padding:var(--space-lg) var(--space-xl);<?= $i < 3 ? 'border-right:1px solid var(--border-light)' : '' ?>">
        <span style="font-size:1.8rem"><?= $b[0] ?></span>
        <div>
          <p style="font-weight:700;font-size:0.95rem;color:var(--text-primary)"><?= $b[1] ?></p>
          <p style="font-size:0.8rem;color:var(--text-muted)"><?= $b[2] ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- CATEGORIES -->
<section class="section-sm" style="background:var(--cream)">
  <div class="container">
    <div class="section-header" style="margin-bottom:var(--space-xl)">
      <span class="section-tag">Browse by Category</span>
      <h2 class="section-title">What are you <em>craving?</em></h2>
    </div>
    <div class="category-scroller">
      <div class="category-pills">
        <?php foreach ($categories as $cat): ?>
        <a href="<?= SITE_URL ?>/shop.php?cat=<?= urlencode($cat['slug']) ?>" class="cat-pill">
          <span class="cat-pill-icon"><?= $cat['icon'] ?></span>
          <span class="cat-pill-name"><?= sanitize($cat['name']) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- FEATURED PRODUCTS -->
<?php if (!empty($featured_products)): ?>
<section class="section">
  <div class="container">
    <div class="section-header" style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:var(--space-md)">
      <div>
        <span class="section-tag">Handpicked For You</span>
        <h2 class="section-title">Featured <em>Delights</em></h2>
      </div>
      <a href="<?= SITE_URL ?>/shop.php?featured=1" class="btn btn-outline btn-sm">View All →</a>
    </div>
    <div class="products-grid">
      <?php foreach ($featured_products as $product): include 'includes/product_card.php'; endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- BANNER STRIP -->
<div style="background:var(--grad-warm);padding:var(--space-3xl) 0;overflow:hidden;position:relative">
  <div style="position:absolute;inset:0;opacity:0.04;background-image:radial-gradient(circle,#fff 1px,transparent 1px);background-size:32px 32px"></div>
  <div class="container" style="position:relative;z-index:2">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-3xl);align-items:center">
      <div>
        <span style="display:inline-block;background:rgba(212,148,30,0.2);border:1px solid rgba(212,148,30,0.4);color:var(--amber-light);font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;padding:4px 14px;border-radius:99px;margin-bottom:var(--space-lg)">For Bakers 🥐</span>
        <h2 style="font-family:var(--font-display);font-size:clamp(1.8rem,3.5vw,2.8rem);font-weight:700;color:var(--cream);line-height:1.15;margin-bottom:var(--space-lg)">
          Turn Your Passion<br>Into a <span style="background:var(--grad-gold);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text">Thriving Business</span>
        </h2>
        <p style="color:rgba(253,246,236,0.7);font-size:1rem;line-height:1.7;margin-bottom:var(--space-2xl);max-width:400px">Join hundreds of artisan bakers already selling on Crumbly. Set up your shop in minutes, manage everything in one place.</p>
        <div style="display:flex;gap:var(--space-md);flex-wrap:wrap">
          <a href="<?= SITE_URL ?>/auth/signup.php?role=seller" class="btn btn-amber">Start Selling Free →</a>
          <a href="#" class="btn btn-ghost" style="color:rgba(253,246,236,0.7)">Learn More</a>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-lg)">
        <?php
        $features = [
          ['📊', 'Smart Analytics', 'Track sales, views, and trends'],
          ['💳', 'Fast Payouts', 'Get paid within 24 hours'],
          ['🎯', 'Marketing Tools', 'Coupons, promos & more'],
          ['📦', 'Order Management', 'Real-time order tracking'],
        ];
        foreach ($features as $f): ?>
        <div style="background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-lg);padding:var(--space-xl)">
          <span style="font-size:1.8rem;display:block;margin-bottom:var(--space-sm)"><?= $f[0] ?></span>
          <p style="font-weight:700;color:var(--cream);margin-bottom:4px;font-size:0.95rem"><?= $f[1] ?></p>
          <p style="font-size:0.8rem;color:rgba(253,246,236,0.55)"><?= $f[2] ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- BESTSELLERS -->
<?php if (!empty($bestsellers)): ?>
<section class="section" style="background:var(--white)">
  <div class="container">
    <div class="section-header" style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:var(--space-md)">
      <div>
        <span class="section-tag">🔥 Hot Right Now</span>
        <h2 class="section-title">Best <em>Sellers</em></h2>
      </div>
      <a href="<?= SITE_URL ?>/shop.php?sort=popular" class="btn btn-outline btn-sm">View All →</a>
    </div>
    <div class="products-grid">
      <?php foreach ($bestsellers as $product): include 'includes/product_card.php'; endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- TOP BAKERIES -->
<?php if (!empty($top_shops)): ?>
<section class="section" style="background:var(--cream)">
  <div class="container">
    <div class="section-header" style="text-align:center">
      <span class="section-tag">🏪 Discover</span>
      <h2 class="section-title">Top <em>Bakeries</em> Near You</h2>
      <p class="section-desc">Handpicked artisan bakers with the highest ratings and freshest products.</p>
    </div>
    <div class="shops-grid" style="margin-top:var(--space-2xl)">
      <?php foreach ($top_shops as $shop): ?>
      <a href="<?= SITE_URL ?>/bakery.php?slug=<?= urlencode($shop['shop_slug']) ?>" class="shop-card" style="display:block;text-decoration:none;color:inherit">
        <div class="shop-card-banner">
          <?php if ($shop['banner']): ?>
            <img src="<?= SITE_URL . '/' . sanitize($shop['banner']) ?>" alt="">
          <?php else: ?>
            <div style="width:100%;height:100%;background:var(--grad-warm);opacity:0.6"></div>
          <?php endif; ?>
          <?php if ($shop['is_verified']): ?>
            <span class="shop-verified-badge">✓ Verified</span>
          <?php endif; ?>
          <div class="shop-card-logo">
            <?php if ($shop['logo']): ?>
              <img src="<?= SITE_URL . '/' . sanitize($shop['logo']) ?>" alt="<?= sanitize($shop['shop_name']) ?>">
            <?php else: ?>
              <div style="width:100%;height:100%;display:grid;place-items:center;background:var(--cream-dark);font-size:1.5rem">🥐</div>
            <?php endif; ?>
          </div>
        </div>
        <div class="shop-card-body">
          <h3 class="shop-card-name"><?= sanitize($shop['shop_name']) ?></h3>
          <p class="shop-card-city">
            <?= sanitize($shop['city'] ?? '') ?><?= $shop['city'] && $shop['state'] ? ', ' : '' ?><?= sanitize($shop['state'] ?? '') ?>
          </p>
          <div style="display:flex;align-items:center;gap:6px;margin-bottom:var(--space-md)">
            <?= get_star_rating($shop['rating'] ?? 0) ?>
            <span style="font-size:0.85rem;font-weight:600"><?= number_format($shop['rating'] ?? 0, 1) ?></span>
            <span style="font-size:0.75rem;color:var(--text-muted)">(<?= $shop['total_reviews'] ?> reviews)</span>
          </div>
          <div class="shop-stats">
            <div>
              <div class="shop-stat-val"><?= $shop['product_count'] ?>+</div>
              <div class="shop-stat-key">Products</div>
            </div>
            <div>
              <div class="shop-stat-val"><?= number_format($shop['total_sales']) ?></div>
              <div class="shop-stat-key">Orders</div>
            </div>
            <?php if ($shop['delivery_fee'] == 0): ?>
            <div>
              <div class="shop-stat-val" style="color:var(--sage)">Free</div>
              <div class="shop-stat-key">Delivery</div>
            </div>
            <?php else: ?>
            <div>
              <div class="shop-stat-val"><?= format_price($shop['delivery_fee']) ?></div>
              <div class="shop-stat-key">Delivery</div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <div style="text-align:center;margin-top:var(--space-2xl)">
      <a href="<?= SITE_URL ?>/sellers.php" class="btn btn-primary btn-lg">View All Bakeries</a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- NEW ARRIVALS -->
<?php if (!empty($new_arrivals)): ?>
<section class="section" style="background:var(--white)">
  <div class="container">
    <div class="section-header" style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:var(--space-md)">
      <div>
        <span class="section-tag">✨ Just Added</span>
        <h2 class="section-title">New <em>Arrivals</em></h2>
      </div>
      <a href="<?= SITE_URL ?>/shop.php?sort=newest" class="btn btn-outline btn-sm">View All →</a>
    </div>
    <div class="products-grid">
      <?php foreach ($new_arrivals as $product): include 'includes/product_card.php'; endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- TESTIMONIALS -->
<section class="section" style="background:var(--cream)">
  <div class="container">
    <div class="section-header centered">
      <span class="section-tag">💬 Reviews</span>
      <h2 class="section-title">What Our <em>Customers</em> Say</h2>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:var(--space-xl);margin-top:var(--space-2xl)">
      <?php
      $testimonials = [
        ['⭐⭐⭐⭐⭐', 'The sourdough from Bakehouse Co. is absolutely divine. Fresh, crusty, and delivered right to my door!', 'Priya M.', 'Regular Customer'],
        ['⭐⭐⭐⭐⭐', 'Ordered a custom birthday cake — the baker nailed it perfectly. Everyone at the party was amazed!', 'Rahul K.', 'Verified Buyer'],
        ['⭐⭐⭐⭐⭐', 'Best croissants I\'ve ever had in the city. Crumbly makes it so easy to discover hidden gem bakeries.', 'Sneha R.', 'Food Blogger'],
      ];
      foreach ($testimonials as $t): ?>
      <div class="card" style="padding:var(--space-xl)">
        <div style="font-size:1rem;margin-bottom:var(--space-md)"><?= $t[0] ?></div>
        <p style="font-size:0.95rem;color:var(--text-secondary);line-height:1.7;margin-bottom:var(--space-lg);font-style:italic">"<?= $t[1] ?>"</p>
        <div style="display:flex;align-items:center;gap:var(--space-md)">
          <div style="width:40px;height:40px;border-radius:50%;background:var(--grad-warm);display:grid;place-items:center;color:var(--cream);font-weight:700"><?= substr($t[2], 0, 1) ?></div>
          <div>
            <p style="font-weight:700;font-size:0.9rem"><?= $t[2] ?></p>
            <p style="font-size:0.75rem;color:var(--text-muted)"><?= $t[3] ?></p>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- APP DOWNLOAD CTA -->
<section style="background:var(--espresso);padding:var(--space-3xl) 0">
  <div class="container">
    <div style="text-align:center;max-width:600px;margin:0 auto">
      <span style="font-size:3rem;display:block;margin-bottom:var(--space-lg)">📱</span>
      <h2 style="font-family:var(--font-display);font-size:2.2rem;font-weight:700;color:var(--cream);margin-bottom:var(--space-md)">Get Fresh Deals Every Day</h2>
      <p style="color:rgba(253,246,236,0.65);font-size:1rem;line-height:1.7;margin-bottom:var(--space-2xl)">Sign up for free and start discovering artisan baked goods from local bakeries in your neighbourhood.</p>
      <a href="<?= SITE_URL ?>/auth/signup.php" class="btn btn-amber btn-xl">Create Free Account →</a>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
