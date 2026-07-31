<?php

declare(strict_types=1);

use App\Core\Config;

return [
    'password_min_length'   => (int) Config::env('PASSWORD_MIN_LENGTH', 12),
    'login_max_attempts'    => (int) Config::env('LOGIN_MAX_ATTEMPTS', 5),
    'login_lockout_minutes' => (int) Config::env('LOGIN_LOCKOUT_MINUTES', 15),

    // Device API replay protection
    'api_timestamp_window'  => (int) Config::env('API_TIMESTAMP_WINDOW_SECONDS', 30),
    'heartbeat_interval'    => (int) Config::env('HEARTBEAT_INTERVAL_SECONDS', 30),
    'heartbeat_offline_after' => (int) Config::env('HEARTBEAT_OFFLINE_AFTER_SECONDS', 90),
    'sync_queue_alert'      => (int) Config::env('DEVICE_SYNC_QUEUE_ALERT', 25),

    // Rate limits: [max attempts, window minutes]
    'rate_limits' => [
        'login'             => [5, 15],
        'fingerprint_verify'=> [5, 5],
        'rfid_scan'         => [120, 1],
        'api_general'       => [300, 1],
    ],
];
