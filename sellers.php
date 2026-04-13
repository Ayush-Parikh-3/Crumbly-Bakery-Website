<?php
require_once 'includes/config.php';
$q = sanitize($_GET['q'] ?? '');
$city = sanitize($_GET['city'] ?? '');
$sort = sanitize($_GET['sort'] ?? 'rating');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page-1)*$per_page;

$where = ["s.is_active = 1"];
$params = [];
if ($q) { $where[] = "(s.shop_name LIKE ? OR s.description LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($city) { $where[] = "s.city LIKE ?"; $params[] = "%$city%"; }
$order_sql = match($sort) { 'newest'=>'s.created_at DESC', 'sales'=>'s.total_sales DESC', default=>'s.rating DESC, s.total_reviews DESC' };
$where_sql = 'WHERE '.implode(' AND ',$where);
$total = (int)Database::fetch("SELECT COUNT(*) as cnt FROM shops s $where_sql", $params)['cnt'];
$shops = Database::fetchAll("SELECT s.*, COUNT(DISTINCT p.id) as product_count FROM shops s LEFT JOIN products p ON p.shop_id=s.id AND p.is_active=1 $where_sql GROUP BY s.id ORDER BY $order_sql LIMIT $per_page OFFSET $offset", $params);
$total_pages = ceil($total/$per_page);
$cities = Database::fetchAll("SELECT DISTINCT city FROM shops WHERE is_active=1 AND city IS NOT NULL AND city != '' ORDER BY city LIMIT 30");

$page_title = 'Browse Bakeries';
include 'includes/header.php';
?>
<div class="section-sm" style="background:var(--espresso)">
<div class="container">
  <div style="max-width:600px;margin:0 auto;text-align:center;padding:var(--space-3xl) 0">
    <h1 style="font-family:var(--font-display);font-size:2.5rem;font-weight:700;color:var(--cream);margin-bottom:var(--space-md)">Discover Artisan <span style="color:var(--amber-light)">Bakeries</span></h1>
    <p style="color:rgba(253,246,236,0.7);margin-bottom:var(--space-xl)">Find the finest local bakers near you</p>
    <form method="GET" style="display:flex;gap:var(--space-sm)">
      <input type="text" name="q" value="<?= sanitize($q) ?>" class="form-input" placeholder="Search bakeries..." style="flex:1;background:rgba(255,255,255,0.1);border-color:rgba(255,255,255,0.2);color:var(--cream)">
      <button type="submit" class="btn btn-amber">Search</button>
    </form>
  </div>
</div>
</div>
<div class="section" style="background:var(--cream)">
<div class="container">
  <!-- Filters -->
  <div style="display:flex;align-items:center;gap:var(--space-md);margin-bottom:var(--space-2xl);flex-wrap:wrap">
    <div style="display:flex;gap:var(--space-sm);flex-wrap:wrap;flex:1">
      <?php foreach ($cities as $c): ?>
      <a href="?city=<?= urlencode($c['city']) ?>" class="cat-link <?= $city===$c['city']?'active':'' ?>" style="background:<?= $city===$c['city']?'var(--espresso)':'var(--white)' ?>;color:<?= $city===$c['city']?'var(--cream)':'var(--text-secondary)' ?>;border:1px solid var(--border-light);border-radius:var(--radius-full);padding:6px 14px;font-size:0.82rem;font-weight:500">📍 <?= sanitize($c['city']) ?></a>
      <?php endforeach; ?>
      <?php if ($city): ?><a href="?" style="font-size:0.82rem;color:var(--rose);padding:6px">✕ Clear</a><?php endif; ?>
    </div>
    <select onchange="window.location='?q=<?= urlencode($q) ?>&city=<?= urlencode($city) ?>&sort='+this.value" class="form-select" style="width:160px">
      <?php foreach (['rating'=>'Top Rated','sales'=>'Most Popular','newest'=>'Newest'] as $k=>$v): ?>
        <option value="<?= $k ?>" <?= $sort===$k?'selected':'' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <p style="color:var(--text-muted);font-size:0.875rem;margin-bottom:var(--space-xl)"><?= number_format($total) ?> bakeries found</p>
  <?php if (empty($shops)): ?>
    <div style="text-align:center;padding:var(--space-4xl);background:var(--white);border-radius:var(--radius-lg)"><span style="font-size:3rem">🔍</span><h3 style="font-family:var(--font-display);margin:var(--space-md) 0">No bakeries found</h3><p style="color:var(--text-muted)">Try a different search.</p></div>
  <?php else: ?>
  <div class="shops-grid">
    <?php foreach ($shops as $shop): ?>
    <a href="<?= SITE_URL ?>/bakery.php?slug=<?= urlencode($shop['shop_slug']) ?>" class="shop-card" style="text-decoration:none;color:inherit;display:block">
      <div class="shop-card-banner">
        <?php if ($shop['banner']): ?><img src="<?= SITE_URL.'/'.$shop['banner'] ?>" alt=""><?php else: ?><div style="width:100%;height:100%;background:var(--grad-warm);opacity:0.7"></div><?php endif; ?>
        <?php if ($shop['is_verified']): ?><span class="shop-verified-badge">✓ Verified</span><?php endif; ?>
        <div class="shop-card-logo">
          <?php if ($shop['logo']): ?><img src="<?= SITE_URL.'/'.$shop['logo'] ?>" alt=""><?php else: ?><div style="width:100%;height:100%;display:grid;place-items:center;background:var(--cream-dark);font-size:1.4rem">🥐</div><?php endif; ?>
        </div>
      </div>
      <div class="shop-card-body">
        <h3 class="shop-card-name"><?= sanitize($shop['shop_name']) ?></h3>
        <p class="shop-card-city"><?= sanitize($shop['city']??'') ?><?= $shop['state']?', '.sanitize($shop['state']):'' ?></p>
        <div style="display:flex;align-items:center;gap:6px;margin-bottom:var(--space-md)"><?= get_star_rating($shop['rating']) ?> <span style="font-size:0.85rem;font-weight:600"><?= number_format($shop['rating'],1) ?></span> <span style="font-size:0.75rem;color:var(--text-muted)">(<?= $shop['total_reviews'] ?>)</span></div>
        <div class="shop-stats">
          <div><div class="shop-stat-val"><?= $shop['product_count'] ?>+</div><div class="shop-stat-key">Products</div></div>
          <div><div class="shop-stat-val"><?= number_format($shop['total_sales']) ?></div><div class="shop-stat-key">Orders</div></div>
          <div><div class="shop-stat-val" style="color:<?= $shop['delivery_fee']==0?'var(--sage)':'var(--text-primary)' ?>"><?= $shop['delivery_fee']==0?'Free':format_price($shop['delivery_fee']) ?></div><div class="shop-stat-key">Delivery</div></div>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php if ($total_pages>1): ?><div class="pagination"><?php for ($i=1;$i<=$total_pages;$i++): ?><a href="?q=<?= urlencode($q) ?>&city=<?= urlencode($city) ?>&sort=<?= $sort ?>&page=<?= $i ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a><?php endfor; ?></div><?php endif; ?>
  <?php endif; ?>
</div>
</div>
<?php include 'includes/footer.php'; ?>
