<?php
declare(strict_types=1);

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/_layout_admin.php';

if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$id = $_GET['id'] ?? '';
if (!ctype_digit((string)$id)) {
    http_response_code(400);
    die('Invalid product id');
}
$productId = (int)$id;

// Load product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
$stmt->execute([$productId]);
$product = $stmt->fetch();
if (!$product) {
    http_response_code(404);
    die('Product not found');
}

// Load categories
$cats = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

// Form defaults
$errors = [];
$name = (string)($product['name'] ?? '');
$desc = (string)($product['description'] ?? '');
$price = (string)($product['price'] ?? '');
$stock = (string)($product['stock'] ?? '');
$categoryId = $product['category_id'] === null ? '' : (string)$product['category_id'];

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

        $removeImage = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';

        $oldImage = $product['image'] ?? null;
        $newImageName = null;
        $uploadedNewImage = false;

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
                        $newImageName = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
                        $destDir = __DIR__ . '/../../storage/uploads/';

                        if (!is_dir($destDir)) {
                            $errors[] = 'Upload folder missing: storage/uploads';
                        } else {
                            $dest = $destDir . $newImageName;

                            if (!move_uploaded_file($tmp, $dest)) {
                                $errors[] = 'Failed to save uploaded image.';
                            } else {
                                $uploadedNewImage = true;
                            }
                        }
                    }
                }
            }
        }

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

        $imageFinal = $oldImage;
        if ($removeImage) {
            $imageFinal = null;
        }
        if ($uploadedNewImage) {
            $imageFinal = $newImageName;
        }

        if (!$errors) {
            $stmt = $pdo->prepare("
              UPDATE products
              SET category_id = ?, name = ?, description = ?, price = ?, image = ?, stock = ?
              WHERE id = ?
            ");
            $stmt->execute([
                $catIdFinal,
                $name,
                $desc === '' ? null : $desc,
                (float)$price,
                $imageFinal,
                (int)$stock,
                $productId
            ]);

            $uploadsDir = __DIR__ . '/../../storage/uploads/';

            if ($oldImage && ($uploadedNewImage || $removeImage)) {
                if (preg_match('/^[a-f0-9]{32}\.(jpg|jpeg|png|webp)$/i', (string)$oldImage)) {
                    $oldPath = $uploadsDir . $oldImage;
                    if (is_file($oldPath)) {
                        @unlink($oldPath);
                    }
                }
            }

            $_SESSION['csrf'] = bin2hex(random_bytes(32));

            header('Location: products.php', true, 303);
            exit;
        }
    }
}

admin_layout_header('Admin — Edit Product', $_SESSION['user'] ?? null);
?>

<div style="max-width:1100px; margin:0 auto;">

  <div style="display:flex;justify-content:space-between;align-items:end;gap:10px;flex-wrap:wrap;margin-bottom:12px">
    <div>
      <div class="muted">Catalog</div>
      <h1 style="margin:6px 0 0">Edit product #<?= (int)$productId ?></h1>
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

      <div class="grid" style="grid-template-columns: 1.3fr .9fr; gap:14px; align-items:start;">

        <!-- LEFT: main details -->
        <div class="card" style="padding:14px; background:rgba(255,255,255,.02);">
          <h3 style="margin-top:0;">Product details</h3>

          <label for="name">Name</label>
          <input id="name" name="name" type="text" value="<?= e($name) ?>" required>

          <label for="description">Description</label>
          <textarea id="description" name="description" rows="6"><?= e($desc) ?></textarea>

          <div style="height:8px"></div>

          <?php if (!empty($product['image'])): ?>
            <div class="card" style="padding:12px; background:rgba(255,255,255,.03);">
              <div class="muted" style="font-size:12px;">Current image</div>
              <img
                src="../image.php?f=<?= urlencode((string)$product['image']) ?>"
                alt=""
                style="width:100%; max-width:420px; aspect-ratio: 16/9; object-fit:cover; border-radius:14px; margin-top:8px;"
              >

              <label style="margin-top:10px; display:flex; gap:10px; align-items:center;">
                <input type="checkbox" name="remove_image" value="1" style="width:auto;">
                <span>Remove current image</span>
              </label>
            </div>
          <?php endif; ?>

          <label for="image">Replace image (JPG/PNG/WEBP, max 2MB)</label>
          <input id="image" type="file" name="image" accept=".jpg,.jpeg,.png,.webp">

          <div class="muted" style="font-size:12px; margin-top:8px; line-height:1.5;">
            Uploads are MIME-validated and stored outside web root.
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
          <input id="price" name="price" type="number" step="0.01" value="<?= e($price) ?>" required>

          <label for="stock">Stock</label>
          <input id="stock" name="stock" type="number" step="1" min="0" value="<?= e($stock) ?>" required>

          <div style="height:12px;"></div>

          <button class="btn btn-primary" type="submit" style="width:100%;">Save changes</button>
          <a class="btn btn-ghost" href="products.php" style="width:100%; margin-top:10px; text-align:center;">Cancel</a>

          <div class="muted" style="font-size:12px; margin-top:10px; line-height:1.5;">
            Tip: If you upload a new image, the old one is deleted safely.
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
