<?php
declare(strict_types=1);

/** طبقة الاتصال بقاعدة البيانات (PDO) */
final class Database
{
    private static ?PDO $pdo = null;
    private static array $config = [];

    public static function configure(array $config): void
    {
        self::$config = $config + [
            'host' => 'localhost', 'port' => 3306, 'database' => '',
            'username' => 'root', 'password' => '', 'charset' => 'utf8mb4',
        ];
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $c   = self::$config;
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], (int) $c['port'], $c['database'], $c['charset']);

        try {
            self::$pdo = new PDO($dsn, $c['username'], $c['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // توحيد المنطقة الزمنية بين PHP وقاعدة البيانات حتى تتطابق أوقات الإرسال والاعتماد
            try {
                self::$pdo->exec("SET time_zone = '" . (new DateTimeImmutable('now'))->format('P') . "'");
            } catch (Throwable) {
                // بعض الاستضافات لا تسمح بتغيير المنطقة الزمنية — تُستخدم منطقة الخادم
            }
        } catch (PDOException $e) {
            if (!empty($GLOBALS['config']['debug'])) {
                throw $e;
            }
            http_response_code(500);
            exit('تعذر الاتصال بقاعدة البيانات. تحقق من ملف الإعدادات config/config.php');
        }

        return self::$pdo;
    }

    /** تنفيذ استعلام مع معاملات آمنة */
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
        return $value === false ? $default : $value;
    }

    public static function insert(string $table, array $data): int
    {
        $columns      = array_keys($data);
        $placeholders = array_map(static fn ($c) => ':' . $c, $columns);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(static fn ($c) => "`$c`", $columns)),
            implode(', ', $placeholders)
        );
        self::run($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = "`$column` = :$column";
        }
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $sets), $where);
        return self::run($sql, $data + $whereParams)->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::run("DELETE FROM `$table` WHERE $where", $params)->rowCount();
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** هل الجداول منشأة؟ */
    public static function isInstalled(): bool
    {
        try {
            self::pdo()->query('SELECT 1 FROM `users` LIMIT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
