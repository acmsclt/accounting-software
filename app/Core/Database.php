<?php
// app/Core/Database.php — PDO Singleton

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $config = require BASE_PATH . '/config/database.php';
            $c = $config['connections'][$config['default']];

            $dsn = "{$c['driver']}:host={$c['host']};port={$c['port']};dbname={$c['database']};charset={$c['charset']}";

            try {
                self::$instance = new PDO($dsn, $c['username'], $c['password'], $c['options']);
            } catch (PDOException $e) {
                if (defined('APP_DEBUG') && APP_DEBUG) {
                    throw $e;
                }
                http_response_code(500);
                die(json_encode(['error' => 'Database connection failed.']));
            }
        }

        return self::$instance;
    }

    /** Execute a prepared statement and return the PDOStatement */
    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Fetch all rows */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /** Fetch a single row */
    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row ?: null;
    }

    /** Fetch a single column value */
    public static function fetchColumn(string $sql, array $params = []): mixed
    {
        return self::query($sql, $params)->fetchColumn();
    }

    /** Insert and return last insert ID */
    public static function insert(string $table, array $data): int
    {
        $cols = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        self::query("INSERT INTO `{$table}` ({$cols}) VALUES ({$placeholders})", array_values($data));
        return (int) self::getInstance()->lastInsertId();
    }

    /** Update matching rows */
    public static function update(string $table, array $data, array $where): int
    {
        $set   = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
        $conds = implode(' AND ', array_map(fn($k) => "`{$k}` = ?", array_keys($where)));
        $stmt  = self::query(
            "UPDATE `{$table}` SET {$set} WHERE {$conds}",
            [...array_values($data), ...array_values($where)]
        );
        return $stmt->rowCount();
    }

    /** Soft-delete or hard-delete */
    public static function delete(string $table, array $where, bool $soft = true): int
    {
        if ($soft) {
            return self::update($table, ['deleted_at' => date('Y-m-d H:i:s')], $where);
        }
        $conds = implode(' AND ', array_map(fn($k) => "`{$k}` = ?", array_keys($where)));
        $stmt  = self::query("DELETE FROM `{$table}` WHERE {$conds}", array_values($where));
        return $stmt->rowCount();
    }

    public static function beginTransaction(): void { self::getInstance()->beginTransaction(); }
    public static function commit(): void           { self::getInstance()->commit(); }
    public static function rollback(): void         { self::getInstance()->rollBack(); }
}
