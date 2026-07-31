<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class ApiKey extends Model
{
    protected string $table = 'api_keys';
    protected array $fillable = ['device_id', 'key_hash', 'key_hint', 'status', 'expires_at', 'revoked_at'];
    protected bool $timestamps = true;

    /**
     * Generate a fresh 256-bit key for a device, revoking any active key.
     * Returns the plaintext key — shown to the administrator exactly once.
     */
    public function issueFor(int $deviceId): string
    {
        $plaintext = bin2hex(random_bytes(32)); // 256-bit
        $this->execute(
            'UPDATE api_keys SET status = "revoked", revoked_at = NOW() WHERE device_id = ? AND status = "active"',
            [$deviceId]
        );
        $this->create([
            'device_id' => $deviceId,
            'key_hash'  => hash('sha256', $plaintext),
            'key_hint'  => substr($plaintext, 0, 6),
            'status'    => 'active',
        ]);
        return $plaintext;
    }

    public function revokeFor(int $deviceId): void
    {
        $this->execute(
            'UPDATE api_keys SET status = "revoked", revoked_at = NOW() WHERE device_id = ? AND status = "active"',
            [$deviceId]
        );
    }
}
