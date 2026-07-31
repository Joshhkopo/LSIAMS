<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Schedule extends Model
{
    protected string $table = 'schedules';
    protected array $fillable = [
        'teacher_id', 'subject_id', 'section_id', 'classroom_id', 'day_of_week',
        'start_time', 'end_time', 'attendance_window_minutes', 'late_threshold_minutes', 'status',
    ];

    private const DETAIL_SELECT =
        'SELECT sch.*, CONCAT(t.first_name, " ", t.last_name) AS teacher_name,
                sub.subject_code, sub.name AS subject_name,
                sec.name AS section_name, c.room_number
         FROM schedules sch
         JOIN teachers t   ON t.id = sch.teacher_id
         JOIN subjects sub ON sub.id = sch.subject_id
         JOIN sections sec ON sec.id = sch.section_id
         JOIN classrooms c ON c.id = sch.classroom_id';

    public function listDetailed(array $filters = []): array
    {
        $where = ['sch.status = "active"'];
        $params = [];
        foreach (['teacher_id' => 'sch.teacher_id', 'classroom_id' => 'sch.classroom_id',
                  'section_id' => 'sch.section_id', 'day_of_week' => 'sch.day_of_week'] as $key => $col) {
            if (!empty($filters[$key])) {
                $where[] = "$col = ?";
                $params[] = $filters[$key];
            }
        }
        return $this->query(
            self::DETAIL_SELECT . ' WHERE ' . implode(' AND ', $where) .
            ' ORDER BY sch.day_of_week, sch.start_time',
            $params
        );
    }

    public function findDetailed(int $id): ?array
    {
        return $this->queryOne(self::DETAIL_SELECT . ' WHERE sch.id = ?', [$id]);
    }

    /**
     * Active schedule for a classroom at a given moment (used when a device
     * asks to open attendance).
     */
    public function activeForClassroom(int $classroomId, int $dayOfWeek, string $time): ?array
    {
        return $this->queryOne(
            self::DETAIL_SELECT .
            ' WHERE sch.classroom_id = ? AND sch.day_of_week = ?
               AND sch.start_time <= ? AND sch.end_time > ? AND sch.status = "active"
              LIMIT 1',
            [$classroomId, $dayOfWeek, $time, $time]
        );
    }

    /**
     * Overlap detection for teacher OR classroom on the same day.
     * Two intervals overlap when start < other_end AND end > other_start.
     */
    public function findConflicts(
        int $teacherId,
        int $classroomId,
        int $dayOfWeek,
        string $startTime,
        string $endTime,
        ?int $exceptId = null,
    ): array {
        return $this->query(
            self::DETAIL_SELECT .
            ' WHERE sch.day_of_week = ? AND sch.status = "active"
               AND (sch.teacher_id = ? OR sch.classroom_id = ?)
               AND sch.start_time < ? AND sch.end_time > ?
               AND (? IS NULL OR sch.id <> ?)',
            [$dayOfWeek, $teacherId, $classroomId, $endTime, $startTime, $exceptId, $exceptId]
        );
    }
}
