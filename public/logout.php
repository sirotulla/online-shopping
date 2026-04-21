<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 1. Clear session data
$_SESSION = [];

// 2. Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// 3. Destroy session on server
session_destroy();

// 4. Redirect to home
// Using a 303 redirect is standard practice for Post-Logout
header("Location: index.php", true, 303);
exit;
