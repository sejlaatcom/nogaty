<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * تنفيذ البنية (schema.sql — آمنة لإعادة التنفيذ) ثم ملفات database/migrations/NNN_*.php غير المطبقة.
 * كل ملف ترحيل يُرجع دالة تستقبل PDO.
 */
final class Migrator
{
    public static function statements(string $file): array
    {
        $sql = (string) file_get_contents($file);
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        return array_values(array_filter(array_map('trim', explode(';', $sql)), static fn ($s) => $s !== ''));
    }

    public static function run(PDO $pdo): array
    {
        $done = [];
        foreach (self::statements(BASE_PATH . '/database/schema.sql') as $statement) {
            $pdo->exec($statement);
        }
        $done[] = 'schema.sql';

        $applied = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        foreach (glob(BASE_PATH . '/database/migrations/*.php') ?: [] as $file) {
            $version = basename($file, '.php');
            if (in_array($version, $applied, true)) {
                continue;
            }
            $migration = require $file;
            if (is_callable($migration)) {
                $migration($pdo);
            }
            $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$version]);
            $done[] = $version;
        }
        return $done;
    }
}
