<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class User extends Model
{
    protected string $table = 'users';
    protected array $fillable = [
        'role_id', 'username', 'email', 'password_hash', 'status',
        'failed_attempts', 'locked_at', 'must_change_password', 'last_login', 'photo',
    ];
    protected bool $softDelete = true;

    public function findWithRole(int $id): ?array
    {
        return $this->queryOne(
            'SELECT u.*, r.name AS role_name FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND u.deleted_at IS NULL',
            [$id]
        );
    }

    public function usernameExists(string $username, ?int $exceptId = null): bool
    {
        $row = $this->queryOne(
            'SELECT id FROM users WHERE username = ? AND (? IS NULL OR id <> ?) LIMIT 1',
            [$username, $exceptId, $exceptId]
        );
        return $row !== null;
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $row = $this->queryOne(
            'SELECT id FROM users WHERE email = ? AND (? IS NULL OR id <> ?) LIMIT 1',
            [$email, $exceptId, $exceptId]
        );
        return $row !== null;
    }

    public function setPassword(int $id, string $plainPassword): bool
    {
        return $this->execute(
            'UPDATE users SET password_hash = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?',
            [password_hash($plainPassword, PASSWORD_BCRYPT), $id]
        ) > 0;
    }
}
