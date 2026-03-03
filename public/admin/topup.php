<?php
declare(strict_types=1);

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/_layout_admin.php';

if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = '';
$paymentId = '';
$amount = '';
$note = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'], $csrf)) {
        http_response_code(403);
        $errors[] = 'Request blocked.';
    } else {
        $paymentId = strtoupper(trim((string)($_POST['payment_id'] ?? '')));
        $amount = trim((string)($_POST['amount'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));

        // Validate payment id format (matches our generator)
        if (!preg_match('/^EBG-[A-F0-9]{12}$/', $paymentId)) {
            $errors[] = 'Invalid Payment ID format.';
        }

        // Validate amount
        if (!is_numeric($amount)) {
            $errors[] = 'Amount must be a number.';
        } else {
            $amt = (float)$amount;
            if ($amt <= 0 || $amt > 10000) {
                $errors[] = 'Amount out of range.';
            }
        }

        if (mb_strlen($note) > 255) {
            $errors[] = 'Note too long.';
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();

                // Find user and lock row (prevents race conditions)
                $stmt = $pdo->prepare("SELECT id, balance FROM users WHERE payment_id = ? LIMIT 1 FOR UPDATE");
                $stmt->execute([$paymentId]);
                $user = $stmt->fetch();

                if (!$user) {
                    throw new RuntimeException('User not found');
                }

                $userId = (int)$user['id'];
                $oldBal = (float)$user['balance'];
                $newBal = $oldBal + (float)$amount;

                // Update balance
                $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ? LIMIT 1");
                $stmt->execute([$newBal, $userId]);

                // Log transaction
                $admin = current_user(); // from auth.php via _guard.php
                $adminId = $admin ? (int)$admin['id'] : null;

                $stmt = $pdo->prepare("
                  INSERT INTO wallet_transactions (user_id, type, amount, note, created_by_admin_id)
                  VALUES (?, 'topup', ?, ?, ?)
                ");
                $stmt->execute([$userId, (float)$amount, $note === '' ? 'Manual top-up' : $note, $adminId]);

                $pdo->commit();

                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                $success = 'Top-up successful.';
                $paymentId = $amount = $note = '';

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Top-up failed.';
            }
        }
    }
}

admin_layout_header('Admin — Manual Top Up', $_SESSION['user'] ?? null);
?>

<div style="max-width:900px; margin:0 auto;">

  <div style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:12px">
    <div>
      <div class="muted">Wallet</div>
      <h1 style="margin:6px 0 0">Manual top-up</h1>
    </div>
    <a class="pill" href="topup_requests.php">View requests →</a>
  </div>

  <section class="card" style="padding:14px;">
    <div class="muted" style="font-size:12px; line-height:1.6;">
      Use this when you verified payment outside the site.
      <br>
      Payment ID format: <strong>EBG-XXXXXXXXXXXX</strong> (12 hex chars).
    </div>
  </section>

  <div style="height:12px;"></div>

  <?php if ($success): ?>
    <div class="alert alert-ok" style="margin-bottom:12px;"><?= e($success) ?></div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="alert alert-bad" style="margin-bottom:12px;">
      <ul style="margin:0; padding-left:18px;">
        <?php foreach ($errors as $msg): ?>
          <li><?= e($msg) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <section class="card" style="padding:16px;">
    <form method="post" class="form" style="grid-template-columns: 1fr 1fr; gap:12px;">
      <input type="hidden" name="csrf" value="<?= e((string)$_SESSION['csrf']) ?>">

      <div style="grid-column: 1 / -1;">
        <label for="payment_id">Payment ID</label>
        <input id="payment_id" name="payment_id" type="text"
               value="<?= e($paymentId) ?>" placeholder="EBG-12AB34CD56EF" required>
      </div>

      <div>
        <label for="amount">Amount</label>
        <input id="amount" name="amount" type="number" step="0.01"
               value="<?= e($amount) ?>" placeholder="e.g. 10.00" required>
      </div>

      <div>
        <label for="note">Note (optional)</label>
        <input id="note" name="note" type="text"
               value="<?= e($note) ?>" placeholder="e.g. Cash payment verified">
      </div>

      <div style="grid-column: 1 / -1; display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-top:6px;">
        <button class="btn btn-primary" type="submit">Top up balance</button>
        <a class="btn btn-ghost" href="index.php">Cancel</a>
      </div>

      <div class="muted" style="grid-column: 1 / -1; font-size:12px; line-height:1.5; margin-top:6px;">
        Security: user row is locked during update to prevent race conditions.
      </div>
    </form>
  </section>

</div>

<style>
@media (max-width: 900px){
  .form[style*="grid-template-columns"]{ grid-template-columns: 1fr !important; }
}
</style>

<?php admin_layout_footer(); ?>
