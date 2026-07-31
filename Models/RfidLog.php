<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class RfidLog extends Model
{
    protected string $table = 'rfid_logs';
    protected array $fillable = ['uid', 'student_id', 'device_id', 'result', 'details'];

    public function tapHistory(string $uid, int $limit = 100): array
    {
        return $this->query(
            'SELECT rl.*, d.device_code FROM rfid_logs rl
             LEFT JOIN devices d ON d.id = rl.device_id
             WHERE rl.uid = ? ORDER BY rl.id DESC LIMIT ' . $limit,
            [$uid]
        );
    }
}
