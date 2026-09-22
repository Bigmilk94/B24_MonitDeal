<?php

// Router script for PHP's built-in dev server only:
// `php -S localhost:8000 -t public public/router.php`
// Real deployments (Apache/nginx) serve /public directly and never use this
// file — the web server itself serves existing files and routes everything
// else to index.php (see public/.htaccess).

if (PHP_SAPI === 'cli-server') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if ($path !== '/' && is_file(__DIR__ . $path)) {
        return false;
    }
}

require_once __DIR__ . '/index.php';
