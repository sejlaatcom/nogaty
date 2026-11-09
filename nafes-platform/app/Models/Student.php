<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Services\Scope;

/** الطلاب وتسجيلهم في الفصول */
final class Student
{
    public const STATUSES = [
        'active'          => 'نشط',
        'inactive'        => 'موقوف',
        'graduated'       => 'متخرج',
        'transferred_out' => 'منقول من المدرسة',
    ];

    public static function find(int $id): ?array
    {
        return DB::first(
            "SELECT s.*, u.username, u.full_name, u.is_active, u.last_login_at, u.must_change_password,
                    e.class_id, c.name AS class_name, g.name AS grade_name, c.grade_id
               FROM students s JOIN users u ON u.id = s.user_id
          LEFT JOIN student_class_enrollments e ON e.student_id = s.id AND e.status = 'active'
          LEFT JOIN classes c ON c.id = e.class_id
          LEFT JOIN grades g ON g.id = c.grade_id
              WHERE s.id = ?",
            [$id]
        );
    }

    public static function byUser(int $userId): ?array
    {
        $id = (int) DB::value('SELECT id FROM students WHERE user_id = ?', [$userId], 0);
        return $id ? self::find($id) : null;
    }

    /**
     * البحث مع التقييد بنطاق المستخدم.
     * $f: q, grade, class, status, account (active|inactive)
     */
    public static function search(array $f, int $page = 1, int $perPage = 50): array
    {
        $where  = ['1 = 1'];
        $params = [];
        if (!Scope::all()) {
            [$in, $p] = DB::in(Scope::classIds());
            $where[] = "e.class_id IN ($in)";
            $params  = array_merge($params, $p);
        }
        if (!empty($f['q'])) {
            $where[] = '(u.full_name LIKE ? OR s.student_number LIKE ? OR u.username LIKE ?)';
            array_push($params, '%' . $f['q'] . '%', '%' . $f['q'] . '%', '%' . $f['q'] . '%');
        }
        if (!empty($f['grade'])) {
            $where[]  = 'c.grade_id = ?';
            $params[] = (int) $f['grade'];
        }
        if (!empty($f['class'])) {
            $where[]  = 'e.class_id = ?';
            $params[] = (int) $f['class'];
        }
        if (!empty($f['status']) && isset(self::STATUSES[$f['status']])) {
            $where[]  = 's.status = ?';
            $params[] = $f['status'];
        }
        if (($f['account'] ?? '') !== '') {
            $where[]  = 'u.is_active = ?';
            $params[] = $f['account'] === 'active' ? 1 : 0;
        }
        if (!empty($f['unassigned'])) {
            $where[] = 'e.id IS NULL';
        }

        $from = "FROM students s JOIN users u ON u.id = s.user_id
            LEFT JOIN student_class_enrollments e ON e.student_id = s.id AND e.status = 'active'
            LEFT JOIN classes c ON c.id = e.class_id
            LEFT JOIN grades g ON g.id = c.grade_id
            WHERE " . implode(' AND ', $where);

        $total = (int) DB::value("SELECT COUNT(*) $from", $params, 0);
        $rows  = DB::all(
            "SELECT s.*, u.username, u.full_name, u.is_active, u.last_login_at, e.class_id, c.name AS class_name, g.name AS grade_name
             $from ORDER BY g.level_order, c.name + 0, c.name, u.full_name
             LIMIT " . (int) $perPage . ' OFFSET ' . (int) (max(0, $page - 1) * $perPage),
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    /** إنشاء طالب: حساب مستخدم + سجل طالب + دور + تسجيل في فصل */
    public static function create(array $data): int
    {
        return DB::transaction(static function () use ($data) {
            $userId = User::create([
                'username' => $data['username'], 'full_name' => $data['full_name'], 'password' => $data['password'],
                'is_active' => $data['status'] === 'active' ? 1 : 0, 'must_change_password' => 1,
            ], ['student']);
            $id = DB::insert('students', [
                'user_id' => $userId, 'student_number' => $data['student_number'],
                'status' => $data['status'], 'notes' => ($data['notes'] ?? '') ?: null,
            ]);
            if (!empty($data['class_id'])) {
                self::enroll($id, (int) $data['class_id']);
            }
            return $id;
        });
    }

    public static function update(int $id, array $data): void
    {
        DB::transaction(static function () use ($id, $data) {
            $student = self::find($id);
            DB::update('students', [
                'student_number' => $data['student_number'], 'status' => $data['status'], 'notes' => ($data['notes'] ?? '') ?: null,
            ], 'id = :id', ['id' => $id]);
            DB::update('users', [
                'full_name' => $data['full_name'], 'username' => mb_strtolower($data['username']),
                'is_active' => $data['status'] === 'active' ? 1 : 0,
            ], 'id = :id', ['id' => $student['user_id']]);
            if (!empty($data['password'])) {
                User::setPassword((int) $student['user_id'], $data['password'], true);
            }
            if (!empty($data['class_id']) && (int) $data['class_id'] !== (int) $student['class_id']) {
                self::enroll($id, (int) $data['class_id']);
            }
        });
    }

    /**
     * تسجيل/نقل طالب إلى فصل: يُغلق التسجيل الحالي (نقل) ويُنشأ تسجيل جديد،
     * فتبقى النتائج السابقة مرتبطة بالفصل الذي كان فيه وقتها.
     */
    public static function enroll(int $studentId, int $classId): void
    {
        $class = DB::first('SELECT id, academic_year_id FROM classes WHERE id = ?', [$classId]);
        if (!$class) {
            return;
        }
        $current = DB::first("SELECT * FROM student_class_enrollments WHERE student_id = ? AND status = 'active'", [$studentId]);
        if ($current && (int) $current['class_id'] === $classId) {
            return;
        }
        if ($current) {
            $status = (int) $current['academic_year_id'] === (int) $class['academic_year_id'] ? 'transferred' : 'completed';
            DB::update('student_class_enrollments', ['status' => $status, 'left_on' => date('Y-m-d')], 'id = :id', ['id' => $current['id']]);
        }
        DB::insert('student_class_enrollments', [
            'student_id' => $studentId, 'class_id' => $classId, 'academic_year_id' => $class['academic_year_id'],
            'enrolled_on' => date('Y-m-d'), 'status' => 'active',
        ]);
    }

    public static function history(int $studentId): array
    {
        return DB::all(
            'SELECT e.*, c.name AS class_name, g.name AS grade_name, y.name AS year_name
               FROM student_class_enrollments e JOIN classes c ON c.id = e.class_id
               JOIN grades g ON g.id = c.grade_id JOIN academic_years y ON y.id = e.academic_year_id
              WHERE e.student_id = ? ORDER BY e.id DESC',
            [$studentId]
        );
    }

    public static function numberTaken(string $number, int $exceptId = 0): bool
    {
        return (bool) DB::value('SELECT 1 FROM students WHERE student_number = ? AND id <> ?', [$number, $exceptId]);
    }

    /** معرفات الطلاب المسجلين حالياً في فصل */
    public static function idsInClass(int $classId): array
    {
        return array_map('intval', DB::column("SELECT student_id FROM student_class_enrollments WHERE class_id = ? AND status = 'active'", [$classId]));
    }

    /** فصل الطالب الحالي */
    public static function currentClassId(int $studentId): ?int
    {
        $id = DB::value("SELECT class_id FROM student_class_enrollments WHERE student_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1", [$studentId]);
        return $id ? (int) $id : null;
    }
}
