<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Teacher extends Model
{
    protected string $table = 'teachers';
    protected array $fillable = [
        'employee_number', 'user_id', 'department', 'first_name', 'middle_name',
        'last_name', 'email', 'phone', 'photo', 'status',
    ];
    protected bool $softDelete = true;

    public function employeeNumberExists(string $number, ?int $exceptId = null): bool
    {
        return $this->queryOne(
            'SELECT id FROM teachers WHERE employee_number = ? AND (? IS NULL OR id <> ?) LIMIT 1',
            [$number, $exceptId, $exceptId]
        ) !== null;
    }

    public function findByUserId(int $userId): ?array
    {
        return $this->queryOne(
            'SELECT * FROM teachers WHERE user_id = ? AND deleted_at IS NULL LIMIT 1',
            [$userId]
        );
    }

    /** List with fingerprint + account status for the management table. */
    public function listWithStatus(int $limit, int $offset, string $term = ''): array
    {
        $where = 't.deleted_at IS NULL';
        $params = [];
        if ($term !== '') {
            $where .= ' AND (t.employee_number LIKE ? OR t.first_name LIKE ? OR t.last_name LIKE ? OR t.department LIKE ?)';
            $like = "%$term%";
            array_push($params, $like, $like, $like, $like);
        }
        $total = (int) ($this->queryOne("SELECT COUNT(*) AS c FROM teachers t WHERE $where", $params)['c'] ?? 0);
        $rows = $this->query(
            "SELECT t.*, u.username, u.status AS account_status, u.last_login,
                    ft.status AS fingerprint_status, ft.enrolled_at,
                    GROUP_CONCAT(DISTINCT sub.subject_code ORDER BY sub.subject_code SEPARATOR ', ') AS subjects
             FROM teachers t
             JOIN users u ON u.id = t.user_id
             LEFT JOIN fingerprint_templates ft ON ft.teacher_id = t.id
             LEFT JOIN teacher_subjects ts ON ts.teacher_id = t.id
             LEFT JOIN subjects sub ON sub.id = ts.subject_id
             WHERE $where
             GROUP BY t.id ORDER BY t.last_name, t.first_name LIMIT $limit OFFSET $offset",
            $params
        );
        return ['rows' => $rows, 'total' => $total];
    }
}
