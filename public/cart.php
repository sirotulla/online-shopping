<?php
declare(strict_types=1);

// session_start() is inside lang.php/auth.php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/lang.php'; // Added lang helper
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
    $_SESSION['csrf'] = bin2hex(random_bytes(32));

    header('Location: cart.php', true, 303);
    exit;
}

/** Load products for cart display */
$items = [];
$total = 0.0;

$productIds = array_keys($cart);
$productIds = array_values(array_filter($productIds, fn($v) => is_int($v) || ctype_digit((string)$v)));

if (count($productIds) > 0) {
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $pdo->prepare("SELECT id, name, price, stock, image FROM products WHERE id IN ($placeholders)");
    $stmt->execute($productIds);
    $products = $stmt->fetchAll();

    $map = [];
    foreach ($products as $p) {
        $map[(int)$p['id']] = $p;
    }

    foreach ($productIds as $pid) {
        $pid = (int)$pid;
        if (!isset($map[$pid])) continue;

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

layout_header(__('nav_cart'), $u, $cartCount);
?>

<div class="container">

  <div style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:12px">
    <div>
      <div class="muted"><?= __('cart_selections') ?></div>
      <h1 style="margin:6px 0 0"><?= __('cart_title') ?></h1>
    </div>
    <a class="pill" href="index.php">← <?= __('btn_continue_shopping') ?></a>
  </div>

  <?php if (count($items) === 0): ?>
    <div class="card" style="padding:16px;">
      <div class="muted"><?= __('cart_empty') ?></div>
      <div style="margin-top:12px;">
        <a class="btn btn-primary" href="index.php"><?= __('btn_browse') ?></a>
      </div>
    </div>
  <?php else: ?>

    <div class="grid" style="grid-template-columns: 1.2fr .8fr; align-items:start;">
      <section class="card" style="padding:14px;">
        <?php foreach ($items as $it): ?>
          <div style="display:flex; gap:12px; padding:12px; border:1px solid rgba(255,255,255,.10); border-radius:18px; background: rgba(255,255,255,.03); margin-bottom:12px;">
            <a href="product.php?id=<?= urlencode((string)$it['id']) ?>" style="width:120px; flex: 0 0 120px;">
              <div class="product-media" style="aspect-ratio: 4/3; border-radius:14px; overflow:hidden; border:1px solid rgba(255,255,255,.10);">
                <?php if (!empty($it['image'])): ?>
                  <img src="image.php?f=<?= urlencode($it['image']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
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
                    <?= __('stock') ?>: <?= (int)$it['stock'] ?>
                  </div>
                </div>

                <div style="text-align:right;">
                  <div class="muted" style="font-size:12px;"><?= __('label_price') ?></div>
                  <div style="font-weight:950;">$<?= number_format($it['price'], 2) ?></div>
                </div>
              </div>

              <div style="display:flex; justify-content:space-between; align-items:end; gap:12px; margin-top:10px; flex-wrap:wrap;">
                <form method="post" style="display:flex; gap:8px; align-items:center;">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">

                  <span class="muted" style="font-size:13px;"><?= __('label_qty') ?></span>
                  <input type="number" name="qty" min="1" max="20" value="<?= (int)$it['qty'] ?>" inputmode="numeric"
                    style="width:90px; padding:10px 10px; border-radius:14px; border:1px solid rgba(255,255,255,.12); background: rgba(255,255,255,.04); color: inherit; outline: none;">
                  <button class="btn" type="submit"><?= __('btn_update') ?></button>
                </form>

                <div style="text-align:right;">
                  <div class="muted" style="font-size:12px;"><?= __('label_line_total') ?></div>
                  <div style="font-weight:950;">$<?= number_format($it['line'], 2) ?></div>
                </div>

                <form method="post" style="margin-left:auto;">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                  <button class="btn btn-ghost" type="submit"><?= __('btn_remove') ?></button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>

        <div class="muted" style="font-size:12px; line-height:1.5;">
          <?= __('cart_security_note') ?>
        </div>
      </section>

      <aside class="card" style="padding:16px;">
        <div class="muted"><?= __('order_summary') ?></div>
        <div style="font-size:30px; font-weight:950; margin:6px 0 10px;">
          $<?= number_format($total, 2) ?>
        </div>

        <div class="muted" style="line-height:1.6; margin-bottom:12px;">
          <?= __('cart_checkout_note') ?>
        </div>

        <a class="btn btn-primary" style="width:100%;" href="checkout.php"><?= __('btn_go_checkout') ?> →</a>
        <div style="height:10px"></div>
        <a class="btn" style="width:100%;" href="index.php"><?= __('btn_add_more') ?></a>

        <div style="margin-top:12px;">
          <div class="pill"><?= __('pill_csrf') ?></div>
          <div class="pill" style="margin-left:6px;"><?= __('pill_stock') ?></div>
        </div>
      </aside>
    </div>
  <?php endif; ?>

</div>

<?php layout_footer(); ?>
