<?php
declare(strict_types=1);

// Allow only our generated filenames: 32 hex chars + .jpg/.jpeg/.png/.webp
$filename = $_GET['f'] ?? '';

if (!preg_match('/^[a-f0-9]{32}\.(jpg|jpeg|png|webp)$/i', $filename)) {
    http_response_code(400);
    die('Bad request');
}

// Path to storage (outside public in production-safe structure)
$path = __DIR__ . '/../storage/uploads/' . $filename;

if (!is_file($path)) {
    http_response_code(404);
    die('Not found');
}

// Detect mime by extension (PHP 7.4 compatible)
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

switch ($ext) {
    case 'jpg':
    case 'jpeg':
        $mime = 'image/jpeg';
        break;

    case 'png':
        $mime = 'image/png';
        break;

    case 'webp':
        $mime = 'image/webp';
        break;

    default:
        $mime = 'application/octet-stream';
        break;
}

// Security & Caching Headers
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($path));

// Browser cache: Tell browser to keep image for 30 days
header('Cache-Control: public, max-age=2592000'); 

readfile($path);
exit;
