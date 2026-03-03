<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../app/helpers/auth.php';

require_login();

if (!is_admin()) {
    http_response_code(403);
    die('Forbidden');
}
