<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Services\Scope;

/** الصفوف والفصول (الشعب) والمواد */
final class ClassRoom
{
    public static function label(array $row): string
    {
        return trim(($row['grade_name'] ?? '') . ' / ' . ($row['class_name'] ?? $row['name'] ?? ''));
    }

    /** فصول عام دراسي مع عدد الطلاب، مقيدة بنطاق المستخدم */
    public static function forYear(?int $yearId = null, bool $scoped = true, bool $activeOnly = false): array
    {
        $yearId ??= Year::currentId();
        [$scopeSql, $params] = $scoped ? Scope::classFilter('c.id') : ['1 = 1', []];
        return DB::all(
            "SELECT c.*, c.name AS class_name, g.name AS grade_name, g.level_order, g.is_nafes_target,
                    (SELECT COUNT(*) FROM student_class_enrollments e WHERE e.class_id = c.id AND e.status = 'active') AS students_count
               FROM classes c JOIN grades g ON g.id = c.grade_id
              WHERE c.academic_year_id = ? AND $scopeSql" . ($activeOnly ? ' AND c.is_active = 1' : '') . '
           ORDER BY g.level_order, c.name + 0, c.name',
            array_merge([$yearId], $params)
        );
    }

    public static function find(int $id): ?array
    {
        return DB::first(
            'SELECT c.*, c.name AS class_name, g.name AS grade_name, g.level_order, y.name AS year_name
               FROM classes c JOIN grades g ON g.id = c.grade_id JOIN academic_years y ON y.id = c.academic_year_id
              WHERE c.id = ?',
            [$id]
        );
    }

    /** إيجاد فصل بالاسم (للاستيراد) مع إنشائه اختيارياً */
    public static function resolve(string $gradeName, string $className, int $yearId, bool $create): ?int
    {
        $gradeId = (int) DB::value('SELECT id FROM grades WHERE name = ?', [trim($gradeName)], 0);
        if (!$gradeId) {
            return null;
        }
        $id = (int) DB::value('SELECT id FROM classes WHERE academic_year_id = ? AND grade_id = ? AND name = ?', [$yearId, $gradeId, trim($className)], 0);
        if (!$id && $create && trim($className) !== '') {
            $id = DB::insert('classes', ['academic_year_id' => $yearId, 'grade_id' => $gradeId, 'name' => trim($className)]);
        }
        return $id ?: null;
    }

    public static function grades(bool $activeOnly = true): array
    {
        return DB::all('SELECT * FROM grades' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY level_order');
    }

    public static function subjects(bool $activeOnly = true): array
    {
        return DB::all('SELECT * FROM subjects' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order, name');
    }

    /** الصفوف المستهدفة بنافس */
    public static function nafesGrades(): array
    {
        return DB::all('SELECT * FROM grades WHERE is_nafes_target = 1 AND is_active = 1 ORDER BY level_order')
            ?: self::grades();
    }
}
