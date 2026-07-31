<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Notification extends Model
{
    protected string $table = 'notifications';
    protected array $fillable = ['user_id', 'title', 'message', 'priority', 'is_read'];
    protected bool $timestamps = false;

    public function forUser(int $userId, int $limit = 30): array
    {
        return $this->query(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ' . $limit,
            [$userId]
        );
    }

    public function unreadCount(int $userId): int
    {
        return (int) ($this->queryOne(
            'SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0',
            [$userId]
        )['c'] ?? 0);
    }

    public function markAllRead(int $userId): void
    {
        $this->execute('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$userId]);
    }
}
