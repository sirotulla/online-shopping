<?php
declare(strict_types=1);

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';
require_once __DIR__ . '/_layout_admin.php';

$u = current_user();

// --- Admin stats ---
$stmt = $pdo->query("SELECT COUNT(*) AS c FROM topup_requests WHERE status='pending'");
$pendingTopups = (int)($stmt->fetch()['c'] ?? 0);

$stmt = $pdo->query("SELECT COALESCE(SUM(amount),0) AS s FROM wallet_transactions WHERE type='topup'");
$totalTopups = (float)($stmt->fetch()['s'] ?? 0);

$stmt = $pdo->query("SELECT COALESCE(SUM(amount),0) AS s FROM wallet_transactions WHERE type='purchase'");
$totalPurchasesNeg = (float)($stmt->fetch()['s'] ?? 0); // negative amounts
$totalPurchases = abs($totalPurchasesNeg);

$stmt = $pdo->query("SELECT COUNT(*) AS c FROM users");
$userCount = (int)($stmt->fetch()['c'] ?? 0);

$stmt = $pdo->query("SELECT COUNT(*) AS c FROM orders");
$orderCount = (int)($stmt->fetch()['c'] ?? 0);

admin_layout_header('Admin Dashboard', $u);
?>

<section class="card hero" style="padding:22px;">
  <div class="hero-row">
    <div>
      <div class="hero-badge">⚙️ Manage products • approve top-ups • track orders</div>
      <h1 style="margin:0 0 10px;">Admin Dashboard</h1>
      <div class="muted" style="line-height:1.5;">
        Hello, <strong><?= e((string)($u['name'] ?? 'Admin')) ?></strong>
        <span class="muted">(role: <?= e((string)($u['role'] ?? 'admin')) ?>)</span>
      </div>

      <div class="hero-actions" style="margin-top:12px;">
        <a class="btn btn-primary" href="topup_requests.php">Pending requests (<?= (int)$pendingTopups ?>)</a>
        <a class="btn" href="products.php">Products</a>
        <a class="btn" href="categories.php">Categories</a>
      </div>
    </div>

    <div class="hero-right">
      <div class="hero-stat">
        <div class="num"><?= (int)$pendingTopups ?></div>
        <div class="lab">Pending top-up requests</div>
      </div>
      <div class="hero-stat">
        <div class="num">$<?= number_format($totalTopups, 2) ?></div>
        <div class="lab">Total approved top-ups</div>
      </div>
      <div class="hero-stat">
        <div class="num">$<?= number_format($totalPurchases, 2) ?></div>
        <div class="lab">Total purchases</div>
      </div>
    </div>
  </div>
</section>

<div style="height:16px"></div>

<!-- Stats cards -->
<section>
  <div style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:10px">
    <div>
      <div class="muted">Overview</div>
      <h2 style="margin:6px 0 0">Key metrics</h2>
    </div>
    <div class="pill">Live totals from database</div>
  </div>

  <div class="grid grid-3">
    <div class="card" style="padding:16px;">
      <div class="muted" style="font-size:13px;">Pending top-up requests</div>
      <div style="font-size:34px; font-weight:950; margin-top:6px;"><?= (int)$pendingTopups ?></div>
      <div style="margin-top:10px;">
        <a class="btn btn-primary" href="topup_requests.php" style="width:100%;">Open requests →</a>
      </div>
    </div>

    <div class="card" style="padding:16px;">
      <div class="muted" style="font-size:13px;">Total approved top-ups</div>
      <div style="font-size:34px; font-weight:950; margin-top:6px;">$<?= number_format($totalTopups, 2) ?></div>
      <div class="muted" style="margin-top:8px;">Wallet topup transactions</div>
    </div>

    <div class="card" style="padding:16px;">
      <div class="muted" style="font-size:13px;">Users / Orders</div>
      <div style="font-size:34px; font-weight:950; margin-top:6px;"><?= (int)$userCount ?> / <?= (int)$orderCount ?></div>
      <div class="muted" style="margin-top:8px;">Registered users and total orders</div>
    </div>
  </div>
</section>

<div style="height:16px"></div>

<!-- Manage -->
<section class="card" style="padding:18px;">
  <div style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;">
    <div>
      <div class="muted">Admin actions</div>
      <h2 style="margin:6px 0 0">Manage</h2>
    </div>
    <div class="pill">Quick navigation</div>
  </div>

  <div class="grid" style="grid-template-columns: repeat(3, minmax(0, 1fr)); gap:12px; margin-top:12px;">
    <a class="card product-card" href="products.php" style="padding:16px; display:block;">
      <div class="pill">Catalog</div>
      <div style="font-weight:950; font-size:18px; margin-top:8px;">Products</div>
      <div class="muted" style="margin-top:6px;">Create, edit, delete items</div>
    </a>

    <a class="card product-card" href="categories.php" style="padding:16px; display:block;">
      <div class="pill">Catalog</div>
      <div style="font-weight:950; font-size:18px; margin-top:8px;">Categories</div>
      <div class="muted" style="margin-top:6px;">Organize products</div>
    </a>

    <a class="card product-card" href="orders.php" style="padding:16px; display:block;">
      <div class="pill">Sales</div>
      <div style="font-weight:950; font-size:18px; margin-top:8px;">Orders</div>
      <div class="muted" style="margin-top:6px;">View order history</div>
    </a>

    <a class="card product-card" href="topup_requests.php" style="padding:16px; display:block;">
      <div class="pill">Wallet</div>
      <div style="font-weight:950; font-size:18px; margin-top:8px;">Top-up Requests</div>
      <div class="muted" style="margin-top:6px;">Approve or reject requests</div>
    </a>

    <a class="card product-card" href="topup.php" style="padding:16px; display:block;">
      <div class="pill">Wallet</div>
      <div style="font-weight:950; font-size:18px; margin-top:8px;">Manual Top-up</div>
      <div class="muted" style="margin-top:6px;">Add balance to user</div>
    </a>

    <a class="card product-card" href="../index.php" style="padding:16px; display:block;">
      <div class="pill">Site</div>
      <div style="font-weight:950; font-size:18px; margin-top:8px;">Back to Shop</div>
      <div class="muted" style="margin-top:6px;">View frontend</div>
    </a>
  </div>
</section>

<?php admin_layout_footer(); ?>
