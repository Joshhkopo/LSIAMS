<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Device extends Model
{
    protected string $table = 'devices';
    protected array $fillable = [
        'device_code', 'device_name', 'mac_address', 'classroom_id', 'firmware_version',
        'ip_address', 'wifi_ssid', 'status', 'is_online', 'last_heartbeat',
    ];

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        return $this->queryOne(
            'SELECT id FROM devices WHERE device_code = ? AND (? IS NULL OR id <> ?) LIMIT 1',
            [$code, $exceptId, $exceptId]
        ) !== null;
    }

    public function macExists(string $mac, ?int $exceptId = null): bool
    {
        return $this->queryOne(
            'SELECT id FROM devices WHERE mac_address = ? AND (? IS NULL OR id <> ?) LIMIT 1',
            [$mac, $exceptId, $exceptId]
        ) !== null;
    }

    public function classroomTaken(int $classroomId, ?int $exceptId = null): bool
    {
        return $this->queryOne(
            'SELECT id FROM devices WHERE classroom_id = ? AND status <> "archived" AND (? IS NULL OR id <> ?) LIMIT 1',
            [$classroomId, $exceptId, $exceptId]
        ) !== null;
    }

    public function listWithHealth(): array
    {
        return $this->query(
            'SELECT d.*, c.room_number, k.key_hint, k.status AS key_status,
                    hb.signal_strength, hb.queue_count
             FROM devices d
             LEFT JOIN classrooms c ON c.id = d.classroom_id
             LEFT JOIN api_keys k ON k.device_id = d.id AND k.status = "active"
             LEFT JOIN device_heartbeats hb ON hb.id = (
                 SELECT MAX(h2.id) FROM device_heartbeats h2 WHERE h2.device_id = d.id
             )
             WHERE d.status <> "archived"
             ORDER BY d.device_code'
        );
    }

    /** Flag devices whose heartbeat is older than the offline threshold. */
    public function markStaleOffline(int $offlineAfterSeconds): array
    {
        $stale = $this->query(
            'SELECT id, device_code FROM devices
             WHERE status = "active" AND is_online = 1
               AND (last_heartbeat IS NULL OR last_heartbeat < DATE_SUB(NOW(), INTERVAL ? SECOND))',
            [$offlineAfterSeconds]
        );
        if ($stale !== []) {
            $ids = array_column($stale, 'id');
            $in = implode(',', array_fill(0, count($ids), '?'));
            $this->execute("UPDATE devices SET is_online = 0 WHERE id IN ($in)", $ids);
        }
        return $stale;
    }
}
