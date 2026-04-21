<?php
declare(strict_types=1);

// session_start() is inside lang.php/auth.php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/lang.php'; // Added lang helper
require __DIR__ . '/_layout.php';

require_login();
$user = current_user();

if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$err = '';

$cart = $_SESSION['cart'] ?? [];
if (!is_array($cart) || count($cart) === 0) {
    http_response_code(400);
    layout_header(__('nav_checkout'), $user, 0);
    echo '<div class="container"><div class="card" style="padding:16px">' . __('cart_empty') . '</div></div>';
    layout_footer();
    exit;
}

/** cart count */
$cartCount = array_sum(array_map('intval', $cart));

/** Load products from DB */
$productIds = array_keys($cart);
$productIds = array_values(array_filter($productIds, fn($v) => is_int($v) || ctype_digit((string)$v)));
if (count($productIds) === 0) {
    http_response_code(400);
    layout_header(__('nav_checkout'), $user, $cartCount);
    echo '<div class="container"><div class="card" style="padding:16px">' . __('cart_empty') . '</div></div>';
    layout_footer();
    exit;
}

$placeholders = implode(',', array_fill(0, count($productIds), '?'));
$stmt = $pdo->prepare("SELECT id, name, price, stock, image FROM products WHERE id IN ($placeholders)");
$stmt->execute($productIds);
$products = $stmt->fetchAll();

$map = [];
foreach ($products as $p) {
    $map[(int)$p['id']] = $p;
}

/** Build checkout items */
$items = [];
$total = 0.0;

foreach ($productIds as $pid) {
    $pid = (int)$pid;
    if (!isset($map[$pid])) continue;

    $qty = isset($cart[$pid]) ? (int)$cart[$pid] : 0;
    if ($qty < 1) continue;

    $stock = (int)($map[$pid]['stock'] ?? 0);
    if ($stock <= 0) continue;

    if ($qty > $stock) $qty = $stock;

    $price = (float)$map[$pid]['price'];
    $line  = $price * $qty;
    $total += $line;

    $items[] = [
        'id'    => $pid,
        'name'  => (string)$map[$pid]['name'],
        'price' => $price,
        'qty'   => $qty,
        'stock' => $stock,
        'line'  => $line,
        'image' => (string)($map[$pid]['image'] ?? ''),
    ];
}

if (count($items) === 0) {
    http_response_code(400);
    layout_header(__('nav_checkout'), $user, $cartCount);
    echo '<div class="container"><div class="card" style="padding:16px">' . __('checkout_no_items') . '</div></div>';
    layout_footer();
    exit;
}

/** Handle order placement */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'], $csrf)) {
        http_response_code(403);
        $err = __('err_csrf');
    } else {
        try {
            $pdo->beginTransaction();

            $userId = (int)($user['id'] ?? 0);
            if ($userId < 1) {
                throw new RuntimeException('User not found');
            }

            $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? LIMIT 1 FOR UPDATE");
            $stmt->execute([$userId]);
            $row = $stmt->fetch();

            if (!$row) {
                throw new RuntimeException('User not found');
            }

            $balance = (float)$row['balance'];

            if ($balance + 0.00001 < $total) {
                throw new RuntimeException('Insufficient balance');
            }

            $stmt = $pdo->prepare("INSERT INTO orders (user_id, total, status) VALUES (?, ?, 'pending')");
            $stmt->execute([$userId, $total]);
            $orderId = (int)$pdo->lastInsertId();

            $itemStmt  = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            $stockStmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");

            foreach ($items as $it) {
                $itemStmt->execute([$orderId, $it['id'], $it['qty'], $it['price']]);
                $stockStmt->execute([$it['qty'], $it['id'], $it['qty']]);
                if ($stockStmt->rowCount() !== 1) {
                    throw new RuntimeException('Stock update failed');
                }
            }

            $newBalance = $balance - $total;
            $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ? LIMIT 1");
            $stmt->execute([$newBalance, $userId]);

            $stmt = $pdo->prepare("
              INSERT INTO wallet_transactions (user_id, type, amount, note, created_by_admin_id)
              VALUES (?, 'purchase', ?, ?, NULL)
            ");
            $stmt->execute([$userId, -$total, 'Order #' . $orderId]);

            $pdo->commit();

            $_SESSION['cart'] = [];
            $_SESSION['csrf'] = bin2hex(random_bytes(32));

            header('Location: checkout_success.php?order_id=' . urlencode((string)$orderId), true, 303);
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();

            if ($e instanceof RuntimeException && $e->getMessage() === 'Insufficient balance') {
                $err = __('err_insufficient_balance');
            } else {
                $err = __('err_checkout_failed');
            }
        }
    }
}

layout_header(__('nav_checkout'), $user, $cartCount);
?>

<div class="container">

  <div style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:12px">
    <div>
      <div class="muted"><?= __('checkout_final_step') ?></div>
      <h1 style="margin:6px 0 0"><?= __('nav_checkout') ?></h1>
    </div>
    <a class="pill" href="cart.php">← <?= __('btn_back_cart') ?></a>
  </div>

  <?php if ($err !== ''): ?>
    <div class="card" style="padding:14px; border-color: rgba(255,92,122,.35); background: rgba(255,92,122,.08); margin-bottom:12px;">
      <?= htmlspecialchars($err, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <div class="grid" style="grid-template-columns: 1.2fr .8fr; align-items:start;">

    <section class="card" style="padding:14px;">
      <div class="muted" style="margin-bottom:10px;"><?= __('checkout_items_verified') ?></div>

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

          <div style="flex:1; min-width:220px;">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:start;">
              <div>
                <div style="font-weight:950; font-size:16px;">
                  <?= htmlspecialchars($it['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </div>
                <div class="muted" style="margin-top:4px;"><?= __('label_qty') ?>: <?= (int)$it['qty'] ?></div>
              </div>

              <div style="text-align:right;">
                <div class="muted" style="font-size:12px;"><?= __('label_line_total') ?></div>
                <div style="font-weight:950;">$<?= number_format($it['line'], 2) ?></div>
              </div>
            </div>

            <div class="muted" style="margin-top:8px; font-size:12px;">
              <?= __('label_price') ?>: $<?= number_format($it['price'], 2) ?> • <?= __('pill_stock') ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </section>

    <aside class="card" style="padding:16px;">
      <div class="muted"><?= __('order_summary') ?></div>
      <div style="font-size:34px; font-weight:950; margin:6px 0 10px;">
        $<?= number_format($total, 2) ?>
      </div>

      <div class="muted" style="line-height:1.6; margin-bottom:12px;">
        <?= __('checkout_deduction_note') ?>
      </div>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <button class="btn btn-primary" type="submit" style="width:100%;"><?= __('btn_place_order') ?></button>
      </form>

      <div style="height:10px"></div>
      <a class="btn" style="width:100%;" href="my_account.php"><?= __('btn_view_wallet') ?></a>

      <div style="margin-top:12px;">
        <div class="pill"><?= __('pill_csrf') ?></div>
        <div class="pill" style="margin-left:6px;"><?= __('pill_db_totals') ?></div>
      </div>
    </aside>
  </div>

  <style>
    @media (max-width: 900px){
      .grid[style*="grid-template-columns"]{ grid-template-columns: 1fr !important; }
    }
  </style>

</div>

<?php layout_footer(); ?>
