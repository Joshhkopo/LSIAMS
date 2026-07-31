<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class DeviceLog extends Model
{
    protected string $table = 'device_logs';
    protected array $fillable = ['device_id', 'event', 'details'];

    public function recent(int $deviceId, int $limit = 50): array
    {
        return $this->query(
            'SELECT * FROM device_logs WHERE device_id = ? ORDER BY id DESC LIMIT ' . $limit,
            [$deviceId]
        );
    }
}
