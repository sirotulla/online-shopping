<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/auth.php';
require __DIR__ . '/_layout.php';

require_login();
$u = current_user();

// Reload user from DB (don’t trust session for balance/payment_id)
$stmt = $pdo->prepare("
  SELECT id, name, email, payment_id, balance, role
  FROM users
  WHERE id = ?
  LIMIT 1
");
$stmt->execute([(int)$u['id']]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    layout_header('My Account', null, 0);
    echo '<div class="container"><div class="card" style="padding:16px">User not found.</div></div>';
    layout_footer();
    exit;
}

// Load recent transactions
$stmt = $pdo->prepare("
  SELECT type, amount, note, created_at
  FROM wallet_transactions
  WHERE user_id = ?
  ORDER BY id DESC
  LIMIT 20
");
$stmt->execute([(int)$user['id']]);
$tx = $stmt->fetchAll();

// Load recent top-up requests (pending/approved/rejected) for this user
$stmt = $pdo->prepare("
  SELECT id, amount, method, reference, contact_telegram, status, admin_note, created_at, handled_at
  FROM topup_requests
  WHERE user_id = ?
  ORDER BY id DESC
  LIMIT 12
");
$stmt->execute([(int)$user['id']]);
$topups = $stmt->fetchAll();

/** cart count */
$cartCount = isset($_SESSION['cart']) && is_array($_SESSION['cart'])
    ? array_sum($_SESSION['cart'])
    : 0;

layout_header('My Account', $user, $cartCount);
?>

<?php if (!empty($_SESSION['flash_success'])): ?>
  <div class="container">
    <div class="alert alert-ok" style="margin-bottom:12px;">
      <?= htmlspecialchars($_SESSION['flash_success'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
    </div>
  </div>
  <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>


<div class="container">

  <!-- Header -->
  <div style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:14px">
    <div>
      <div class="muted">Account overview</div>
      <h1 style="margin:6px 0 0">My Account</h1>
    </div>

    <div class="hero-actions">
      <a class="btn" href="index.php">Shop</a>
      <a class="btn" href="cart.php">Cart</a>
      <?php if (is_admin()): ?>
        <a class="btn btn-ghost" href="admin/index.php">Admin</a>
      <?php endif; ?>
      <a class="btn btn-ghost" href="logout.php">Logout</a>
    </div>
  </div>

  <!-- Account cards -->
  <div class="grid" style="grid-template-columns: 1.2fr .8fr; align-items:start;">

    <!-- LEFT: Profile & Wallet -->
    <section class="card" style="padding:18px;">
      <h3 style="margin-top:0;">Profile & Wallet</h3>

      <div style="margin-top:10px;">
        <div class="muted">Name</div>
        <div style="font-weight:900;"><?= htmlspecialchars($user['name'], ENT_QUOTES) ?></div>
      </div>

      <div style="margin-top:10px;">
        <div class="muted">Email</div>
        <div><?= htmlspecialchars($user['email'], ENT_QUOTES) ?></div>
      </div>

      <div style="margin-top:12px;">
        <div class="muted">Payment ID</div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
          <code id="pid" class="pill"><?= htmlspecialchars((string)$user['payment_id'], ENT_QUOTES) ?></code>
          <button
            type="button"
            class="btn"
            onclick="navigator.clipboard.writeText(document.getElementById('pid').innerText)"
          >
            Copy
          </button>
        </div>
        <div class="muted" style="font-size:12px; margin-top:4px;">
          Share this ID with admin when requesting top-ups
        </div>
      </div>

      <div style="margin-top:14px;">
        <div class="muted">Wallet balance</div>
        <div style="font-size:32px; font-weight:950;">
          $<?= number_format((float)$user['balance'], 2) ?>
        </div>
      </div>

      <div style="margin-top:14px;">
        <a class="btn btn-primary" href="topup_request.php">Request top-up</a>
      </div>

      <hr style="margin:18px 0; opacity:.2;">

      <div style="margin-top:10px;">
        <a class="pill" href="change_password.php">Change password</a>
      </div>

    </section>

    <!-- RIGHT: Quick info -->
    <aside class="card" style="padding:18px;">
      <h3 style="margin-top:0;">Quick info</h3>

      <div class="pill" style="margin-bottom:8px;">Secure wallet</div>
      <div class="pill" style="margin-bottom:8px;">CSRF protected</div>
      <div class="pill">Server-verified balance</div>

      <div class="muted" style="margin-top:10px; line-height:1.5;">
        All balances and transactions are calculated and verified on the server.
      </div>
    </aside>
  </div>

  <div style="height:18px"></div>

  <!-- Top-up requests -->
  <section class="card" style="padding:18px;">
    <div style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;">
      <div>
        <div class="muted">Requests</div>
        <h2 style="margin:6px 0 0">Top-up requests</h2>
      </div>
      <a class="btn btn-ghost" href="topup_request.php">New request</a>
    </div>

    <div class="muted" style="margin-top:8px; font-size:13px;">
      Rejected requests don’t change your balance. If rejected, check the reason and submit a new request.
    </div>

    <?php if (!$topups): ?>
      <p class="muted" style="margin-top:10px;">No top-up requests yet.</p>
    <?php else: ?>
      <div style="overflow-x:auto; margin-top:10px;">
        <table style="width:100%; border-collapse:collapse;">
          <thead>
            <tr>
              <th style="text-align:left; padding:10px;">ID</th>
              <th style="text-align:right; padding:10px;">Amount</th>
              <th style="text-align:left; padding:10px;">Method</th>
              <th style="text-align:left; padding:10px;">Status</th>
              <th style="text-align:left; padding:10px;">Admin note</th>
              <th style="text-align:left; padding:10px;">Created</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($topups as $r): ?>
              <?php
                $status = (string)($r['status'] ?? 'pending');
                $statusLabel = $status;
                if ($status === 'approved') $statusLabel = 'approved ✅';
                elseif ($status === 'rejected') $statusLabel = 'rejected ❌';
                else $statusLabel = 'pending ⏳';

                $adminNote = (string)($r['admin_note'] ?? '');
                if ($status === 'rejected' && $adminNote === '') {
                    $adminNote = 'Rejected (no reason provided)';
                }
              ?>
              <tr>
                <td style="padding:10px;">
                  <span class="pill">#<?= (int)$r['id'] ?></span>
                </td>
                <td style="padding:10px; text-align:right;">
                  $<?= number_format((float)$r['amount'], 2) ?>
                </td>
                <td style="padding:10px;">
                  <?= htmlspecialchars((string)($r['method'] ?? ''), ENT_QUOTES) ?>
                  <?php if (!empty($r['reference'])): ?>
                    <div class="muted" style="font-size:12px;">Ref: <?= htmlspecialchars((string)$r['reference'], ENT_QUOTES) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($r['contact_telegram'])): ?>
                    <div class="muted" style="font-size:12px;">TG: <?= htmlspecialchars((string)$r['contact_telegram'], ENT_QUOTES) ?></div>
                  <?php endif; ?>
                </td>
                <td style="padding:10px;">
                  <span class="pill"><?= htmlspecialchars($statusLabel, ENT_QUOTES) ?></span>
                </td>
                <td style="padding:10px;">
                  <?php if ($status === 'rejected'): ?>
                    <span class="muted"><?= htmlspecialchars($adminNote, ENT_QUOTES) ?></span>
                  <?php elseif ($status === 'approved'): ?>
                    <span class="muted"><?= htmlspecialchars($adminNote !== '' ? $adminNote : 'Approved', ENT_QUOTES) ?></span>
                  <?php else: ?>
                    <span class="muted">Waiting for admin review</span>
                  <?php endif; ?>
                </td>
                <td style="padding:10px;">
                  <?= htmlspecialchars((string)($r['created_at'] ?? ''), ENT_QUOTES) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <div style="height:18px"></div>

  <!-- Transactions -->
  <section class="card" style="padding:18px;">
    <div style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;">
      <div>
        <div class="muted">History</div>
        <h2 style="margin:6px 0 0">Recent transactions</h2>
      </div>
    </div>

    <?php if (!$tx): ?>
      <p class="muted" style="margin-top:10px;">No transactions yet.</p>
    <?php else: ?>
      <div style="overflow-x:auto; margin-top:10px;">
        <table style="width:100%; border-collapse:collapse;">
          <thead>
            <tr>
              <th style="text-align:left; padding:10px;">Type</th>
              <th style="text-align:right; padding:10px;">Amount</th>
              <th style="text-align:left; padding:10px;">Note</th>
              <th style="text-align:left; padding:10px;">Time</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tx as $t): ?>
              <tr>
                <td style="padding:10px;">
                  <span class="pill"><?= htmlspecialchars($t['type'], ENT_QUOTES) ?></span>
                </td>
                <td style="padding:10px; text-align:right;">
                  $<?= number_format((float)$t['amount'], 2) ?>
                </td>
                <td style="padding:10px;">
                  <?= htmlspecialchars($t['note'] ?? '', ENT_QUOTES) ?>
                </td>
                <td style="padding:10px;">
                  <?= htmlspecialchars((string)$t['created_at'], ENT_QUOTES) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

</div>

<style>
@media (max-width: 900px){
  .grid[style*="grid-template-columns"]{
    grid-template-columns: 1fr !important;
  }
}
</style>

<?php layout_footer(); ?>
