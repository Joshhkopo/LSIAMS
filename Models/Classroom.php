<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Classroom extends Model
{
    protected string $table = 'classrooms';
    protected array $fillable = ['room_number', 'building', 'floor', 'capacity', 'status'];

    public function roomNumberExists(string $room, ?int $exceptId = null): bool
    {
        return $this->queryOne(
            'SELECT id FROM classrooms WHERE room_number = ? AND (? IS NULL OR id <> ?) LIMIT 1',
            [$room, $exceptId, $exceptId]
        ) !== null;
    }

    public function listWithDevice(): array
    {
        return $this->query(
            'SELECT c.*, d.device_code, d.device_name, d.is_online
             FROM classrooms c
             LEFT JOIN devices d ON d.classroom_id = c.id AND d.status <> "archived"
             ORDER BY c.room_number'
        );
    }
}
