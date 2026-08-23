<?php
declare(strict_types=1);

/** استيراد البيانات من ملفات CSV (المعلمون، الإسناد، الخطط) */
final class Importer
{
    /** مرادفات رؤوس الأعمدة المقبولة */
    private const ALIASES = [
        'name'       => ['الاسم', 'اسم المعلم', 'الاسم الكامل', 'name', 'teacher'],
        'username'   => ['اسم المستخدم', 'المستخدم', 'username', 'user'],
        'password'   => ['كلمة المرور', 'password'],
        'phone'      => ['الجوال', 'الهاتف', 'phone', 'mobile'],
        'email'      => ['البريد', 'البريد الإلكتروني', 'email'],
        'national_id'=> ['الهوية', 'رقم الهوية', 'national_id', 'id_number'],
        'job_title'  => ['المسمى الوظيفي', 'الوظيفة', 'job_title'],
        'role'       => ['الصلاحية', 'الدور', 'role'],
        'subject'    => ['المادة', 'اسم المادة', 'subject'],
        'grade'      => ['الصف', 'الصف الدراسي', 'grade'],
        'classroom'  => ['الفصل', 'الشعبة', 'class', 'classroom'],
        'week'       => ['الأسبوع', 'رقم الأسبوع', 'week'],
        'field'      => ['البند', 'العنصر', 'field'],
        'day1'       => ['الأحد', 'sunday'],
        'day2'       => ['الاثنين', 'الإثنين', 'monday'],
        'day3'       => ['الثلاثاء', 'tuesday'],
        'day4'       => ['الأربعاء', 'wednesday'],
        'day5'       => ['الخميس', 'thursday'],
    ];

    /** تحويل صفوف CSV إلى مصفوفات بمفاتيح موحّدة */
    public static function normalize(array $rows): array
    {
        if (!$rows) {
            return [];
        }
        $headers = array_map(static fn ($h) => self::canonical((string) $h), array_shift($rows));

        $out = [];
        foreach ($rows as $row) {
            $item = [];
            foreach ($headers as $index => $key) {
                if ($key !== '') {
                    $item[$key] = trim((string) ($row[$index] ?? ''));
                }
            }
            if (array_filter($item, static fn ($v) => $v !== '')) {
                $out[] = $item;
            }
        }
        return $out;
    }

    private static function canonical(string $header): string
    {
        $header = trim(str_replace(['ـ', '"', "'"], '', $header));
        $lower  = mb_strtolower($header);
        foreach (self::ALIASES as $key => $aliases) {
            foreach ($aliases as $alias) {
                if ($lower === mb_strtolower($alias)) {
                    return $key;
                }
            }
        }
        return '';
    }

    /* ------------------------- المعلمون ------------------------- */
    public static function teachers(array $rows): array
    {
        $result = ['created' => 0, 'updated' => 0, 'errors' => [], 'passwords' => []];
        $items  = self::normalize($rows);

        foreach ($items as $line => $item) {
            $name = $item['name'] ?? '';
            if ($name === '') {
                $result['errors'][] = 'السطر ' . ($line + 2) . ': الاسم مفقود.';
                continue;
            }

            $username = strtolower($item['username'] ?? '');
            if ($username === '') {
                $username = self::suggestUsername($name);
            }
            if (!preg_match('/^[a-z0-9_.-]{3,64}$/', $username)) {
                $result['errors'][] = 'السطر ' . ($line + 2) . ': اسم المستخدم غير صالح (' . $username . ').';
                continue;
            }

            $data = [
                'name'        => $name,
                'phone'       => ($item['phone'] ?? '') ?: null,
                'email'       => ($item['email'] ?? '') ?: null,
                'national_id' => ($item['national_id'] ?? '') ?: null,
                'job_title'   => ($item['job_title'] ?? '') ?: 'معلم',
                'role'        => (($item['role'] ?? '') === 'admin' || ($item['role'] ?? '') === 'إدارة') ? 'admin' : 'teacher',
            ];

            $existing = Database::first('SELECT id FROM users WHERE username = ?', [$username]);
            if ($existing) {
                Database::update('users', $data, 'id = :uid', ['uid' => $existing['id']]);
                $result['updated']++;
            } else {
                $plain = ($item['password'] ?? '') ?: random_password();
                Database::insert('users', $data + [
                    'username'             => $username,
                    'password_hash'        => password_hash($plain, PASSWORD_DEFAULT),
                    'is_active'            => 1,
                    'must_change_password' => 1,
                ]);
                $result['created']++;
                $result['passwords'][] = ['name' => $name, 'username' => $username, 'password' => $plain];
            }
        }

        Activity::log('import_teachers', 'users', null, 'created=' . $result['created'] . ' updated=' . $result['updated']);
        return $result;
    }

