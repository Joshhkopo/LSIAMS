<?php

declare(strict_types=1);

/**
 * Device REST API (stateless). Every route uses the `device` middleware:
 * device_id + api_key + timestamp + nonce are validated before the
 * controller runs. See docs/API.md for the contract.
 */

use App\Controllers\Api\AttendanceApiController;
use App\Controllers\Api\DeviceApiController;
use App\Controllers\Api\FingerprintApiController;
use App\Controllers\Api\RfidApiController;
use App\Core\Router;

return static function (Router $r): void {
    $device = ['device'];

    // Device lifecycle
    $r->post('/api/device/authenticate', [DeviceApiController::class, 'authenticate'], $device);
    $r->post('/api/device/heartbeat', [DeviceApiController::class, 'heartbeat'], $device);
    $r->post('/api/device/sync', [AttendanceApiController::class, 'sync'], $device);
    $r->post('/api/device/log', [DeviceApiController::class, 'log'], $device);

    // Fingerprint: verification opens the attendance session
    $r->post('/api/fingerprint/verify', [FingerprintApiController::class, 'verify'], $device);

    // RFID
    $r->post('/api/rfid/scan', [RfidApiController::class, 'scan'], $device);

    // Attendance lifecycle
    $r->post('/api/attendance/record', [AttendanceApiController::class, 'record'], $device);
    $r->post('/api/attendance/end', [AttendanceApiController::class, 'end'], $device);
    $r->post('/api/attendance/session', [AttendanceApiController::class, 'currentSession'], $device);
};
