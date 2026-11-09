<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Models\Year;

/**
 * نطاق البيانات المسموح للمستخدم:
 * - من يملك scope.all (مدير النظام، قائد المدرسة) يرى كل الفصول.
 * - المعلم والوكيل/المشرف يرون الفصول المسندة إليهم في العام الحالي فقط.
 * - الطالب يرى بياناته فقط.
 */
final class Scope
{
    private static ?array $classIds = null;

    public static function all(): bool
    {
        return Auth::can('scope.all');
    }

    /** معرفات الفصول المسموحة (العام الحالي) */
    public static function classIds(?int $yearId = null): array
    {
        $yearId ??= Year::currentId();
        if ($yearId === Year::currentId() && self::$classIds !== null) {
            return self::$classIds;
        }
        if (self::all()) {
            $ids = array_map('intval', DB::column('SELECT id FROM classes WHERE academic_year_id = ?', [$yearId]));
        } else {
            $ids = array_map('intval', DB::column(
                'SELECT DISTINCT class_id FROM teacher_assignments WHERE teacher_id = ? AND academic_year_id = ?',
                [Auth::teacherId(), $yearId]
            ));
        }
        if ($yearId === Year::currentId()) {
            self::$classIds = $ids;
        }
        return $ids;
    }

    /** مواد المعلم في فصل (فارغة = كل المواد للمشرف أو الإدارة) */
    public static function subjectIds(): ?array
    {
        if (self::all()) {
            return null;
        }
        $rows = DB::all('SELECT DISTINCT subject_id FROM teacher_assignments WHERE teacher_id = ? AND academic_year_id = ?', [Auth::teacherId(), Year::currentId()]);
        foreach ($rows as $row) {
            if ($row['subject_id'] === null) {
                return null; // إشراف على كل المواد
            }
        }
        return array_map(static fn ($r) => (int) $r['subject_id'], $rows);
    }

    public static function canClass(int $classId): bool
    {
        return in_array($classId, self::classIds(), true)
            || (self::all() && (bool) DB::value('SELECT 1 FROM classes WHERE id = ?', [$classId]));
    }

    public static function authorizeClass(int $classId): void
    {
        if (!self::canClass($classId)) {
            throw new HttpException(403, 'هذا الفصل غير مسند إليك.');
        }
    }

    /** يستطيع المستخدم الاطلاع على بيانات الطالب؟ */
    public static function canStudent(int $studentId): bool
    {
        if (Auth::studentId() === $studentId && Auth::hasRole('student')) {
            return true;
        }
        if (self::all()) {
            return true;
        }
        $classes = self::classIds();
        if (!$classes) {
            return false;
        }
        [$in, $params] = DB::in($classes);
        return (bool) DB::value(
            "SELECT 1 FROM student_class_enrollments WHERE student_id = ? AND status = 'active' AND class_id IN ($in) LIMIT 1",
            array_merge([$studentId], $params)
        );
    }

    public static function authorizeStudent(int $studentId): void
    {
        if (!self::canStudent($studentId)) {
            throw new HttpException(403, 'لا تملك صلاحية الاطلاع على بيانات هذا الطالب.');
        }
    }

    /** شرط SQL لتقييد عمود فصل بنطاق المستخدم */
    public static function classFilter(string $column): array
    {
        if (self::all()) {
            return ['1 = 1', []];
        }
        [$in, $params] = DB::in(self::classIds());
        return ["$column IN ($in)", $params];
    }

    public static function flush(): void
    {
        self::$classIds = null;
    }
}
