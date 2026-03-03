<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/auth.php';

/** cart count */
$cartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cartCount = array_sum($_SESSION['cart']);
}

$u = current_user();

/**
 * Filters (GET)
 * - q: keyword
 * - cat: category id
 * - sort: newest | price_asc | price_desc
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

// only active products
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
layout_header('Home', $u, $cartCount);
?>

<!-- HERO (combined: hero + features in same card) -->
<section class="card hero" style="margin-bottom:16px;">
  <div class="hero-row">
    <div>
      <div class="hero-badge">✨ New gifts • Secure wallet • Fast checkout</div>

      <h1>
        Find the perfect <span style="color:var(--accent);">gift</span> in minutes
        <span style="margin-left:8px;">🎁</span>
      </h1>


      <p>
        Browse fresh gifts & souvenirs, add to cart, checkout smoothly, and manage everything from your account.
      </p>

      <div class="hero-actions">
        <a class="btn btn-primary" href="#products">Browse products</a>
        <a class="btn" href="cart.php">🛒 Cart (<?= (int)$cartCount ?>)</a>

        <?php if ($u): ?>
          <?php if (($u['role'] ?? '') === 'admin'): ?>
            <a class="btn btn-ghost" href="admin/index.php">Admin Panel</a>
          <?php else: ?>
            <a class="btn btn-ghost" href="my_account.php">My Account</a>
          <?php endif; ?>
        <?php else: ?>
          <a class="btn btn-ghost" href="login.php">Login</a>
          <a class="btn btn-ghost" href="register.php">Register</a>
        <?php endif; ?>
      </div>

      <!-- Keep Hello line (inside left column) -->
      <?php if ($u): ?>
        <div style="margin-top:12px" class="muted">
          Hello, <strong style="color:inherit"><?= htmlspecialchars($u['name']) ?></strong>
          · <a href="logout.php" class="muted" style="text-decoration:underline">Logout</a>
        </div>
      <?php endif; ?>
    </div>

    <div class="hero-right" style="display:flex; justify-content:center; align-items:center;">
      <!-- Gift boxes image (size locked inline to avoid CSS cache issues) -->
      <div class="hero-visual"
           style="width:100%; max-width:560px; aspect-ratio:16/9; max-height:280px; overflow:hidden; border-radius:18px; border:1px solid var(--line); background:var(--surface2);">
        <img src="assets/hero-gifts.jpg"
             alt="Gift boxes"
             loading="eager"
             style="width:100%; height:100%; object-fit:cover; display:block;">
      </div>
    </div>
  </div>

  <!-- Divider + feature strip INSIDE hero card -->
  <hr style="border:0;height:1px;background:var(--line);margin:16px 0 12px;">

  <div class="hero-strip" style="display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap:12px;">
    <div style="border:1px solid var(--line); background:var(--surface2); border-radius:16px; padding:12px 14px;">
      <div style="font-weight:900; font-size:16px;">Secure Top-ups</div>
      <div class="muted" style="font-size:13px; margin-top:4px;">Cash / Payme / Click / Bank transfer</div>
    </div>

    <div style="border:1px solid var(--line); background:var(--surface2); border-radius:16px; padding:12px 14px;">
      <div style="font-weight:900; font-size:16px;">Real Orders</div>
      <div class="muted" style="font-size:13px; margin-top:4px;">Cart → Checkout → Order history</div>
    </div>

    <div style="border:1px solid var(--line); background:var(--surface2); border-radius:16px; padding:12px 14px;">
      <div style="font-weight:900; font-size:16px;">Admin Control</div>
      <div class="muted" style="font-size:13px; margin-top:4px;">Products • Categories • Approvals</div>
    </div>
  </div>

  <style>
    @media (max-width: 980px){
      .hero-strip{ grid-template-columns: 1fr !important; }
    }
    /* sparkles */
    .sparkle{
      position:absolute;
      left:50%;
      top:50%;
      width:10px;
      height:10px;
      border-radius:999px;
      background: radial-gradient(circle, rgba(255,255,255,.95), rgba(124,92,255,.65));
      transform: translate(-50%,-50%);
      animation: sparkleFly .9s ease forwards;
      pointer-events:none;
      filter: blur(.2px);
    }
    @keyframes sparkleFly{
      0%{ opacity:1; transform:translate(-50%,-50%) scale(1); }
      100%{ opacity:0; transform:translate(calc(-50% + var(--dx)), calc(-50% + var(--dy))) scale(.4); }
    }
    .gift-bounce{ animation: giftBounce .45s ease; }
    @keyframes giftBounce{
      0%{ transform: translateY(0) scale(1); }
      40%{ transform: translateY(-3px) scale(1.06); }
      100%{ transform: translateY(0) scale(1); }
    }
  </style>
</section>

<!-- PRODUCTS -->
<section id="products" class="products-section">
  <div style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:10px">
    <div>
      <div class="muted">Latest items</div>
      <h2 style="margin:6px 0 0">Featured products</h2>
    </div>
    <div class="pill">
      <?= count($products) ?> result<?= count($products) === 1 ? '' : 's' ?>
    </div>
  </div>

  <!-- Filters -->
  <form method="get" class="card" style="padding:12px; margin-bottom:12px;">
    <div class="grid" style="grid-template-columns: 1.4fr 1fr 1fr auto; gap:10px; align-items:end;">
      <div>
        <label class="muted" style="font-size:12px;">Search</label>
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search gifts, souvenirs..." />
      </div>

      <div>
        <label class="muted" style="font-size:12px;">Category</label>
        <select name="cat">
          <option value="">— All —</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ($catId === (int)$c['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="muted" style="font-size:12px;">Sort</label>
        <select name="sort">
          <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
          <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low → High</option>
          <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High → Low</option>
        </select>
      </div>

      <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <button class="btn btn-primary" type="submit">Apply</button>
        <a class="btn btn-ghost" href="index.php#products">Reset</a>
      </div>
    </div>
  </form>

  <?php if (!$products): ?>
    <div class="card" style="padding:16px;">
      <div style="font-weight:900; font-size:18px;">No products found</div>
      <div class="muted" style="margin-top:6px;">
        Try removing filters or searching a different keyword.
      </div>
    </div>
  <?php else: ?>
    <div class="grid grid-3">
      <?php foreach ($products as $p): ?>
        <a class="card product-card" href="product.php?id=<?= urlencode((string)$p['id']) ?>" style="display:block">
          <div class="product-media">
            <?php if (!empty($p['image'])): ?>
              <img
                src="image.php?f=<?= urlencode((string)$p['image']) ?>"
                alt=""
                style="width:100%;height:100%;object-fit:cover;display:block;"
              >
            <?php else: ?>
              <div>🖼️ No image</div>
            <?php endif; ?>
          </div>

          <div class="product-body">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:8px">
              <span class="pill"><?= htmlspecialchars($p['category_name'] ?? 'No category') ?></span>
              <span class="pill">Stock: <?= (int)$p['stock'] ?></span>
            </div>

            <div class="product-title"><?= htmlspecialchars($p['name']) ?></div>

            <?php if (!empty($p['description'])): ?>
              <div class="muted" style="margin:6px 0 10px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                <?= htmlspecialchars($p['description']) ?>
              </div>
            <?php else: ?>
              <div class="muted" style="margin:6px 0 10px;">No description.</div>
            <?php endif; ?>

            <div class="product-meta">
              <div class="muted">View details →</div>
              <div class="price">$<?= number_format((float)$p['price'], 2) ?></div>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php layout_footer(); ?>
