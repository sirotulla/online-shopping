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

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'], $csrf)) {
        http_response_code(403);
        $errors[] = __('err_csrf');
    } else {
        $name  = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $pass1 = (string)($_POST['password'] ?? '');
        $pass2 = (string)($_POST['password2'] ?? '');

        // Validation
        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = __('err_name_length');
        }

        if ($email === '' || mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = __('err_invalid_email');
        }

        if (strlen($pass1) < 8 || strlen($pass1) > 72) {
            $errors[] = __('err_password_length');
        }

        if (!hash_equals($pass1, $pass2)) {
            $errors[] = __('err_passwords_mismatch');
        }

        if (!$errors) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = __('err_registration_failed'); // Generic to prevent enumeration
            } else {
                $hash = password_hash($pass1, PASSWORD_DEFAULT);
                require_once __DIR__ . '/../app/helpers/wallet.php';
                $bonus = 5.00;

                try {
                    $pdo->beginTransaction();

                    $paymentId = null;
                    for ($i = 0; $i < 5; $i++) {
                        $candidate = generate_payment_id();
                        $check = $pdo->prepare("SELECT id FROM users WHERE payment_id = ? LIMIT 1");
                        $check->execute([$candidate]);
                        if (!$check->fetch()) {
                            $paymentId = $candidate;
                            break;
                        }
                    }
                    
                    if ($paymentId === null) throw new RuntimeException('Payment ID error');

                    $stmt = $pdo->prepare("
                      INSERT INTO users (name, email, password_hash, role, payment_id, balance)
                      VALUES (?, ?, ?, 'user', ?, ?)
                    ");
                    $stmt->execute([$name, $email, $hash, $paymentId, $bonus]);

                    $userId = (int)$pdo->lastInsertId();

                    $stmt = $pdo->prepare("
                      INSERT INTO wallet_transactions (user_id, type, amount, note, created_by_admin_id)
                      VALUES (?, 'bonus', ?, ?, NULL)
                    ");
                    $stmt->execute([$userId, $bonus, __('tx_signup_bonus')]);

                    $pdo->commit();
                    $_SESSION['csrf'] = bin2hex(random_bytes(32));
                    header('Location: login.php?registered=1', true, 303);
                    exit;

                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $errors[] = __('err_registration_failed');
                }
            }
        }
    }
}

$cartCount = (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) ? array_sum($_SESSION['cart']) : 0;

layout_header(__('nav_register'), null, $cartCount);
?>

<div class="container auth-page" style="max-width:520px;">
  <section class="card" style="padding:22px;">

    <div style="text-align:center; margin-bottom:16px;">
      <div style="font-size:42px;">✨</div>
      <h1 style="margin:6px 0;"><?= __('title_create_account') ?></h1>
      <div class="muted"><?= __('desc_register') ?></div>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-bad" style="margin-bottom:12px;">
        <ul style="margin:0; padding-left:18px;">
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" class="form" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

      <label for="name"><?= __('label_name') ?></label>
      <input id="name" name="name" type="text" value="<?= htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>

      <label for="email"><?= __('label_email') ?></label>
      <input id="email" name="email" type="email" value="<?= htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>

      <label for="password"><?= __('label_password') ?></label>
      <input id="password" name="password" type="password" minlength="8" required>

      <label for="password2"><?= __('label_confirm_password') ?></label>
      <input id="password2" name="password2" type="password" minlength="8" required>

      <button class="btn btn-primary" type="submit" style="margin-top:6px; width:100%;">
        <?= __('nav_register') ?>
      </button>
    </form>

    <div style="margin-top:14px; text-align:center;">
      <span class="muted"><?= __('text_have_account') ?></span>
      <a href="login.php"><?= __('nav_login') ?></a>
    </div>

    <div style="margin-top:10px; text-align:center;">
      <a class="pill" href="index.php">← <?= __('btn_back_shop') ?></a>
    </div>

  </section>
</div>

<?php layout_footer(); ?>
