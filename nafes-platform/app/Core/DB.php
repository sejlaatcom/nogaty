<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;
use Throwable;

/** طبقة PDO: كل الاستعلامات عبر Prepared Statements */
final class DB
{
    private static ?PDO $pdo = null;

    public static function connect(?array $c = null): PDO
    {
        $c ??= (array) config('database');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], (int) $c['port'], $c['database'], $c['charset'] ?? 'utf8mb4');
        $pdo = new PDO($dsn, (string) $c['username'], (string) $c['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
        try {
            $pdo->exec("SET time_zone = '" . (new \DateTimeImmutable('now'))->format('P') . "'");
        } catch (Throwable) {
            // بعض الاستضافات تمنع تغيير المنطقة الزمنية
        }
        return $pdo;
    }

    public static function pdo(): PDO
    {
        return self::$pdo ??= self::connect();
    }

    public static function setPdo(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function first(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = [], mixed $default = null): mixed
    {
        $value = self::run($sql, $params)->fetchColumn();
        return $value === false || $value === null ? $default : $value;
    }

    public static function column(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql  = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(static fn ($c) => "`$c`", $cols)),
            implode(', ', array_map(static fn ($c) => ':' . $c, $cols))
        );
        self::run($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $params = []): int
    {
        $sets = [];
        $bind = [];
        foreach ($data as $col => $value) {
            $sets[] = "`$col` = :set_$col";
            $bind['set_' . $col] = $value;
        }
        return self::run(sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $sets), $where), $bind + $params)->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::run("DELETE FROM `$table` WHERE $where", $params)->rowCount();
    }

    /** IN (?, ?, ?) آمن مع معاملاته */
    public static function in(array $values): array
    {
        $values = array_values($values) ?: [0];
        return [implode(',', array_fill(0, count($values), '?')), $values];
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();
        if ($pdo->inTransaction()) {
            return $callback();
        }
        $pdo->beginTransaction();
        try {
            $result = $callback();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
