<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Database;

/**
 * Security event log — separate from the audit trail. Records failed logins,
 * unknown devices/RFID, replay attempts, rate-limit hits, injection attempts.
 */
final class SecurityLogger
{
    /**
     * @param string $severity low|medium|high|critical
     */
    public static function log(
        string $event,
        string $severity,
        string $description,
        ?string $sourceIp = null,
        ?int $deviceId = null,
    ): void {
        try {
            Database::pdo()->prepare(
                'INSERT INTO security_logs (event, severity, description, source_ip, device_id, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())'
            )->execute([
                substr($event, 0, 60),
                in_array($severity, ['low', 'medium', 'high', 'critical'], true) ? $severity : 'medium',
                substr($description, 0, 500),
                $sourceIp ?? ($_SERVER['REMOTE_ADDR'] ?? null),
                $deviceId,
            ]);
        } catch (\Throwable $e) {
            Logger::error('Security log write failed: ' . $e->getMessage());
        }
    }
}
