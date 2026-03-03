<?php
declare(strict_types=1);

/**
 * Admin layout (shared header/footer + theme toggle)
 * Use with:
 *   require_once __DIR__ . '/_guard.php';
 *   require_once __DIR__ . '/_layout_admin.php';
 *   admin_layout_header('Admin — Something', $u);
 *   ...content...
 *   admin_layout_footer();
 */

function e(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Optional: same security headers idea as main layout */
function admin_send_security_headers(): void {
    if (headers_sent()) return;

    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    // Keep report-only like your main layout (safe for dev)
    header("Content-Security-Policy-Report-Only: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; base-uri 'self'; frame-ancestors 'none';");
}

function admin_layout_header(string $title, ?array $user = null): void
{
    admin_send_security_headers();
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> — EasyBuy Gifts</title>

  <!-- Theme bootstrap (prevents flash + applies saved choice) -->
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

  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="page-wrap"><!-- enables side background/glow via CSS -->

<header class="site-header">
  <div class="container header-row">
    <a class="brand" href="index.php">
      <span class="brand-logo">🛡️</span>
      <span class="brand-name">Admin</span>
      <span class="brand-sub"><?= e($title) ?></span>
    </a>

    <nav class="nav">
      <a href="index.php">Dashboard</a>
      <a href="topup_requests.php">Top-ups</a>
      <a href="orders.php">Orders</a>
      <a href="products.php">Products</a>
      <a href="categories.php">Categories</a>

      <button class="btn btn-ghost" type="button" id="themeToggle" aria-label="Toggle theme" title="Toggle light/dark">
        🌙
      </button>

      <a class="btn btn-ghost" href="../logout.php">Logout</a>
    </nav>
  </div>
</header>

<main class="site-main">
  <div class="container main-container">
<?php
}

function admin_layout_footer(): void
{
?>
  </div><!-- /.container.main-container -->
</main>

<footer class="site-footer">
  <div class="container footer-row">
    <div class="footer-left">
      <div class="foot-brand">🛡️ Admin — EasyBuy Gifts</div>
      <div class="muted">Secure management panel</div>
    </div>
    <div class="footer-right muted">© <?= date('Y') ?> EasyBuy Gifts</div>
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
<?php
}
