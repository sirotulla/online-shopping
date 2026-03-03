<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/auth.php';
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
        $errors[] = 'Request blocked.';
    } else {
        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if ($current === '' || $new === '' || $confirm === '') {
            $errors[] = 'All fields are required.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New password and confirmation do not match.';
        } elseif (mb_strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new === $current) {
            $errors[] = 'New password must be different from current password.';
        }

        if (!$errors) {
            // load fresh hash from DB (never trust session)
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$u['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $errors[] = 'User not found.';
            } elseif (!password_verify($current, (string)$row['password_hash'])) {
                $errors[] = 'Current password is incorrect.';
            } else {
                $newHash = password_hash($new, PASSWORD_DEFAULT);

                $upd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ? LIMIT 1");
                if ($upd->execute([$newHash, (int)$u['id']])) {
                    // rotate session after password change
                    session_regenerate_id(true);
                    $_SESSION['csrf'] = bin2hex(random_bytes(32));

                    $_SESSION['flash_success'] = 'Password updated successfully.';
                    header('Location: my_account.php', true, 303);
                    exit;

                } else {
                    $errors[] = 'Failed to update password.';
                }
            }
        }
    }
}

$cartCount = isset($_SESSION['cart']) && is_array($_SESSION['cart'])
    ? array_sum($_SESSION['cart'])
    : 0;

// Optional: reload user for header display
$stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1");
$stmt->execute([(int)$u['id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

layout_header('Change Password', $user ?: null, $cartCount);
?>

<div class="container auth-page" style="max-width:520px;">

  <section class="card" style="padding:22px;">

    <div style="text-align:center; margin-bottom:16px;">
      <div style="font-size:42px;">🔒</div>
      <h1 style="margin:6px 0;">Change password</h1>
      <div class="muted">Update your account password securely</div>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-ok" style="margin-bottom:12px;">
        <?= htmlspecialchars($success, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
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

    <form method="post" class="form" autocomplete="off">
      <input type="hidden" name="csrf"
             value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

      <label for="current_password">Current password</label>
      <input id="current_password" name="current_password" type="password" required>

      <label for="new_password">New password</label>
      <input id="new_password" name="new_password" type="password" required>

      <label for="confirm_password">Confirm new password</label>
      <input id="confirm_password" name="confirm_password" type="password" required>

      <button class="btn btn-primary" type="submit" style="margin-top:6px;">
        Update password
      </button>
    </form>

    <div style="margin-top:12px; text-align:center;">
      <a class="pill" href="my_account.php">← Back to my account</a>
    </div>

  </section>
</div>

<?php layout_footer(); ?>
