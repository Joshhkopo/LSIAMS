<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class DeviceHeartbeat extends Model
{
    protected string $table = 'device_heartbeats';
    protected array $fillable = ['device_id', 'signal_strength', 'queue_count', 'firmware', 'uptime_seconds'];

    /** Retention rule: heartbeats are kept 90 days. */
    public function pruneOld(): int
    {
        return $this->execute('DELETE FROM device_heartbeats WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)');
    }
}