    private static function suggestUsername(string $name): string
    {
        $base = 't' . substr(md5($name), 0, 7);
        $i = 0;
        do {
            $candidate = $base . ($i > 0 ? (string) $i : '');
            $exists = Database::value('SELECT id FROM users WHERE username = ?', [$candidate]);
            $i++;
        } while ($exists);
        return $candidate;
    }

    /* ------------------------- الإسناد ------------------------- */
    public static function assignments(array $rows, bool $createMissing = true): array
    {
        $result = ['created' => 0, 'updated' => 0, 'errors' => []];
        $items  = self::normalize($rows);
        $yearId = (int) (Plans::activeYear()['id'] ?? 0);

        if (!$yearId) {
            $result['errors'][] = 'لا يوجد عام دراسي نشط.';
            return $result;
        }

        foreach ($items as $line => $item) {
            $row = $line + 2;
            $teacherName = $item['name'] ?? '';
            $username    = strtolower($item['username'] ?? '');
            $subjectName = $item['subject'] ?? '';
            $gradeName   = $item['grade'] ?? '';
            $className   = $item['classroom'] ?? '';

            if ($subjectName === '' || $gradeName === '' || $className === '') {
                $result['errors'][] = "السطر $row: المادة أو الصف أو الفصل مفقود.";
                continue;
            }

            $teacher = null;
            if ($username !== '') {
                $teacher = Database::first('SELECT id FROM users WHERE username = ?', [$username]);
            }
            if (!$teacher && $teacherName !== '') {
                $teacher = Database::first('SELECT id FROM users WHERE name = ?', [$teacherName]);
            }
            if (!$teacher) {
                $result['errors'][] = "السطر $row: لم يتم العثور على المعلم ($teacherName $username).";
                continue;
            }

            $subjectId = self::lookupId('subjects', 'name', $subjectName, $createMissing, ['sort_order' => 99]);
            $gradeId   = self::lookupId('grades', 'name', $gradeName, $createMissing, ['stage' => 'ابتدائي', 'sort_order' => 99]);
            if (!$subjectId || !$gradeId) {
                $result['errors'][] = "السطر $row: المادة أو الصف غير معرّف.";
                continue;
            }

            $classroomId = (int) Database::value('SELECT id FROM classrooms WHERE grade_id = ? AND name = ?', [$gradeId, $className], 0);
            if (!$classroomId) {
                if (!$createMissing) {
                    $result['errors'][] = "السطر $row: الفصل غير موجود.";
                    continue;
                }
                $classroomId = Database::insert('classrooms', ['grade_id' => $gradeId, 'name' => $className, 'sort_order' => 1, 'is_active' => 1]);
            }

            $existing = Database::value(
                'SELECT id FROM assignments WHERE subject_id = ? AND classroom_id = ? AND year_id = ?',
                [$subjectId, $classroomId, $yearId]
            );
            Database::run(
                'INSERT INTO assignments (teacher_id, subject_id, classroom_id, year_id) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id)',
                [$teacher['id'], $subjectId, $classroomId, $yearId]
            );
            $existing ? $result['updated']++ : $result['created']++;
        }

        Activity::log('import_assignments', 'assignments', null, 'created=' . $result['created'] . ' updated=' . $result['updated']);
        return $result;
    }

    private static function lookupId(string $table, string $column, string $value, bool $create, array $extra = []): int
    {
        $id = (int) Database::value("SELECT id FROM `$table` WHERE `$column` = ?", [$value], 0);
        if ($id || !$create) {
            return $id;
        }
        return Database::insert($table, [$column => $value] + $extra);
    }

