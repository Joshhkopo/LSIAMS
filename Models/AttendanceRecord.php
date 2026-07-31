<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class AttendanceRecord extends Model
{
    protected string $table = 'attendance_records';
    protected array $fillable = [
        'session_id', 'student_id', 'teacher_id', 'device_id', 'rfid_uid',
        'status_id', 'recorded_at', 'source', 'ip_address', 'mac_address',
    ];

    public function existsForSessionStudent(int $sessionId, int $studentId): bool
    {
        return $this->queryOne(
            'SELECT id FROM attendance_records WHERE session_id = ? AND student_id = ? LIMIT 1',
            [$sessionId, $studentId]
        ) !== null;
    }

    private const DETAIL_SELECT =
        'SELECT ar.*, st.code AS status_code, st.name AS status_name,
                s.student_number, CONCAT(s.first_name, " ", s.last_name) AS student_name, s.photo AS student_photo,
                CONCAT(t.first_name, " ", t.last_name) AS teacher_name,
                sub.name AS subject_name, sec.name AS section_name, c.room_number,
                ats.session_code
         FROM attendance_records ar
         JOIN attendance_statuses st ON st.id = ar.status_id
         JOIN students s ON s.id = ar.student_id
         JOIN teachers t ON t.id = ar.teacher_id
         JOIN attendance_sessions ats ON ats.id = ar.session_id
         JOIN subjects sub ON sub.id = ats.subject_id
         JOIN sections sec ON sec.id = ats.section_id
         JOIN classrooms c ON c.id = ats.classroom_id';

    public function findDetailed(int $id): ?array
    {
        return $this->queryOne(self::DETAIL_SELECT . ' WHERE ar.id = ?', [$id]);
    }

    /**
     * Filterable, paginated attendance listing used by the attendance page,
     * teacher views, live feed and reports.
     * @return array{rows: array, total: int}
     */
    public function search(array $filters, int $limit, int $offset): array
    {
        $where = ['1=1'];
        $params = [];

        $map = [
            'teacher_id'   => 'ar.teacher_id = ?',
            'student_id'   => 'ar.student_id = ?',
            'session_id'   => 'ar.session_id = ?',
            'section_id'   => 'ats.section_id = ?',
            'subject_id'   => 'ats.subject_id = ?',
            'classroom_id' => 'ats.classroom_id = ?',
            'status'       => 'st.code = ?',
        ];
        foreach ($map as $key => $condition) {
            if (!empty($filters[$key])) {
                $where[] = $condition;
                $params[] = $filters[$key];
            }
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(ar.recorded_at) >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(ar.recorded_at) <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(s.student_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR ar.rfid_uid LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if (!empty($filters['since_id'])) {
            $where[] = 'ar.id > ?';
            $params[] = $filters['since_id'];
        }

        $whereSql = implode(' AND ', $where);
        $countSql = 'SELECT COUNT(*) AS c FROM attendance_records ar
                     JOIN attendance_statuses st ON st.id = ar.status_id
                     JOIN students s ON s.id = ar.student_id
                     JOIN attendance_sessions ats ON ats.id = ar.session_id
                     WHERE ' . $whereSql;
        $total = (int) ($this->queryOne($countSql, $params)['c'] ?? 0);
        $rows = $this->query(
            self::DETAIL_SELECT . " WHERE $whereSql ORDER BY ar.id DESC LIMIT $limit OFFSET $offset",
            $params
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** Aggregate counts for dashboards: [status_code => count] for a date. */
    public function countsByStatusForDate(string $date, ?int $teacherId = null): array
    {
        $rows = $this->query(
            'SELECT st.code, COUNT(*) AS total
             FROM attendance_records ar
             JOIN attendance_statuses st ON st.id = ar.status_id
             WHERE DATE(ar.recorded_at) = ? AND (? IS NULL OR ar.teacher_id = ?)
             GROUP BY st.code',
            [$date, $teacherId, $teacherId]
        );
        $out = ['present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0, 'official_business' => 0, 'incomplete' => 0];
        foreach ($rows as $row) {
            $out[$row['code']] = (int) $row['total'];
        }
        return $out;
    }

    /** Daily totals for the trend charts. */
    public function dailyTrend(int $days, ?int $teacherId = null): array
    {
        return $this->query(
            'SELECT DATE(ar.recorded_at) AS day, st.code, COUNT(*) AS total
             FROM attendance_records ar
             JOIN attendance_statuses st ON st.id = ar.status_id
             WHERE ar.recorded_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
               AND (? IS NULL OR ar.teacher_id = ?)
             GROUP BY DATE(ar.recorded_at), st.code
             ORDER BY day',
            [$days, $teacherId, $teacherId]
        );
    }
}
