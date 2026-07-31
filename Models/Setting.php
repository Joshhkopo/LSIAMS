<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Setting extends Model
{
    protected string $table = 'settings';
    protected string $primaryKey = 'setting_key';
    protected array $fillable = ['setting_value', 'updated_by'];
    protected bool $timestamps = false;

    /** @var array<string, string|null>|null */
    private static ?array $cache = null;

    public function value(string $key, ?string $default = null): ?string
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach ($this->query('SELECT setting_key, setting_value FROM settings') as $row) {
                self::$cache[$row['setting_key']] = $row['setting_value'];
            }
        }
        return self::$cache[$key] ?? $default;
    }

    public function set(string $key, string $value, ?int $userId = null): void
    {
        $this->execute(
            'INSERT INTO settings (setting_key, setting_value, updated_by)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)',
            [$key, $value, $userId]
        );
        self::$cache = null;
    }
}
