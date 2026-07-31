<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class AttendanceSession extends Model
{
    protected string $table = 'attendance_sessions';
    protected array $fillable = [
        'session_code', 'teacher_id', 'device_id', 'classroom_id', 'schedule_id',
        'subject_id', 'section_id', 'started_at', 'ended_at', 'status', 'opened_ip',
    ];

    public function openForClassroom(int $classroomId): ?array
    {
        return $this->queryOne(
            'SELECT * FROM attendance_sessions WHERE classroom_id = ? AND status = "open" LIMIT 1',
            [$classroomId]
        );
    }

    public function findByCode(string $code): ?array
    {
        return $this->findBy('session_code', $code);
    }

    public function findDetailed(int $id): ?array
    {
        return $this->queryOne(
            'SELECT ats.*, CONCAT(t.first_name, " ", t.last_name) AS teacher_name,
                    sub.name AS subject_name, sec.name AS section_name, c.room_number,
                    sch.attendance_window_minutes, sch.late_threshold_minutes,
                    sch.start_time AS schedule_start, sch.end_time AS schedule_end
             FROM attendance_sessions ats
             JOIN teachers t ON t.id = ats.teacher_id
             JOIN subjects sub ON sub.id = ats.subject_id
             JOIN sections sec ON sec.id = ats.section_id
             JOIN classrooms c ON c.id = ats.classroom_id
             JOIN schedules sch ON sch.id = ats.schedule_id
             WHERE ats.id = ?',
            [$id]
        );
    }

    /** Sessions whose schedule window has elapsed — candidates for auto-close. */
    public function findExpiredOpen(): array
    {
        return $this->query(
            'SELECT ats.id FROM attendance_sessions ats
             JOIN schedules sch ON sch.id = ats.schedule_id
             WHERE ats.status = "open"
               AND ats.started_at < DATE_SUB(NOW(), INTERVAL TIME_TO_SEC(TIMEDIFF(sch.end_time, sch.start_time)) SECOND)'
        );
    }

    public function listDetailed(array $filters, int $limit, int $offset): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['teacher_id'])) {
            $where[] = 'ats.teacher_id = ?';
            $params[] = $filters['teacher_id'];
        }
        if (!empty($filters['date'])) {
            $where[] = 'DATE(ats.started_at) = ?';
            $params[] = $filters['date'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'ats.status = ?';
            $params[] = $filters['status'];
        }
        $whereSql = implode(' AND ', $where);
        $total = (int) ($this->queryOne(
            "SELECT COUNT(*) AS c FROM attendance_sessions ats WHERE $whereSql", $params
        )['c'] ?? 0);
        $rows = $this->query(
            "SELECT ats.*, CONCAT(t.first_name, ' ', t.last_name) AS teacher_name,
                    sub.name AS subject_name, sec.name AS section_name, c.room_number,
                    (SELECT COUNT(*) FROM attendance_records ar WHERE ar.session_id = ats.id) AS record_count
             FROM attendance_sessions ats
             JOIN teachers t ON t.id = ats.teacher_id
             JOIN subjects sub ON sub.id = ats.subject_id
             JOIN sections sec ON sec.id = ats.section_id
             JOIN classrooms c ON c.id = ats.classroom_id
             WHERE $whereSql ORDER BY ats.started_at DESC LIMIT $limit OFFSET $offset",
            $params
        );
        return ['rows' => $rows, 'total' => $total];
    }
}
