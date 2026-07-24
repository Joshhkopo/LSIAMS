<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Fingerprint;
use App\Models\FingerprintLog;
use App\Models\RfidCard;
use App\Models\RfidLog;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\UnknownRfid;
use App\Helpers\Notifier;
use App\Helpers\SecurityLogger;

/**
 * The attendance engine. Implements the full lifecycle:
 *
 *   fingerprint verify -> session open -> RFID validation chain ->
 *   record (present/late) -> auto close -> absent generation -> sync.
 *
 * Every state change runs inside a database transaction so attendance is
 * never partially saved.
 */
final class AttendanceService
{
    public const STATUS_PRESENT  = 1;
    public const STATUS_LATE     = 2;
    public const STATUS_ABSENT   = 3;

    public function __construct(
        private readonly AttendanceSession $sessions = new AttendanceSession(),
        private readonly AttendanceRecord $records = new AttendanceRecord(),
        private readonly Schedule $schedules = new Schedule(),
        private readonly RfidCard $rfidCards = new RfidCard(),
        private readonly RfidLog $rfidLogs = new RfidLog(),
        private readonly Fingerprint $fingerprints = new Fingerprint(),
        private readonly FingerprintLog $fingerprintLogs = new FingerprintLog(),
        private readonly UnknownRfid $unknownRfid = new UnknownRfid(),
        private readonly Setting $settings = new Setting(),
    ) {
    }

    /**
     * Verify a teacher fingerprint reported by a device and, when every rule
     * passes, open the attendance session for the device's classroom.
     *
     * @param array $device Authenticated device row (from DeviceAuthMiddleware)
     * @return array{success: bool, message: string, data: array}
     */
    public function openSessionByFingerprint(array $device, int $sensorTemplateId, string $ip): array
    {
        $deviceId = (int) $device['id'];
        $classroomId = $device['classroom_id'] !== null ? (int) $device['classroom_id'] : null;

        if ($classroomId === null) {
            return $this->fingerprintFail($deviceId, null, 'Device has no classroom assignment.');
        }

        $template = $this->fingerprints->teacherForSlot($deviceId, $sensorTemplateId);
        if ($template === null) {
            $result = $this->fingerprintFail($deviceId, null, 'Fingerprint not enrolled.');
            $this->checkFingerprintLockout($deviceId);
            return $result;
        }

        $teacherId = (int) $template['teacher_id'];
        if ($template['teacher_status'] !== 'active') {
            return $this->fingerprintFail($deviceId, $teacherId, 'Teacher account is not active.');
        }

        // Schedule check: the teacher must be assigned to THIS classroom NOW.
        $now = new \DateTimeImmutable();
        $schedule = $this->schedules->activeForClassroom($classroomId, (int) $now->format('N'), $now->format('H:i:s'));
        if ($schedule === null) {
            return $this->fingerprintFail($deviceId, $teacherId, 'No active schedule for this classroom right now.');
        }
        if ((int) $schedule['teacher_id'] !== $teacherId) {
            SecurityLogger::log('unauthorized_session', 'high',
                "Teacher #{$teacherId} attempted to open attendance for a class assigned to teacher #{$schedule['teacher_id']}",
                $ip, $deviceId);
            return $this->fingerprintFail($deviceId, $teacherId, 'You are not assigned to this classroom at this time.');
        }

        // Only one open session per classroom.
        if ($this->sessions->openForClassroom($classroomId) !== null) {
            $this->fingerprintLogs->create([
                'teacher_id' => $teacherId, 'device_id' => $deviceId,
                'result' => 'verified', 'details' => 'Session already open',
            ]);
            return ['success' => false, 'message' => 'An attendance session is already active for this classroom.', 'data' => []];
        }

        $session = Database::transaction(function () use ($teacherId, $deviceId, $classroomId, $schedule, $ip, $now) {
            $code = 'ATT-' . $now->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $sessionId = $this->sessions->create([
                'session_code' => $code,
                'teacher_id'   => $teacherId,
                'device_id'    => $deviceId,
                'classroom_id' => $classroomId,
                'schedule_id'  => (int) $schedule['id'],
                'subject_id'   => (int) $schedule['subject_id'],
                'section_id'   => (int) $schedule['section_id'],
                'started_at'   => $now->format('Y-m-d H:i:s'),
                'status'       => 'open',
                'opened_ip'    => $ip,
            ]);
            $this->fingerprintLogs->create([
                'teacher_id' => $teacherId, 'device_id' => $deviceId,
                'result' => 'verified', 'details' => 'Attendance session ' . $code . ' opened',
            ]);
            return ['id' => $sessionId, 'code' => $code];
        });

        \App\Helpers\Audit::log('attendance_session_started', 'attendance', $session['id'], null, [
            'session_code' => $session['code'], 'teacher_id' => $teacherId, 'classroom_id' => $classroomId,
        ]);

        return [
            'success' => true,
            'message' => 'Fingerprint verified. Attendance session opened.',
            'data' => [
                'session_id'   => $session['id'],
                'session_code' => $session['code'],
                'teacher'      => $template['teacher_name'],
                'subject'      => $schedule['subject_name'],
                'section'      => $schedule['section_name'],
                'ends_at'      => $schedule['end_time'],
            ],
        ];
    }

