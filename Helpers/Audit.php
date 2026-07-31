<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Database;

/**
 * Immutable audit trail. Every administrative and authentication action is
 * recorded here; there is deliberately no update/delete path for this table
 * through the application.
 */
final class Audit
{
    public static function log(
        string $action,
        string $module,
        int|string|null $affectedId = null,
        mixed $oldValue = null,
        mixed $newValue = null,
    ): void {
        try {
            Database::pdo()->prepare(
                'INSERT INTO audit_logs
                    (user_id, action, module, affected_record, old_value, new_value, ip_address, user_agent, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            )->execute([
                Auth::id(),
                substr($action, 0, 100),
                substr($module, 0, 50),
                $affectedId !== null ? (string) $affectedId : null,
                $oldValue !== null ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : null,
                $newValue !== null ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Audit log write failed: ' . $e->getMessage());
        }
    }
}
