<?php
declare(strict_types=1);

/**
 * ترقية قاعدة البيانات للتثبيتات السابقة.
 * يعيد تنفيذ schema.sql (آمن لأن كل الجداول IF NOT EXISTS) ثم يضيف الأعمدة الجديدة.
 */
final class Migrator
{
    /** هل تحتاج قاعدة البيانات إلى ترقية؟ */
    public static function needed(): bool
    {
        try {
            if (!self::columnExists('users', 'classroom_id')) {
                return true;
            }
            foreach (self::tables() as $table) {
                if (!self::tableExists($table)) {
                    return true;
                }
            }
            return false;
        } catch (Throwable) {
            return false;
        }
    }

    /** تنفيذ الترقية — تُعيد قائمة بما تم */
    public static function run(?PDO $pdo = null): array
    {
        $pdo ??= Database::pdo();
        $done = [];

        foreach (self::statements() as $statement) {
            $pdo->exec($statement);
        }
        $done[] = 'تم التحقق من جميع الجداول وإنشاء الناقص منها.';

        $role = (string) $pdo->query(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'"
        )->fetchColumn();
        if ($role !== '' && !str_contains($role, "'student'")) {
            $pdo->exec("ALTER TABLE `users` MODIFY `role` ENUM('admin','teacher','student') NOT NULL DEFAULT 'teacher'");
            $done[] = 'إضافة صلاحية «طالب» للمستخدمين.';
        }

        if (!self::columnExists('users', 'classroom_id', $pdo)) {
            $pdo->exec('ALTER TABLE `users` ADD `classroom_id` INT UNSIGNED NULL AFTER `role`, ADD KEY `idx_users_classroom` (`classroom_id`)');
            $done[] = 'إضافة عمود فصل الطالب.';
        }

        return $done;
    }

    /** جمل ملف البنية بعد إزالة التعليقات */
    public static function statements(): array
    {
        $sql = file_get_contents(APP_ROOT . '/database/schema.sql');
        if ($sql === false) {
            throw new RuntimeException('تعذر قراءة ملف database/schema.sql');
        }
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;

        $out = [];
        foreach (explode(';', $sql) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $out[] = $statement;
            }
        }
        return $out;
    }

    /** أسماء الجداول المعرّفة في ملف البنية */
    private static function tables(): array
    {
        $names = [];
        foreach (self::statements() as $statement) {
            if (preg_match('/CREATE TABLE IF NOT EXISTS `([a-z_]+)`/i', $statement, $m)) {
                $names[] = $m[1];
            }
        }
        return $names;
    }

    private static function tableExists(string $table): bool
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table],
            0
        ) > 0;
    }

    private static function columnExists(string $table, string $column, ?PDO $pdo = null): bool
    {
        $pdo ??= Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
