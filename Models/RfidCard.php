<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class RfidCard extends Model
{
    protected string $table = 'rfid_cards';
    protected array $fillable = ['student_id', 'uid', 'issue_date', 'replacement_of', 'status'];

    public function uidExists(string $uid, ?int $exceptId = null): bool
    {
        return $this->queryOne(
            'SELECT id FROM rfid_cards WHERE uid = ? AND (? IS NULL OR id <> ?) LIMIT 1',
            [$uid, $exceptId, $exceptId]
        ) !== null;
    }

    public function findByUid(string $uid): ?array
    {
        return $this->queryOne(
            'SELECT rc.*, s.id AS student_id, s.student_number, s.section_id, s.status AS student_status,
                    CONCAT(s.first_name, " ", s.last_name) AS student_name, s.photo AS student_photo
             FROM rfid_cards rc JOIN students s ON s.id = rc.student_id
             WHERE rc.uid = ? LIMIT 1',
            [$uid]
        );
    }

    public function activeCardForStudent(int $studentId): ?array
    {
        return $this->queryOne(
            'SELECT * FROM rfid_cards WHERE student_id = ? AND status = "active" LIMIT 1',
            [$studentId]
        );
    }

    public function listDetailed(string $term, int $limit, int $offset): array
    {
        $where = '1=1';
        $params = [];
        if ($term !== '') {
            $where = '(rc.uid LIKE ? OR s.student_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)';
            $like = "%$term%";
            $params = [$like, $like, $like, $like];
        }
        $total = (int) ($this->queryOne(
            "SELECT COUNT(*) AS c FROM rfid_cards rc JOIN students s ON s.id = rc.student_id WHERE $where",
            $params
        )['c'] ?? 0);
        $rows = $this->query(
            "SELECT rc.*, s.student_number, CONCAT(s.first_name, ' ', s.last_name) AS student_name
             FROM rfid_cards rc JOIN students s ON s.id = rc.student_id
             WHERE $where ORDER BY rc.created_at DESC LIMIT $limit OFFSET $offset",
            $params
        );
        return ['rows' => $rows, 'total' => $total];
    }
}
