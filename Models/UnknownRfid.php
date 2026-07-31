<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class UnknownRfid extends Model
{
    protected string $table = 'unknown_rfid_cards';
    protected array $fillable = ['uid', 'device_id', 'seen_count', 'status'];
    protected bool $timestamps = false;

    /** Mark a formerly-unknown UID as assigned once it gets registered. */
    public function markAssigned(string $uid): void
    {
        $this->execute("UPDATE unknown_rfid_cards SET status = 'assigned' WHERE uid = ?", [$uid]);
    }

    /** Record a sighting of an unregistered UID (insert or bump counter). */
    public function recordSighting(string $uid, ?int $deviceId): void
    {
        $this->execute(
            'INSERT INTO unknown_rfid_cards (uid, device_id, seen_count, first_seen, last_seen)
             VALUES (?, ?, 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE seen_count = seen_count + 1, last_seen = NOW(), device_id = VALUES(device_id)',
            [$uid, $deviceId]
        );
    }
}
