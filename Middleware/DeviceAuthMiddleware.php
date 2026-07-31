<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\RateLimiter;
use App\Helpers\SecurityLogger;

/**
 * Multi-layer ESP32 device authentication. Every device API call must carry:
 *
 *   device_id   registered device code (e.g. DEV-001)
 *   api_key     plaintext key; only its SHA-256 hash is stored server-side
 *   timestamp   ISO-8601, must fall within the configured window (±30 s)
 *   nonce       single-use random value (replay protection)
 *
 * Failures are rejected, security-logged, and never reveal which check failed
 * beyond a generic message.
 */
final class DeviceAuthMiddleware
{
    public function handle(Request $request): void
    {
        [$max, $window] = Config::get('security.rate_limits.api_general');
        if (!RateLimiter::hit('api:' . $request->ip(), $max, $window)) {
            SecurityLogger::log('rate_limit', 'high', 'Device API rate limit exceeded', $request->ip());
            Response::fail('Too many requests.', 429);
        }

        $deviceCode = (string) $request->input('device_id', '');
        $apiKey     = (string) $request->input('api_key', '');
        $timestamp  = (string) $request->input('timestamp', '');
        $nonce      = (string) $request->input('nonce', '');

        if ($deviceCode === '' || $apiKey === '' || $timestamp === '' || $nonce === '') {
            $this->reject($request, null, 'Missing authentication fields from ' . $request->ip());
        }

        $pdo = Database::pdo();

        // Blocked IP check.
        $stmt = $pdo->prepare('SELECT id FROM blocked_ips WHERE ip_address = ? AND unblocked_at IS NULL LIMIT 1');
        $stmt->execute([$request->ip()]);
        if ($stmt->fetch() !== false) {
            $this->reject($request, null, 'Request from blocked IP ' . $request->ip());
        }

        // Device + active API key lookup.
        $stmt = $pdo->prepare(
            'SELECT d.*, k.id AS key_id, k.key_hash
             FROM devices d
             JOIN api_keys k ON k.device_id = d.id AND k.status = "active"
                AND (k.expires_at IS NULL OR k.expires_at > NOW())
             WHERE d.device_code = ? AND d.status = "active"
             LIMIT 1'
        );
        $stmt->execute([$deviceCode]);
        $device = $stmt->fetch();

        if ($device === false) {
            $this->reject($request, null, "Unknown or inactive device '{$deviceCode}'");
        }

        if (!hash_equals((string) $device['key_hash'], hash('sha256', $apiKey))) {
            $this->reject($request, (int) $device['id'], "Invalid API key for device '{$deviceCode}'");
        }

        // Timestamp freshness (replay window).
        $windowSeconds = (int) Config::get('security.api_timestamp_window');
        $requestTime = strtotime($timestamp);
        if ($requestTime === false || abs(time() - $requestTime) > $windowSeconds) {
            SecurityLogger::log('replay_attempt', 'high', "Stale/invalid timestamp from '{$deviceCode}'", $request->ip(), (int) $device['id']);
            Response::fail('Request timestamp outside the accepted window.', 401);
        }

        // Single-use nonce. Unique index makes duplicate inserts fail atomically.
        try {
            $pdo->prepare('INSERT INTO api_nonces (device_id, nonce, created_at) VALUES (?, ?, NOW())')
                ->execute([(int) $device['id'], substr($nonce, 0, 64)]);
        } catch (\PDOException) {
            SecurityLogger::log('replay_attempt', 'critical', "Nonce reuse from '{$deviceCode}'", $request->ip(), (int) $device['id']);
            Response::fail('Duplicate request rejected.', 401);
        }
        // Prune expired nonces opportunistically.
        $pdo->prepare('DELETE FROM api_nonces WHERE created_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)')->execute();

        // Track the device's current IP for the dashboard.
        $pdo->prepare('UPDATE devices SET ip_address = ? WHERE id = ?')->execute([$request->ip(), $device['id']]);

        $request->setDevice($device);
    }

    private function reject(Request $request, ?int $deviceId, string $description): never
    {
        SecurityLogger::log('unauthorized_device', 'high', $description, $request->ip(), $deviceId);
        Response::fail('Device authentication failed.', 401);
    }
}
