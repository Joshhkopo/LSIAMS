<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class GeneratedReport extends Model
{
    protected string $table = 'generated_reports';
    protected array $fillable = ['name', 'report_type', 'filters', 'file_path', 'generated_by'];
}
