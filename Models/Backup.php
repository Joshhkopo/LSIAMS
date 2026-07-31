<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Backup extends Model
{
    protected string $table = 'backups';
    protected array $fillable = ['name', 'file_path', 'size_bytes', 'status', 'created_by'];
}
