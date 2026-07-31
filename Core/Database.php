<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * Shared PDO connection (singleton). Exceptions enabled, emulated prepares
 * disabled so every query is a true server-side prepared statement.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                Config::get('db.host'),
                Config::get('db.port'),
                Config::get('db.database'),
            );
            try {
                self::$pdo = new PDO($dsn, Config::get('db.username'), Config::get('db.password'), [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                // Never leak credentials or DSN details to the client.
                throw new PDOException('Database connection failed.', (int) $e->getCode());
            }
        }
        return self::$pdo;
    }

    /**
     * Run $fn inside a transaction; rolls back on any throwable so
     * attendance and other critical writes are never partially saved.
     */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
