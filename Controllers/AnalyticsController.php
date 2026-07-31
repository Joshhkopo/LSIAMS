<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;

final class AnalyticsController extends Controller
{
    public function index(Request $request): never
    {
        $this->view('analytics/index', ['pageTitle' => 'Analytics']);
    }

    /** Aggregations for the analytics charts (admin only). */
    public function data(Request $request): never
    {
        $pdo = Database::pdo();
        $days = min(365, max(7, (int) $request->query('days', 30)));

        $fetch = static function (string $sql, array $params = []) use ($pdo): array {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        };

        $this->ok('OK', [
            'daily' => $fetch(
                'SELECT DATE(ar.recorded_at) AS day, st.code, COUNT(*) AS total
                 FROM attendance_records ar JOIN attendance_statuses st ON st.id = ar.status_id
                 WHERE ar.recorded_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                 GROUP BY day, st.code ORDER BY day', [$days]),
            'by_grade' => $fetch(
                'SELECT gl.name AS label, st.code, COUNT(*) AS total
                 FROM attendance_records ar
                 JOIN attendance_statuses st ON st.id = ar.status_id
                 JOIN students s ON s.id = ar.student_id
                 JOIN grade_levels gl ON gl.id = s.grade_level_id
                 WHERE ar.recorded_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                 GROUP BY gl.id, st.code ORDER BY gl.sort_order', [$days]),
            'by_section' => $fetch(
                'SELECT sec.name AS label, st.code, COUNT(*) AS total
                 FROM attendance_records ar
                 JOIN attendance_statuses st ON st.id = ar.status_id
                 JOIN attendance_sessions ats ON ats.id = ar.session_id
                 JOIN sections sec ON sec.id = ats.section_id
                 WHERE ar.recorded_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                 GROUP BY sec.id, st.code ORDER BY sec.name', [$days]),
            'by_subject' => $fetch(
                'SELECT sub.subject_code AS label, st.code, COUNT(*) AS total
                 FROM attendance_records ar
                 JOIN attendance_statuses st ON st.id = ar.status_id
                 JOIN attendance_sessions ats ON ats.id = ar.session_id
                 JOIN subjects sub ON sub.id = ats.subject_id
                 WHERE ar.recorded_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                 GROUP BY sub.id, st.code ORDER BY sub.subject_code', [$days]),
            'by_teacher' => $fetch(
                'SELECT CONCAT(t.first_name, " ", t.last_name) AS label, st.code, COUNT(*) AS total
                 FROM attendance_records ar
                 JOIN attendance_statuses st ON st.id = ar.status_id
                 JOIN teachers t ON t.id = ar.teacher_id
                 WHERE ar.recorded_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                 GROUP BY t.id, st.code ORDER BY label', [$days]),
            'device_uptime' => $fetch(
                'SELECT d.device_code AS label, COUNT(hb.id) AS heartbeats,
                        AVG(hb.signal_strength) AS avg_signal
                 FROM devices d
                 LEFT JOIN device_heartbeats hb ON hb.device_id = d.id
                    AND hb.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 WHERE d.status = "active"
                 GROUP BY d.id ORDER BY d.device_code', [$days]),
            'auth_success' => $fetch(
                'SELECT result, COUNT(*) AS total FROM fingerprint_logs
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY result', [$days]),
        ]);
    }
}
