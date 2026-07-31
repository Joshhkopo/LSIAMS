<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Models\AttendanceSession;
use App\Services\AttendanceService;

/**
 * Attendance endpoints for ESP32 terminals: live taps, offline queue sync,
 * session close, and session status polling.
 */
final class AttendanceApiController extends Controller
{
    /**
     * POST /api/attendance/record
     * Body: { session_id: "ATT-...", rfid_uid: "04A7C1935D" }
     */
    public function record(Request $request): never
    {
        $result = (new AttendanceService())->recordRfidTap(
            $request->device(),
            (string) $request->input('session_id', ''),
            (string) $request->input('rfid_uid', ''),
            null, // live tap: use server time for consistency
            $request->ip(),
        );
        $this->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * POST /api/attendance/end
     * Body: { session_id: "ATT-..." } — closes the session and generates
     * absents. Only the owning device may close its session.
     */
    public function end(Request $request): never
    {
        $sessions = new AttendanceSession();
        $session = $sessions->findByCode((string) $request->input('session_id', ''));
        if ($session === null || (int) $session['device_id'] !== (int) $request->device()['id']) {
            $this->fail('Unknown session for this device.', 404);
        }
        $result = (new AttendanceService())->closeSession((int) $session['id'], 'device');
        $this->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * POST /api/attendance/session
     * Returns the open session for this device's classroom (used after a
     * reboot so the terminal can resume an in-progress session).
     */
    public function currentSession(Request $request): never
    {
        $device = $request->device();
        if ($device['classroom_id'] === null) {
            $this->fail('Device has no classroom assignment.', 422);
        }
        $session = (new AttendanceSession())->openForClassroom((int) $device['classroom_id']);
        $this->ok('OK', ['session' => $session === null ? null : [
            'session_id' => $session['session_code'],
            'started_at' => $session['started_at'],
        ]]);
    }

    /**
     * POST /api/attendance/sync
     * Offline queue upload. Body: { records: [{session_id, rfid_uid, timestamp}, ...] }
     * Original tap timestamps are preserved; duplicates are rejected
     * idempotently so devices can safely retry.
     */
    public function sync(Request $request): never
    {
        $records = $request->input('records');
        if (!is_array($records) || $records === []) {
            $this->fail('No records to synchronize.');
        }
        if (count($records) > 500) {
            $this->fail('Batch too large; upload at most 500 records per call.');
        }

        $service = new AttendanceService();
        $results = [];
        foreach ($records as $index => $record) {
            if (!is_array($record)) {
                $results[] = ['index' => $index, 'success' => false, 'message' => 'Malformed record.'];
                continue;
            }
            $outcome = $service->recordRfidTap(
                $request->device(),
                (string) ($record['session_id'] ?? ''),
                (string) ($record['rfid_uid'] ?? ''),
                isset($record['timestamp']) ? (string) $record['timestamp'] : null,
                $request->ip(),
                'sync',
            );
            $results[] = [
                'index'   => $index,
                'success' => $outcome['success']
                    // "already recorded" counts as synced — the device must drop it.
                    || str_contains($outcome['message'], 'already recorded'),
                'message' => $outcome['message'],
            ];
        }

        (new \App\Models\DeviceLog())->create([
            'device_id' => (int) $request->device()['id'],
            'event'     => 'sync',
            'details'   => count($records) . ' offline records synchronized',
        ]);

        $this->ok('Synchronization processed.', ['results' => $results]);
    }
}
