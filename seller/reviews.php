<?php
require_once '../includes/config.php';
$user = require_seller();
$shop = get_shop_by_user($user['id']);
if (!$shop) redirect('/seller/setup.php');
$shop_id = $shop['id'];

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;
$rating_f = (int)($_GET['rating'] ?? 0);

$where = ["r.shop_id = $shop_id"];
$params = [];
if ($rating_f >= 1 && $rating_f <= 5) { $where[] = "r.rating = ?"; $params[] = $rating_f; }
$where_sql = 'WHERE ' . implode(' AND ', $where);

$total = (int)Database::fetch("SELECT COUNT(*) as cnt FROM reviews r $where_sql", $params)['cnt'];
$reviews = Database::fetchAll("
    SELECT r.*, u.full_name
    FROM reviews r
    JOIN users u ON u.id = r.user_id
    $where_sql ORDER BY r.created_at DESC LIMIT $per_page OFFSET $offset
", $params);
$total_pages = ceil($total / $per_page);

$summary = Database::fetch("
    SELECT
        COUNT(*) as total,
        ROUND(AVG(rating),2) as avg_rating,
        SUM(rating=5) as five, SUM(rating=4) as four,
        SUM(rating=3) as three, SUM(rating=2) as two, SUM(rating=1) as one
    FROM reviews WHERE shop_id = ?
", [$shop_id]);

$page_title = 'Reviews';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Reviews — Crumbly Seller</title>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <style>body{background:var(--cream)}.rating-bar{height:8px;background:var(--border-light);border-radius:4px;overflow:hidden;flex:1}.rating-bar-fill{height:100%;background:var(--amber);border-radius:4px}</style>
</head>
<body>
<div class="seller-layout">
  <?php include '../includes/seller_sidebar.php'; ?>
  <main class="seller-main">

    <div class="page-header">
      <div><h1 class="page-title">Reviews ⭐</h1><p class="page-subtitle"><?= $total ?> reviews for your shop</p></div>
    </div>

    <!-- Summary Card -->
    <div class="card" style="padding:var(--space-2xl);margin-bottom:var(--space-xl)">
      <div style="display:grid;grid-template-columns:auto 1fr;gap:var(--space-3xl);align-items:center">
        <div style="text-align:center">
          <div style="font-size:4rem;font-weight:900;font-family:var(--font-display);line-height:1;color:var(--espresso)"><?= number_format($summary['avg_rating'] ?? 0, 1) ?></div>
          <div style="margin:8px 0"><?= get_star_rating($summary['avg_rating'] ?? 0) ?></div>
          <div style="font-size:0.82rem;color:var(--text-muted)"><?= $summary['total'] ?> reviews</div>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px">
          <?php foreach ([5,4,3,2,1] as $star):
            $cnt = $summary[['','one','two','three','four','five'][$star]] ?? 0;
            $pct = $summary['total'] > 0 ? round(($cnt / $summary['total']) * 100) : 0;
          ?>
          <div style="display:flex;align-items:center;gap:var(--space-md)">
            <a href="?rating=<?= $star ?>" style="font-size:0.8rem;font-weight:600;color:var(--text-muted);white-space:nowrap;min-width:40px"><?= $star ?> ★</a>
            <div class="rating-bar"><div class="rating-bar-fill" style="width:<?= $pct ?>%"></div></div>
            <span style="font-size:0.78rem;color:var(--text-muted);min-width:30px"><?= $cnt ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Filter by rating -->
    <div style="display:flex;gap:var(--space-sm);margin-bottom:var(--space-xl);flex-wrap:wrap">
      <a href="?" class="btn btn-sm <?= !$rating_f?'btn-primary':'btn-outline' ?>">All</a>
      <?php foreach ([5,4,3,2,1] as $s): ?>
        <a href="?rating=<?= $s ?>" class="btn btn-sm <?= $rating_f===$s?'btn-primary':'btn-outline' ?>"><?= $s ?> ★</a>
      <?php endforeach; ?>
    </div>

    <!-- Reviews List -->
    <?php if (empty($reviews)): ?>
      <div style="text-align:center;padding:var(--space-4xl);background:var(--white);border-radius:var(--radius-lg)">
        <span style="font-size:2.5rem">⭐</span>
        <p style="font-family:var(--font-display);font-size:1.2rem;margin:var(--space-md) 0">No reviews yet</p>
        <p style="color:var(--text-muted)">Reviews from customers will appear here.</p>
      </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:var(--space-md)">
      <?php foreach ($reviews as $r): ?>
      <div class="card" style="padding:var(--space-xl)">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:var(--space-md);flex-wrap:wrap;margin-bottom:var(--space-md)">
          <div style="display:flex;align-items:center;gap:var(--space-md)">
            <div style="width:42px;height:42px;border-radius:50%;background:var(--grad-warm);display:grid;place-items:center;color:var(--cream);font-weight:700;flex-shrink:0"><?= strtoupper(substr($r['full_name'],0,1)) ?></div>
            <div>
              <p style="font-weight:700"><?= sanitize($r['full_name']) ?></p>
              <div style="display:flex;align-items:center;gap:8px"><?= get_star_rating($r['rating']) ?><span style="font-size:0.75rem;color:var(--text-muted)"><?= time_ago($r['created_at']) ?></span></div>
            </div>
          </div>
          <div style="text-align:right">
            <span style="font-size:0.72rem;color:var(--sage);font-weight:600">✓ Verified Purchase</span>
          </div>
        </div>
        <?php if ($r['title']): ?><p style="font-weight:700;margin-bottom:6px"><?= sanitize($r['title']) ?></p><?php endif; ?>
        <?php if ($r['body']): ?><p style="font-size:0.9rem;color:var(--text-secondary);line-height:1.7"><?= sanitize($r['body']) ?></p><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php for ($i=1;$i<=$total_pages;$i++): ?>
        <a href="?rating=<?= $rating_f ?>&page=<?= $i ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

  </main>
</div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
