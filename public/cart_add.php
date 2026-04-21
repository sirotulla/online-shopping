<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/lang.php'; // Added lang helper

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

/** PHP 7.4 helpers */
function starts_with(string $haystack, string $needle): bool {
    if ($needle === '') return true;
    return strncmp($haystack, $needle, strlen($needle)) === 0;
}
function contains_str(string $haystack, string $needle): bool {
    if ($needle === '') return true;
    return strpos($haystack, $needle) !== false;
}

/** Safe local return URL */
function safe_return_url(string $raw, string $fallback): string {
    $raw = trim($raw);
    if ($raw === '') return $fallback;

    if (preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:/', $raw)) return $fallback; 
    if (starts_with($raw, '//')) return $fallback;
    if (starts_with($raw, '/')) return $raw;
    if (contains_str($raw, '\\')) return $fallback;

    return $raw;
}

/** Flash helper */
function flash_set(string $key, string $msg): void {
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][$key] = $msg;
}

// CSRF protection
$csrf = (string)($_POST['csrf'] ?? '');
if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
    http_response_code(403);
    exit('Request blocked');
}

// Validate product id
$productId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
if (!$productId) {
    http_response_code(400);
    exit('Invalid request');
}

// Validate qty (1..20)
$quantity = filter_var($_POST['qty'] ?? 1, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 20]
]);
if (!$quantity) {
    http_response_code(400);
    exit('Invalid quantity');
}

// Return URL
$fallbackReturn = 'product.php?id=' . urlencode((string)$productId);
$returnRaw = (string)($_POST['return'] ?? '');
$returnTo = safe_return_url($returnRaw, $fallbackReturn);

// Check product exists + stock
$stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ? LIMIT 1");
$stmt->execute([(int)$productId]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    exit('Product not found');
}

$stock = (int)($row['stock'] ?? 0);
if ($stock <= 0) {
    // TRANSLATED FLASH
    flash_set('bad', __('flash_out_of_stock') . ' ❌');
    header('Location: ' . $returnTo, true, 303);
    exit;
}

// Init cart
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$current = isset($_SESSION['cart'][$productId]) ? (int)$_SESSION['cart'][$productId] : 0;
$requested = $current + (int)$quantity;

// Hard cap to stock
$newQty = ($requested > $stock) ? $stock : $requested;
$_SESSION['cart'][$productId] = $newQty;

// TRANSLATED FLASHES
if ($requested > $stock) {
    flash_set('ok', __('flash_added_max'));
} else {
    flash_set('ok', __('flash_added'));
}

// Post/Redirect/Get
header('Location: ' . $returnTo, true, 303);
exit;
