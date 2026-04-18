<?php
declare(strict_types=1);

/**
 * Handle language state and translation loading.
 * This version relies on _layout.php providing links that 
 * preserve existing GET parameters.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/** 1. Update session if lang is passed via URL **/
if (isset($_GET['lang'])) {
    $requestedLang = (string)$_GET['lang'];
    if (in_array($requestedLang, ['en', 'uz'], true)) {
        $_SESSION['lang'] = $requestedLang;
    }
}

/** 2. Set default language if none exists **/
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}

/** 3. Load the corresponding translation array **/
$langCode = $_SESSION['lang'];
$langFile = __DIR__ . "/../languages/{$langCode}.php";

if (is_file($langFile)) {
    $translations = include $langFile;
} else {
    $translations = [];
}

/** * 4. Translation Helper Function 
 * Returns the translated string or the key itself if not found.
 */
function __(?string $key): string {
    global $translations;
    if ($key === null) return '';
    return $translations[$key] ?? $key;
}
