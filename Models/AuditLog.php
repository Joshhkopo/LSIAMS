<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Read-only access; writes go through App\Helpers\Audit only. */
final class AuditLog extends Model
{
    protected string $table = 'audit_logs';
    protected array $fillable = [];
    protected bool $timestamps = false;

    public function search(array $filters, int $limit, int $offset): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['user_id'])) {
            $where[] = 'al.user_id = ?';
            $params[] = $filters['user_id'];
        }
        if (!empty($filters['module'])) {
            $where[] = 'al.module = ?';
            $params[] = $filters['module'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(al.created_at) >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(al.created_at) <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(al.action LIKE ? OR al.module LIKE ? OR u.username LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            array_push($params, $like, $like, $like);
        }
        $whereSql = implode(' AND ', $where);
        $base = 'FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id WHERE ' . $whereSql;
        $total = (int) ($this->queryOne("SELECT COUNT(*) AS c $base", $params)['c'] ?? 0);
        $rows = $this->query(
            "SELECT al.*, u.username $base ORDER BY al.id DESC LIMIT $limit OFFSET $offset",
            $params
        );
        return ['rows' => $rows, 'total' => $total];
    }
}
