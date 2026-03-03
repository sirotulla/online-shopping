<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/auth.php';
require __DIR__ . '/_layout.php';

require_login();
$user = current_user();

/** Validate order_id from URL */
$orderId = filter_var($_GET['order_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
if (!$orderId) {
    http_response_code(400);
    layout_header('Order', $user, 0);
    echo '<div class="container"><div class="card" style="padding:16px">Invalid order.</div></div>';
    layout_footer();
    exit;
}

/** Load order (only if it belongs to this user) */
$stmt = $pdo->prepare("
  SELECT id, total, status, created_at
  FROM orders
  WHERE id = ? AND user_id = ?
  LIMIT 1
");
$stmt->execute([$orderId, (int)$user['id']]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    layout_header('Order', $user, 0);
    echo '<div class="container"><div class="card" style="padding:16px">Order not found.</div></div>';
    layout_footer();
    exit;
}

/** Load order items */
$stmt = $pdo->prepare("
  SELECT oi.quantity, oi.price, p.name, p.image
  FROM order_items oi
  JOIN products p ON p.id = oi.product_id
  WHERE oi.order_id = ?
");
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();

/** cart count (should be empty now, but safe) */
$cartCount = isset($_SESSION['cart']) && is_array($_SESSION['cart'])
    ? array_sum($_SESSION['cart'])
    : 0;

layout_header('Order Success', $user, $cartCount);
?>

<div class="container">

  <section class="card" style="padding:22px; text-align:center; max-width:760px; margin:0 auto;">
    <div style="font-size:56px; margin-bottom:6px;">🎉</div>

    <h1 style="margin:6px 0 6px;">Order placed successfully</h1>

    <div class="muted" style="margin-bottom:14px;">
      Thank you, <?= htmlspecialchars($user['name'], ENT_QUOTES) ?>.
      Your payment was successful and your order has been created.
    </div>

    <div class="pill" style="margin-bottom:14px;">
      Order #<?= (int)$order['id'] ?> • <?= htmlspecialchars($order['status']) ?>
    </div>

    <div style="font-size:34px; font-weight:950; margin-bottom:10px;">
      $<?= number_format((float)$order['total'], 2) ?>
    </div>

    <div class="muted" style="font-size:13px;">
      Placed on <?= htmlspecialchars($order['created_at']) ?>
    </div>
  </section>

  <div style="height:18px"></div>

  <section>
    <div style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:10px">
      <div>
        <div class="muted">What you bought</div>
        <h2 style="margin:6px 0 0">Order items</h2>
      </div>
    </div>

    <div class="grid grid-3">
      <?php foreach ($items as $it): ?>
        <div class="card product-card">
          <div class="product-media">
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

          <div class="product-body">
            <div class="product-title">
              <?= htmlspecialchars($it['name'], ENT_QUOTES) ?>
            </div>
            <div class="product-meta">
              <div class="muted">Qty: <?= (int)$it['quantity'] ?></div>
              <div class="price">$<?= number_format((float)$it['price'], 2) ?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <div style="height:20px"></div>

  <section class="card" style="padding:16px; text-align:center;">
    <div class="hero-actions" style="justify-content:center;">
      <a class="btn btn-primary" href="index.php">Continue shopping</a>
      <a class="btn" href="my_account.php">My orders</a>
    </div>
  </section>

</div>

<?php layout_footer(); ?>
