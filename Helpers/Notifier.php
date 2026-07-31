<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Database;

/**
 * In-app notification dispatcher (notifications table). Future channels
 * (SMS, email, push) plug in here without touching callers.
 */
final class Notifier
{
    public static function notify(int $userId, string $title, string $message, string $priority = 'normal'): void
    {
        try {
            Database::pdo()->prepare(
                'INSERT INTO notifications (user_id, title, message, priority, is_read, created_at)
                 VALUES (?, ?, ?, ?, 0, NOW())'
            )->execute([$userId, substr($title, 0, 150), substr($message, 0, 500), $priority]);
        } catch (\Throwable $e) {
            Logger::error('Notification failed: ' . $e->getMessage());
        }
    }

    /** Broadcast to every active administrator. */
    public static function notifyAdmins(string $title, string $message, string $priority = 'normal'): void
    {
        try {
            $pdo = Database::pdo();
            $admins = $pdo->query(
                "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
                 WHERE r.name = 'Administrator' AND u.status = 'active' AND u.deleted_at IS NULL"
            )->fetchAll();
            foreach ($admins as $admin) {
                self::notify((int) $admin['id'], $title, $message, $priority);
            }
        } catch (\Throwable $e) {
            Logger::error('Admin notification failed: ' . $e->getMessage());
        }
    }
}
