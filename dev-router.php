<?php

declare(strict_types=1);

/**
 * Router for PHP's built-in development server (NOT for production).
 *
 *   php -S 0.0.0.0:8000 -t public scripts/dev-router.php
 *
 * Serves real files (assets, uploads) directly and routes everything else
 * through the front controller, emulating the Apache rewrite rules.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . '/../public' . $path;

if ($path !== '/' && is_file($file)) {
    return false; // let the built-in server serve the static file
}

require __DIR__ . '/../public/index.php';
