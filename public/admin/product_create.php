<?php
declare(strict_types=1);

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/_layout_admin.php';

if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Load categories for dropdown
$cats = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

$errors = [];
$name = '';
$desc = '';
$price = '';
$stock = '';
$categoryId = '';
$imageName = null;

/**
 * NOTE: your upload validation is good. We keep it.
 * It runs if a file is present.
 */
if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Image upload failed.';
    } else {
        $maxBytes = 2 * 1024 * 1024; // 2MB
        if ((int)$_FILES['image']['size'] > $maxBytes) {
            $errors[] = 'Image too large (max 2MB).';
        } else {
            $tmp = $_FILES['image']['tmp_name'];

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmp);

            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
            ];

            if (!isset($allowed[$mime])) {
                $errors[] = 'Only JPG, PNG, WEBP allowed.';
            } else {
                $ext = $allowed[$mime];
                $imageName = bin2hex(random_bytes(16)) . '.' . $ext;

                $destDir = __DIR__ . '/../../storage/uploads/';
                if (!is_dir($destDir)) {
                    $errors[] = 'Upload directory missing.';
                } else {
                    $dest = $destDir . $imageName;

                    if (!move_uploaded_file($tmp, $dest)) {
                        $errors[] = 'Failed to save uploaded image.';
                    }
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'], $csrf)) {
        http_response_code(403);
        $errors[] = 'Request blocked.';
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        $price = trim((string)($_POST['price'] ?? ''));
        $stock = trim((string)($_POST['stock'] ?? ''));
        $categoryId = trim((string)($_POST['category_id'] ?? ''));

        // Validate
        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 150) {
            $errors[] = 'Name must be 2-150 characters.';
        }
        if (mb_strlen($desc) > 2000) {
            $errors[] = 'Description too long.';
        }
        if (!is_numeric($price) || (float)$price < 0 || (float)$price > 100000) {
            $errors[] = 'Price must be a valid number.';
        }
        if (!ctype_digit($stock) || (int)$stock < 0 || (int)$stock > 100000) {
            $errors[] = 'Stock must be a valid non-negative integer.';
        }

        $catIdFinal = null;
        if ($categoryId !== '') {
            if (!ctype_digit($categoryId)) {
                $errors[] = 'Invalid category.';
            } else {
                $catIdFinal = (int)$categoryId;
            }
        }

        if (!$errors) {
            $stmt = $pdo->prepare("
              INSERT INTO products (category_id, name, description, price, image, stock)
              VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $catIdFinal,
                $name,
                $desc === '' ? null : $desc,
                (float)$price,
                $imageName,
                (int)$stock
            ]);

            // Rotate CSRF after successful write
            $_SESSION['csrf'] = bin2hex(random_bytes(32));

            header('Location: products.php', true, 303);
            exit;
        }
    }
}

admin_layout_header('Admin — Add Product', $_SESSION['user'] ?? null);
?>

<div style="max-width:980px; margin:0 auto;">

  <div style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:12px">
    <div>
      <div class="muted">Catalog</div>
      <h1 style="margin:6px 0 0">Create new product</h1>
    </div>
    <a class="pill" href="products.php">← Back to products</a>
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
    <form method="post" enctype="multipart/form-data" class="form">
      <input type="hidden" name="csrf" value="<?= e((string)$_SESSION['csrf']) ?>">

      <div class="grid" style="grid-template-columns: 1.4fr .8fr; gap:14px; align-items:start;">

        <!-- LEFT: main details -->
        <div class="card" style="padding:14px; background:rgba(255,255,255,.02);">
          <h3 style="margin-top:0;">Product details</h3>

          <label for="name">Name</label>
          <input id="name" name="name" type="text" value="<?= e($name) ?>" required placeholder="e.g. Teddy Bear Gift Box">

          <label for="description">Description</label>
          <textarea id="description" name="description" rows="5" placeholder="Short, clear description..."><?= e($desc) ?></textarea>

          <label for="image">Image (jpg/png/webp, max 2MB)</label>
          <input id="image" type="file" name="image" accept=".jpg,.jpeg,.png,.webp">

          <div class="muted" style="font-size:12px; margin-top:8px; line-height:1.5;">
            Security note: uploads are validated by MIME type and stored outside web root.
          </div>
        </div>

        <!-- RIGHT: pricing + category -->
        <div class="card" style="padding:14px; background:rgba(255,255,255,.02);">
          <h3 style="margin-top:0;">Pricing & stock</h3>

          <label for="category_id">Category</label>
          <select id="category_id" name="category_id">
            <option value="">— None —</option>
            <?php foreach ($cats as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= ($categoryId === (string)$c['id']) ? 'selected' : '' ?>>
                <?= e((string)$c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <label for="price">Price</label>
          <input id="price" name="price" type="number" step="0.01" value="<?= e($price) ?>" placeholder="e.g. 4.99" required>

          <label for="stock">Stock</label>
          <input id="stock" name="stock" type="number" step="1" min="0" value="<?= e($stock) ?>" placeholder="e.g. 50" required>

          <div style="height:12px;"></div>

          <button class="btn btn-primary" type="submit" style="width:100%;">Create</button>

          <div class="muted" style="font-size:12px; margin-top:10px; line-height:1.5;">
            Tip: Keep names short and clear for better product cards on the shop page.
          </div>
        </div>

      </div>
    </form>
  </section>

</div>

<style>
@media (max-width: 900px){
  .grid[style*="grid-template-columns"]{ grid-template-columns: 1fr !important; }
}
</style>

<?php admin_layout_footer(); ?>
