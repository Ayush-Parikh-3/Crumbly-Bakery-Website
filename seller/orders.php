<?php
require_once '../includes/config.php';
$user = require_seller();
$shop = get_shop_by_user($user['id']);
if (!$shop) redirect('/seller/setup.php');
$sid = $shop['id'];

// Handle status update (AJAX or form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $order_id  = (int)($_POST['order_id'] ?? 0);
    $status    = $_POST['status'] ?? '';
    $valid     = ['pending','confirmed','preparing','ready','out_for_delivery','delivered','cancelled'];
    if ($order_id && in_array($status, $valid)) {
        $order = Database::fetch("SELECT id FROM orders WHERE id=? AND shop_id=?", [$order_id, $sid]);
        if ($order) {
            Database::query("UPDATE orders SET order_status=? WHERE id=?", [$status, $order_id]);
            Database::insert("INSERT INTO order_status_history (order_id,status,changed_by) VALUES (?,?,?)", [$order_id,$status,$user['id']]);
        }
    }
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) json_response(['success'=>true]);
    flash_set('success', 'Order status updated!');
    redirect('/seller/orders.php' . (isset($_GET['status']) ? '?status='.$_GET['status'] : ''));
}

$status_f = sanitize($_GET['status'] ?? '');
$search_f = sanitize($_GET['search'] ?? '');
$page     = max(1,(int)($_GET['page'] ?? 1));
$per_page = ORDERS_PER_PAGE;
$offset   = ($page-1)*$per_page;

$where  = ["o.shop_id=$sid"];
$params = [];
if ($status_f) { $where[]="o.order_status=?"; $params[]=$status_f; }
if ($search_f) { $where[]="(o.order_number LIKE ? OR u.full_name LIKE ?)"; $params[]="%$search_f%"; $params[]="%$search_f%"; }
$ws = 'WHERE '.implode(' AND ',$where);
$total = (int)Database::fetch("SELECT COUNT(*) as cnt FROM orders o JOIN users u ON u.id=o.user_id $ws",$params)['cnt'];
$orders = Database::fetchAll("SELECT o.*,u.full_name AS customer_name,u.phone AS customer_phone FROM orders o JOIN users u ON u.id=o.user_id $ws ORDER BY o.created_at DESC LIMIT $per_page OFFSET $offset",$params);
$total_pages = ceil($total/$per_page);

$sc_rows = Database::fetchAll("SELECT order_status,COUNT(*) as cnt FROM orders WHERE shop_id=? GROUP BY order_status",[$sid]);
$sc = array_column($sc_rows,'cnt','order_status');

// Status flow: what actions can seller take per status
$next_actions = [
    'pending'          => [['confirmed','✅ Accept'],  ['cancelled','❌ Cancel']],
    'confirmed'        => [['preparing','👨‍🍳 Start Preparing']],
    'preparing'        => [['ready','📦 Mark Ready']],
    'ready'            => [['out_for_delivery','🚴 Out for Delivery']],
    'out_for_delivery' => [['delivered','✅ Mark Delivered']],
    'delivered'        => [],
    'cancelled'        => [],
];

