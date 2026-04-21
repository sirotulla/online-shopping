<?php
declare(strict_types=1);

// session_start() is inside lang.php/auth.php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/lang.php'; // Added lang helper
require __DIR__ . '/_layout.php';

require_login();
$u = current_user();

// Reload user from DB
$stmt = $pdo->prepare("SELECT id, name, email, payment_id, balance, role FROM users WHERE id = ? LIMIT 1");
$stmt->execute([(int)$u['id']]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    layout_header(__('nav_account'), null, 0);
    echo '<div class="container"><div class="card" style="padding:16px">' . __('err_user_not_found') . '</div></div>';
    layout_footer();
    exit;
}

// Load recent transactions
$stmt = $pdo->prepare("SELECT type, amount, note, created_at FROM wallet_transactions WHERE user_id = ? ORDER BY id DESC LIMIT 20");
$stmt->execute([(int)$user['id']]);
$tx = $stmt->fetchAll();

// Load recent top-up requests
$stmt = $pdo->prepare("SELECT id, amount, method, reference, contact_telegram, status, admin_note, created_at, handled_at FROM topup_requests WHERE user_id = ? ORDER BY id DESC LIMIT 12");
$stmt->execute([(int)$user['id']]);
$topups = $stmt->fetchAll();

$cartCount = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

