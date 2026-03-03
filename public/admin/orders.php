<?php
declare(strict_types=1);

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/_layout_admin.php';

if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$allowedStatuses = ['pending', 'paid', 'shipped', 'cancelled'];

// Handle status update (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'], $csrf)) {
        http_response_code(403);
        die('Invalid CSRF token');
    }

    $id = (string)($_POST['id'] ?? '');
    $status = (string)($_POST['status'] ?? '');

    if (!ctype_digit($id)) {
        http_response_code(400);
        die('Invalid order id');
    }
    if (!in_array($status, $allowedStatuses, true)) {
        http_response_code(400);
        die('Invalid status');
    }

    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ? LIMIT 1");
    $stmt->execute([$status, (int)$id]);

    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    header('Location: orders.php', true, 303);
    exit;
}

// List orders
$stmt = $pdo->query("
  SELECT o.id, o.user_id, o.total, o.status, o.created_at,
         u.email AS user_email
  FROM orders o
  LEFT JOIN users u ON u.id = o.user_id
  ORDER BY o.id DESC
");
$orders = $stmt->fetchAll();

admin_layout_header('Admin — Orders', $_SESSION['user'] ?? null);
?>

<div style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:12px">
  <div>
    <div class="muted">Sales</div>
    <h1 style="margin:6px 0 0">Orders</h1>
  </div>
  <div class="pill"><?= count($orders) ?> total</div>
</div>

<section class="card" style="padding:14px;">
  <div class="muted" style="font-size:12px; line-height:1.5;">
    Tip: Use <strong>View</strong> to see order items. Status updates are CSRF-protected.
  </div>

  <div class="table-wrap" style="margin-top:10px;">
    <table>
      <thead>
        <tr>
          <th style="width:90px;">ID</th>
          <th>User</th>
          <th class="right" style="width:140px;">Total</th>
          <th style="width:150px;">Status</th>
          <th style="width:190px;">Created</th>
          <th style="width:320px;">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($orders as $o): ?>
        <?php
          $oid = (int)$o['id'];
          $status = (string)($o['status'] ?? 'pending');

          // UI label mapping (only UI)
          $statusLabel = $status;
          if ($status === 'pending')   $statusLabel = 'Pending';
          if ($status === 'paid')      $statusLabel = 'Paid';
          if ($status === 'shipped')   $statusLabel = 'Shipped';
          if ($status === 'cancelled') $statusLabel = 'Cancelled';
        ?>
        <tr>
          <td><?= $oid ?></td>
          <td style="font-weight:850;">
            <?= e((string)($o['user_email'] ?? ('User #' . (int)$o['user_id']))) ?>
          </td>
          <td class="right">$<?= number_format((float)$o['total'], 2) ?></td>
          <td><span class="pill"><?= e($statusLabel) ?></span></td>
          <td><?= e((string)$o['created_at']) ?></td>
          <td>
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
              <a class="btn" href="order_view.php?id=<?= $oid ?>">View</a>

              <form method="post" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="hidden" name="csrf" value="<?= e((string)$_SESSION['csrf']) ?>">
                <input type="hidden" name="id" value="<?= $oid ?>">

                <select name="status" style="min-width:150px;">
                  <?php foreach ($allowedStatuses as $s): ?>
                    <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>>
                      <?= e($s) ?>
                    </option>
                  <?php endforeach; ?>
                </select>

                <button class="btn btn-primary" type="submit">Update</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php admin_layout_footer(); ?>
