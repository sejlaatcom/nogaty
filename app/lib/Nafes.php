<?php
declare(strict_types=1);

/** منصة نافس: المواد والصفوف المستهدفة، الطلاب، ومستويات الأداء */
final class Nafes
{
    /* ---------------- المواد والصفوف المستهدفة ---------------- */

    /** مواد نافس المحددة في الإعدادات (أو كل المواد المفعّلة) */
    public static function subjects(): array
    {
        $ids = self::idList(Settings::get('nafes_subject_ids'));
        if ($ids) {
            $in = implode(',', $ids);
            return Database::all("SELECT * FROM subjects WHERE id IN ($in) ORDER BY sort_order, name");
        }
        return Database::all('SELECT * FROM subjects WHERE is_active = 1 ORDER BY sort_order, name');
    }

    /** صفوف نافس المحددة في الإعدادات (أو كل الصفوف) */
    public static function grades(): array
    {
        $ids = self::idList(Settings::get('nafes_grade_ids'));
        if ($ids) {
            $in = implode(',', $ids);
            return Database::all("SELECT * FROM grades WHERE id IN ($in) ORDER BY sort_order, name");
        }
        return Database::all('SELECT * FROM grades ORDER BY sort_order, name');
    }

    public static function idList(string $csv): array
    {
        $ids = array_filter(array_map('intval', explode(',', $csv)), static fn ($v) => $v > 0);
        return array_values(array_unique($ids));
    }

    /* ---------------- الطلاب والفصول ---------------- */

    /** الطلاب مع فصولهم — يمكن التصفية بالفصل أو الصف أو البحث بالاسم */
    public static function students(?int $classroomId = null, ?int $gradeId = null, string $search = '', bool $activeOnly = false): array
    {
        $where  = ["u.role = 'student'"];
        $params = [];
        if ($classroomId) {
            $where[]  = 'u.classroom_id = ?';
            $params[] = $classroomId;
        }
        if ($gradeId) {
            $where[]  = 'c.grade_id = ?';
            $params[] = $gradeId;
        }
        if ($search !== '') {
            $where[]  = '(u.name LIKE ? OR u.username LIKE ? OR u.national_id LIKE ?)';
            $like     = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }
        if ($activeOnly) {
            $where[] = 'u.is_active = 1';
        }

        return Database::all(
            'SELECT u.*, c.name AS class_name, c.grade_id, g.name AS grade_name
               FROM users u
          LEFT JOIN classrooms c ON c.id = u.classroom_id
          LEFT JOIN grades g     ON g.id = c.grade_id
              WHERE ' . implode(' AND ', $where) . '
           ORDER BY g.sort_order, c.sort_order, u.name',
            $params
        );
    }

    public static function student(int $id): ?array
    {
        return Database::first(
            "SELECT u.*, c.name AS class_name, c.grade_id, g.name AS grade_name
               FROM users u
          LEFT JOIN classrooms c ON c.id = u.classroom_id
          LEFT JOIN grades g     ON g.id = c.grade_id
              WHERE u.id = ? AND u.role = 'student'",
            [$id]
        );
    }

    /** «الصف / الفصل» لصف طالب من students() أو student() */
    public static function classOf(array $student): string
    {
        if (empty($student['grade_name'])) {
            return '—';
        }
        return Plans::classLabel(['grade_name' => $student['grade_name'], 'name' => $student['class_name'] ?? '']);
    }

    /** هل للطالب محاولات مسجلة؟ (لا يُحذف حسابه حفاظاً على النتائج) */
    public static function studentHasRecords(int $studentId): bool
    {
        return (bool) Database::value('SELECT 1 FROM nafes_attempts WHERE student_id = ? LIMIT 1', [$studentId]);
    }

    /** الفصول التي يستطيع المستخدم متابعتها: الإدارة كل الفصول، والمعلم فصوله المسندة */
    public static function staffClassrooms(array $user): array
    {
        if ($user['role'] === 'admin') {
            return Plans::classrooms();
        }
        $yearId = (int) (Plans::activeYear()['id'] ?? 0);
        return Database::all(
            'SELECT DISTINCT c.*, g.name AS grade_name, g.stage, g.sort_order AS grade_order
               FROM assignments a
               JOIN classrooms c ON c.id = a.classroom_id
               JOIN grades g     ON g.id = c.grade_id
              WHERE a.teacher_id = ? AND a.year_id = ? AND c.is_active = 1
           ORDER BY g.sort_order, c.sort_order, c.name',
            [$user['id'], $yearId]
        );
    }

    public static function canAccessClassroom(array $user, int $classroomId): bool
    {
        foreach (self::staffClassrooms($user) as $classroom) {
            if ((int) $classroom['id'] === $classroomId) {
                return true;
            }
        }
        return false;
    }

    /* ---------------- مستويات الأداء ---------------- */

    /** حدود المستويات من الإعدادات (نسبة مئوية) */
    public static function levelCutoffs(): array
    {
        return [
            'advanced'   => (float) Settings::get('nafes_level_advanced'),
            'proficient' => (float) Settings::get('nafes_level_proficient'),
            'basic'      => (float) Settings::get('nafes_level_basic'),
        ];
    }

    /** أسماء المستويات من الأعلى إلى الأدنى */
    public const LEVELS = [
        'advanced'   => 'متقدم',
        'proficient' => 'متمكن',
        'basic'      => 'أساسي',
        'below'      => 'دون الأساسي',
    ];

    /** مفتاح المستوى لنسبة معيّنة */
    public static function levelKey(?float $percent): string
    {
        if ($percent === null) {
            return 'below';
        }
        $cut = self::levelCutoffs();
        return match (true) {
            $percent >= $cut['advanced']   => 'advanced',
            $percent >= $cut['proficient'] => 'proficient',
            $percent >= $cut['basic']      => 'basic',
            default                        => 'below',
        };
    }

    public static function levelLabel(?float $percent): string
    {
        return self::LEVELS[self::levelKey($percent)];
    }

    /** شارة المستوى بلون مناسب */
    public static function levelBadge(?float $percent): string
    {
        if ($percent === null) {
            return '<span class="badge badge-gray">—</span>';
        }
        $key   = self::levelKey($percent);
        $class = match ($key) {
            'advanced'   => 'badge level-advanced',
            'proficient' => 'badge badge-green',
            'basic'      => 'badge badge-amber',
            default      => 'badge badge-red',
        };
        return '<span class="' . $class . '">' . h(self::LEVELS[$key]) . '</span>';
    }

    /** تنسيق النسبة: 87.5٪ */
    public static function pct(?float $value, int $decimals = 0): string
    {
        if ($value === null) {
            return '—';
        }
        return number_format($value, $decimals) . '٪';
    }
}
