<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Models\DeviceLog;
use App\Services\DeviceService;

/**
 * Device lifecycle endpoints. The DeviceAuthMiddleware has already verified
 * device_id/api_key/timestamp/nonce; $request->device() is trusted here.
 */
final class DeviceApiController extends Controller
{
    /**
     * POST /api/device/authenticate
     * Handshake at boot: confirms credentials, returns server time (clock
     * sync) and the device's classroom assignment.
     */
    public function authenticate(Request $request): never
    {
        $device = $request->device();
        (new DeviceLog())->create([
            'device_id' => (int) $device['id'],
            'event'     => 'auth',
            'details'   => 'Boot authentication from ' . $request->ip(),
        ]);

        $this->ok('Device authenticated.', [
            'device_code'  => $device['device_code'],
            'classroom_id' => $device['classroom_id'] !== null ? (int) $device['classroom_id'] : null,
            'server_time'  => date('c'),
            'heartbeat_interval' => (int) \App\Core\Config::get('security.heartbeat_interval'),
        ]);
    }

    /**
     * POST /api/device/heartbeat
     * Every 30 s: {firmware, wifi_signal, queue, uptime}.
     */
    public function heartbeat(Request $request): never
    {
        $service = new DeviceService();
        $data = $service->heartbeat($request->device(), $request->all());
        // Opportunistic sweeps piggyback on heartbeat traffic (no cron needed).
        $service->sweepOffline();
        (new \App\Services\AttendanceService())->autoCloseExpired();

        $this->ok('Heartbeat accepted.', $data);
    }

    /**
     * POST /api/device/log
     * Devices report notable events (boot, error, restart, sync).
     */
    public function log(Request $request): never
    {
        $event = (string) $request->input('event', 'error');
        $allowed = ['boot', 'shutdown', 'restart', 'sync', 'auth', 'error', 'config'];
        (new DeviceLog())->create([
            'device_id' => (int) $request->device()['id'],
            'event'     => in_array($event, $allowed, true) ? $event : 'error',
            'details'   => substr((string) $request->input('details', ''), 0, 500),
        ]);
        $this->ok('Logged.');
    }
}
