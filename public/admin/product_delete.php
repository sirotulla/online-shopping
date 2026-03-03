<?php
declare(strict_types=1);

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../../app/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed');
}

// Ensure CSRF exists
if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$csrf = (string)($_POST['csrf'] ?? '');
if (!hash_equals($_SESSION['csrf'], $csrf)) {
    http_response_code(403);
    die('Invalid CSRF token');
}

$id = (string)($_POST['id'] ?? '');
if (!ctype_digit($id)) {
    http_response_code(400);
    die('Invalid product id');
}

$productId = (int)$id;

try {
    // Soft delete (disable) — only if currently active
    $stmt = $pdo->prepare("
        UPDATE products
        SET is_active = 0
        WHERE id = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$productId]);

    // We do NOT error if already disabled
} catch (Throwable $e) {
    http_response_code(500);
    die('Delete failed');
}

header('Location: products.php', true, 303);
exit;