    /**
     * Full RFID validation chain for one tap. $timestamp may come from the
     * device's offline queue (original tap time is preserved during sync).
     *
     * @return array{success: bool, message: string, data: array}
     */
    public function recordRfidTap(
        array $device,
        string $sessionCode,
        string $uid,
        ?string $timestamp,
        string $ip,
        string $source = 'rfid',
    ): array {
        $deviceId = (int) $device['id'];
        $uid = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $uid) ?? '');
        if ($uid === '' || strlen($uid) > 32) {
            return ['success' => false, 'message' => 'Invalid RFID.', 'data' => []];
        }

        // Session must exist, be open, and belong to this device's classroom.
        $session = $this->sessions->findByCode($sessionCode);
        if ($session === null || $session['status'] !== 'open') {
            $this->rfidLogs->create(['uid' => $uid, 'device_id' => $deviceId, 'result' => 'rejected', 'details' => 'Session closed or unknown']);
            return ['success' => false, 'message' => 'Attendance session closed.', 'data' => []];
        }
        if ((int) $session['device_id'] !== $deviceId) {
            SecurityLogger::log('device_session_mismatch', 'high',
                "Device #{$deviceId} submitted a tap for session {$sessionCode} owned by device #{$session['device_id']}", $ip, $deviceId);
            return ['success' => false, 'message' => 'Session does not belong to this device.', 'data' => []];
        }

        // Card lookup.
        $card = $this->rfidCards->findByUid($uid);
        if ($card === null) {
            $this->unknownRfid->recordSighting($uid, $deviceId);
            $this->rfidLogs->create(['uid' => $uid, 'device_id' => $deviceId, 'result' => 'unknown']);
            SecurityLogger::log('unknown_rfid', 'medium', "Unknown RFID {$uid} at device #{$deviceId}", $ip, $deviceId);
            Notifier::notifyAdmins('Unknown RFID detected', "UID {$uid} tapped on device {$device['device_code']}.", 'high');
            return ['success' => false, 'message' => 'Unknown RFID.', 'data' => []];
        }
        if ($card['status'] !== 'active') {
            $this->rfidLogs->create(['uid' => $uid, 'student_id' => (int) $card['student_id'], 'device_id' => $deviceId, 'result' => 'disabled']);
            return ['success' => false, 'message' => 'Card disabled.', 'data' => []];
        }
        if ($card['student_status'] !== 'active') {
            $this->rfidLogs->create(['uid' => $uid, 'student_id' => (int) $card['student_id'], 'device_id' => $deviceId, 'result' => 'rejected', 'details' => 'Inactive student']);
            return ['success' => false, 'message' => 'Student is not active.', 'data' => []];
        }

        // Section check: student must belong to the section being taught.
        if ((int) $card['section_id'] !== (int) $session['section_id']) {
            $this->rfidLogs->create(['uid' => $uid, 'student_id' => (int) $card['student_id'], 'device_id' => $deviceId, 'result' => 'rejected', 'details' => 'Wrong section']);
            return ['success' => false, 'message' => 'Student is not enrolled in this class.', 'data' => []];
        }

        $studentId = (int) $card['student_id'];

        // Duplicate check (also enforced by the unique DB index).
        if ($this->records->existsForSessionStudent((int) $session['id'], $studentId)) {
            $this->rfidLogs->create(['uid' => $uid, 'student_id' => $studentId, 'device_id' => $deviceId, 'result' => 'duplicate']);
            return ['success' => false, 'message' => 'Attendance already recorded.', 'data' => ['student' => $card['student_name']]];
        }

        // Late / cutoff computation against the session schedule.
        $detail = $this->sessions->findDetailed((int) $session['id']);
        $tapTime = $timestamp !== null && strtotime($timestamp) !== false
            ? new \DateTimeImmutable($timestamp)
            : new \DateTimeImmutable();

        $sessionStart = new \DateTimeImmutable((string) $session['started_at']);
        $minutesSinceStart = max(0, ($tapTime->getTimestamp() - $sessionStart->getTimestamp()) / 60);
        $lateThreshold = (int) ($detail['late_threshold_minutes'] ?? 20);
        $cutoff = (int) $this->settings->value('absent_cutoff_minutes', '60');

        if ($minutesSinceStart > $cutoff) {
            $this->rfidLogs->create(['uid' => $uid, 'student_id' => $studentId, 'device_id' => $deviceId, 'result' => 'rejected', 'details' => 'Past cutoff']);
            return ['success' => false, 'message' => 'Attendance window has closed.', 'data' => []];
        }
        $statusId = $minutesSinceStart > $lateThreshold ? self::STATUS_LATE : self::STATUS_PRESENT;

        try {
            Database::transaction(function () use ($session, $studentId, $deviceId, $uid, $statusId, $tapTime, $ip, $source, $device) {
                $this->records->create([
                    'session_id'  => (int) $session['id'],
                    'student_id'  => $studentId,
                    'teacher_id'  => (int) $session['teacher_id'],
                    'device_id'   => $deviceId,
                    'rfid_uid'    => $uid,
                    'status_id'   => $statusId,
                    'recorded_at' => $tapTime->format('Y-m-d H:i:s'),
                    'source'      => $source,
                    'ip_address'  => $ip,
                    'mac_address' => $device['mac_address'] ?? null,
                ]);
                $this->rfidLogs->create([
                    'uid' => $uid, 'student_id' => $studentId, 'device_id' => $deviceId,
                    'result' => $statusId === self::STATUS_LATE ? 'late' : 'accepted',
                ]);
            });
        } catch (\PDOException $e) {
            // Unique index race: two taps of the same card at the same moment.
            if (str_contains($e->getMessage(), 'uq_attendance') || (string) $e->getCode() === '23000') {
                return ['success' => false, 'message' => 'Attendance already recorded.', 'data' => []];
            }
            throw $e;
        }

        return [
            'success' => true,
            'message' => 'Attendance recorded successfully.',
            'data' => [
                'student' => $card['student_name'],
                'status'  => $statusId === self::STATUS_LATE ? 'Late' : 'Present',
                'time'    => $tapTime->format('h:i A'),
            ],
        ];
    }

    /**
     * Close a session and generate Absent records for every active student
     * of the section who never tapped. Runs in one transaction.
     */
    public function closeSession(int $sessionId, string $closedBy = 'device'): array
    {
        $session = $this->sessions->findDetailed($sessionId);
        if ($session === null || $session['status'] !== 'open') {
            return ['success' => false, 'message' => 'Session is not open.', 'data' => []];
        }

        $absents = Database::transaction(function () use ($session, $sessionId) {
            $pdo = Database::pdo();

            // Absent generation for students without a record in this session.
            $stmt = $pdo->prepare(
                'INSERT INTO attendance_records
                    (session_id, student_id, teacher_id, device_id, status_id, recorded_at, source, created_at)
                 SELECT ?, s.id, ?, ?, ?, NOW(), "system", NOW()
                 FROM students s
                 WHERE s.section_id = ? AND s.status = "active" AND s.deleted_at IS NULL
                   AND NOT EXISTS (
                       SELECT 1 FROM attendance_records ar
                       WHERE ar.session_id = ? AND ar.student_id = s.id
                   )'
            );
            $stmt->execute([
                $sessionId, (int) $session['teacher_id'], (int) $session['device_id'],
                self::STATUS_ABSENT, (int) $session['section_id'], $sessionId,
            ]);
            $absentCount = $stmt->rowCount();

            $pdo->prepare('UPDATE attendance_sessions SET status = "closed", ended_at = NOW() WHERE id = ?')
                ->execute([$sessionId]);
            return $absentCount;
        });

        \App\Helpers\Audit::log('attendance_session_closed', 'attendance', $sessionId, null, [
            'closed_by' => $closedBy, 'absents_generated' => $absents,
        ]);

        return [
            'success' => true,
            'message' => 'Attendance session closed.',
            'data' => ['absents_generated' => $absents],
        ];
    }

    /** Auto-close every open session whose schedule has ended (cron/poll). */
    public function autoCloseExpired(): int
    {
        $closed = 0;
        foreach ($this->sessions->findExpiredOpen() as $row) {
            $this->closeSession((int) $row['id'], 'auto');
            $closed++;
        }
        return $closed;
    }

    /**
     * Administrator-only manual correction. Preserves the original record in
     * attendance_modifications and the audit trail; requires a reason.
     */
    public function correctAttendance(int $attendanceId, int $newStatusId, string $reason, int $adminId): array
    {
        $record = $this->records->find($attendanceId);
        if ($record === null) {
            return ['success' => false, 'message' => 'Attendance record not found.', 'data' => []];
        }
        if (trim($reason) === '') {
            return ['success' => false, 'message' => 'A reason is required for every correction.', 'data' => []];
        }
        if ($newStatusId < 1 || $newStatusId > 6) {
            return ['success' => false, 'message' => 'Invalid attendance status.', 'data' => []];
        }

        Database::transaction(function () use ($record, $attendanceId, $newStatusId, $reason, $adminId) {
            (new AttendanceModificationWriter())->write($attendanceId, $adminId, (int) $record['status_id'], $newStatusId, $reason);
            $this->records->update($attendanceId, ['status_id' => $newStatusId]);
        });

        \App\Helpers\Audit::log('attendance_corrected', 'attendance', $attendanceId,
            ['status_id' => (int) $record['status_id']],
            ['status_id' => $newStatusId, 'reason' => $reason]);

        return ['success' => true, 'message' => 'Attendance updated.', 'data' => []];
    }

    private function fingerprintFail(int $deviceId, ?int $teacherId, string $reason): array
    {
        $this->fingerprintLogs->create([
            'teacher_id' => $teacherId,
            'device_id'  => $deviceId,
            'result'     => $teacherId === null ? 'unknown' : 'failed',
            'details'    => $reason,
        ]);
        return ['success' => false, 'message' => $reason, 'data' => []];
    }

    /** Alert administrators when a device accumulates fingerprint failures. */
    private function checkFingerprintLockout(int $deviceId): void
    {
        $max = (int) $this->settings->value('fingerprint_max_failures', '5');
        if ($this->fingerprintLogs->recentFailures($deviceId, 5) >= $max) {
            SecurityLogger::log('fingerprint_lockout', 'high',
                "Device #{$deviceId} exceeded {$max} fingerprint failures in 5 minutes", null, $deviceId);
            Notifier::notifyAdmins('Fingerprint failures',
                "Device #{$deviceId} has repeated fingerprint failures and is temporarily locked.", 'critical');
        }
    }
}

/**
 * Tiny writer kept separate so AttendanceService stays free of direct SQL
 * for the modifications table.
 */
final class AttendanceModificationWriter
{
    public function write(int $attendanceId, int $adminId, int $oldStatusId, int $newStatusId, string $reason): void
    {
        Database::pdo()->prepare(
            'INSERT INTO attendance_modifications
                (attendance_id, admin_id, old_status_id, new_status_id, reason, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        )->execute([$attendanceId, $adminId, $oldStatusId, $newStatusId, substr($reason, 0, 500)]);
    }
}
