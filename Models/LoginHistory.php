<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class LoginHistory extends Model
{
    protected string $table = 'login_history';
    protected array $fillable = [];
    protected bool $timestamps = false;

    public function forUser(int $userId, int $limit = 20): array
    {
        return $this->query(
            'SELECT * FROM login_history WHERE user_id = ? ORDER BY id DESC LIMIT ' . $limit,
            [$userId]
        );
    }
}
