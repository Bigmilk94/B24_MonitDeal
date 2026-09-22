<?php

declare(strict_types=1);

// Front controller for PHP's built-in dev server (`php -S host:port public/router.php`).
// Static assets are served as-is; everything else goes through index.php.

$path = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$publicDir = __DIR__;
$file = $publicDir . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

require $publicDir . '/index.php';
