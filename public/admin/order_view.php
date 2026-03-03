<?php
declare(strict_types=1);

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/_layout_admin.php';

$id = $_GET['id'] ?? '';
if (!ctype_digit((string)$id)) {
    http_response_code(400);
    die('Invalid order id');
}
$orderId = (int)$id;

// Load order
$stmt = $pdo->prepare("
  SELECT o.*, u.email AS user_email
  FROM orders o
  LEFT JOIN users u ON u.id = o.user_id
  WHERE o.id = ?
  LIMIT 1
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();
if (!$order) {
    http_response_code(404);
    die('Order not found');
}

// Load items
$stmt = $pdo->prepare("
  SELECT oi.product_id, oi.price, oi.quantity,
         p.name AS product_name
  FROM order_items oi
  LEFT JOIN products p ON p.id = oi.product_id
  WHERE oi.order_id = ?
  ORDER BY oi.id ASC
");
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();

$total = 0.0;
foreach ($items as $it) {
    $total += ((float)$it['price']) * ((int)$it['quantity']);
}

$status = (string)($order['status'] ?? 'pending');
$statusLabel = $status;
if ($status === 'pending') $statusLabel = 'Pending';
if ($status === 'paid') $statusLabel = 'Paid';
if ($status === 'shipped') $statusLabel = 'Shipped';
if ($status === 'cancelled') $statusLabel = 'Cancelled';

admin_layout_header('Admin — Order #' . (string)$orderId, $_SESSION['user'] ?? null);
?>

<div style="max-width:1100px; margin:0 auto;">

  <div style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:12px">
    <div>
      <div class="muted">Sales</div>
      <h1 style="margin:6px 0 0">Order details</h1>
    </div>
    <a class="pill" href="orders.php">← Back to orders</a>
  </div>

  <div class="grid" style="grid-template-columns: 1fr .7fr; gap:14px; align-items:start;">

    <!-- Order summary -->
    <section class="card" style="padding:16px;">
      <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap;">
        <div>
          <div class="muted">Order</div>
          <div style="font-size:22px; font-weight:950; margin-top:4px;">#<?= (int)$orderId ?></div>
        </div>
        <div>
          <div class="muted" style="font-size:12px;">Status</div>
          <div class="pill" style="margin-top:6px; display:inline-flex;"><?= e($statusLabel) ?></div>
        </div>
      </div>

      <div style="height:12px;"></div>

      <div class="card" style="padding:12px; background:rgba(255,255,255,.03);">
        <div class="muted" style="font-size:12px;">Customer</div>
        <div style="font-weight:850; margin-top:6px;">
          <?= e((string)($order['user_email'] ?? ('User #' . (int)$order['user_id']))) ?>
        </div>

        <div class="muted" style="font-size:12px; margin-top:10px;">Created</div>
        <div><?= e((string)$order['created_at']) ?></div>
      </div>
    </section>

    <!-- Totals -->
    <aside class="card" style="padding:16px;">
      <div class="muted">Totals</div>
      <div style="font-size:34px; font-weight:950; margin-top:6px;">
        $<?= number_format((float)$order['total'], 2) ?>
      </div>
      <div class="muted" style="font-size:12px; margin-top:6px;">Stored order total</div>

      <div style="height:12px;"></div>

      <div class="card" style="padding:12px; background:rgba(255,255,255,.03);">
        <div class="muted" style="font-size:12px;">Calculated from items</div>
        <div style="font-size:20px; font-weight:900; margin-top:6px;">
          $<?= number_format((float)$total, 2) ?>
        </div>
        <div class="muted" style="font-size:12px; margin-top:8px; line-height:1.5;">
          If these differ, it usually means prices changed after order creation or an item was removed.
        </div>
      </div>
    </aside>

  </div>

  <div style="height:14px;"></div>

  <!-- Items -->
  <section class="card" style="padding:14px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
      <div class="pill"><?= count($items) ?> items</div>
      <div class="muted" style="font-size:12px;">Order line items</div>
    </div>

    <div class="table-wrap" style="margin-top:10px;">
      <table>
        <thead>
          <tr>
            <th>Product</th>
            <th class="right" style="width:140px;">Price</th>
            <th class="right" style="width:120px;">Qty</th>
            <th class="right" style="width:160px;">Line</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $it): ?>
            <?php $line = (float)$it['price'] * (int)$it['quantity']; ?>
            <tr>
              <td style="font-weight:850;">
                <?= e((string)($it['product_name'] ?? ('Product #' . (int)$it['product_id']))) ?>
              </td>
              <td class="right">$<?= number_format((float)$it['price'], 2) ?></td>
              <td class="right"><?= (int)$it['quantity'] ?></td>
              <td class="right">$<?= number_format((float)$line, 2) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

</div>

<style>
@media (max-width: 900px){
  .grid[style*="grid-template-columns"]{ grid-template-columns: 1fr !important; }
}
</style>

<?php admin_layout_footer(); ?>
