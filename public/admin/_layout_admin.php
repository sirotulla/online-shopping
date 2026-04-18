<?php
declare(strict_types=1);

/**
 * Admin layout (shared header/footer + theme toggle)
 */

if (!function_exists('e')) {
    function e(string $v): string {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/** Security headers */
function admin_send_security_headers(): void {
    if (headers_sent()) return;
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy-Report-Only: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; base-uri 'self'; frame-ancestors 'none';");
}

function admin_layout_header(string $title, ?array $user = null): void
{
    admin_send_security_headers();
    ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> — Admin Panel</title>

  <link rel="icon" type="image/png" href="../assets/favicon.png">

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
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap">
</head>
<body class="admin-body">

<div class="page-wrap">

<header class="site-header">
  <div class="container header-row">
    <a class="brand" href="index.php">
      <span class="brand-logo">🛡️</span>
      <div class="brand-name">ADMIN<span class="brand-sub">PANEL</span></div>
    </a>

    <nav class="nav">
      <a href="index.php">Dashboard</a>
      <a href="topup_requests.php">Top-ups</a>
      <a href="orders.php">Orders</a>
      <a href="products.php">Products</a>
      <a href="categories.php">Categories</a>

      <div style="display:flex; gap:8px; margin-left:10px; padding-left:10px; border-left:1px solid var(--line);">
        <button class="btn btn-ghost" type="button" id="themeToggle" style="width:40px; padding:0;">🌙</button>
        <a class="btn btn-ghost" href="../logout.php" style="color:var(--accent);">Exit</a>
      </div>
    </nav>
  </div>
</header>

<main class="site-main">
  <div class="container">
    <div style="margin-bottom:20px;">
        <h1 style="margin:0; font-size:1.8rem; font-weight:900;"><?= e($title) ?></h1>
        <div class="muted">System Management & Control</div>
    </div>
    
    <div class="main-container"> <?php
}

function admin_layout_footer(): void
{
?>
    </div></div></main>

<footer class="site-footer">
  <div class="container">
    <div class="footer-bottom">
      <div style="display:flex; align-items:center; gap:10px;">
        <span style="font-size:1.2rem;">🛡️</span>
        <div>
            <div style="font-weight:700;">EasyBuy Admin Control</div>
            <div class="muted" style="font-size:12px;">Secure Session: Active</div>
        </div>
      </div>
      <div class="muted">© <?= date('Y') ?> Management Dashboard</div>
    </div>
  </div>
</footer>

</div><script>
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
