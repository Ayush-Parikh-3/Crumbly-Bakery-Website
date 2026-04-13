<?php
// shop.php - Product listing / search page
require_once 'includes/config.php';

$q = sanitize($_GET['q'] ?? '');
$cat = sanitize($_GET['cat'] ?? '');
$sort = sanitize($_GET['sort'] ?? 'popular');
$min_price = (float)($_GET['min'] ?? 0);
$max_price = (float)($_GET['max'] ?? 10000);
$vegan = isset($_GET['vegan']) ? 1 : null;
$gf = isset($_GET['gf']) ? 1 : null;
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = PRODUCTS_PER_PAGE;
$offset = ($page-1)*$per_page;

$where = ["p.is_active = 1", "s.is_active = 1", "p.price >= $min_price"];
$params = [];
if ($q) { $where[] = "MATCH(p.name, p.description) AGAINST(? IN BOOLEAN MODE)"; $params[] = "$q*"; }
if ($cat) { $where[] = "c.slug = ?"; $params[] = $cat; }
if ($max_price < 10000) { $where[] = "p.price <= ?"; $params[] = $max_price; }
if ($vegan) { $where[] = "p.is_vegan = 1"; }
if ($gf) { $where[] = "p.is_gluten_free = 1"; }
if (isset($_GET['featured'])) { $where[] = "p.is_featured = 1"; }
if (isset($_GET['new'])) { $where[] = "p.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)"; }
if (isset($_GET['sale'])) { $where[] = "p.compare_price > p.price"; }

$order_sql = match($sort) {
    'newest' => 'p.created_at DESC',
    'price_asc' => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'rating' => 'p.rating DESC',
    default => 'p.total_sold DESC, p.is_featured DESC'
};

$where_sql = 'WHERE '.implode(' AND ', $where);
$total = (int)Database::fetch("SELECT COUNT(*) as cnt FROM products p JOIN shops s ON s.id=p.shop_id LEFT JOIN categories c ON c.id=p.category_id $where_sql", $params)['cnt'];
$products = Database::fetchAll("SELECT p.*, s.shop_name, s.shop_slug, c.name as cat_name, (SELECT image_path FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) as primary_image, ROUND(((p.compare_price - p.price)/p.compare_price)*100) as discount_pct FROM products p JOIN shops s ON s.id=p.shop_id LEFT JOIN categories c ON c.id=p.category_id $where_sql ORDER BY $order_sql LIMIT $per_page OFFSET $offset", $params);
$total_pages = ceil($total/$per_page);
$categories = Database::fetchAll("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order");

$page_title = $q ? "Results for \"$q\"" : ($cat ? ucfirst($cat) : 'Shop All Products');
include 'includes/header.php';
?>
<div class="section-sm" style="background:var(--cream)">
<div class="container">
  <div class="breadcrumb"><a href="<?= SITE_URL ?>">Home</a><span class="breadcrumb-sep">›</span><span><?= sanitize($page_title) ?></span></div>
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--space-md);margin-bottom:var(--space-xl)">
    <div>
      <h1 style="font-family:var(--font-display);font-size:2rem;font-weight:700"><?= sanitize($page_title) ?></h1>
      <p style="color:var(--text-muted)"><?= number_format($total) ?> products found</p>
    </div>
    <div style="display:flex;align-items:center;gap:var(--space-md)">
      <label style="font-size:0.875rem;color:var(--text-muted)">Sort by:</label>
      <select onchange="window.location='?<?= http_build_query(array_filter(array_merge($_GET,['sort'=>'_']))) ?>&sort='+this.value" class="form-select" style="width:180px">
        <?php foreach (['popular'=>'Most Popular','newest'=>'Newest First','price_asc'=>'Price: Low to High','price_desc'=>'Price: High to Low','rating'=>'Highest Rated'] as $k=>$v): ?>
          <option value="<?= $k ?>" <?= $sort===$k?'selected':'' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="shop-layout">
    <!-- Filter Sidebar -->
    <aside class="filter-sidebar">
      <div class="filter-header"><span class="filter-title">Filters</span><a href="<?= SITE_URL ?>/shop.php<?= $q?'?q='.urlencode($q):'' ?>" class="filter-clear">Clear All</a></div>
      <form method="GET" id="filterForm">
        <?php if ($q): ?><input type="hidden" name="q" value="<?= sanitize($q) ?>"><?php endif; ?>
        <div class="filter-section">
          <p class="filter-section-title">Category</p>
          <?php foreach ($categories as $c): ?>
          <label class="filter-option">
            <span class="filter-option-left"><input type="radio" name="cat" value="<?= $c['slug'] ?>" <?= $cat===$c['slug']?'checked':'' ?> onchange="document.getElementById('filterForm').submit()"> <?= $c['icon'] ?> <?= sanitize($c['name']) ?></span>
          </label>
          <?php endforeach; ?>
          <label class="filter-option"><span class="filter-option-left"><input type="radio" name="cat" value="" <?= !$cat?'checked':'' ?> onchange="document.getElementById('filterForm').submit()"> 🛍️ All Categories</span></label>
        </div>
        <div class="filter-section">
          <p class="filter-section-title">Price Range</p>
          <div class="price-range-wrap">
            <input type="range" class="price-range" id="priceRange" name="max" min="0" max="10000" step="100" value="<?= min($max_price,10000) ?>">
            <div class="price-display"><span>₹0</span><span id="priceDisplay">₹<?= number_format(min($max_price,10000)) ?></span></div>
          </div>
        </div>
        <div class="filter-section">
          <p class="filter-section-title">Dietary</p>
          <label class="filter-option"><span class="filter-option-left"><input type="checkbox" name="vegan" value="1" <?= isset($_GET['vegan'])?'checked':'' ?> onchange="this.form.submit()"> 🌿 Vegan</span></label>
          <label class="filter-option"><span class="filter-option-left"><input type="checkbox" name="gf" value="1" <?= isset($_GET['gf'])?'checked':'' ?> onchange="this.form.submit()"> 🌾 Gluten-Free</span></label>
        </div>
        <div class="filter-section">
          <button type="submit" class="btn btn-primary btn-block btn-sm">Apply Filters</button>
        </div>
      </form>
    </aside>
    <!-- Products -->
    <div>
      <?php if (empty($products)): ?>
        <div style="text-align:center;padding:var(--space-4xl);background:var(--white);border-radius:var(--radius-lg)">
          <span style="font-size:3rem">🔍</span>
          <h3 style="font-family:var(--font-display);margin:var(--space-md) 0">No products found</h3>
          <p style="color:var(--text-muted)">Try different search terms or filters.</p>
        </div>
      <?php else: ?>
      <div class="products-grid">
        <?php foreach ($products as $product): include 'includes/product_card.php'; endforeach; ?>
      </div>
      <?php if ($total_pages > 1): ?>
      <div class="pagination">
        <?php for ($i=1;$i<=$total_pages;$i++): ?>
          <a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
</div>
<?php include 'includes/footer.php'; ?>
