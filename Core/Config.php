<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Central configuration store. Reads .env then merges the config/ files.
 * Values are accessed with dot notation: Config::get('db.host').
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    public static function load(): void
    {
        if (self::$items !== []) {
            return;
        }

        self::loadEnv(BASE_PATH . '/.env');

        self::$items = [
            'app'      => require BASE_PATH . '/config/config.php',
            'db'       => require BASE_PATH . '/config/database.php',
            'security' => require BASE_PATH . '/config/security.php',
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$items;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public static function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }
        return match (strtolower((string) $value)) {
            'true'  => true,
            'false' => false,
            default => $value,
        };
    }

    private static function loadEnv(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            $name  = trim($name);
            $value = trim(trim($value), "\"'");
            $_ENV[$name] = $value;
        }
    }
}
