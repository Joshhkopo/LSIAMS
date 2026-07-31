<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Student extends Model
{
    protected string $table = 'students';
    protected array $fillable = [
        'student_number', 'section_id', 'grade_level_id', 'first_name', 'middle_name',
        'last_name', 'gender', 'birthdate', 'address', 'email', 'guardian_name',
        'guardian_contact', 'photo', 'status',
    ];
    protected bool $softDelete = true;

    public function numberExists(string $studentNumber, ?int $exceptId = null): bool
    {
        return $this->queryOne(
            'SELECT id FROM students WHERE student_number = ? AND (? IS NULL OR id <> ?) LIMIT 1',
            [$studentNumber, $exceptId, $exceptId]
        ) !== null;
    }

    /**
     * Paginated search with filters, joined to section/grade/active RFID.
     * @return array{rows: array, total: int}
     */
    public function search(string $term, array $filters, int $limit, int $offset): array
    {
        $where = ['s.deleted_at IS NULL'];
        $params = [];

        if ($term !== '') {
            $where[] = '(s.student_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?
                         OR CONCAT(s.first_name, " ", s.last_name) LIKE ? OR rc.uid LIKE ?)';
            $like = "%$term%";
            array_push($params, $like, $like, $like, $like, $like);
        }
        foreach (['status' => 's.status', 'section_id' => 's.section_id', 'grade_level_id' => 's.grade_level_id'] as $key => $col) {
            if (!empty($filters[$key])) {
                $where[] = "$col = ?";
                $params[] = $filters[$key];
            }
        }

        $base = 'FROM students s
                 LEFT JOIN sections sec ON sec.id = s.section_id
                 LEFT JOIN grade_levels gl ON gl.id = s.grade_level_id
                 LEFT JOIN rfid_cards rc ON rc.student_id = s.id AND rc.status = "active"
                 WHERE ' . implode(' AND ', $where);

        $total = (int) ($this->queryOne("SELECT COUNT(DISTINCT s.id) AS c $base", $params)['c'] ?? 0);
        $rows = $this->query(
            "SELECT s.*, sec.name AS section_name, gl.name AS grade_name, rc.uid AS rfid_uid
             $base GROUP BY s.id ORDER BY s.last_name, s.first_name LIMIT $limit OFFSET $offset",
            $params
        );
        return ['rows' => $rows, 'total' => $total];
    }

    public function attendanceSummary(int $studentId): array
    {
        return $this->query(
            'SELECT st.code, st.name, COUNT(ar.id) AS total
             FROM attendance_records ar
             JOIN attendance_statuses st ON st.id = ar.status_id
             WHERE ar.student_id = ?
             GROUP BY st.id, st.code, st.name',
            [$studentId]
        );
    }
}
