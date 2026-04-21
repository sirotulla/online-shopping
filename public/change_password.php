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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'], $csrf)) {
        http_response_code(403);
        $errors[] = __('err_csrf');
    } else {
        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if ($current === '' || $new === '' || $confirm === '') {
            $errors[] = __('err_all_fields');
        } elseif ($new !== $confirm) {
            $errors[] = __('err_password_match');
        } elseif (mb_strlen($new) < 8) {
            $errors[] = __('err_password_short');
        } elseif ($new === $current) {
            $errors[] = __('err_password_same');
        }

        if (!$errors) {
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$u['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $errors[] = __('err_user_not_found');
            } elseif (!password_verify($current, (string)$row['password_hash'])) {
                $errors[] = __('err_current_password_wrong');
            } else {
                $newHash = password_hash($new, PASSWORD_DEFAULT);

                $upd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ? LIMIT 1");
                if ($upd->execute([$newHash, (int)$u['id']])) {
                    session_regenerate_id(true);
                    $_SESSION['csrf'] = bin2hex(random_bytes(32));

                    // Use localized flash message
                    $_SESSION['flash_success'] = __('flash_password_updated');
                    header('Location: my_account.php', true, 303);
                    exit;
                } else {
                    $errors[] = __('err_update_failed');
                }
            }
        }
    }
}

$cartCount = isset($_SESSION['cart']) && is_array($_SESSION['cart'])
    ? array_sum($_SESSION['cart'])
    : 0;

$stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1");
$stmt->execute([(int)$u['id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

layout_header(__('title_change_password'), $user ?: null, $cartCount);
?>

<div class="container auth-page" style="max-width:520px;">

  <section class="card" style="padding:22px;">

    <div style="text-align:center; margin-bottom:16px;">
      <div style="font-size:42px;">🔒</div>
      <h1 style="margin:6px 0;"><?= __('title_change_password') ?></h1>
      <div class="muted"><?= __('desc_change_password') ?></div>
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
      <input type="hidden" name="csrf"
             value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

      <label for="current_password"><?= __('label_current_password') ?></label>
      <input id="current_password" name="current_password" type="password" required>

      <label for="new_password"><?= __('label_new_password') ?></label>
      <input id="new_password" name="new_password" type="password" required>

      <label for="confirm_password"><?= __('label_confirm_password') ?></label>
      <input id="confirm_password" name="confirm_password" type="password" required>

      <button class="btn btn-primary" type="submit" style="margin-top:6px;">
        <?= __('btn_update_password') ?>
      </button>
    </form>

    <div style="margin-top:12px; text-align:center;">
      <a class="pill" href="my_account.php">← <?= __('btn_back_account') ?></a>
    </div>

  </section>
</div>

<?php layout_footer(); ?>
