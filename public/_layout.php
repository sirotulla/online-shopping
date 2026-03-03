<?php
declare(strict_types=1);

/**
 * Usage:
 *   require __DIR__.'/_layout.php';
 *   layout_header('Home', $user, $cartCount);
 *   ...page content...
 *   layout_footer();
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/** Safe HTML escape helper */
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Asset helper (keep simple) */
function asset(string $path): string {
    return $path;
}

/** Send security headers safely (only if headers not already sent) */
function send_security_headers(): void {
    if (headers_sent()) return;

    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    // CSP in Report-Only (dev-friendly)
    header("Content-Security-Policy-Report-Only: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; base-uri 'self'; frame-ancestors 'none';");
}

/**
 * Pull flash once (PRG-friendly).
 * Shows once, then deletes.
 */
function flash_pull(): array {
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : [];
}

/**
 * Layout header
 * $user can be current_user() array or null.
 */
function layout_header(string $title = 'EasyBuy Gifts', ?array $user = null, int $cartCount = 0): void
{
    send_security_headers();
    $flash = flash_pull();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> — EasyBuy Gifts</title>

  <!-- Theme bootstrap (prevents flash, sets html[data-theme]) -->
  <script>
  (function(){
    try{
      const key = "theme";
      const saved = localStorage.getItem(key);
      const prefersLight = window.matchMedia && window.matchMedia("(prefers-color-scheme: light)").matches;
      const theme = saved || (prefersLight ? "light" : "dark");
      document.documentElement.setAttribute("data-theme", theme);
    }catch(e){}
  })();
  </script>

  <link rel="stylesheet" href="<?= e(asset('assets/style.css?v=' . filemtime(__DIR__ . '/assets/style.css'))) ?>">

</head>

<body>
<div class="page-wrap">

<header class="site-header">
  <div class="container header-row">
    <a class="brand" href="index.php">
      <span class="brand-logo">🎁</span>
      <span class="brand-name">EasyBuy</span>
      <span class="brand-sub">Gifts</span>
    </a>

    <nav class="nav">
      <a href="index.php">Home</a>
      <a href="cart.php">🛒 Cart (<?= (int)$cartCount ?>)</a>

      <button class="btn btn-ghost" type="button" id="themeToggle" aria-label="Toggle theme" title="Toggle light/dark">
        🌙
      </button>

      <?php if ($user): ?>
        <?php if (($user['role'] ?? '') === 'admin'): ?>
          <a href="admin/index.php">Admin</a>
        <?php endif; ?>
        <a href="my_account.php">My Account</a>
        <a class="btn btn-ghost" href="logout.php">Logout</a>
      <?php else: ?>
        <a href="login.php">Login</a>
        <a class="btn btn-ghost" href="register.php">Register</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<main class="site-main">
  <div class="container main-container">

<?php if (!empty($flash['ok'])): ?>
  <div class="flash flash-ok">
    <div class="flash-left">
      <span class="flash-icon">✅</span>
      <span class="flash-text"><?= e((string)$flash['ok']) ?></span>
    </div>
    <div class="flash-actions">
      <a class="flash-link" href="cart.php">View cart</a>
      <a class="flash-link" href="index.php">Continue shopping</a>
    </div>
  </div>
<?php endif; ?>

<?php
}

function layout_footer(): void { ?>
  </div><!-- /.container.main-container -->
</main>

<footer class="site-footer">
  <div class="container footer-row">
    <div class="footer-left">
      <div class="foot-brand">🎁 EasyBuy Gifts</div>
      <div class="muted">Gifts • Souvenirs • Happy moments</div>
    </div>

    <div class="footer-right muted">
      © <?= date('Y') ?> EasyBuy Gifts. All rights reserved.
    </div>
  </div>
</footer>

</div><!-- /.page-wrap -->

<script>
(function(){
  const key = "theme";
  const btn = document.getElementById("themeToggle");
  if(!btn) return;

  function apply(theme){
    document.documentElement.setAttribute("data-theme", theme);
    btn.textContent = (theme === "light") ? "☀️" : "🌙";
  }

  const current = document.documentElement.getAttribute("data-theme") || "dark";
  apply(current);

  btn.addEventListener("click", () => {
    const cur = document.documentElement.getAttribute("data-theme") || "dark";
    const next = (cur === "light") ? "dark" : "light";
    try{ localStorage.setItem(key, next); }catch(e){}
    apply(next);
  });
})();
</script>

</body>
</html>
<?php } ?>

