<?php
declare(strict_types=1);

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/_layout_admin.php';

$q = trim((string)($_GET['q'] ?? ''));

if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Search (safe)
if ($q !== '') {
    $stmt = $pdo->prepare("
      SELECT p.id, p.name, p.price, p.stock, c.name AS category_name
      FROM products p
      LEFT JOIN categories c ON c.id = p.category_id
      WHERE p.is_active = 1 AND p.name LIKE ?
      ORDER BY p.id DESC
    ");
    $stmt->execute(['%' . $q . '%']);
} else {
    $stmt = $pdo->query("
      SELECT p.id, p.name, p.price, p.stock, c.name AS category_name
      FROM products p
      LEFT JOIN categories c ON c.id = p.category_id
      WHERE p.is_active = 1
      ORDER BY p.id DESC
    ");
}

$products = $stmt->fetchAll();

admin_layout_header('Admin — Products', $_SESSION['user'] ?? null);
?>

<div style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:12px">
  <div>
    <div class="muted">Catalog</div>
    <h1 style="margin:6px 0 0">Products</h1>
  </div>

  <div class="hero-actions">
    <a class="btn btn-primary" href="product_create.php">+ Add product</a>
  </div>
</div>

<section class="card" style="padding:14px;">
  <form method="get" class="form" style="grid-template-columns: 1fr auto auto; align-items:end; gap:10px;">
    <div>
      <label for="q">Search by name</label>
      <input id="q" name="q" type="text" value="<?= e($q) ?>" placeholder="e.g. teddy, mug, flowers">
    </div>

    <button class="btn btn-primary" type="submit" style="height:44px;">Search</button>

    <?php if ($q !== ''): ?>
      <a class="btn btn-ghost" href="products.php" style="height:44px; display:inline-flex; align-items:center;">Clear</a>
    <?php else: ?>
      <span class="muted" style="font-size:12px;">&nbsp;</span>
    <?php endif; ?>
  </form>

  <div class="muted" style="font-size:12px; margin-top:10px;">
    Tip: Use Edit to update details. Delete is protected with CSRF.
  </div>
</section>

<div style="height:14px"></div>

<section class="card" style="padding:14px;">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
    <div class="pill"><?= count($products) ?> products</div>
    <div class="muted" style="font-size:12px;">Sorted by newest first</div>
  </div>

  <div class="table-wrap" style="margin-top:10px;">
    <table>
      <thead>
        <tr>
          <th style="width:70px;">ID</th>
          <th>Name</th>
          <th>Category</th>
          <th class="right" style="width:120px;">Price</th>
          <th class="right" style="width:90px;">Stock</th>
          <th style="width:210px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($products as $p): ?>
          <tr>
            <td><?= (int)$p['id'] ?></td>
            <td style="font-weight:850;"><?= e((string)$p['name']) ?></td>
            <td><span class="pill"><?= e((string)($p['category_name'] ?? '—')) ?></span></td>
            <td class="right">$<?= number_format((float)$p['price'], 2) ?></td>
            <td class="right"><?= (int)$p['stock'] ?></td>
            <td>
              <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <a class="btn" href="product_edit.php?id=<?= (int)$p['id'] ?>">Edit</a>

                <form method="post" action="product_delete.php" style="display:inline;">
                  <input type="hidden" name="csrf" value="<?= e((string)$_SESSION['csrf']) ?>">
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <button class="btn btn-ghost" type="submit" onclick="return confirm('Delete this product?')">
                    Delete
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<style>
@media (max-width: 900px){
  .form[style*="grid-template-columns"]{ grid-template-columns: 1fr !important; }
}
</style>

<?php admin_layout_footer(); ?>
