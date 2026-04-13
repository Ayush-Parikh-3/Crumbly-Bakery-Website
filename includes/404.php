<?php
if (!defined('CRUMBLY_VERSION')) {
    require_once __DIR__ . '/config.php';
}
http_response_code(404);
$page_title = 'Page Not Found';
include __DIR__ . '/header.php';
?>
<div style="min-height:60vh;display:flex;align-items:center;justify-content:center;padding:var(--space-4xl) var(--space-xl)">
  <div style="text-align:center;max-width:500px">
    <div style="font-size:5rem;margin-bottom:var(--space-xl)">🍞</div>
    <h1 style="font-family:var(--font-display);font-size:5rem;font-weight:900;color:var(--espresso);line-height:1;margin-bottom:var(--space-md)">404</h1>
    <h2 style="font-family:var(--font-display);font-size:1.6rem;margin-bottom:var(--space-md)">This page got <em style="color:var(--rose)">burnt!</em></h2>
    <p style="color:var(--text-muted);margin-bottom:var(--space-2xl);line-height:1.7">The page you're looking for doesn't exist or has been moved. Let's get you back to the good stuff.</p>
    <div style="display:flex;gap:var(--space-md);justify-content:center;flex-wrap:wrap">
      <a href="<?= SITE_URL ?>/index.php" class="btn btn-primary btn-lg">← Back to Home</a>
      <a href="<?= SITE_URL ?>/shop.php" class="btn btn-amber btn-lg">Browse Products</a>
    </div>
  </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
