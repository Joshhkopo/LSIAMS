<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Section extends Model
{
    protected string $table = 'sections';
    protected array $fillable = ['grade_level_id', 'name', 'capacity', 'status'];

    public function listWithGrade(): array
    {
        return $this->query(
            'SELECT s.*, gl.name AS grade_name,
                    (SELECT COUNT(*) FROM students st WHERE st.section_id = s.id AND st.deleted_at IS NULL) AS student_count
             FROM sections s JOIN grade_levels gl ON gl.id = s.grade_level_id
             ORDER BY gl.sort_order, s.name'
        );
    }
}
