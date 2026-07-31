<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class AttendanceModification extends Model
{
    protected string $table = 'attendance_modifications';
    protected array $fillable = ['attendance_id', 'admin_id', 'old_status_id', 'new_status_id', 'reason'];

    public function historyFor(int $attendanceId): array
    {
        return $this->query(
            'SELECT am.*, u.username AS admin_username,
                    os.name AS old_status, ns.name AS new_status
             FROM attendance_modifications am
             JOIN users u ON u.id = am.admin_id
             JOIN attendance_statuses os ON os.id = am.old_status_id
             JOIN attendance_statuses ns ON ns.id = am.new_status_id
             WHERE am.attendance_id = ? ORDER BY am.id DESC',
            [$attendanceId]
        );
    }
}