$page_title='Orders';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Orders — Crumbly Seller</title>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <style>body{background:var(--cream)}.action-status-btn{padding:5px 12px;border-radius:var(--radius-full);border:1.5px solid;cursor:pointer;font-size:0.75rem;font-weight:700;transition:all 0.2s;background:transparent}</style>
</head>
<body>
<div class="seller-layout">
  <?php include '../includes/seller_sidebar.php'; ?>
  <main class="seller-main">

    <div class="page-header">
      <div><h1 class="page-title">Orders</h1><p class="page-subtitle"><?= $total ?> orders</p></div>
    </div>

    <?php $fs=flash_get('success'); if($fs): ?><div class="alert alert-success" style="margin-bottom:var(--space-xl)">✅ <?= sanitize($fs) ?></div><?php endif; ?>

    <!-- Status Tabs -->
    <div style="display:flex;gap:6px;margin-bottom:var(--space-xl);overflow-x:auto;padding-bottom:4px;flex-wrap:wrap">
      <?php $tabs=[''=>'All','pending'=>'⏳ Pending','confirmed'=>'✅ Confirmed','preparing'=>'👨‍🍳 Preparing','ready'=>'📦 Ready','out_for_delivery'=>'🚴 Out for Delivery','delivered'=>'🏠 Delivered','cancelled'=>'❌ Cancelled']; ?>
      <?php foreach ($tabs as $k=>$v):
        $cnt = $k ? ($sc[$k]??0) : array_sum($sc);
      ?>
      <a href="?status=<?= $k ?>" style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:var(--radius-full);font-size:0.8rem;font-weight:600;white-space:nowrap;text-decoration:none;background:<?= $status_f===$k?'var(--espresso)':'var(--white)' ?>;color:<?= $status_f===$k?'var(--cream)':'var(--text-secondary)' ?>;border:1px solid <?= $status_f===$k?'var(--espresso)':'var(--border-light)' ?>">
        <?= $v ?> <span style="background:<?= $status_f===$k?'rgba(255,255,255,0.2)':'var(--cream-dark)' ?>;padding:1px 7px;border-radius:99px;font-size:0.68rem"><?= $cnt ?></span>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Search -->
    <div class="data-table-wrap" style="margin-bottom:var(--space-xl)">
      <form method="GET" style="display:flex;gap:var(--space-md);padding:var(--space-lg) var(--space-xl);align-items:flex-end;flex-wrap:wrap">
        <input type="hidden" name="status" value="<?= sanitize($status_f) ?>">
        <div><label class="form-label" style="margin-bottom:4px">Search</label>
          <input type="text" name="search" value="<?= sanitize($search_f) ?>" class="form-input" placeholder="Order # or customer name..." style="width:260px">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Search</button>
        <a href="?status=<?= urlencode($status_f) ?>" class="btn btn-ghost btn-sm">Clear</a>
      </form>
    </div>

    <div class="data-table-wrap">
      <?php if (empty($orders)): ?>
        <div style="text-align:center;padding:var(--space-4xl);color:var(--text-muted)">
          <span style="font-size:3rem">📭</span>
          <p style="font-family:var(--font-display);font-size:1.2rem;margin:var(--space-md) 0">No orders found</p>
        </div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Order #</th><th>Customer</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>Quick Action</th><th>Date</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($orders as $o):
              $items_count = Database::fetch("SELECT SUM(quantity) as cnt FROM order_items WHERE order_id=?",[$o['id']])['cnt'] ?? 0;
              $actions = $next_actions[$o['order_status']] ?? [];
            ?>
            <tr>
              <td><strong><?= sanitize($o['order_number']) ?></strong></td>
              <td>
                <p style="font-weight:600"><?= sanitize($o['customer_name']) ?></p>
                <p style="font-size:0.75rem;color:var(--text-muted)"><?= sanitize($o['customer_phone']??'') ?></p>
              </td>
              <td><?= $items_count ?> item<?= $items_count!=1?'s':'' ?></td>
              <td><strong><?= format_price($o['total_amount']) ?></strong></td>
              <td><span class="status-pill <?= $o['payment_status']==='paid'?'status-delivered':'status-pending' ?>"><?= ucfirst($o['payment_status']) ?></span></td>
              <td><span class="status-pill status-<?= $o['order_status'] ?>"><?= ucwords(str_replace('_',' ',$o['order_status'])) ?></span></td>
              <td>
                <?php if (!empty($actions)): ?>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                  <?php foreach ($actions as [$next_s, $label]): ?>
                  <form method="POST" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="order_id"  value="<?= $o['id'] ?>">
                    <input type="hidden" name="status"    value="<?= $next_s ?>">
                    <button type="submit" class="action-status-btn"
                      style="border-color:<?= $next_s==='cancelled'?'var(--rose)':'var(--amber)' ?>;color:<?= $next_s==='cancelled'?'var(--rose)':'var(--espresso)' ?>"
                      onclick="return <?= $next_s==='cancelled' ? "confirm('Cancel this order?')" : 'true' ?>">
                      <?= $label ?>
                    </button>
                  </form>
                  <?php endforeach; ?>
                </div>
                <?php else: ?>
                  <span style="font-size:0.75rem;color:var(--text-muted)">—</span>
                <?php endif; ?>
              </td>
              <td style="color:var(--text-muted);font-size:0.8rem;white-space:nowrap"><?= date('d M, g:i A',strtotime($o['created_at'])) ?></td>
              <td><a href="<?= SITE_URL ?>/seller/order-detail.php?id=<?= $o['id'] ?>" class="action-btn view" title="View">👁️</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($total_pages>1): ?>
      <div class="pagination">
        <?php for ($i=1;$i<=$total_pages;$i++): ?>
          <a href="?status=<?= urlencode($status_f) ?>&page=<?= $i ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>

  </main>
</div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
