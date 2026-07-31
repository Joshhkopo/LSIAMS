<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Secure session wrapper: HttpOnly + SameSite cookies, ID regeneration on
 * privilege change, and automatic inactivity timeout.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name(Config::env('SESSION_NAME', 'lsiams_session'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => (bool) Config::env('SESSION_SECURE_COOKIE', true),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // Inactivity timeout.
        $timeout = ((int) Config::env('SESSION_LIFETIME_MINUTES', 30)) * 60;
        $last = $_SESSION['_last_activity'] ?? time();
        if (time() - $last > $timeout) {
            self::destroy();
            session_start();
            $_SESSION['_flash']['warning'] = 'Session expired. Please log in again.';
        }
        $_SESSION['_last_activity'] = time();
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type] = $message;
    }

    /** @return array<string, string> */
    public static function pullFlashes(): array
    {
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flashes;
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
