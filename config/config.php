<?php

declare(strict_types=1);

use App\Core\Config;

return [
    'name'     => Config::env('APP_NAME', 'L-SIAMS'),
    'env'      => Config::env('APP_ENV', 'production'),
    'debug'    => (bool) Config::env('APP_DEBUG', false),
    'url'      => Config::env('APP_URL', 'https://192.168.1.10'),
    'timezone' => Config::env('APP_TIMEZONE', 'Asia/Manila'),

    'uploads' => [
        'path'            => BASE_PATH . '/public/uploads',
        'max_bytes'       => 2 * 1024 * 1024,
        'allowed_ext'     => ['jpg', 'jpeg', 'png', 'pdf', 'csv', 'xlsx'],
        'allowed_mime'    => [
            'image/jpeg', 'image/png', 'application/pdf', 'text/csv', 'text/plain',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
    ],

    'attendance' => [
        'window_minutes'        => (int) Config::env('ATTENDANCE_WINDOW_MINUTES', 20),
        'late_threshold_minutes'=> (int) Config::env('LATE_THRESHOLD_MINUTES', 20),
        'absent_cutoff_minutes' => (int) Config::env('ABSENT_CUTOFF_MINUTES', 60),
    ],

    'backup' => [
        'dir' => BASE_PATH . '/' . trim((string) Config::env('BACKUP_DIR', 'database/backups'), '/'),
        'key' => Config::env('BACKUP_ENCRYPTION_KEY', ''),
    ],
];
