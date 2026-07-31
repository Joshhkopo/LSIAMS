<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;

/**
 * Web authentication: login with lockout, RBAC checks, login history.
 */
final class Auth
{
    public const ROLE_ADMIN   = 'Administrator';
    public const ROLE_TEACHER = 'Teacher';

    /** @return array<string, mixed>|null Currently authenticated user row. */
    public static function user(): ?array
    {
        return Session::get('user');
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user !== null ? (int) $user['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function role(): ?string
    {
        return self::user()['role_name'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return self::role() === self::ROLE_ADMIN;
    }

    /**
     * Attempt a credential login. Returns [success, message].
     * Applies rate limiting, account lockout and full logging.
     *
     * @return array{0: bool, 1: string}
     */
    public static function attempt(string $username, string $password, Request $request): array
    {
        [$max, $window] = Config::get('security.rate_limits.login');
        if (!RateLimiter::hit('login:' . $request->ip(), $max, $window)) {
            SecurityLogger::log('rate_limit', 'high', 'Login rate limit exceeded', $request->ip());
            return [false, 'Too many attempts. Please try again later.'];
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT u.*, r.name AS role_name
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE (u.username = ? OR u.email = ?) AND u.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user === false) {
            self::recordLogin(null, $request, 'failed');
            SecurityLogger::log('failed_login', 'medium', "Unknown user '{$username}'", $request->ip());
            return [false, 'Invalid username or password.'];
        }

        if ($user['status'] === 'locked') {
            $minutes = (int) Config::get('security.login_lockout_minutes');
            $lockedAt = strtotime((string) $user['locked_at']);
            if (time() - $lockedAt < $minutes * 60) {
                self::recordLogin((int) $user['id'], $request, 'locked');
                return [false, 'Account is temporarily locked. Contact the administrator.'];
            }
            // Lockout window elapsed — unlock automatically.
            $pdo->prepare("UPDATE users SET status = 'active', failed_attempts = 0, locked_at = NULL WHERE id = ?")
                ->execute([$user['id']]);
            $user['status'] = 'active';
        }

        if ($user['status'] !== 'active') {
            self::recordLogin((int) $user['id'], $request, 'inactive');
            return [false, 'Account is not active. Contact the administrator.'];
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            $attempts = (int) $user['failed_attempts'] + 1;
            $maxAttempts = (int) Config::get('security.login_max_attempts');
            if ($attempts >= $maxAttempts) {
                $pdo->prepare("UPDATE users SET failed_attempts = ?, status = 'locked', locked_at = NOW() WHERE id = ?")
                    ->execute([$attempts, $user['id']]);
                SecurityLogger::log('account_lockout', 'high', "Account '{$user['username']}' locked after {$attempts} failures", $request->ip());
                Notifier::notifyAdmins('Account locked', "User '{$user['username']}' locked after repeated failed logins.", 'high');
            } else {
                $pdo->prepare('UPDATE users SET failed_attempts = ? WHERE id = ?')->execute([$attempts, $user['id']]);
            }
            self::recordLogin((int) $user['id'], $request, 'failed');
            SecurityLogger::log('failed_login', 'medium', "Wrong password for '{$user['username']}'", $request->ip());
            return [false, 'Invalid username or password.'];
        }

        // Success — reset counters, rotate the session ID, persist context.
        $pdo->prepare('UPDATE users SET failed_attempts = 0, last_login = NOW() WHERE id = ?')->execute([$user['id']]);
        Session::regenerate();
        unset($user['password_hash']);
        Session::set('user', $user);
        self::recordLogin((int) $user['id'], $request, 'success');
        Audit::log('login', 'auth', (int) $user['id'], null, null);
        return [true, 'Login successful.'];
    }

    public static function logout(Request $request): void
    {
        if (self::check()) {
            Audit::log('logout', 'auth', self::id(), null, null);
        }
        Session::destroy();
    }

    private static function recordLogin(?int $userId, Request $request, string $status): void
    {
        Database::pdo()
            ->prepare('INSERT INTO login_history (user_id, ip_address, user_agent, status, created_at) VALUES (?, ?, ?, ?, NOW())')
            ->execute([$userId, $request->ip(), $request->userAgent(), $status]);
    }
}
