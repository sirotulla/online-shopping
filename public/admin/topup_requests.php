<?php
declare(strict_types=1);

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';
require_once __DIR__ . '/_layout_admin.php';

if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$errors = [];
$admin = current_user();
$adminId = $admin ? (int)$admin['id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'], $csrf)) {
        http_response_code(403);
        $errors[] = 'Request blocked.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $id = (string)($_POST['id'] ?? '');
        $adminNote = trim((string)($_POST['admin_note'] ?? ''));

        if (!in_array($action, ['approve', 'reject'], true)) {
            $errors[] = 'Invalid action.';
        }
        if (!ctype_digit($id)) {
            $errors[] = 'Invalid request id.';
        }
        if (mb_strlen($adminNote) > 255) {
            $errors[] = 'Admin note too long.';
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();

                // Lock request row
                $stmt = $pdo->prepare("
                  SELECT tr.*
                  FROM topup_requests tr
                  WHERE tr.id = ?
                  LIMIT 1
                  FOR UPDATE
                ");
                $stmt->execute([(int)$id]);
                $req = $stmt->fetch();

                if (!$req) {
                    throw new RuntimeException('Request not found.');
                }
                if (($req['status'] ?? '') !== 'pending') {
                    throw new RuntimeException('Request already handled.');
                }

                $userId = (int)$req['user_id'];
                $amount = (float)$req['amount'];

                if ($action === 'reject') {
                    $stmt = $pdo->prepare("
                      UPDATE topup_requests
                      SET status='rejected', admin_note=?, handled_by_admin_id=?, handled_at=NOW()
                      WHERE id = ? LIMIT 1
                    ");
                    $stmt->execute([$adminNote === '' ? 'Rejected' : $adminNote, $adminId, (int)$id]);

                    $pdo->commit();
                    $_SESSION['csrf'] = bin2hex(random_bytes(32));
                    header('Location: topup_requests.php', true, 303);
                    exit;
                }

                // Approve flow
                if ($amount <= 0 || $amount > 10000) {
                    throw new RuntimeException('Bad amount.');
                }

                // Lock user row
                $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? LIMIT 1 FOR UPDATE");
                $stmt->execute([$userId]);
                $urow = $stmt->fetch();
                if (!$urow) {
                    throw new RuntimeException('User not found.');
                }

                $newBal = (float)$urow['balance'] + $amount;

                // Update balance
                $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ? LIMIT 1");
                $stmt->execute([$newBal, $userId]);

                // Log wallet transaction
                $stmt = $pdo->prepare("
                  INSERT INTO wallet_transactions (user_id, type, amount, note, created_by_admin_id)
                  VALUES (?, 'topup', ?, ?, ?)
                ");
                $note = $adminNote === '' ? ('Top-up approved (Request #' . (int)$id . ')') : $adminNote;
                $stmt->execute([$userId, $amount, $note, $adminId]);

                // Mark request approved
                $stmt = $pdo->prepare("
                  UPDATE topup_requests
                  SET status='approved', admin_note=?, handled_by_admin_id=?, handled_at=NOW()
                  WHERE id = ? LIMIT 1
                ");
                $stmt->execute([$note, $adminId, (int)$id]);

                $pdo->commit();

                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                header('Location: topup_requests.php', true, 303);
                exit;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Action failed.';
            }
        }
    }
}

// Load pending requests
$stmt = $pdo->query("
  SELECT tr.id, tr.user_id, tr.amount, tr.method, tr.reference, tr.contact_telegram, tr.note, tr.status, tr.created_at,
         u.email AS user_email, u.payment_id AS payment_id
  FROM topup_requests tr
  LEFT JOIN users u ON u.id = tr.user_id
  WHERE tr.status = 'pending'
  ORDER BY tr.id DESC
");
$pending = $stmt->fetchAll();

admin_layout_header('Admin — Top-up Requests', $_SESSION['user'] ?? null);
?>

<div class="container">

  <div style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:12px">
    <div>
      <div class="muted">Admin</div>
      <h1 style="margin:6px 0 0">Pending top-up requests</h1>
    </div>
    <div class="pill"><?= count($pending) ?> pending</div>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-bad" style="margin-bottom:12px;">
      <ul style="margin:0; padding-left:18px;">
        <?php foreach ($errors as $msg): ?>
          <li><?= e($msg) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if (!$pending): ?>
    <div class="card" style="padding:16px;">
      <div class="muted">No pending requests.</div>
    </div>
  <?php else: ?>

    <?php foreach ($pending as $r): ?>
      <section class="card" style="padding:16px; margin-bottom:12px;">
        <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
          <div>
            <div class="pill">Request #<?= (int)$r['id'] ?></div>
            <div style="margin-top:8px; font-weight:950; font-size:16px;">
              <?= e((string)($r['user_email'] ?? ('User #' . (int)$r['user_id']))) ?>
            </div>
            <div class="muted" style="margin-top:6px;">
              Created: <?= e((string)$r['created_at']) ?>
            </div>
          </div>

          <div style="text-align:right;">
            <div class="muted" style="font-size:12px;">Amount</div>
            <div style="font-size:30px; font-weight:950;">$<?= number_format((float)$r['amount'], 2) ?></div>
            <div class="pill" style="margin-top:6px; display:inline-flex;">
              <?= e((string)($r['method'] ?? '')) ?>
            </div>
          </div>
        </div>

        <div style="height:12px"></div>

        <div class="grid" style="grid-template-columns: 1fr 1fr; gap:12px; align-items:start;">
          <div class="card" style="padding:12px; background:rgba(255,255,255,.03);">
            <div class="muted" style="font-size:12px;">Payment ID</div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-top:6px;">
              <code class="code" id="pid<?= (int)$r['id'] ?>"><?= e((string)($r['payment_id'] ?? '')) ?></code>
              <button class="btn" type="button"
                onclick="navigator.clipboard.writeText(document.getElementById('pid<?= (int)$r['id'] ?>').innerText)">
                Copy
              </button>
            </div>

            <div class="muted" style="font-size:12px; margin-top:10px;">Reference</div>
            <div><?= e((string)($r['reference'] ?? '—')) ?></div>

            <div class="muted" style="font-size:12px; margin-top:10px;">Telegram</div>
            <div><?= e((string)($r['contact_telegram'] ?? '—')) ?></div>
          </div>

          <div class="card" style="padding:12px; background:rgba(255,255,255,.03);">
            <div class="muted" style="font-size:12px;">User note</div>
            <div style="line-height:1.5; margin-top:6px;">
              <?= e((string)($r['note'] ?? '—')) ?>
            </div>

            <div style="height:10px"></div>

            <div class="muted" style="font-size:12px;">Admin note (optional)</div>

            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:8px;">
              <!-- Approve -->
              <form method="post" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <input type="hidden" name="csrf" value="<?= e((string)$_SESSION['csrf']) ?>">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">

                <input name="admin_note" type="text" placeholder="Approve note (optional)" style="min-width:240px;">
                <button class="btn btn-primary" type="submit"
                  onclick="return confirm('Approve this request?')">
                  Approve
                </button>
              </form>

              <!-- Reject -->
              <form method="post" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <input type="hidden" name="csrf" value="<?= e((string)$_SESSION['csrf']) ?>">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">

                <input name="admin_note" type="text" placeholder="Reject reason (optional)" style="min-width:240px;">
                <button class="btn btn-ghost" type="submit"
                  onclick="return confirm('Reject this request?')">
                  Reject
                </button>
              </form>
            </div>

            <div class="muted" style="font-size:12px; margin-top:10px; line-height:1.5;">
              Approve will add money to the user’s balance and log a wallet transaction.
            </div>
          </div>
        </div>

      </section>
    <?php endforeach; ?>

    <style>
      @media (max-width: 900px){
        .grid[style*="grid-template-columns"]{ grid-template-columns: 1fr !important; }
        input[style*="min-width"]{ min-width: 100% !important; }
      }
    </style>

  <?php endif; ?>

</div>

<?php admin_layout_footer(); ?>
