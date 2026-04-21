<?php
declare(strict_types=1);

// session_start() is now handled inside lang.php/auth.php, 
// but we keep it safe here or ensure layout handles it.
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/lang.php'; // Ensure lang helper is loaded

/** cart count */
$cartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cartCount = array_sum($_SESSION['cart']);
}

$u = current_user();

/**
 * Filters (GET)
 */
$q = trim((string)($_GET['q'] ?? ''));
$cat = trim((string)($_GET['cat'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'newest');

$allowedSort = ['newest', 'price_asc', 'price_desc'];
if (!in_array($sort, $allowedSort, true)) {
    $sort = 'newest';
}

// Load categories
$cats = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

// Build query safely
$where = [];
$params = [];

$where[] = "p.is_active = 1";

if ($q !== '') {
    $where[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}

$catId = null;
if ($cat !== '' && ctype_digit($cat)) {
    $catId = (int)$cat;
    $where[] = "p.category_id = ?";
    $params[] = $catId;
}

$orderBy = "p.id DESC";
if ($sort === 'price_asc') $orderBy = "p.price ASC, p.id DESC";
if ($sort === 'price_desc') $orderBy = "p.price DESC, p.id DESC";

$sql = "
  SELECT p.id, p.name, p.description, p.price, p.stock, p.image, c.name AS category_name
  FROM products p
  LEFT JOIN categories c ON c.id = p.category_id
";

if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY $orderBy";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

require __DIR__ . '/_layout.php';
layout_header(__('nav_home'), $u, $cartCount);
?>

<section class="card hero" style="margin-bottom:16px;">
  <div class="hero-row">
    <div>
      <div class="hero-badge">✨ <?= __('hero_badge') ?></div>

      <h1>
        <?= __('hero_title_part1') ?> <span style="color:var(--accent);"><?= __('hero_title_accent') ?></span> <?= __('hero_title_part2') ?>
        <span style="margin-left:8px;">🎁</span>
      </h1>

      <p><?= __('hero_desc') ?></p>

      <div class="hero-actions">
        <a class="btn btn-primary" href="#products"><?= __('btn_browse') ?></a>
        <a class="btn" href="cart.php">🛒 <?= __('nav_cart') ?> (<?= (int)$cartCount ?>)</a>

        <?php if ($u): ?>
          <?php if (($u['role'] ?? '') === 'admin'): ?>
            <a class="btn btn-ghost" href="admin/index.php"><?= __('nav_admin') ?></a>
          <?php else: ?>
            <a class="btn btn-ghost" href="my_account.php"><?= __('nav_account') ?></a>
          <?php endif; ?>
        <?php else: ?>
          <a class="btn btn-ghost" href="login.php"><?= __('nav_login') ?></a>
          <a class="btn btn-ghost" href="register.php"><?= __('nav_register') ?></a>
        <?php endif; ?>
      </div>

      <?php if ($u): ?>
        <div style="margin-top:12px" class="muted">
          <?= __('hello') ?>, <strong style="color:inherit"><?= htmlspecialchars($u['name']) ?></strong>
          · <a href="logout.php" class="muted" style="text-decoration:underline"><?= __('nav_logout') ?></a>
        </div>
      <?php endif; ?>
    </div>

    <div class="hero-right" style="display:flex; justify-content:center; align-items:center;">
      <div class="hero-visual"
           style="width:100%; max-width:560px; aspect-ratio:16/9; max-height:280px; overflow:hidden; border-radius:18px; border:1px solid var(--line); background:var(--surface2);">
        <img src="assets/hero-gifts.jpg" alt="Gift boxes" loading="eager"
             style="width:100%; height:100%; object-fit:cover; display:block;">
      </div>
    </div>
  </div>

  <hr style="border:0;height:1px;background:var(--line);margin:16px 0 12px;">

  <!-- <div class="hero-strip" style="display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap:12px;">
    <div style="border:1px solid var(--line); background:var(--surface2); border-radius:16px; padding:12px 14px;">
      <div style="font-weight:900; font-size:16px;"><?= __('feat_secure_title') ?></div>
      <div class="muted" style="font-size:13px; margin-top:4px;"><?= __('feat_secure_desc') ?></div>
    </div>

    <div style="border:1px solid var(--line); background:var(--surface2); border-radius:16px; padding:12px 14px;">
      <div style="font-weight:900; font-size:16px;"><?= __('feat_orders_title') ?></div>
      <div class="muted" style="font-size:13px; margin-top:4px;"><?= __('feat_orders_desc') ?></div>
    </div>

    <div style="border:1px solid var(--line); background:var(--surface2); border-radius:16px; padding:12px 14px;">
      <div style="font-weight:900; font-size:16px;"><?= __('feat_admin_title') ?></div>
      <div class="muted" style="font-size:13px; margin-top:4px;"><?= __('feat_admin_desc') ?></div>
    </div>
  </div> -->
</section>

<section id="products" class="products-section">
  <div style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:10px">
    <div>
      <div class="muted"><?= __('latest_items') ?></div>
      <h2 style="margin:6px 0 0"><?= __('featured_products') ?></h2>
    </div>
    <div class="pill">
      <?= count($products) ?> <?= count($products) === 1 ? __('result_singular') : __('result_plural') ?>
    </div>
  </div>

  <form method="get" class="card" style="padding:12px; margin-bottom:12px;">
    <div class="grid" style="grid-template-columns: 1.4fr 1fr 1fr auto; gap:10px; align-items:end;">
      <div>
        <label class="muted" style="font-size:12px;"><?= __('label_search') ?></label>
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="<?= __('placeholder_search') ?>" />
      </div>

      <div>
        <label class="muted" style="font-size:12px;"><?= __('label_category') ?></label>
        <select name="cat">
          <option value="">— <?= __('all_categories') ?> —</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ($catId === (int)$c['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="muted" style="font-size:12px;"><?= __('label_sort') ?></label>
        <select name="sort">
          <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>><?= __('sort_newest') ?></option>
          <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>><?= __('sort_price_asc') ?></option>
          <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>><?= __('sort_price_desc') ?></option>
        </select>
      </div>

      <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <button class="btn btn-primary" type="submit"><?= __('btn_apply') ?></button>
        <a class="btn btn-ghost" href="index.php#products"><?= __('btn_reset') ?></a>
      </div>
    </div>
  </form>

  <?php if (!$products): ?>
    <div class="card" style="padding:16px;">
      <div style="font-weight:900; font-size:18px;"><?= __('no_products') ?></div>
      <div class="muted" style="margin-top:6px;"><?= __('no_products_desc') ?></div>
    </div>
  <?php else: ?>
    <div class="grid grid-3">
      <?php foreach ($products as $p): ?>
        <a class="card product-card" href="product.php?id=<?= urlencode((string)$p['id']) ?>" style="display:block">
          <div class="product-media">
            <?php if (!empty($p['image'])): ?>
              <img src="image.php?f=<?= urlencode((string)$p['image']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
            <?php else: ?>
              <div>🖼️ <?= __('no_image') ?></div>
            <?php endif; ?>
          </div>

          <div class="product-body">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:8px">
              <span class="pill"><?= htmlspecialchars($p['category_name'] ?? __('no_category')) ?></span>
              <span class="pill"><?= __('stock') ?>: <?= (int)$p['stock'] ?></span>
            </div>
            <div class="product-title"><?= htmlspecialchars($p['name']) ?></div>
            <div class="muted" style="margin:6px 0 10px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                <?= !empty($p['description']) ? htmlspecialchars($p['description']) : __('no_description') ?>
            </div>
            <div class="product-meta">
              <div class="muted"><?= __('view_details') ?> →</div>
              <div class="price">$<?= number_format((float)$p['price'], 2) ?></div>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php layout_footer(); ?>
