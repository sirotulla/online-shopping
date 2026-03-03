<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/auth.php';
require __DIR__ . '/_layout.php';

if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf']) || $_SESSION['csrf'] === '') {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/** Security: strict product id */
$idRaw = $_GET['id'] ?? '';
$id = filter_var($idRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    http_response_code(400);
    layout_header('Product', current_user(), 0);
    echo '<div class="card" style="padding:16px">Invalid product.</div>';
    layout_footer();
    exit;
}

$stmt = $pdo->prepare("
  SELECT p.*, c.name AS category_name
  FROM products p
  LEFT JOIN categories c ON c.id = p.category_id
  WHERE p.id = ? AND p.is_active = 1
  LIMIT 1
");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    layout_header('Not found', current_user(), 0);
    echo '<div class="card" style="padding:16px">Product not found.</div>';
    layout_footer();
    exit;
}

/** cart count */
$cartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cartCount = array_sum($_SESSION['cart']);
}

/** user */
$u = current_user();

/** UI quantity max: min(stock, 20) but at least 1 */
$stock = (int)($product['stock'] ?? 0);
$qtyMax = max(1, min(20, $stock > 0 ? $stock : 1));

/** Optional: related products (same category, excluding current) */
$related = [];
if (!empty($product['category_id'])) {
    $rel = $pdo->prepare("
      SELECT id, name, price, image
      FROM products
      WHERE category_id = ? AND id <> ? AND is_active = 1
      ORDER BY id DESC
      LIMIT 6
    ");
    $rel->execute([(int)$product['category_id'], (int)$product['id']]);
    $related = $rel->fetchAll();
}

layout_header((string)$product['name'], $u, $cartCount);
?>

<div style="margin-bottom:14px;">
  <a class="pill" href="index.php">← Back to shop</a>
</div>

<div class="grid" style="grid-template-columns: 1.1fr .9fr; align-items:start;">
  <!-- LEFT: Media -->
  <section class="card" style="overflow:hidden;">
    <div class="product-media" style="aspect-ratio: 16/10;">
      <?php if (!empty($product['image'])): ?>
        <img
          src="image.php?f=<?= urlencode((string)$product['image']) ?>"
          alt=""
          style="width:100%;height:100%;object-fit:cover;display:block;"
        >
      <?php else: ?>
        <div>🖼️ No image</div>
      <?php endif; ?>
    </div>

    <div style="padding:14px;">
      <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:space-between;">
        <div class="pill">
          <?= htmlspecialchars((string)($product['category_name'] ?? 'No category'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>

        <?php if ($stock > 0): ?>
          <div class="pill">✅ In stock: <?= (int)$stock ?></div>
        <?php else: ?>
          <div class="pill">❌ Out of stock</div>
        <?php endif; ?>
      </div>

      <h1 style="margin:12px 0 8px;">
        <?= htmlspecialchars((string)$product['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
      </h1>

      <?php if (!empty($product['description'])): ?>
        <p class="muted" style="margin:0; line-height:1.55;">
          <?= htmlspecialchars((string)$product['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </p>
      <?php else: ?>
        <p class="muted" style="margin:0;">No description.</p>
      <?php endif; ?>
    </div>
  </section>

  <!-- RIGHT: Purchase box -->
  <aside class="card" style="padding:16px;">
    <div class="muted">Price</div>
    <div style="font-size:34px; font-weight:950; margin:6px 0 10px;">
      $<?= number_format((float)$product['price'], 2) ?>
    </div>

    <div class="muted" style="margin-bottom:12px;">
      Secure checkout • Wallet supported • Orders tracked
    </div>

    <?php if ($stock > 0): ?>
      <form method="post" action="cart_add.php" style="display:grid; gap:10px;">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$_SESSION['csrf'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
        <input type="hidden" name="return" value="<?= htmlspecialchars((string)($_SERVER['REQUEST_URI'] ?? 'product.php?id='.(int)$product['id']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

        <label class="muted" for="qty" style="font-size:13px;">Quantity</label>
        <input
          id="qty"
          type="number"
          name="qty"
          value="1"
          min="1"
          max="<?= (int)$qtyMax ?>"
          inputmode="numeric"
          style="
            padding:12px 12px;
            border-radius:14px;
            border:1px solid rgba(255,255,255,.12);
            background: rgba(255,255,255,.04);
            color: inherit;
            outline: none;
          "
        >

        <button type="submit" class="btn btn-primary" style="width:100%;">
          Add to cart
        </button>

        <a class="btn" href="cart.php" style="width:100%;">Go to cart</a>

        <div class="muted" style="font-size:12px; line-height:1.5;">
          We’ll confirm stock at checkout.
        </div>
      </form>
    <?php else: ?>
      <div class="pill" style="display:inline-flex; margin-bottom:10px;">Currently unavailable</div>
      <div class="muted" style="line-height:1.5;">
        This item is out of stock right now. Please check back later.
      </div>
    <?php endif; ?>
  </aside>
</div>

<?php if (!empty($related)): ?>
  <div style="height:16px"></div>

  <section>
    <div style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:10px">
      <div>
        <div class="muted">You may also like</div>
        <h2 style="margin:6px 0 0">Related products</h2>
      </div>
      <div class="pill">Same category</div>
    </div>

    <div class="grid grid-3">
      <?php foreach ($related as $rp): ?>
        <a class="card product-card" href="product.php?id=<?= urlencode((string)$rp['id']) ?>" style="display:block">
          <div class="product-media">
            <?php if (!empty($rp['image'])): ?>
              <img
                src="image.php?f=<?= urlencode((string)$rp['image']) ?>"
                alt=""
                style="width:100%;height:100%;object-fit:cover;display:block;"
              >
            <?php else: ?>
              <div>🖼️ No image</div>
            <?php endif; ?>
          </div>
          <div class="product-body">
            <div class="product-title"><?= htmlspecialchars((string)$rp['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <div class="product-meta">
              <div class="muted">View →</div>
              <div class="price">$<?= number_format((float)$rp['price'], 2) ?></div>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<style>
/* Responsive fix for the 2-column product layout */
@media (max-width: 860px){
  .grid[style*="grid-template-columns"]{ grid-template-columns: 1fr !important; }
}
</style>

<?php layout_footer(); ?>
