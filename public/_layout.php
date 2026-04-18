<?php
declare(strict_types=1);

// Load the language helper
require_once __DIR__ . '/../app/helpers/lang.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/** Safe HTML escape helper */
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Asset helper */
function asset(string $path): string {
    return $path;
}

/** Send security headers safely */
function send_security_headers(): void {
    if (headers_sent()) return;
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

function flash_pull(): array {
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : [];
}

function layout_header(string $title = 'EasyBuy Gifts', ?array $user = null, int $cartCount = 0): void
{
    send_security_headers();
    $flash = flash_pull();
    $currentLang = $_SESSION['lang'] ?? 'en';
?>
<!doctype html>
<html lang="<?= e($currentLang) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> — EasyBuy Gifts</title>

  <link rel="icon" type="image/png" href="<?= e(asset('assets/favicon.png')) ?>">

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
      <a href="index.php"><?= __('nav_home') ?></a>
      <a href="cart.php">🛒 <?= __('nav_cart') ?> (<?= (int)$cartCount ?>)</a>

      <div class="lang-switcher">
          <?php
            $params = $_GET;
            $params['lang'] = 'en';
            $enUrl = '?' . http_build_query($params);
            $params['lang'] = 'uz';
            $uzUrl = '?' . http_build_query($params);
          ?>
          <a href="<?= e($enUrl) ?>" class="<?= $currentLang === 'en' ? 'active-lang' : '' ?>">EN</a>
          <span class="muted">|</span>
          <a href="<?= e($uzUrl) ?>" class="<?= $currentLang === 'uz' ? 'active-lang' : '' ?>">UZ</a>
      </div>

      <button class="btn btn-ghost" type="button" id="themeToggle" aria-label="Toggle theme">🌙</button>

      <?php if ($user): ?>
        <?php if (($user['role'] ?? '') === 'admin'): ?>
          <a href="admin/index.php"><?= __('nav_admin') ?></a>
        <?php endif; ?>
        <a href="my_account.php"><?= __('nav_account') ?></a>
        <a class="btn btn-ghost" href="logout.php"><?= __('nav_logout') ?></a>
      <?php else: ?>
        <a href="login.php"><?= __('nav_login') ?></a>
        <a class="btn btn-ghost" href="register.php"><?= __('nav_register') ?></a>
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
      <a class="flash-link" href="cart.php"><?= __('flash_view_cart') ?></a>
      <a class="flash-link" href="index.php"><?= __('flash_continue') ?></a>
    </div>
  </div>
<?php endif; ?>

<?php
}

function layout_footer(): void { ?>
  </div> </main>

<footer class="site-footer">
  <div class="container">
    <div class="footer-features">
      <div class="feat-card">
        <span class="feat-icon">🛡️</span>
        <div class="feat-info">
          <h4><?= __('feat_secure_title') ?></h4>
          <p><?= __('feat_secure_desc') ?></p>
        </div>
      </div>
      <div class="feat-card">
        <span class="feat-icon">📦</span>
        <div class="feat-info">
          <h4><?= __('feat_orders_title') ?></h4>
          <p><?= __('feat_orders_desc') ?></p>
        </div>
      </div>
      <div class="feat-card">
        <span class="feat-icon">👑</span>
        <div class="feat-info">
          <h4><?= __('feat_admin_title') ?></h4>
          <p><?= __('feat_admin_desc') ?></p>
        </div>
      </div>
    </div>

    <hr class="footer-sep">

    <div class="footer-main-grid">
      <div class="footer-column about">
        <h3 class="footer-heading">EasyBuy Gifts</h3>
        <p class="muted"><?= __('footer_about_desc') ?></p>
      </div>

      <div class="footer-column links">
        <h3 class="footer-heading"><?= __('footer_links_title') ?></h3>
        <ul class="foot-nav">
          <li><a href="index.php"><?= __('nav_home') ?></a></li>
          <li><a href="cart.php"><?= __('nav_cart') ?></a></li>
          <li><a href="my_account.php"><?= __('nav_account') ?></a></li>
        </ul>
      </div>

      <div class="footer-column social">
        <h3 class="footer-heading"><?= __('footer_contact_title') ?></h3>
        <div class="social-stack">
          <a href="https://t.me/easyshops_uz" target="_blank" class="social-link tg-chan">
              <span class="icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <line x1="22" y1="2" x2="11" y2="13"></line>
                      <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                  </svg>
              </span> 
              <?= __('footer_tg_channel') ?>
          </a>
          <a href="https://t.me/easyshops_uz_chat" target="_blank" class="social-link tg-admin">
            <span class="icon">💬</span> <?= __('footer_tg_admin') ?>
          </a>
        </div>
      </div>
    </div>

    <div class="footer-bottom">
      <div class="copy-text">
        © <?= date('Y') ?> EasyBuy Gifts. <?= __('footer_rights') ?>
      </div>
      <div class="tagline-text muted">
        <?= __('footer_tagline') ?>
      </div>
    </div>
  </div>
</footer>

</div> <script>
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


