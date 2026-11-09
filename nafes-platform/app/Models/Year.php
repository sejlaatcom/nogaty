<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/** الأعوام الدراسية */
final class Year
{
    private static ?array $current = null;

    public static function current(): ?array
    {
        return self::$current ??= DB::first('SELECT * FROM academic_years WHERE is_current = 1 ORDER BY id DESC LIMIT 1')
            ?? DB::first('SELECT * FROM academic_years ORDER BY id DESC LIMIT 1');
    }

    public static function currentId(): int
    {
        return (int) (self::current()['id'] ?? 0);
    }

    public static function all(): array
    {
        return DB::all('SELECT * FROM academic_years ORDER BY starts_on DESC, id DESC');
    }

    public static function find(int $id): ?array
    {
        return DB::first('SELECT * FROM academic_years WHERE id = ?', [$id]);
    }

    public static function flush(): void
    {
        self::$current = null;
    }
}
