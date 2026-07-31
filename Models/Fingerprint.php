<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Fingerprint extends Model
{
    protected string $table = 'fingerprint_templates';
    protected array $fillable = ['teacher_id', 'sensor_template_id', 'enrolled_device_id', 'enrolled_at', 'status'];

    public function findByTeacher(int $teacherId): ?array
    {
        return $this->findBy('teacher_id', $teacherId);
    }

    /** Resolve a teacher from the sensor slot id reported by a device. */
    public function teacherForSlot(int $deviceId, int $sensorTemplateId): ?array
    {
        return $this->queryOne(
            'SELECT ft.*, t.id AS teacher_id, t.status AS teacher_status,
                    CONCAT(t.first_name, " ", t.last_name) AS teacher_name
             FROM fingerprint_templates ft
             JOIN teachers t ON t.id = ft.teacher_id AND t.deleted_at IS NULL
             WHERE ft.enrolled_device_id = ? AND ft.sensor_template_id = ? AND ft.status = "active"
             LIMIT 1',
            [$deviceId, $sensorTemplateId]
        );
    }

    public function listDetailed(): array
    {
        return $this->query(
            'SELECT ft.*, CONCAT(t.first_name, " ", t.last_name) AS teacher_name,
                    t.employee_number, d.device_code,
                    (SELECT COUNT(*) FROM fingerprint_logs fl
                      WHERE fl.teacher_id = ft.teacher_id AND fl.result = "verified") AS verification_count
             FROM fingerprint_templates ft
             JOIN teachers t ON t.id = ft.teacher_id
             LEFT JOIN devices d ON d.id = ft.enrolled_device_id
             ORDER BY t.last_name'
        );
    }
}
