<?php
require_once '../includes/config.php';
$user = require_seller();
$shop = get_shop_by_user($user['id']);
if (!$shop) redirect('/seller/setup.php');
$shop_id = $shop['id'];

$action = $_GET['action'] ?? 'list';
$edit_id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';
$categories = Database::fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $error = 'Invalid request.'; goto render; }
    $form_action = $_POST['form_action'] ?? '';

    if ($form_action === 'save_product') {
        $pid = (int)($_POST['product_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $cat_id = (int)($_POST['category_id'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        $ingredients = trim($_POST['ingredients'] ?? '');
        $allergens = trim($_POST['allergen_info'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $compare = (float)($_POST['compare_price'] ?? 0);
        $stock = (int)($_POST['stock_qty'] ?? 0);
        $track = isset($_POST['track_stock']) ? 1 : 0;
        $is_vegan = isset($_POST['is_vegan']) ? 1 : 0;
        $is_gf = isset($_POST['is_gluten_free']) ? 1 : 0;
        $is_nut = isset($_POST['is_nut_free']) ? 1 : 0;
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $sku = trim($_POST['sku'] ?? '');
        $weight = (int)($_POST['weight_grams'] ?? 0);
        $serving = trim($_POST['serving_size'] ?? '');
        $low_thresh = max(1, (int)($_POST['low_stock_threshold'] ?? 5));

        if (!$name || !$cat_id || $price <= 0) { $error = 'Name, category, and price are required.'; goto render; }

        $slug = generate_slug($name);
        if ($pid) {
            // Update
            Database::query("UPDATE products SET name=?,category_id=?,description=?,ingredients=?,allergen_info=?,price=?,compare_price=?,stock_qty=?,track_stock=?,is_vegan=?,is_gluten_free=?,is_nut_free=?,is_featured=?,is_active=?,sku=?,weight_grams=?,serving_size=?,low_stock_threshold=? WHERE id=? AND shop_id=?",
                [$name,$cat_id,$desc,$ingredients,$allergens,$price,$compare ?: null,$stock,$track,$is_vegan,$is_gf,$is_nut,$is_featured,$is_active,$sku,$weight,$serving,$low_thresh,$pid,$shop_id]);
        } else {
            // Check slug uniqueness
            $sc = Database::fetch("SELECT COUNT(*) as c FROM products WHERE shop_id=? AND slug LIKE ?", [$shop_id, $slug.'%']);
            if ($sc['c'] > 0) $slug .= '-'.time();
            $pid = Database::insert("INSERT INTO products (shop_id,category_id,name,slug,description,ingredients,allergen_info,price,compare_price,stock_qty,track_stock,is_vegan,is_gluten_free,is_nut_free,is_featured,is_active,sku,weight_grams,serving_size,low_stock_threshold) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$shop_id,$cat_id,$name,$slug,$desc,$ingredients,$allergens,$price,$compare ?: null,$stock,$track,$is_vegan,$is_gf,$is_nut,$is_featured,$is_active,$sku,$weight,$serving,$low_thresh]);
        }

        // Handle images
        if (!empty($_FILES['images']['name'][0])) {
            $has_primary   = Database::fetch("SELECT id FROM product_images WHERE product_id=? AND is_primary=1", [$pid]);
            $upload_errors = 0;
            foreach ($_FILES['images']['tmp_name'] as $idx => $tmp) {
                if ($_FILES['images']['error'][$idx] !== UPLOAD_ERR_OK) continue;
                $single = [
                    'tmp_name' => $tmp,
                    'name'     => $_FILES['images']['name'][$idx],
                    'size'     => $_FILES['images']['size'][$idx],
                    'type'     => $_FILES['images']['type'][$idx],
                    'error'    => UPLOAD_ERR_OK,
                ];
                $path = upload_image($single, 'products');
                if ($path) {
                    $is_primary = (!$has_primary && $idx === 0) ? 1 : 0;
                    Database::insert(
                        "INSERT INTO product_images (product_id,image_path,is_primary,sort_order) VALUES (?,?,?,?)",
                        [$pid, $path, $is_primary, $idx]
                    );
                    if ($is_primary) $has_primary = true;
                } else {
                    $upload_errors++;
                }
            }
            if ($upload_errors > 0) {
                flash_set('error', $upload_errors . ' image(s) could not be uploaded. Only JPG, PNG, WebP under 5MB are allowed.');
            }
        }
        flash_set('success', 'Product saved successfully! 🎉');
        redirect('/seller/products.php');
    }

    if ($form_action === 'delete_product') {
        $pid = (int)($_POST['product_id'] ?? 0);
        Database::query("DELETE FROM products WHERE id=? AND shop_id=?", [$pid, $shop_id]);
        flash_set('success', 'Product deleted.');
        redirect('/seller/products.php');
    }

    if ($form_action === 'delete_image') {
        $img_id = (int)($_POST['image_id'] ?? 0);
        $img = Database::fetch("SELECT pi.* FROM product_images pi JOIN products p ON p.id=pi.product_id WHERE pi.id=? AND p.shop_id=?", [$img_id, $shop_id]);
        if ($img) {
            $full_path = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/') . '/' . $img['image_path'];
            if (file_exists($full_path)) @unlink($full_path);
            Database::query("DELETE FROM product_images WHERE id=?", [$img_id]);
        }
        redirect('/seller/products.php?action=edit&id=' . ($edit_id ?: $_POST['pid']));
    }
}

// Load product for editing
$edit_product = null;
$edit_images = [];
if ($action === 'edit' && $edit_id) {
    $edit_product = Database::fetch("SELECT * FROM products WHERE id=? AND shop_id=?", [$edit_id, $shop_id]);
    if (!$edit_product) redirect('/seller/products.php');
    $edit_images = Database::fetchAll("SELECT * FROM product_images WHERE product_id=? ORDER BY is_primary DESC, sort_order", [$edit_id]);
}

// Product list with filters
$search = sanitize($_GET['search'] ?? '');
$cat_filter = (int)($_GET['cat'] ?? 0);
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$where = ["p.shop_id = $shop_id"];
$params = [];
if ($search) { $where[] = "p.name LIKE ?"; $params[] = "%$search%"; }
if ($cat_filter) { $where[] = "p.category_id = ?"; $params[] = $cat_filter; }
if ($status_filter === 'active') { $where[] = "p.is_active = 1"; }
elseif ($status_filter === 'inactive') { $where[] = "p.is_active = 0"; }
elseif ($status_filter === 'low_stock') { $where[] = "p.track_stock = 1 AND p.stock_qty <= p.low_stock_threshold"; }

$where_sql = 'WHERE ' . implode(' AND ', $where);
$total = Database::fetch("SELECT COUNT(*) as cnt FROM products p $where_sql", $params)['cnt'];
$products = Database::fetchAll("SELECT p.*, c.name as cat_name, (SELECT image_path FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) as image FROM products p LEFT JOIN categories c ON c.id=p.category_id $where_sql ORDER BY p.created_at DESC LIMIT $per_page OFFSET $offset", $params);
$total_pages = ceil($total / $per_page);

render:
$page_title = $action === 'add' ? 'Add Product' : ($action === 'edit' ? 'Edit Product' : 'Products');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= sanitize($page_title) ?> — Crumbly Seller</title>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <style>
    body{background:var(--cream)}
    .img-preview-grid{display:flex;flex-wrap:wrap;gap:10px;margin-top:10px}
    .img-preview-item{position:relative;width:80px;height:80px}
    .img-preview-item img{width:100%;height:100%;object-fit:cover;border-radius:8px;border:2px solid var(--border-light)}
    .img-preview-item .del-img{position:absolute;top:-6px;right:-6px;width:20px;height:20px;background:var(--rose);color:white;border:none;border-radius:50%;cursor:pointer;font-size:0.7rem;display:grid;place-items:center}
    .primary-badge{position:absolute;bottom:2px;left:2px;background:var(--amber);color:var(--espresso);font-size:0.55rem;font-weight:700;padding:1px 4px;border-radius:3px}
  </style>
</head>
<body>
<div class="seller-layout">
  <?php include '../includes/seller_sidebar.php'; ?>
  <main class="seller-main">

    <?php if ($action === 'list'): ?>
    <!-- PRODUCT LIST -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Products</h1>
        <p class="page-subtitle"><?= $total ?> products in your shop</p>
      </div>
      <a href="?action=add" class="btn btn-amber">+ Add New Product</a>
    </div>

    <?php if ($error): ?><div class="alert alert-error" style="margin-bottom:var(--space-xl)"><?= sanitize($error) ?></div><?php endif; ?>
    <?php $fs = flash_get('success'); if ($fs): ?><div class="alert alert-success" style="margin-bottom:var(--space-xl)">✅ <?= sanitize($fs) ?></div><?php endif; ?>
    <?php $fe = flash_get('error');   if ($fe): ?><div class="alert alert-warning" style="margin-bottom:var(--space-xl)">⚠️ <?= sanitize($fe) ?></div><?php endif; ?>

    <!-- Filters -->
    <div class="data-table-wrap" style="margin-bottom:var(--space-xl)">
      <form method="GET" style="display:flex;gap:var(--space-md);padding:var(--space-lg) var(--space-xl);flex-wrap:wrap;align-items:flex-end">
        <input type="hidden" name="action" value="list">
        <div>
          <label class="form-label" style="margin-bottom:4px">Search</label>
          <input type="text" name="search" value="<?= sanitize($search) ?>" class="form-input" placeholder="Product name..." style="width:220px">
        </div>
        <div>
          <label class="form-label" style="margin-bottom:4px">Category</label>
          <select name="cat" class="form-select" style="width:160px">
            <option value="">All Categories</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $cat_filter === (int)$c['id'] ? 'selected' : '' ?>><?= sanitize($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label" style="margin-bottom:4px">Status</label>
          <select name="status" class="form-select" style="width:140px">
            <option value="">All</option>
            <option value="active" <?= $status_filter==='active'?'selected':'' ?>>Active</option>
            <option value="inactive" <?= $status_filter==='inactive'?'selected':'' ?>>Inactive</option>
            <option value="low_stock" <?= $status_filter==='low_stock'?'selected':'' ?>>Low Stock</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="?action=list" class="btn btn-ghost btn-sm">Reset</a>
      </form>
    </div>

    <div class="data-table-wrap">
      <?php if (empty($products)): ?>
        <div style="text-align:center;padding:var(--space-4xl);color:var(--text-muted)">
          <span style="font-size:3rem">🧁</span>
          <p style="font-family:var(--font-display);font-size:1.3rem;margin:var(--space-md) 0">No products yet</p>
          <p style="margin-bottom:var(--space-xl)">Start by adding your first delicious product!</p>
          <a href="?action=add" class="btn btn-amber">+ Add First Product</a>
        </div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th><input type="checkbox" id="selectAll"></th><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Sales</th><th>Status</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($products as $p): ?>
            <tr>
              <td><input type="checkbox" name="ids[]" value="<?= $p['id'] ?>" class="product-check"></td>
              <td>
                <div style="display:flex;align-items:center;gap:var(--space-md)">
                  <img src="<?= $p['image'] ? SITE_URL.'/'.$p['image'] : SITE_URL.'/assets/images/placeholder.svg' ?>" class="product-td-img" alt="">
                  <div>
                    <p style="font-weight:600"><?= sanitize($p['name']) ?></p>
                    <?php if ($p['is_featured']): ?><span class="badge badge-hot" style="font-size:0.65rem">⭐ Featured</span><?php endif; ?>
                  </div>
                </div>
              </td>
              <td style="color:var(--text-muted)"><?= sanitize($p['cat_name'] ?? '') ?></td>
              <td>
                <strong><?= format_price($p['price']) ?></strong>
                <?php if ($p['compare_price']): ?>
                  <del style="font-size:0.75rem;color:var(--text-muted)"><?= format_price($p['compare_price']) ?></del>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($p['track_stock']): ?>
                  <span style="color:<?= $p['stock_qty'] <= $p['low_stock_threshold'] ? 'var(--rose)' : 'var(--sage)' ?>;font-weight:700"><?= $p['stock_qty'] ?></span>
                <?php else: ?>
                  <span style="color:var(--text-muted)">Unlimited</span>
                <?php endif; ?>
              </td>
              <td><?= $p['total_sold'] ?></td>
              <td>
                <label style="cursor:pointer;display:flex;align-items:center;gap:6px">
                  <input type="checkbox" class="toggle-product-status" data-id="<?= $p['id'] ?>" <?= $p['is_active'] ? 'checked' : '' ?> style="accent-color:var(--amber)">
                  <span class="status-pill <?= $p['is_active'] ? 'status-active' : 'status-inactive' ?>"><?= $p['is_active'] ? 'Active' : 'Hidden' ?></span>
                </label>
              </td>
              <td>
                <div class="action-btns">
                  <a href="?action=edit&id=<?= $p['id'] ?>" class="action-btn edit" title="Edit">✏️</a>
                  <a href="<?= SITE_URL ?>/product.php?id=<?= $p['id'] ?>" class="action-btn view" title="Preview" target="_blank">👁️</a>
                  <form method="POST" style="display:inline" onsubmit="return confirm('Delete this product permanently?')">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="form_action" value="delete_product">
                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                    <button type="submit" class="action-btn delete" title="Delete">🗑️</button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($total_pages > 1): ?>
      <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
          <a href="?action=list&page=<?= $i ?>&search=<?= urlencode($search) ?>&cat=<?= $cat_filter ?>&status=<?= urlencode($status_filter) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>

    <?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- ADD/EDIT PRODUCT FORM -->
    <div class="page-header">
      <div>
        <h1 class="page-title"><?= $action === 'edit' ? 'Edit Product' : 'Add New Product' ?></h1>
        <p class="page-subtitle"><?= $action === 'edit' ? 'Update product details' : 'List a new product in your shop' ?></p>
      </div>
      <a href="?action=list" class="btn btn-outline btn-sm">← Back to Products</a>
    </div>

    <?php if ($error): ?><div class="alert alert-error" style="margin-bottom:var(--space-xl)"><?= sanitize($error) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" data-validate>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="form_action" value="save_product">
      <input type="hidden" name="product_id" value="<?= $edit_product['id'] ?? 0 ?>">

      <div style="display:grid;grid-template-columns:2fr 1fr;gap:var(--space-xl);align-items:start">
        <!-- Left: Main Info -->
        <div style="display:flex;flex-direction:column;gap:var(--space-xl)">

          <div class="card" style="padding:var(--space-2xl)">
            <h3 style="font-family:var(--font-display);margin-bottom:var(--space-xl)">Product Info</h3>
            <div class="form-group">
              <label class="form-label">Product Name <span class="required">*</span></label>
              <input type="text" name="name" class="form-input" required placeholder="e.g. Dark Chocolate Truffle Cake" value="<?= sanitize($edit_product['name'] ?? '') ?>">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-md)">
              <div class="form-group">
                <label class="form-label">Category <span class="required">*</span></label>
                <select name="category_id" class="form-select" required>
                  <option value="">Select Category</option>
                  <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($edit_product['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">SKU / Product Code</label>
                <input type="text" name="sku" class="form-input" placeholder="e.g. CAKE-001" value="<?= sanitize($edit_product['sku'] ?? '') ?>">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-textarea" rows="4" placeholder="Describe your product in detail..."><?= sanitize($edit_product['description'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
              <label class="form-label">Ingredients</label>
              <textarea name="ingredients" class="form-textarea" rows="2" placeholder="List ingredients..."><?= sanitize($edit_product['ingredients'] ?? '') ?></textarea>
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Allergen Information</label>
              <input type="text" name="allergen_info" class="form-input" placeholder="e.g. Contains: Wheat, Dairy, Eggs, Nuts" value="<?= sanitize($edit_product['allergen_info'] ?? '') ?>">
            </div>
          </div>

          <!-- Pricing -->
          <div class="card" style="padding:var(--space-2xl)">
            <h3 style="font-family:var(--font-display);margin-bottom:var(--space-xl)">Pricing</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-md)">
              <div class="form-group">
                <label class="form-label">Selling Price (₹) <span class="required">*</span></label>
                <input type="number" name="price" class="form-input" required step="0.01" min="1" placeholder="0.00" value="<?= $edit_product['price'] ?? '' ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Compare-at Price (₹)</label>
                <input type="number" name="compare_price" class="form-input" step="0.01" min="0" placeholder="0.00 (for strikethrough)" value="<?= $edit_product['compare_price'] ?? '' ?>">
                <p class="form-hint">Original price shown crossed out for sale effect</p>
              </div>
            </div>
          </div>

          <!-- Images -->
          <div class="card" style="padding:var(--space-2xl)">
            <h3 style="font-family:var(--font-display);margin-bottom:var(--space-xl)">Product Images</h3>
            <?php if (!empty($edit_images)): ?>
            <div class="img-preview-grid" style="margin-bottom:var(--space-lg)">
              <?php foreach ($edit_images as $img): ?>
              <div class="img-preview-item">
                <img src="<?= SITE_URL.'/'.$img['image_path'] ?>" alt="">
                <?php if ($img['is_primary']): ?><span class="primary-badge">Primary</span><?php endif; ?>
                <form method="POST" style="display:inline" onsubmit="return confirm('Remove this image?')">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="form_action" value="delete_image">
                  <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                  <input type="hidden" name="pid" value="<?= $edit_product['id'] ?? 0 ?>">
                  <button type="submit" class="del-img">✕</button>
                </form>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="file-upload-area">
              <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" style="display:none">
              <div class="file-upload-icon">📸</div>
              <p class="file-upload-text">Click or drag to upload images<br><strong>JPG, PNG, WebP</strong> up to 5MB each</p>
            </div>
          </div>

        </div>

        <!-- Right: Sidebar options -->
        <div style="display:flex;flex-direction:column;gap:var(--space-xl)">

          <!-- Status & Visibility -->
          <div class="card" style="padding:var(--space-xl)">
            <h3 style="font-family:var(--font-display);font-size:1rem;margin-bottom:var(--space-lg)">Status</h3>
            <label class="checkbox-label" style="margin-bottom:var(--space-md)">
              <input type="checkbox" name="is_active" <?= ($edit_product['is_active'] ?? 1) ? 'checked' : '' ?>> Visible in shop
            </label>
            <label class="checkbox-label">
              <input type="checkbox" name="is_featured" <?= ($edit_product['is_featured'] ?? 0) ? 'checked' : '' ?>> Mark as Featured
            </label>
            <div style="margin-top:var(--space-xl)">
              <button type="submit" class="btn btn-amber btn-block">💾 Save Product</button>
            </div>
          </div>

          <!-- Inventory -->
          <div class="card" style="padding:var(--space-xl)">
            <h3 style="font-family:var(--font-display);font-size:1rem;margin-bottom:var(--space-lg)">Inventory</h3>
            <label class="checkbox-label" style="margin-bottom:var(--space-md)">
              <input type="checkbox" name="track_stock" id="trackStock" <?= ($edit_product['track_stock'] ?? 1) ? 'checked' : '' ?>> Track stock quantity
            </label>
            <div id="stockFields">
              <div class="form-group">
                <label class="form-label">Stock Quantity</label>
                <input type="number" name="stock_qty" class="form-input" min="0" placeholder="0" value="<?= $edit_product['stock_qty'] ?? 0 ?>">
              </div>
              <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Low Stock Alert At</label>
                <input type="number" name="low_stock_threshold" class="form-input" min="1" placeholder="5" value="<?= $edit_product['low_stock_threshold'] ?? 5 ?>">
              </div>
            </div>
          </div>

          <!-- Details -->
          <div class="card" style="padding:var(--space-xl)">
            <h3 style="font-family:var(--font-display);font-size:1rem;margin-bottom:var(--space-lg)">Details</h3>
            <div class="form-group">
              <label class="form-label">Weight (grams)</label>
              <input type="number" name="weight_grams" class="form-input" min="0" placeholder="e.g. 500" value="<?= $edit_product['weight_grams'] ?? '' ?>">
            </div>
            <div class="form-group" style="margin-bottom:var(--space-xl)">
              <label class="form-label">Serving Size</label>
              <input type="text" name="serving_size" class="form-input" placeholder="e.g. 8 slices" value="<?= sanitize($edit_product['serving_size'] ?? '') ?>">
            </div>
            <p style="font-size:0.8rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-muted);margin-bottom:var(--space-md)">Dietary Flags</p>
            <label class="checkbox-label" style="margin-bottom:10px"><input type="checkbox" name="is_vegan" <?= ($edit_product['is_vegan'] ?? 0) ? 'checked' : '' ?>> 🌿 Vegan</label>
            <label class="checkbox-label" style="margin-bottom:10px"><input type="checkbox" name="is_gluten_free" <?= ($edit_product['is_gluten_free'] ?? 0) ? 'checked' : '' ?>> 🌾 Gluten-Free</label>
            <label class="checkbox-label"><input type="checkbox" name="is_nut_free" <?= ($edit_product['is_nut_free'] ?? 0) ? 'checked' : '' ?>> 🥜 Nut-Free</label>
          </div>

        </div>
      </div>
    </form>
    <?php endif; ?>

  </main>
</div>

<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
// Toggle stock fields
const trackStock = document.getElementById('trackStock');
const stockFields = document.getElementById('stockFields');
if (trackStock) {
  function updateStockVis() {
    if (stockFields) stockFields.style.opacity = trackStock.checked ? '1' : '0.4';
  }
  trackStock.addEventListener('change', updateStockVis);
  updateStockVis();
}
// Select all checkbox
document.getElementById('selectAll')?.addEventListener('change', function() {
  document.querySelectorAll('.product-check').forEach(c => c.checked = this.checked);
});
</script>
</body>
</html>