layout_header(__('nav_account'), $user, $cartCount);
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

  <div style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:14px">
    <div>
      <div class="muted"><?= __('acc_overview') ?></div>
      <h1 style="margin:6px 0 0"><?= __('nav_account') ?></h1>
    </div>

    <div class="hero-actions">
      <a class="btn" href="index.php"><?= __('nav_home') ?></a>
      <a class="btn" href="cart.php"><?= __('nav_cart') ?></a>
      <?php if (is_admin()): ?>
        <a class="btn btn-ghost" href="admin/index.php"><?= __('nav_admin') ?></a>
      <?php endif; ?>
      <a class="btn btn-ghost" href="logout.php"><?= __('nav_logout') ?></a>
    </div>
  </div>

  <div class="grid" style="grid-template-columns: 1.2fr .8fr; align-items:start;">

    <section class="card" style="padding:18px;">
      <h3 style="margin-top:0;"><?= __('acc_profile_wallet') ?></h3>

      <div style="margin-top:10px;">
        <div class="muted"><?= __('label_name') ?></div>
        <div style="font-weight:900;"><?= htmlspecialchars($user['name'], ENT_QUOTES) ?></div>
      </div>

      <div style="margin-top:10px;">
        <div class="muted"><?= __('label_email') ?></div>
        <div><?= htmlspecialchars($user['email'], ENT_QUOTES) ?></div>
      </div>

      <div style="margin-top:12px;">
        <div class="muted"><?= __('label_payment_id') ?></div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
          <code id="pid" class="pill"><?= htmlspecialchars((string)$user['payment_id'], ENT_QUOTES) ?></code>
          <button type="button" class="btn" onclick="navigator.clipboard.writeText(document.getElementById('pid').innerText)">
            <?= __('btn_copy') ?>
          </button>
        </div>
        <div class="muted" style="font-size:12px; margin-top:4px;">
          <?= __('desc_payment_id') ?>
        </div>
      </div>

      <div style="margin-top:14px;">
        <div class="muted"><?= __('label_wallet_balance') ?></div>
        <div style="font-size:32px; font-weight:950;">
          $<?= number_format((float)$user['balance'], 2) ?>
        </div>
      </div>

      <div style="margin-top:14px;">
        <a class="btn btn-primary" href="topup_request.php"><?= __('btn_request_topup') ?></a>
      </div>

      <hr style="margin:18px 0; opacity:.2;">

      <div style="margin-top:10px;">
        <a class="pill" href="change_password.php"><?= __('title_change_password') ?></a>
      </div>

    </section>

    <aside class="card" style="padding:18px;">
      <h3 style="margin-top:0;"><?= __('acc_quick_info') ?></h3>
      <div class="pill" style="margin-bottom:8px;"><?= __('pill_secure_wallet') ?></div>
      <div class="pill" style="margin-bottom:8px;"><?= __('pill_csrf') ?></div>
      <div class="pill"><?= __('pill_verified_balance') ?></div>
      <div class="muted" style="margin-top:10px; line-height:1.5;">
        <?= __('acc_server_note') ?>
      </div>
    </aside>
  </div>

  <div style="height:18px"></div>

  <section class="card" style="padding:18px;">
    <div style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;">
      <div>
        <div class="muted"><?= __('acc_requests') ?></div>
        <h2 style="margin:6px 0 0"><?= __('acc_topup_requests') ?></h2>
      </div>
      <a class="btn btn-ghost" href="topup_request.php"><?= __('btn_new_request') ?></a>
    </div>

    <div class="muted" style="margin-top:8px; font-size:13px;">
      <?= __('acc_topup_note') ?>
    </div>

    <?php if (!$topups): ?>
      <p class="muted" style="margin-top:10px;"><?= __('acc_no_topups') ?></p>
    <?php else: ?>
      <div style="overflow-x:auto; margin-top:10px;">
        <table style="width:100%; border-collapse:collapse;">
          <thead>
            <tr>
              <th style="text-align:left; padding:10px;"><?= __('th_id') ?></th>
              <th style="text-align:right; padding:10px;"><?= __('label_amount') ?></th>
              <th style="text-align:left; padding:10px;"><?= __('th_method') ?></th>
              <th style="text-align:left; padding:10px;"><?= __('th_status') ?></th>
              <th style="text-align:left; padding:10px;"><?= __('th_admin_note') ?></th>
              <th style="text-align:left; padding:10px;"><?= __('th_created') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($topups as $r): ?>
              <?php
                $status = (string)($r['status'] ?? 'pending');
                $statusLabel = __('status_' . $status);
                if ($status === 'approved') $statusLabel .= ' ✅';
                elseif ($status === 'rejected') $statusLabel .= ' ❌';
                else $statusLabel .= ' ⏳';

                $adminNote = (string)($r['admin_note'] ?? '');
                if ($status === 'rejected' && $adminNote === '') {
                    $adminNote = __('acc_rejected_no_reason');
                }
              ?>
              <tr>
                <td style="padding:10px;"><span class="pill">#<?= (int)$r['id'] ?></span></td>
                <td style="padding:10px; text-align:right;">$<?= number_format((float)$r['amount'], 2) ?></td>
                <td style="padding:10px;">
                  <?= htmlspecialchars((string)($r['method'] ?? ''), ENT_QUOTES) ?>
                  <?php if (!empty($r['reference'])): ?>
                    <div class="muted" style="font-size:12px;">Ref: <?= htmlspecialchars((string)$r['reference'], ENT_QUOTES) ?></div>
                  <?php endif; ?>
                </td>
                <td style="padding:10px;"><span class="pill"><?= htmlspecialchars($statusLabel, ENT_QUOTES) ?></span></td>
                <td style="padding:10px;">
                  <?php if ($status === 'rejected'): ?>
                    <span class="muted"><?= htmlspecialchars($adminNote, ENT_QUOTES) ?></span>
                  <?php elseif ($status === 'approved'): ?>
                    <span class="muted"><?= htmlspecialchars($adminNote !== '' ? $adminNote : __('status_approved'), ENT_QUOTES) ?></span>
                  <?php else: ?>
                    <span class="muted"><?= __('acc_waiting_review') ?></span>
                  <?php endif; ?>
                </td>
                <td style="padding:10px;"><?= htmlspecialchars((string)($r['created_at'] ?? ''), ENT_QUOTES) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <div style="height:18px"></div>

  <section class="card" style="padding:18px;">
    <div style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;">
      <div>
        <div class="muted"><?= __('acc_history') ?></div>
        <h2 style="margin:6px 0 0"><?= __('acc_recent_transactions') ?></h2>
      </div>
    </div>

    <?php if (!$tx): ?>
      <p class="muted" style="margin-top:10px;"><?= __('acc_no_transactions') ?></p>
    <?php else: ?>
      <div style="overflow-x:auto; margin-top:10px;">
        <table style="width:100%; border-collapse:collapse;">
          <thead>
            <tr>
              <th style="text-align:left; padding:10px;"><?= __('th_type') ?></th>
              <th style="text-align:right; padding:10px;"><?= __('label_amount') ?></th>
              <th style="text-align:left; padding:10px;"><?= __('th_note') ?></th>
              <th style="text-align:left; padding:10px;"><?= __('th_time') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tx as $t): ?>
              <tr>
                <td style="padding:10px;"><span class="pill"><?= htmlspecialchars($t['type'], ENT_QUOTES) ?></span></td>
                <td style="padding:10px; text-align:right;">$<?= number_format((float)$t['amount'], 2) ?></td>
                <td style="padding:10px;"><?= htmlspecialchars($t['note'] ?? '', ENT_QUOTES) ?></td>
                <td style="padding:10px;"><?= htmlspecialchars((string)$t['created_at'], ENT_QUOTES) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

</div>

<style>
@media (max-width: 900px){ .grid[style*="grid-template-columns"]{ grid-template-columns: 1fr !important; } }
</style>

<?php layout_footer(); ?>
