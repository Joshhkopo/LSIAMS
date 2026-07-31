<?php

declare(strict_types=1);

use App\Core\Config;

return [
    'host'     => Config::env('DB_HOST', '127.0.0.1'),
    'port'     => Config::env('DB_PORT', '3306'),
    'database' => Config::env('DB_DATABASE', 'lsiams_db'),
    'username' => Config::env('DB_USERNAME', 'lsiams_app'),
    'password' => Config::env('DB_PASSWORD', ''),
];
