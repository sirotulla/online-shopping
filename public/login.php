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
$email = '';

/**
 * Safe next redirect target
 */
function sanitize_next(string $next): string {
    $next = trim($next);
    if ($next === '' || $next[0] !== '/' || strpos($next, '://') !== false) {
        return '/index.php';
    }
    return $next;
}

$next = sanitize_next((string)($_GET['next'] ?? '/index.php'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'], $csrf)) {
        http_response_code(403);
        $errors[] = __('err_csrf');
    } else {
        $next = sanitize_next((string)($_POST['next'] ?? '/index.php'));
        $email = trim((string)($_POST['email'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = __('err_invalid_credentials');
        } else {
            $stmt = $pdo->prepare("SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($pass, (string)$user['password_hash'])) {
                $errors[] = __('err_invalid_credentials');
            } else {
                session_regenerate_id(true);

                $_SESSION['user'] = [
                    'id'    => (int)$user['id'],
                    'name'  => (string)$user['name'],
                    'email' => (string)$user['email'],
                    'role'  => (string)$user['role'],
                ];

                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                header('Location: ' . $next, true, 303);
                exit;
            }
        }
    }
}

$registered = isset($_GET['registered']) && $_GET['registered'] === '1';

$cartCount = isset($_SESSION['cart']) && is_array($_SESSION['cart'])
    ? array_sum($_SESSION['cart'])
    : 0;

layout_header(__('nav_login'), null, $cartCount);
?>

<div class="container auth-page" style="max-width:520px;">

  <section class="card" style="padding:22px;">

    <div style="text-align:center; margin-bottom:16px;">
      <div style="font-size:42px;">🔐</div>
      <h1 style="margin:6px 0;"><?= __('title_welcome_back') ?></h1>
      <div class="muted"><?= __('desc_login') ?></div>
    </div>

    <?php if ($registered): ?>
      <div class="alert alert-ok" style="margin-bottom:12px;">
        ✅ <?= __('flash_registered_success') ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($_GET['next'])): ?>
      <div class="alert" style="margin-bottom:12px;">
        <?= __('alert_login_required') ?>
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
      <input type="hidden" name="next"
             value="<?= htmlspecialchars($next, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

      <label for="email"><?= __('label_email') ?></label>
      <input id="email" name="email" type="email" 
             value="<?= htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>

      <label for="password"><?= __('label_password') ?></label>
      <input id="password" name="password" type="password" required>

      <button class="btn btn-primary" type="submit" style="margin-top:6px; width:100%;">
        <?= __('nav_login') ?>
      </button>
    </form>

    <div style="margin-top:14px; text-align:center;">
      <span class="muted"><?= __('text_no_account') ?></span>
      <a href="register.php?next=<?= urlencode($next) ?>"><?= __('nav_register') ?></a>
    </div>

    <div style="margin-top:10px; text-align:center;">
      <a class="pill" href="<?= htmlspecialchars($next, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">← <?= __('btn_back') ?></a>
    </div>

  </section>
</div>

<?php layout_footer(); ?>
