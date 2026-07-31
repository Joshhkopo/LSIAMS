<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class SecurityLog extends Model
{
    protected string $table = 'security_logs';
    protected array $fillable = ['resolved', 'resolved_by', 'notes'];
    protected bool $timestamps = false;

    public function search(array $filters, int $limit, int $offset): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['severity'])) {
            $where[] = 'sl.severity = ?';
            $params[] = $filters['severity'];
        }
        if (isset($filters['resolved']) && $filters['resolved'] !== '') {
            $where[] = 'sl.resolved = ?';
            $params[] = (int) $filters['resolved'];
        }
        if (!empty($filters['event'])) {
            $where[] = 'sl.event = ?';
            $params[] = $filters['event'];
        }
        $whereSql = implode(' AND ', $where);
        $base = 'FROM security_logs sl LEFT JOIN devices d ON d.id = sl.device_id WHERE ' . $whereSql;
        $total = (int) ($this->queryOne("SELECT COUNT(*) AS c $base", $params)['c'] ?? 0);
        $rows = $this->query(
            "SELECT sl.*, d.device_code $base ORDER BY sl.id DESC LIMIT $limit OFFSET $offset",
            $params
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** Event counts for the security dashboard (last 7 days). */
    public function weeklySummary(): array
    {
        return $this->query(
            'SELECT event, severity, COUNT(*) AS total
             FROM security_logs
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY event, severity ORDER BY total DESC'
        );
    }
}
