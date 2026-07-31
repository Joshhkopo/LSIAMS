<?php

declare(strict_types=1);

/**
 * L-SIAMS scheduled maintenance (CLI only).
 *
 *   php scripts/cron.php          # every minute: sweeps + auto-close + retention
 *   php scripts/cron.php backup   # daily: automatic database backup
 *
 * The web app also performs the sweeps opportunistically on heartbeat
 * traffic, so this cron is belt-and-braces for quiet periods.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/Autoloader.php';
App\Core\Autoloader::register();
App\Core\Config::load();
date_default_timezone_set(App\Core\Config::get('app.timezone'));

$mode = $argv[1] ?? 'tick';

if ($mode === 'backup') {
    $settings = new App\Models\Setting();
    if ($settings->value('backup_schedule', 'daily') !== 'manual') {
        [$ok, $message] = (new App\Services\BackupService())->create('auto');
        echo ($ok ? 'OK: ' : 'FAIL: ') . $message . PHP_EOL;
    }
    exit(0);
}

// Regular tick.
(new App\Services\DeviceService())->sweepOffline();
$closed = (new App\Services\AttendanceService())->autoCloseExpired();
$pruned = (new App\Models\DeviceHeartbeat())->pruneOld();

// Notification retention: 180 days.
App\Core\Database::pdo()
    ->prepare('DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)')
    ->execute();

echo sprintf("tick ok — sessions auto-closed: %d, heartbeats pruned: %d\n", $closed, $pruned);
