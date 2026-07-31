<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\Device;
use App\Models\DeviceHeartbeat;
use App\Models\DeviceLog;
use App\Helpers\Notifier;
use App\Helpers\SecurityLogger;

/**
 * Device lifecycle: heartbeats, online/offline transitions and alerts.
 */
final class DeviceService
{
    public function __construct(
        private readonly Device $devices = new Device(),
        private readonly DeviceHeartbeat $heartbeats = new DeviceHeartbeat(),
        private readonly DeviceLog $deviceLogs = new DeviceLog(),
    ) {
    }

    /** Store a heartbeat and refresh device state. */
    public function heartbeat(array $device, array $payload): array
    {
        $deviceId = (int) $device['id'];

        $this->heartbeats->create([
            'device_id'       => $deviceId,
            'signal_strength' => isset($payload['wifi_signal']) ? (int) $payload['wifi_signal'] : null,
            'queue_count'     => max(0, (int) ($payload['queue'] ?? 0)),
            'firmware'        => substr((string) ($payload['firmware'] ?? ''), 0, 20),
            'uptime_seconds'  => isset($payload['uptime']) ? (int) $payload['uptime'] : null,
        ]);

        $wasOffline = (int) $device['is_online'] === 0;
        $this->devices->update($deviceId, [
            'is_online'        => 1,
            'last_heartbeat'   => date('Y-m-d H:i:s'),
            'firmware_version' => substr((string) ($payload['firmware'] ?? $device['firmware_version'] ?? ''), 0, 20),
        ]);
        if ($wasOffline) {
            $this->deviceLogs->create(['device_id' => $deviceId, 'event' => 'online', 'details' => 'Heartbeat resumed']);
        }

        // Growing offline queue is an operational warning.
        $queue = (int) ($payload['queue'] ?? 0);
        if ($queue >= (int) Config::get('security.sync_queue_alert')) {
            Notifier::notifyAdmins('Device sync queue growing',
                "Device {$device['device_code']} reports {$queue} unsynced attendance records.", 'high');
        }

        return ['server_time' => date('c')];
    }

    /** Sweep for devices that stopped sending heartbeats; notify admins. */
    public function sweepOffline(): void
    {
        $threshold = (int) Config::get('security.heartbeat_offline_after');
        foreach ($this->devices->markStaleOffline($threshold) as $device) {
            $this->deviceLogs->create([
                'device_id' => (int) $device['id'], 'event' => 'offline',
                'details' => "No heartbeat for {$threshold}s",
            ]);
            SecurityLogger::log('device_offline', 'medium', "Device {$device['device_code']} went offline");
            Notifier::notifyAdmins('Device offline', "Device {$device['device_code']} stopped sending heartbeats.", 'high');
        }
    }
}
