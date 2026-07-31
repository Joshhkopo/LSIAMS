<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Base model. Subclasses define $table, $primaryKey and $fillable.
 * All queries are prepared statements; there is no string concatenation of
 * user input into SQL anywhere in the model layer.
 */
abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';
    /** @var string[] Columns accepted by create()/update(). */
    protected array $fillable = [];
    protected bool $softDelete = false;
    protected bool $timestamps = true;

    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    public function find(int|string $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findBy(string $column, mixed $value): ?array
    {
        $this->assertColumn($column);
        $stmt = $this->db->prepare("SELECT * FROM `{$this->table}` WHERE `$column` = ? LIMIT 1");
        $stmt->execute([$value]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $where  column => value equality filters
     */
    public function all(array $where = [], string $orderBy = '', int $limit = 0, int $offset = 0): array
    {
        [$sql, $params] = $this->buildSelect('*', $where, $orderBy, $limit, $offset);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function count(array $where = []): int
    {
        [$sql, $params] = $this->buildSelect('COUNT(*) AS c', $where);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetch()['c'];
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $data = $this->onlyFillable($data);
        if ($this->timestamps) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->table,
            implode(', ', array_map(fn($c) => "`$c`", $columns)),
            implode(', ', array_fill(0, count($columns), '?')),
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($data));
        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int|string $id, array $data): bool
    {
        $data = $this->onlyFillable($data);
        if ($data === []) {
            return false;
        }
        if ($this->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        $set = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($data)));
        $stmt = $this->db->prepare(
            "UPDATE `{$this->table}` SET $set WHERE `{$this->primaryKey}` = ?"
        );
        return $stmt->execute([...array_values($data), $id]);
    }

    /**
     * Soft-delete when the table supports it (sets deleted_at / status);
     * physical delete is deliberately unavailable for protected tables.
     */
    public function archive(int|string $id): bool
    {
        if (!$this->softDelete) {
            $stmt = $this->db->prepare("DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?");
            return $stmt->execute([$id]);
        }
        $stmt = $this->db->prepare(
            "UPDATE `{$this->table}` SET deleted_at = NOW(), status = 'archived' WHERE `{$this->primaryKey}` = ?"
        );
        return $stmt->execute([$id]);
    }

    /** Raw parameterized query helper for module-specific joins/aggregates. */
    protected function query(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    protected function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    protected function execute(string $sql, array $params = []): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    private function buildSelect(string $select, array $where, string $orderBy = '', int $limit = 0, int $offset = 0): array
    {
        $sql = "SELECT $select FROM `{$this->table}`";
        $params = [];
        $conditions = [];
        foreach ($where as $column => $value) {
            $this->assertColumn($column);
            if ($value === null) {
                $conditions[] = "`$column` IS NULL";
            } else {
                $conditions[] = "`$column` = ?";
                $params[] = $value;
            }
        }
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        if ($orderBy !== '') {
            // Order-by clauses come from code, never from raw user input.
            $sql .= ' ORDER BY ' . $orderBy;
        }
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit . ' OFFSET ' . max(0, $offset);
        }
        return [$sql, $params];
    }

    private function onlyFillable(array $data): array
    {
        return array_intersect_key($data, array_flip($this->fillable));
    }

    private function assertColumn(string $column): void
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new \InvalidArgumentException('Invalid column name.');
        }
    }
}
