<?php
declare(strict_types=1);

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/_layout_admin.php';

if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$errors = [];

// Handle add/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'], $csrf)) {
        http_response_code(403);
        $errors[] = 'Request blocked.';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'add') {
            $name = trim((string)($_POST['name'] ?? ''));

            if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 60) {
                $errors[] = 'Category name must be 2-60 characters.';
            } else {
                // Prevent duplicates (case-insensitive)
                $stmt = $pdo->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?) LIMIT 1");
                $stmt->execute([$name]);
                if ($stmt->fetch()) {
                    $errors[] = 'Category already exists.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
                    $stmt->execute([$name]);
                    $_SESSION['csrf'] = bin2hex(random_bytes(32));
                    header('Location: categories.php', true, 303);
                    exit;
                }
            }
        }

        if ($action === 'delete') {
            $id = (string)($_POST['id'] ?? '');
            if (!ctype_digit($id)) {
                $errors[] = 'Invalid category id.';
            } else {
                $catId = (int)$id;

                // Safe delete: detach products
                $stmt = $pdo->prepare("UPDATE products SET category_id = NULL WHERE category_id = ?");
                $stmt->execute([$catId]);

                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ? LIMIT 1");
                $stmt->execute([$catId]);

                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                header('Location: categories.php', true, 303);
                exit;
            }
        }
    }
}

// List categories with product counts
$stmt = $pdo->query("
  SELECT c.id, c.name, COUNT(p.id) AS product_count
  FROM categories c
  LEFT JOIN products p ON p.category_id = c.id
  GROUP BY c.id, c.name
  ORDER BY c.name ASC
");
$cats = $stmt->fetchAll();

admin_layout_header('Admin — Categories', $_SESSION['user'] ?? null);
?>

<!-- Page width constraint stays, but now inside admin layout container -->
<div style="max-width:980px; margin:0 auto;">

  <div style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:12px">
    <div>
      <div class="muted">Catalog</div>
      <h1 style="margin:6px 0 0">Categories</h1>
    </div>
    <div class="pill"><?= count($cats) ?> total</div>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-bad" style="margin-bottom:12px;">
      <ul style="margin:0; padding-left:18px;">
        <?php foreach ($errors as $msg): ?>
          <li><?= e($msg) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <section class="card" style="padding:16px;">
    <div style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;">
      <div>
        <div class="muted">Add new</div>
        <h2 style="margin:6px 0 0;">Create category</h2>
      </div>
      <div class="muted" style="font-size:12px;">2–60 chars, unique name</div>
    </div>

    <form method="post" class="form" style="grid-template-columns: 1fr auto; gap:10px; align-items:end; margin-top:12px;">
      <input type="hidden" name="csrf" value="<?= e((string)$_SESSION['csrf']) ?>">
      <input type="hidden" name="action" value="add">

      <div>
        <label for="name">Category name</label>
        <input id="name" name="name" type="text" placeholder="e.g. Accessories" required>
      </div>

      <button class="btn btn-primary" type="submit" style="height:44px;">Add</button>
    </form>
  </section>

  <div style="height:14px;"></div>

  <section class="card" style="padding:14px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
      <div class="pill">List</div>
      <div class="muted" style="font-size:12px;">Deleting makes products “uncategorized”</div>
    </div>

    <div class="table-wrap" style="margin-top:10px;">
      <table>
        <thead>
          <tr>
            <th style="width:90px;">ID</th>
            <th>Name</th>
            <th style="width:130px;">Products</th>
            <th style="width:140px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cats as $c): ?>
            <tr>
              <td><?= (int)$c['id'] ?></td>
              <td style="font-weight:850;"><?= e((string)$c['name']) ?></td>
              <td>
                <span class="pill"><?= (int)$c['product_count'] ?> items</span>
              </td>
              <td>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="csrf" value="<?= e((string)$_SESSION['csrf']) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                  <button class="btn btn-ghost" type="submit"
                    onclick="return confirm('Delete this category? Products will become uncategorized.')">
                    Delete
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

</div>

<style>
@media (max-width: 900px){
  .form[style*="grid-template-columns"]{ grid-template-columns: 1fr !important; }
}
</style>

<?php admin_layout_footer(); ?>
