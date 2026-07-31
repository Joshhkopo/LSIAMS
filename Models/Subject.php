<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Subject extends Model
{
    protected string $table = 'subjects';
    protected array $fillable = ['subject_code', 'name', 'description', 'units', 'status'];

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        return $this->queryOne(
            'SELECT id FROM subjects WHERE subject_code = ? AND (? IS NULL OR id <> ?) LIMIT 1',
            [$code, $exceptId, $exceptId]
        ) !== null;
    }

    public function listWithTeachers(): array
    {
        return $this->query(
            'SELECT s.*, GROUP_CONCAT(DISTINCT CONCAT(t.first_name, " ", t.last_name)
                    ORDER BY t.last_name SEPARATOR ", ") AS teachers
             FROM subjects s
             LEFT JOIN teacher_subjects ts ON ts.subject_id = s.id
             LEFT JOIN teachers t ON t.id = ts.teacher_id AND t.deleted_at IS NULL
             GROUP BY s.id ORDER BY s.subject_code'
        );
    }
}
