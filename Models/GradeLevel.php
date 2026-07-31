<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class GradeLevel extends Model
{
    protected string $table = 'grade_levels';
    protected array $fillable = ['name', 'sort_order', 'status'];
}
