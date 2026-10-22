<?php
declare(strict_types=1);

namespace App\Services\Import;

use App\Core\HttpException;

/** أنواع الاستيراد المتاحة في المعالج */
final class Registry
{
    public const KINDS = [
        'students'  => StudentImporter::class,
        'teachers'  => TeacherImporter::class,
    ];

    public static function make(string $kind): Importer
    {
        $class = self::KINDS[$kind] ?? null;
        if (!$class) {
            throw new HttpException(404);
        }
        return new $class();
    }
}
