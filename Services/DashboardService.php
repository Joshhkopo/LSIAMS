<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\AttendanceRecord;
use App\Models\SecurityLog;

/**
 * Aggregated statistics for the administrator and teacher dashboards.
 */
final class DashboardService
{
    public function adminSummary(): array
    {
        $pdo = Database::pdo();
        $count = static function (string $sql, array $params = []) use ($pdo): int {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        };
        $today = date('Y-m-d');
        $statuses = (new AttendanceRecord())->countsByStatusForDate($today);

        return [
            'total_students'    => $count("SELECT COUNT(*) FROM students WHERE deleted_at IS NULL AND status = 'active'"),
            'total_teachers'    => $count("SELECT COUNT(*) FROM teachers WHERE deleted_at IS NULL AND status = 'active'"),
            'total_classrooms'  => $count("SELECT COUNT(*) FROM classrooms WHERE status = 'active'"),
            'total_devices'     => $count("SELECT COUNT(*) FROM devices WHERE status = 'active'"),
            'devices_online'    => $count("SELECT COUNT(*) FROM devices WHERE status = 'active' AND is_online = 1"),
            'devices_offline'   => $count("SELECT COUNT(*) FROM devices WHERE status = 'active' AND is_online = 0"),
            'present_today'     => $statuses['present'],
            'late_today'        => $statuses['late'],
            'absent_today'      => $statuses['absent'],
            'active_sessions'   => $count("SELECT COUNT(*) FROM attendance_sessions WHERE status = 'open'"),
            'attendance_pct'    => $this->attendancePercentage($statuses),
            'failed_logins_24h' => $count("SELECT COUNT(*) FROM login_history WHERE status = 'failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"),
            'security_events_24h' => $count("SELECT COUNT(*) FROM security_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"),
            'unknown_rfid_24h'  => $count("SELECT COUNT(*) FROM rfid_logs WHERE result = 'unknown' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"),
            'trend'             => (new AttendanceRecord())->dailyTrend(14),
            'security_summary'  => (new SecurityLog())->weeklySummary(),
        ];
    }

    public function teacherSummary(int $teacherId): array
    {
        $pdo = Database::pdo();
        $today = date('Y-m-d');
        $dayOfWeek = (int) date('N');
        $statuses = (new AttendanceRecord())->countsByStatusForDate($today, $teacherId);

        $stmt = $pdo->prepare(
            'SELECT sch.*, sub.name AS subject_name, sub.subject_code, sec.name AS section_name, c.room_number,
                    (SELECT ats.id FROM attendance_sessions ats
                      WHERE ats.schedule_id = sch.id AND ats.status = "open" LIMIT 1) AS open_session_id
             FROM schedules sch
             JOIN subjects sub ON sub.id = sch.subject_id
             JOIN sections sec ON sec.id = sch.section_id
             JOIN classrooms c ON c.id = sch.classroom_id
             WHERE sch.teacher_id = ? AND sch.day_of_week = ? AND sch.status = "active"
             ORDER BY sch.start_time'
        );
        $stmt->execute([$teacherId, $dayOfWeek]);
        $todayClasses = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM attendance_sessions
             WHERE teacher_id = ? AND started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        $stmt->execute([$teacherId]);
        $weekSessions = (int) $stmt->fetchColumn();

        return [
            'today_classes'   => $todayClasses,
            'present_today'   => $statuses['present'],
            'late_today'      => $statuses['late'],
            'absent_today'    => $statuses['absent'],
            'attendance_pct'  => $this->attendancePercentage($statuses),
            'week_sessions'   => $weekSessions,
            'trend'           => (new AttendanceRecord())->dailyTrend(14, $teacherId),
        ];
    }

    private function attendancePercentage(array $statuses): float
    {
        $attended = $statuses['present'] + $statuses['late'];
        $total = $attended + $statuses['absent'];
        return $total === 0 ? 0.0 : round($attended / $total * 100, 1);
    }
}
