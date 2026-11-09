<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/** المعلمون والوكلاء وإسنادهم للفصول والمواد */
final class Teacher
{
    /** الأدوار المتاحة لمنسوبي المدرسة من وحدة المعلمين */
    public const ROLES = ['teacher' => 'معلم', 'supervisor' => 'وكيل / مشرف', 'principal' => 'قائد المدرسة'];

    public static function find(int $id): ?array
    {
        return DB::first(
            'SELECT t.*, u.username, u.full_name, u.email, u.is_active, u.last_login_at
               FROM teachers t JOIN users u ON u.id = t.user_id WHERE t.id = ?',
            [$id]
        );
    }

    public static function all(?int $yearId = null): array
    {
        $yearId ??= Year::currentId();
        return DB::all(
            "SELECT t.*, u.username, u.full_name, u.email, u.is_active, u.last_login_at,
                    (SELECT GROUP_CONCAT(r.name SEPARATOR '، ') FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id AND r.slug <> 'student') AS role_names,
                    (SELECT GROUP_CONCAT(DISTINCT s.name SEPARATOR '، ') FROM teacher_assignments ta JOIN subjects s ON s.id = ta.subject_id WHERE ta.teacher_id = t.id AND ta.academic_year_id = ?) AS subjects,
                    (SELECT COUNT(DISTINCT ta.class_id) FROM teacher_assignments ta WHERE ta.teacher_id = t.id AND ta.academic_year_id = ?) AS classes_count
               FROM teachers t JOIN users u ON u.id = t.user_id
           ORDER BY u.is_active DESC, u.full_name",
            [$yearId, $yearId]
        );
    }

    public static function create(array $data, array $roles): int
    {
        return DB::transaction(static function () use ($data, $roles) {
            $userId = User::create([
                'username' => $data['username'], 'full_name' => $data['full_name'], 'email' => $data['email'] ?? null,
                'password' => $data['password'], 'is_active' => (int) ($data['is_active'] ?? 1), 'must_change_password' => 1,
            ], $roles);
            return DB::insert('teachers', [
                'user_id' => $userId, 'employee_number' => $data['employee_number'],
                'specialization' => ($data['specialization'] ?? '') ?: null,
            ]);
        });
    }

    public static function assignments(int $teacherId, ?int $yearId = null): array
    {
        return DB::all(
            'SELECT ta.*, c.name AS class_name, g.name AS grade_name, s.name AS subject_name,
                    (SELECT COUNT(*) FROM student_class_enrollments e WHERE e.class_id = ta.class_id AND e.status = \'active\') AS students_count
               FROM teacher_assignments ta
               JOIN classes c ON c.id = ta.class_id JOIN grades g ON g.id = c.grade_id
          LEFT JOIN subjects s ON s.id = ta.subject_id
              WHERE ta.teacher_id = ? AND ta.academic_year_id = ?
           ORDER BY g.level_order, c.name + 0, s.sort_order',
            [$teacherId, $yearId ?? Year::currentId()]
        );
    }

    /** إسناد فصل ومادة (المادة فارغة = إشراف على الفصل) — يمنع التكرار */
    public static function assign(int $teacherId, int $classId, ?int $subjectId): bool
    {
        $yearId = (int) DB::value('SELECT academic_year_id FROM classes WHERE id = ?', [$classId], 0);
        if (!$yearId) {
            return false;
        }
        $exists = DB::value(
            'SELECT 1 FROM teacher_assignments WHERE teacher_id = ? AND class_id = ? AND ' . ($subjectId ? 'subject_id = ?' : 'subject_id IS NULL'),
            $subjectId ? [$teacherId, $classId, $subjectId] : [$teacherId, $classId]
        );
        if ($exists) {
            return false;
        }
        DB::insert('teacher_assignments', ['teacher_id' => $teacherId, 'class_id' => $classId, 'subject_id' => $subjectId, 'academic_year_id' => $yearId]);
        return true;
    }

    public static function numberTaken(string $number, int $exceptId = 0): bool
    {
        return (bool) DB::value('SELECT 1 FROM teachers WHERE employee_number = ? AND id <> ?', [$number, $exceptId]);
    }
}
