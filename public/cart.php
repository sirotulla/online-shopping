<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/auth.php';
require __DIR__ . '/_layout.php';

if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$cart = $_SESSION['cart'] ?? [];
if (!is_array($cart)) $cart = [];

/** cart count */
$cartCount = 0;
if (is_array($cart)) {
    $cartCount = array_sum(array_map('intval', $cart));
}

$u = current_user();

/** Handle actions: update qty / remove */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'], $csrf)) {
        http_response_code(403);
        exit('Request blocked');
    }

    $action = (string)($_POST['action'] ?? '');

    $productId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);
    if (!$productId) {
        http_response_code(400);
        exit('Invalid request');
    }
    $productId = (int)$productId;

    if ($action === 'remove') {
        unset($cart[$productId]);
    } elseif ($action === 'update') {
        $quantity = filter_var($_POST['qty'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 20]
        ]);
        if (!$quantity) {
            http_response_code(400);
            exit('Invalid quantity');
        }
        $quantity = (int)$quantity;

        // Check stock server-side
        $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ? LIMIT 1");
        $stmt->execute([$productId]);
        $row = $stmt->fetch();

        if (!$row) {
            // Product removed from DB -> remove from cart
            unset($cart[$productId]);
        } else {
            $stock = (int)($row['stock'] ?? 0);
            if ($stock <= 0) {
                unset($cart[$productId]);
            } else {
                if ($quantity > $stock) $quantity = $stock;
                $cart[$productId] = $quantity;
            }
        }
    } else {
        http_response_code(400);
        exit('Invalid request');
    }

    $_SESSION['cart'] = $cart;

    // Rotate CSRF after state change (good practice)
    $_SESSION['csrf'] = bin2hex(random_bytes(32));

    header('Location: cart.php', true, 303);
    exit;
}

/** Load products for cart display (prices always from DB) */
$items = [];
$total = 0.0;

$productIds = array_keys($cart);
$productIds = array_values(array_filter($productIds, fn($v) => is_int($v) || ctype_digit((string)$v)));

if (count($productIds) > 0) {
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));

    // Pull only what we need (add image for premium UI)
    $stmt = $pdo->prepare("SELECT id, name, price, stock, image FROM products WHERE id IN ($placeholders)");
    $stmt->execute($productIds);
    $products = $stmt->fetchAll();

    $map = [];
    foreach ($products as $p) {
        $map[(int)$p['id']] = $p;
    }

    foreach ($productIds as $pid) {
        $pid = (int)$pid;
        if (!isset($map[$pid])) {
            continue; // product deleted
        }

        $qty = isset($cart[$pid]) ? (int)$cart[$pid] : 0;
        if ($qty < 1) continue;

        $price = (float)$map[$pid]['price'];
        $line  = $price * $qty;
        $total += $line;

        $items[] = [
            'id'    => $pid,
            'name'  => (string)$map[$pid]['name'],
            'price' => $price,
            'qty'   => $qty,
            'line'  => $line,
            'stock' => (int)$map[$pid]['stock'],
            'image' => (string)($map[$pid]['image'] ?? ''),
        ];
    }
}

layout_header('Cart', $u, $cartCount);
?>

<div class="container">

  <div style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:12px">
    <div>
      <div class="muted">Your selections</div>
      <h1 style="margin:6px 0 0">Shopping Cart</h1>
    </div>
    <a class="pill" href="index.php">← Continue shopping</a>
  </div>

  <?php if (count($items) === 0): ?>
    <div class="card" style="padding:16px;">
      <div class="muted">Your cart is empty.</div>
      <div style="margin-top:12px;">
        <a class="btn btn-primary" href="index.php">Browse products</a>
      </div>
    </div>
  <?php else: ?>

    <div class="grid" style="grid-template-columns: 1.2fr .8fr; align-items:start;">
      <!-- LEFT: Items -->
      <section class="card" style="padding:14px;">
        <?php foreach ($items as $it): ?>
          <div style="display:flex; gap:12px; padding:12px; border:1px solid rgba(255,255,255,.10); border-radius:18px; background: rgba(255,255,255,.03); margin-bottom:12px;">
            <a href="product.php?id=<?= urlencode((string)$it['id']) ?>" style="width:120px; flex: 0 0 120px;">
              <div class="product-media" style="aspect-ratio: 4/3; border-radius:14px; overflow:hidden; border:1px solid rgba(255,255,255,.10);">
                <?php if (!empty($it['image'])): ?>
                  <img
                    src="image.php?f=<?= urlencode($it['image']) ?>"
                    alt=""
                    style="width:100%;height:100%;object-fit:cover;display:block;"
                  >
                <?php else: ?>
                  <div>🖼️</div>
                <?php endif; ?>
              </div>
            </a>

            <div style="flex:1; min-width: 220px;">
              <div style="display:flex; justify-content:space-between; gap:12px; align-items:start;">
                <div>
                  <div style="font-weight:950; font-size:16px;">
                    <?= htmlspecialchars($it['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                  </div>
                  <div class="muted" style="margin-top:4px;">
                    Stock: <?= (int)$it['stock'] ?>
                  </div>
                </div>

                <div style="text-align:right;">
                  <div class="muted" style="font-size:12px;">Price</div>
                  <div style="font-weight:950;">$<?= number_format($it['price'], 2) ?></div>
                </div>
              </div>

              <div style="display:flex; justify-content:space-between; align-items:end; gap:12px; margin-top:10px; flex-wrap:wrap;">
                <form method="post" style="display:flex; gap:8px; align-items:center;">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">

                  <span class="muted" style="font-size:13px;">Qty</span>
                  <input
                    type="number"
                    name="qty"
                    min="1"
                    max="20"
                    value="<?= (int)$it['qty'] ?>"
                    inputmode="numeric"
                    style="
                      width:90px;
                      padding:10px 10px;
                      border-radius:14px;
                      border:1px solid rgba(255,255,255,.12);
                      background: rgba(255,255,255,.04);
                      color: inherit;
                      outline: none;
                    "
                  >
                  <button class="btn" type="submit">Update</button>
                </form>

                <div style="text-align:right;">
                  <div class="muted" style="font-size:12px;">Line total</div>
                  <div style="font-weight:950;">$<?= number_format($it['line'], 2) ?></div>
                </div>

                <form method="post" style="margin-left:auto;">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                  <button class="btn btn-ghost" type="submit">Remove</button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>

        <div class="muted" style="font-size:12px; line-height:1.5;">
          Security note: totals are calculated from database prices (not from the browser).
        </div>
      </section>

      <!-- RIGHT: Summary -->
      <aside class="card" style="padding:16px;">
        <div class="muted">Order summary</div>
        <div style="font-size:30px; font-weight:950; margin:6px 0 10px;">
          $<?= number_format($total, 2) ?>
        </div>

        <div class="muted" style="line-height:1.6; margin-bottom:12px;">
          Checkout will re-validate stock and prices before creating the order.
        </div>

        <a class="btn btn-primary" style="width:100%;" href="checkout.php">Go to checkout →</a>
        <div style="height:10px"></div>
        <a class="btn" style="width:100%;" href="index.php">Add more items</a>

        <div style="margin-top:12px;">
          <div class="pill">CSRF protected</div>
          <div class="pill" style="margin-left:6px;">Stock checked</div>
        </div>
      </aside>
    </div>

    <style>
      @media (max-width: 900px){
        .grid[style*="grid-template-columns"]{ grid-template-columns: 1fr !important; }
      }
    </style>

  <?php endif; ?>

</div>

<?php layout_footer(); ?>
