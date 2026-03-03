<?php
declare(strict_types=1);

function current_user(): ?array {
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function require_login(): void {
    if (!current_user()) {

        // Remember where user wanted to go
        $next = $_SERVER['REQUEST_URI'] ?? '/';
        $next = is_string($next) ? $next : '/';

        header('Location: /login.php?next=' . urlencode($next), true, 303);
        exit;
    }
}

function is_admin(): bool {
    $u = current_user();
    return $u && ($u['role'] ?? '') === 'admin';
}
