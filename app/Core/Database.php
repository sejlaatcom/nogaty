<?php
/**
 * طبقة الاتصال بقاعدة البيانات (PDO)
 */
class Database
{
    private static ?PDO $pdo = null;

    public static function connect(array $cfg): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'], (int)($cfg['port'] ?? 3306), $cfg['database'], $cfg['charset'] ?? 'utf8mb4');

        self::$pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'",
        ]);
        return self::$pdo;
    }

    public static function pdo(): PDO
    {
        if (!self::$pdo) {
            throw new RuntimeException('لم يتم إنشاء الاتصال بقاعدة البيانات.');
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

    /** صف واحد */
    public static function first(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** كل الصفوف */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** قيمة مفردة */
    public static function value(string $sql, array $params = [])
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /** إدراج صف وإرجاع المعرف */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql  = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (:'
              . implode(',:', $cols) . ')';
        self::run($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    /** تحديث صفوف */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        foreach (array_keys($data) as $c) {
            $sets[] = "`$c` = :s_$c";
        }
        $params = [];
        foreach ($data as $k => $v) {
            $params["s_$k"] = $v;
        }
        $sql = 'UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE ' . $where;
        return self::run($sql, array_merge($params, $whereParams))->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::run('DELETE FROM `' . $table . '` WHERE ' . $where, $params)->rowCount();
    }

    public static function begin(): void   { self::pdo()->beginTransaction(); }
    public static function commit(): void  { self::pdo()->commit(); }
    public static function rollback(): void{ if (self::pdo()->inTransaction()) self::pdo()->rollBack(); }
}
