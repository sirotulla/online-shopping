<?php
declare(strict_types=1);

// session_start() is inside lang.php/auth.php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/lang.php'; // Added lang helper
require __DIR__ . '/_layout.php';

require_login();
$u = current_user();

if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = '';

$amount = '';
$method = '';
$reference = '';
$telegram = '';
$note = '';

// Internal keys for methods (keeping them English for DB consistency)
$methods = ['Cash', 'Bank transfer', 'Payme', 'Click'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'], $csrf)) {
        http_response_code(403);
        $errors[] = __('err_csrf');
    } else {
        $amount = trim((string)($_POST['amount'] ?? ''));
        $method = trim((string)($_POST['method'] ?? ''));
        $reference = trim((string)($_POST['reference'] ?? ''));
        $reference = preg_replace('/\s+/', ' ', $reference);
        $telegram = trim((string)($_POST['telegram'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));

        // Validation: amount
        if (!is_numeric($amount)) {
            $errors[] = __('err_amount_numeric');
        } else {
            $amt = (float)$amount;
            if ($amt <= 0 || $amt > 10000) {
                $errors[] = __('err_amount_range');
            }
        }

        // Validation: method
        if (!in_array($method, $methods, true)) {
            $errors[] = __('err_select_method');
        }

        // Validation: reference
        if (mb_strlen($reference) > 80) {
            $errors[] = __('err_ref_length');
        }
        if ($reference !== '' && !preg_match('/^[A-Za-z0-9\-\_\.\/\#\s]{3,80}$/', $reference)) {
            $errors[] = __('err_ref_chars');
        }

        // Validation: telegram
        if ($telegram !== '') {
            if ($telegram[0] === '@') {
                $telegram = substr($telegram, 1);
            }
            if (!preg_match('/^[A-Za-z0-9_]{5,32}$/', $telegram)) {
                $errors[] = __('err_tg_invalid');
            } else {
                $telegram = '@' . $telegram;
            }
        }

        if ($method === 'Cash' && $telegram === '') {
            $errors[] = __('err_tg_required_cash');
        }

        $needsReference = ['Bank transfer', 'Payme', 'Click'];
        if (in_array($method, $needsReference, true) && $reference === '') {
            $errors[] = __('err_ref_required');
        }

        // Validation: note
        if (mb_strlen($note) > 255) {
            $errors[] = __('err_note_length');
        }

        if (!$errors) {
            $stmt = $pdo->prepare("
              INSERT INTO topup_requests
                (user_id, amount, method, reference, contact_telegram, note, status)
              VALUES
                (?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                (int)$u['id'],
                (float)$amount,
                $method,
                $reference === '' ? null : $reference,
                $telegram === '' ? null : $telegram,
                $note === '' ? null : $note
            ]);

            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            $success = __('msg_request_sent');
            $amount = $method = $reference = $telegram = $note = '';
        }
    }
}

// Load user Payment ID for display
$stmt = $pdo->prepare("SELECT payment_id, balance FROM users WHERE id = ? LIMIT 1");
$stmt->execute([(int)$u['id']]);
$me = $stmt->fetch();

$cartCount = (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) ? array_sum($_SESSION['cart']) : 0;

layout_header(__('btn_request_topup'), $u, $cartCount);
?>

<div class="container">

  <div style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:12px">
    <div>
      <div class="muted"><?= __('label_wallet_balance') ?></div>
      <h1 style="margin:6px 0 0"><?= __('btn_request_topup') ?></h1>
    </div>
    <a class="pill" href="my_account.php">← <?= __('btn_back_account') ?></a>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-ok" style="margin-bottom:12px;">
      ✅ <?= htmlspecialchars($success, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="alert alert-bad" style="margin-bottom:12px;">
      <ul style="margin:0; padding-left:18px;">
        <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="grid" style="grid-template-columns: 1fr 1fr; align-items:start;">

    <section class="card" style="padding:18px;">
      <h3 style="margin-top:0;"><?= __('acc_profile_wallet') ?></h3>

      <div style="margin-top:10px;">
        <div class="muted"><?= __('label_payment_id') ?></div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-top:6px;">
          <code id="pid" class="code"><?= htmlspecialchars((string)($me['payment_id'] ?? ''), ENT_QUOTES) ?></code>
          <button class="btn" type="button" onclick="navigator.clipboard.writeText(document.getElementById('pid').innerText)">
            <?= __('btn_copy') ?>
          </button>
        </div>
        <div class="muted" style="font-size:12px; margin-top:6px;">
          <?= __('desc_payment_id_short') ?>
        </div>
      </div>

      <div style="margin-top:14px;">
        <div class="muted"><?= __('label_wallet_balance') ?></div>
        <div style="font-size:32px; font-weight:950; margin-top:6px;">
          $<?= number_format((float)($me['balance'] ?? 0), 2) ?>
        </div>
      </div>

      <hr class="hr">

      <div class="muted" style="line-height:1.6;">
        <?= __('desc_topup_instruction') ?>
      </div>
    </section>

    <section class="card" style="padding:18px;">
      <h3 style="margin-top:0;"><?= __('btn_new_request') ?></h3>

      <form method="post" class="form" id="topupForm">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

        <label for="amount"><?= __('label_amount') ?></label>
        <input type="number" step="0.01" id="amount" name="amount" value="<?= htmlspecialchars($amount, ENT_QUOTES) ?>" placeholder="e.g. 10.00" required>

        <label for="method"><?= __('th_method') ?></label>
        <select id="method" name="method" required>
          <option value="">— <?= __('label_select') ?> —</option>
          <?php foreach ($methods as $m): ?>
            <option value="<?= htmlspecialchars($m, ENT_QUOTES) ?>" <?= $method === $m ? 'selected' : '' ?>>
              <?= __('method_' . strtolower(str_replace(' ', '_', $m))) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <p class="muted" style="margin:0; font-size:13px;">
          <?= __('desc_method_requirements') ?>
        </p>

        <label for="reference"><?= __('label_reference') ?></label>
        <input type="text" id="reference" name="reference" value="<?= htmlspecialchars($reference, ENT_QUOTES) ?>" placeholder="<?= __('label_ref_placeholder') ?>">

        <label for="telegram"><?= __('label_tg_username') ?></label>
        <input type="text" id="telegram" name="telegram" value="<?= htmlspecialchars($telegram, ENT_QUOTES) ?>" placeholder="@yourname">

        <label for="note"><?= __('label_note_optional') ?></label>
        <textarea id="note" name="note" rows="3"><?= htmlspecialchars($note, ENT_QUOTES) ?></textarea>

        <button class="btn btn-primary" type="submit"><?= __('btn_send_request') ?></button>

        <div class="muted" style="font-size:12px; line-height:1.5;">
          <?= __('desc_form_tip') ?>
        </div>
      </form>
    </section>

  </div>

</div>

<script>
(function(){
  const method = document.getElementById('method');
  const ref = document.getElementById('reference');
  const tg = document.getElementById('telegram');

  function apply(){
    const m = method.value;
    const needsRef = (m === 'Bank transfer' || m === 'Payme' || m === 'Click');
    const needsTg = (m === 'Cash');

    ref.style.borderColor = needsRef ? 'rgba(124,92,255,.55)' : 'rgba(255,255,255,.12)';
    tg.style.borderColor  = needsTg  ? 'rgba(124,92,255,.55)' : 'rgba(255,255,255,.12)';
  }

  method.addEventListener('change', apply);
  apply();
})();
</script>

<style>
@media (max-width: 900px){ .grid[style*="grid-template-columns"]{ grid-template-columns: 1fr !important; } }
</style>

<?php layout_footer(); ?>
