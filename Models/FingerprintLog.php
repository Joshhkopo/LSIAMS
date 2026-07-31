<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class FingerprintLog extends Model
{
    protected string $table = 'fingerprint_logs';
    protected array $fillable = ['teacher_id', 'device_id', 'result', 'details'];

    /** Consecutive failures from a device within the last N minutes. */
    public function recentFailures(int $deviceId, int $minutes): int
    {
        return (int) ($this->queryOne(
            'SELECT COUNT(*) AS c FROM fingerprint_logs
             WHERE device_id = ? AND result <> "verified"
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)',
            [$deviceId, $minutes]
        )['c'] ?? 0);
    }
}