    /* ------------------------- الخطط ------------------------- */
    public static function plans(array $rows, ?int $defaultWeekId = null, bool $approve = false): array
    {
        $result = ['plans' => 0, 'rows' => 0, 'errors' => []];
        $items  = self::normalize($rows);
        $term   = Plans::activeTerm();
        $admin  = Auth::id();

        // خريطة أسماء البنود -> أعمدة قاعدة البيانات
        $fieldMap = [];
        foreach (PLAN_FIELDS as $key => $label) {
            $fieldMap[$label] = $key;
            $fieldMap[$key]   = $key;
        }
        $fieldMap['الواجبات المنزلية'] = 'homework';
        $fieldMap['الواجبات']          = 'homework';
        $fieldMap['ملاحظات']           = 'notes';

        $touched = [];

        foreach ($items as $line => $item) {
            $row = $line + 2;

            // تحديد الأسبوع
            $weekId = $defaultWeekId;
            if (($item['week'] ?? '') !== '' && $term) {
                $weekNo = (int) preg_replace('/\D+/', '', $item['week']);
                $found  = $weekNo ? Database::value('SELECT id FROM weeks WHERE term_id = ? AND week_no = ?', [$term['id'], $weekNo]) : null;
                if ($found) {
                    $weekId = (int) $found;
                }
            }
            if (!$weekId) {
                $result['errors'][] = "السطر $row: تعذر تحديد الأسبوع.";
                continue;
            }

            $gradeId = (int) Database::value('SELECT id FROM grades WHERE name = ?', [$item['grade'] ?? ''], 0);
            $classroomId = $gradeId
                ? (int) Database::value('SELECT id FROM classrooms WHERE grade_id = ? AND name = ?', [$gradeId, $item['classroom'] ?? ''], 0)
                : 0;
            $subjectId = (int) Database::value('SELECT id FROM subjects WHERE name = ?', [$item['subject'] ?? ''], 0);

            if (!$classroomId || !$subjectId) {
                $result['errors'][] = "السطر $row: الفصل أو المادة غير موجود ({$item['grade']} / {$item['classroom']} / {$item['subject']}).";
                continue;
            }

            $field = $fieldMap[$item['field'] ?? ''] ?? null;
            if ($field === null) {
                $result['errors'][] = "السطر $row: البند غير معروف ({$item['field']}).";
                continue;
            }

            // المعلم المسند لهذه المادة في هذا الفصل
            $teacherId = (int) Database::value(
                'SELECT teacher_id FROM assignments WHERE subject_id = ? AND classroom_id = ? AND year_id = ?',
                [$subjectId, $classroomId, (int) (Plans::activeYear()['id'] ?? 0)],
                0
            );
            if (!$teacherId) {
                $result['errors'][] = "السطر $row: لا يوجد معلم مسند لهذه المادة في هذا الفصل.";
                continue;
            }

            $plan   = Plans::findOrCreate($weekId, $classroomId, $subjectId, $teacherId);
            $planId = (int) $plan['id'];

            foreach (array_keys(WEEK_DAYS) as $dayIndex) {
                $value = $item['day' . $dayIndex] ?? '';
                $existing = Database::value('SELECT id FROM plan_days WHERE plan_id = ? AND day_index = ?', [$planId, $dayIndex]);
                if ($existing) {
                    Database::update('plan_days', [$field => $value], 'id = :pd', ['pd' => $existing]);
                } else {
                    Database::insert('plan_days', ['plan_id' => $planId, 'day_index' => $dayIndex, $field => $value]);
                }
            }

            $touched[$planId] = true;
            $result['rows']++;
        }

        foreach (array_keys($touched) as $planId) {
            if ($approve && $admin) {
                Database::run(
                    "UPDATE plans SET status = 'approved', submitted_at = COALESCE(submitted_at, NOW()),
                            reviewed_by = ?, reviewed_at = NOW() WHERE id = ?",
                    [$admin, $planId]
                );
            }
        }
        $result['plans'] = count($touched);

        Activity::log('import_plans', 'plans', null, 'plans=' . $result['plans'] . ' rows=' . $result['rows']);
        return $result;
    }
}
