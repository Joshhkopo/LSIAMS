<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Database;

/**
 * Database-backed sliding-window rate limiter (works across PHP workers,
 * no external cache needed on the LAN server).
 */
final class RateLimiter
{
    /**
     * Register a hit for $key. Returns false when the caller has exceeded
     * $maxAttempts within the last $windowMinutes.
     */
    public static function hit(string $key, int $maxAttempts, int $windowMinutes): bool
    {
        $pdo = Database::pdo();
        $key = substr($key, 0, 190);

        // Opportunistic cleanup of stale windows.
        $pdo->prepare('DELETE FROM rate_limits WHERE last_hit < DATE_SUB(NOW(), INTERVAL 1 DAY)')->execute();

        $stmt = $pdo->prepare('SELECT hits, window_start FROM rate_limits WHERE rl_key = ? FOR UPDATE');
        $pdo->beginTransaction();
        try {
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            $windowSeconds = $windowMinutes * 60;

            if ($row === false) {
                $pdo->prepare('INSERT INTO rate_limits (rl_key, hits, window_start, last_hit) VALUES (?, 1, NOW(), NOW())')
                    ->execute([$key]);
                $pdo->commit();
                return true;
            }

            if (time() - strtotime((string) $row['window_start']) > $windowSeconds) {
                $pdo->prepare('UPDATE rate_limits SET hits = 1, window_start = NOW(), last_hit = NOW() WHERE rl_key = ?')
                    ->execute([$key]);
                $pdo->commit();
                return true;
            }

            $hits = (int) $row['hits'] + 1;
            $pdo->prepare('UPDATE rate_limits SET hits = ?, last_hit = NOW() WHERE rl_key = ?')
                ->execute([$hits, $key]);
            $pdo->commit();
            return $hits <= $maxAttempts;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::error('Rate limiter failure: ' . $e->getMessage());
            return true; // fail-open for availability; the event is logged
        }
    }
}
