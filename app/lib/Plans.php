<?php
declare(strict_types=1);

/** منطق الخطط الأسبوعية: الإنشاء، الحفظ، الاعتماد، المتابعة */
final class Plans
{
    /* ---------------- الأعوام والفصول والأسابيع ---------------- */

    public static function activeYear(): ?array
    {
        return Database::first('SELECT * FROM academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1')
            ?? Database::first('SELECT * FROM academic_years ORDER BY id DESC LIMIT 1');
    }

    public static function activeTerm(): ?array
    {
        $year = self::activeYear();
        if (!$year) {
            return null;
        }
        return Database::first('SELECT * FROM terms WHERE year_id = ? AND is_active = 1 ORDER BY term_no LIMIT 1', [$year['id']])
            ?? Database::first('SELECT * FROM terms WHERE year_id = ? ORDER BY term_no LIMIT 1', [$year['id']]);
    }

    public static function weeks(?int $termId = null): array
    {
        $termId ??= (int) (self::activeTerm()['id'] ?? 0);
        if (!$termId) {
            return [];
        }
        return Database::all('SELECT * FROM weeks WHERE term_id = ? ORDER BY week_no', [$termId]);
    }

    public static function week(int $weekId): ?array
    {
        return Database::first(
            'SELECT w.*, t.name AS term_name, t.term_no, y.name AS year_name, y.id AS year_id
               FROM weeks w
               JOIN terms t ON t.id = w.term_id
               JOIN academic_years y ON y.id = t.year_id
              WHERE w.id = ?',
            [$weekId]
        );
    }

    /** أسبوع اليوم الحالي، أو أقرب أسبوع في الفصل النشط */
    public static function currentWeek(): ?array
    {
        $term = self::activeTerm();
        if (!$term) {
            return null;
        }
        $today = date('Y-m-d');

        $week = Database::first(
            'SELECT * FROM weeks WHERE term_id = ? AND ? BETWEEN start_date AND end_date LIMIT 1',
            [$term['id'], $today]
        );
        $week ??= Database::first(
            'SELECT * FROM weeks WHERE term_id = ? AND end_date >= ? ORDER BY start_date LIMIT 1',
            [$term['id'], $today]
        );
        $week ??= Database::first('SELECT * FROM weeks WHERE term_id = ? ORDER BY week_no DESC LIMIT 1', [$term['id']]);

        return $week ? self::week((int) $week['id']) : null;
    }

    /** توليد أسابيع الفصل الدراسي بدءاً من تاريخ الأحد */
    public static function generateWeeks(int $termId, string $firstSunday, int $count, bool $replace = false): int
    {
        if ($replace) {
            Database::delete('weeks', 'term_id = ?', [$termId]);
        }
        $start   = new DateTimeImmutable($firstSunday);
        $created = 0;

        for ($i = 0; $i < $count; $i++) {
            $weekStart = $start->modify('+' . ($i * 7) . ' day');
            $weekEnd   = $weekStart->modify('+4 day');
            $exists = Database::value('SELECT id FROM weeks WHERE term_id = ? AND week_no = ?', [$termId, $i + 1]);
            $data = [
                'term_id'     => $termId,
                'week_no'     => $i + 1,
                'title'       => 'الأسبوع ' . week_ordinal($i + 1),
                'start_date'  => $weekStart->format('Y-m-d'),
                'end_date'    => $weekEnd->format('Y-m-d'),
                'hijri_start' => Hijri::long($weekStart->format('Y-m-d')),
                'hijri_end'   => Hijri::long($weekEnd->format('Y-m-d')),
            ];
            if ($exists) {
                Database::update('weeks', $data, 'id = :wid', ['wid' => $exists]);
            } else {
                Database::insert('weeks', $data);
            }
            $created++;
        }
        return $created;
    }

    /* ---------------- الفصول والإسناد ---------------- */

    public static function classrooms(): array
    {
        return Database::all(
            'SELECT c.*, g.name AS grade_name, g.stage, g.sort_order AS grade_order
               FROM classrooms c
               JOIN grades g ON g.id = c.grade_id
              WHERE c.is_active = 1
           ORDER BY g.sort_order, g.name, c.sort_order, c.name'
        );
    }

    public static function classroom(int $id): ?array
    {
        return Database::first(
            'SELECT c.*, g.name AS grade_name, g.stage
               FROM classrooms c JOIN grades g ON g.id = c.grade_id
              WHERE c.id = ?',
            [$id]
        );
    }

    public static function classLabel(array $classroom): string
    {
        return trim(($classroom['grade_name'] ?? '') . ' / ' . ($classroom['name'] ?? ''));
    }

    /** إسناد المعلم: المواد والفصول التي يدرّسها */
    public static function teacherAssignments(int $teacherId, ?int $yearId = null): array
    {
        $yearId ??= (int) (self::activeYear()['id'] ?? 0);
        return Database::all(
            'SELECT a.*, s.name AS subject_name, s.sort_order AS subject_order,
                    c.name AS class_name, g.name AS grade_name, g.sort_order AS grade_order
               FROM assignments a
               JOIN subjects s   ON s.id = a.subject_id
               JOIN classrooms c ON c.id = a.classroom_id
               JOIN grades g     ON g.id = c.grade_id
              WHERE a.teacher_id = ? AND a.year_id = ?
           ORDER BY g.sort_order, c.sort_order, s.sort_order',
            [$teacherId, $yearId]
        );
    }

    /** مواد فصل معين مع معلميها */
    public static function classroomSubjects(int $classroomId, ?int $yearId = null): array
    {
        $yearId ??= (int) (self::activeYear()['id'] ?? 0);
        return Database::all(
            'SELECT a.id AS assignment_id, a.subject_id, a.teacher_id,
                    s.name AS subject_name, s.sort_order,
                    u.name AS teacher_name
               FROM assignments a
               JOIN subjects s ON s.id = a.subject_id
               JOIN users u    ON u.id = a.teacher_id
              WHERE a.classroom_id = ? AND a.year_id = ?
           ORDER BY s.sort_order, s.name',
            [$classroomId, $yearId]
        );
    }

    /* ---------------- الخطط ---------------- */

    public static function find(int $weekId, int $classroomId, int $subjectId): ?array
    {
        return Database::first(
            'SELECT * FROM plans WHERE week_id = ? AND classroom_id = ? AND subject_id = ?',
            [$weekId, $classroomId, $subjectId]
        );
    }

    public static function findOrCreate(int $weekId, int $classroomId, int $subjectId, int $teacherId): array
    {
        $plan = self::find($weekId, $classroomId, $subjectId);
        if ($plan) {
            return $plan;
        }
        $id = Database::insert('plans', [
            'week_id'      => $weekId,
            'classroom_id' => $classroomId,
            'subject_id'   => $subjectId,
            'teacher_id'   => $teacherId,
            'status'       => 'draft',
        ]);
        return Database::first('SELECT * FROM plans WHERE id = ?', [$id]) ?? [];
    }

    public static function withDetails(int $planId): ?array
    {
        $plan = Database::first(
            'SELECT p.*, s.name AS subject_name, u.name AS teacher_name,
                    c.name AS class_name, g.name AS grade_name,
                    w.week_no, w.title AS week_title, w.start_date, w.end_date,
                    w.hijri_start, w.hijri_end, w.status AS week_status,
                    r.name AS reviewer_name
               FROM plans p
               JOIN subjects s   ON s.id = p.subject_id
               JOIN users u      ON u.id = p.teacher_id
               JOIN classrooms c ON c.id = p.classroom_id
               JOIN grades g     ON g.id = c.grade_id
               JOIN weeks w      ON w.id = p.week_id
          LEFT JOIN users r      ON r.id = p.reviewed_by
              WHERE p.id = ?',
            [$planId]
        );
        if (!$plan) {
            return null;
        }
        $plan['days'] = self::days((int) $plan['id']);
        return $plan;
    }

    /** أيام الخطة مرتبة 1..5 مع قيم فارغة افتراضية */
    public static function days(int $planId): array
    {
        $rows = Database::all('SELECT * FROM plan_days WHERE plan_id = ? ORDER BY day_index', [$planId]);
        $byIndex = [];
        foreach ($rows as $row) {
            $byIndex[(int) $row['day_index']] = $row;
        }
        $days = [];
        foreach (array_keys(WEEK_DAYS) as $index) {
            $days[$index] = $byIndex[$index] ?? [
                'day_index' => $index, 'topic' => '', 'objective' => '', 'homework' => '', 'notes' => '',
            ];
        }
        return $days;
    }

    /** حفظ محتوى الأيام. $input = [day_index => ['topic'=>..,'objective'=>..,...]] */
    public static function saveDays(int $planId, array $input): void
    {
        Database::transaction(static function () use ($planId, $input): void {
            foreach (array_keys(WEEK_DAYS) as $index) {
                $row = $input[$index] ?? [];
                $values = [];
                foreach (array_keys(PLAN_FIELDS) as $field) {
                    $values[$field] = mb_substr(trim((string) ($row[$field] ?? '')), 0, 2000);
                }
                $existing = Database::value('SELECT id FROM plan_days WHERE plan_id = ? AND day_index = ?', [$planId, $index]);
                if ($existing) {
                    Database::update('plan_days', $values, 'id = :pd_id', ['pd_id' => $existing]);
                } else {
                    Database::insert('plan_days', $values + ['plan_id' => $planId, 'day_index' => $index]);
                }
            }
            Database::run('UPDATE plans SET updated_at = NOW() WHERE id = ?', [$planId]);
        });
    }

    /** هل الخطة تحتوي على أي محتوى؟ */
    public static function hasContent(int $planId): bool
    {
        return (int) Database::value(
            "SELECT COUNT(*) FROM plan_days
              WHERE plan_id = ?
                AND (COALESCE(topic,'') <> '' OR COALESCE(objective,'') <> ''
                  OR COALESCE(homework,'') <> '' OR COALESCE(notes,'') <> '')",
            [$planId],
            0
        ) > 0;
    }

    public static function submit(int $planId): bool
    {
        if (!self::hasContent($planId)) {
            return false;
        }
        Database::run(
            "UPDATE plans SET status = 'submitted', submitted_at = NOW(), review_note = NULL,
                              reviewed_by = NULL, reviewed_at = NULL
              WHERE id = ?",
            [$planId]
        );
        Activity::log('plan_submit', 'plans', $planId);
        return true;
    }

    public static function approve(int $planId, int $reviewerId, ?string $note = null): void
    {
        Database::run(
            "UPDATE plans SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), review_note = ?
              WHERE id = ?",
            [$reviewerId, $note, $planId]
        );
        Activity::log('plan_approve', 'plans', $planId, $note);
    }

    public static function reject(int $planId, int $reviewerId, string $note): void
    {
        Database::run(
            "UPDATE plans SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), review_note = ?
              WHERE id = ?",
            [$reviewerId, $note, $planId]
        );
        Activity::log('plan_reject', 'plans', $planId, $note);
    }

    /** إرجاع الخطة إلى المعلم كمسودة */
    public static function reopen(int $planId): void
    {
        Database::run("UPDATE plans SET status = 'draft', reviewed_by = NULL, reviewed_at = NULL WHERE id = ?", [$planId]);
        Activity::log('plan_reopen', 'plans', $planId);
    }

    /** نسخ محتوى خطة من أسبوع سابق لنفس المادة والفصل */
    public static function copyFromWeek(int $targetPlanId, int $sourceWeekId): bool
    {
        $target = Database::first('SELECT * FROM plans WHERE id = ?', [$targetPlanId]);
        if (!$target) {
            return false;
        }
        $source = self::find($sourceWeekId, (int) $target['classroom_id'], (int) $target['subject_id']);
        if (!$source) {
            return false;
        }
        $days = self::days((int) $source['id']);
        $input = [];
        foreach ($days as $index => $day) {
            $input[$index] = [
                'topic'     => $day['topic'],
                'objective' => $day['objective'],
                'homework'  => $day['homework'],
                'notes'     => $day['notes'],
            ];
        }
        self::saveDays($targetPlanId, $input);
        return true;
    }

    /* ---------------- ورقة الفصل الأسبوعية ---------------- */

    /**
     * صفوف ورقة الخطة لفصل معين في أسبوع معين.
     * كل صف: المادة + المعلم + الحالة + أيام الأسبوع.
     * (استعلامان فقط مهما بلغ عدد المواد)
     */
    public static function classSheet(int $weekId, int $classroomId, bool $onlyApproved = false): array
    {
        $subjects = self::classroomSubjects($classroomId);
        if (!$subjects) {
            return [];
        }

        // خطط هذا الفصل في هذا الأسبوع
        $plans = [];
        foreach (Database::all('SELECT * FROM plans WHERE week_id = ? AND classroom_id = ?', [$weekId, $classroomId]) as $plan) {
            $plans[(int) $plan['subject_id']] = $plan;
        }

        // أيام كل الخطط دفعة واحدة
        $daysByPlan = [];
        if ($plans) {
            $ids = array_map(static fn ($p) => (int) $p['id'], $plans);
            $in  = implode(',', array_fill(0, count($ids), '?'));
            foreach (Database::all("SELECT * FROM plan_days WHERE plan_id IN ($in)", array_values($ids)) as $day) {
                $daysByPlan[(int) $day['plan_id']][(int) $day['day_index']] = $day;
            }
        }

        $empty = [];
        foreach (array_keys(WEEK_DAYS) as $index) {
            $empty[$index] = ['day_index' => $index, 'topic' => '', 'objective' => '', 'homework' => '', 'notes' => ''];
        }

        $rows = [];
        foreach ($subjects as $subject) {
            $plan    = $plans[(int) $subject['subject_id']] ?? null;
            $visible = $plan && (!$onlyApproved || $plan['status'] === 'approved');
            $days    = $empty;

            if ($visible) {
                foreach ($daysByPlan[(int) $plan['id']] ?? [] as $index => $day) {
                    $days[$index] = $day;
                }
            }

            $rows[] = [
                'subject_id'   => (int) $subject['subject_id'],
                'subject_name' => $subject['subject_name'],
                'teacher_id'   => (int) $subject['teacher_id'],
                'teacher_name' => $subject['teacher_name'],
                'plan_id'      => $plan ? (int) $plan['id'] : null,
                'status'       => $plan['status'] ?? null,
                'filled'       => (bool) $visible,
                'days'         => $days,
            ];
        }
        return $rows;
    }

    /** حالة التعبئة لفصل: [المكتملة، الإجمالي، أسماء المتأخرين] */
    public static function completion(int $weekId, int $classroomId, bool $approvedOnly = false, ?int $yearId = null): array
    {
        $yearId ??= (int) (self::activeYear()['id'] ?? 0);
        $statuses = $approvedOnly ? "'approved'" : "'submitted','approved'";

        $rows = Database::all(
            "SELECT s.name AS subject_name, u.id AS teacher_id, u.name AS teacher_name,
                    CASE WHEN p.status IN ($statuses) THEN 1 ELSE 0 END AS done
               FROM assignments a
               JOIN subjects s ON s.id = a.subject_id
               JOIN users u    ON u.id = a.teacher_id
          LEFT JOIN plans p    ON p.week_id = ? AND p.classroom_id = a.classroom_id AND p.subject_id = a.subject_id
              WHERE a.classroom_id = ? AND a.year_id = ?
           ORDER BY s.sort_order, s.name",
            [$weekId, $classroomId, $yearId]
        );

        $done = 0;
        $late = [];
        foreach ($rows as $row) {
            if ((int) $row['done'] === 1) {
                $done++;
            } else {
                $late[] = ['teacher_id' => (int) $row['teacher_id'], 'teacher_name' => $row['teacher_name'], 'subject_name' => $row['subject_name']];
            }
        }

        return ['done' => $done, 'total' => count($rows), 'late' => $late];
    }

    /** لوحة المتابعة: حالة كل فصل في أسبوع (استعلامان فقط) */
    public static function followUpClasses(int $weekId, ?int $yearId = null): array
    {
        $yearId ??= (int) (self::activeYear()['id'] ?? 0);

        $classes = Database::all(
            "SELECT c.id, c.name, g.name AS grade_name, g.stage,
                    COUNT(a.id) AS total,
                    SUM(CASE WHEN p.status IN ('submitted','approved') THEN 1 ELSE 0 END) AS done
               FROM classrooms c
               JOIN grades g      ON g.id = c.grade_id
               JOIN assignments a ON a.classroom_id = c.id AND a.year_id = :year
          LEFT JOIN plans p       ON p.week_id = :week AND p.classroom_id = c.id AND p.subject_id = a.subject_id
              WHERE c.is_active = 1
           GROUP BY c.id, c.name, g.name, g.stage, g.sort_order, c.sort_order
           ORDER BY g.sort_order, c.sort_order, c.name",
            ['year' => $yearId, 'week' => $weekId]
        );

        // المتأخرون في كل فصل
        $late = [];
        foreach (Database::all(
            "SELECT a.classroom_id, s.name AS subject_name, u.id AS teacher_id, u.name AS teacher_name
               FROM assignments a
               JOIN subjects s ON s.id = a.subject_id
               JOIN users u    ON u.id = a.teacher_id
          LEFT JOIN plans p    ON p.week_id = :week AND p.classroom_id = a.classroom_id AND p.subject_id = a.subject_id
              WHERE a.year_id = :year AND (p.id IS NULL OR p.status NOT IN ('submitted','approved'))
           ORDER BY s.sort_order",
            ['year' => $yearId, 'week' => $weekId]
        ) as $row) {
            $late[(int) $row['classroom_id']][] = [
                'teacher_id'   => (int) $row['teacher_id'],
                'teacher_name' => $row['teacher_name'],
                'subject_name' => $row['subject_name'],
            ];
        }

        $result = [];
        foreach ($classes as $class) {
            $result[] = $class + [
                'total' => (int) $class['total'],
                'done'  => (int) $class['done'],
                'late'  => $late[(int) $class['id']] ?? [],
            ];
        }
        return $result;
    }

    /** لوحة المتابعة: حالة كل معلم في أسبوع */
    public static function followUpTeachers(int $weekId, ?int $yearId = null): array
    {
        $yearId ??= (int) (self::activeYear()['id'] ?? 0);

        return Database::all(
            "SELECT u.id, u.name, u.phone,
                    COUNT(a.id) AS total,
                    SUM(CASE WHEN p.status IN ('submitted','approved') THEN 1 ELSE 0 END) AS done,
                    SUM(CASE WHEN p.status = 'approved'  THEN 1 ELSE 0 END) AS approved,
                    SUM(CASE WHEN p.status = 'submitted' THEN 1 ELSE 0 END) AS waiting,
                    SUM(CASE WHEN p.status = 'rejected'  THEN 1 ELSE 0 END) AS rejected
               FROM users u
               JOIN assignments a ON a.teacher_id = u.id AND a.year_id = :year
          LEFT JOIN plans p ON p.week_id = :week
                           AND p.classroom_id = a.classroom_id
                           AND p.subject_id = a.subject_id
              WHERE u.role = 'teacher' AND u.is_active = 1
           GROUP BY u.id, u.name, u.phone
           ORDER BY (COUNT(a.id) - SUM(CASE WHEN p.status IN ('submitted','approved') THEN 1 ELSE 0 END)) DESC, u.name",
            ['year' => $yearId, 'week' => $weekId]
        );
    }

    /** إحصاءات سريعة لأسبوع */
    public static function weekStats(int $weekId): array
    {
        $row = Database::first(
            "SELECT
                COUNT(*) AS total,
                SUM(status = 'draft')     AS draft,
                SUM(status = 'submitted') AS submitted,
                SUM(status = 'approved')  AS approved,
                SUM(status = 'rejected')  AS rejected
             FROM plans WHERE week_id = ?",
            [$weekId]
        ) ?: [];

        return array_map(static fn ($v) => (int) $v, $row + ['total' => 0, 'draft' => 0, 'submitted' => 0, 'approved' => 0, 'rejected' => 0]);
    }

    /** رسالة إلى الأسرة لورقة الفصل */
    public static function familyMessage(int $weekId, int $classroomId): string
    {
        $value = Database::value(
            'SELECT family_message FROM class_week_meta WHERE week_id = ? AND classroom_id = ?',
            [$weekId, $classroomId]
        );
        $value = is_string($value) ? trim($value) : '';
        return $value !== '' ? $value : Settings::get('family_message');
    }

    public static function saveFamilyMessage(int $weekId, int $classroomId, string $message): void
    {
        Database::run(
            'INSERT INTO class_week_meta (week_id, classroom_id, family_message)
             VALUES (:w, :c, :m)
             ON DUPLICATE KEY UPDATE family_message = VALUES(family_message)',
            ['w' => $weekId, 'c' => $classroomId, 'm' => $message]
        );
    }

    /** الفصول التي لديها خطط معتمدة في أسبوع (للواجهة العامة) */
    public static function classroomsWithApproved(int $weekId): array
    {
        return Database::all(
            "SELECT c.id, c.name AS class_name, g.name AS grade_name, g.sort_order,
                    COUNT(p.id) AS approved_count
               FROM plans p
               JOIN classrooms c ON c.id = p.classroom_id
               JOIN grades g     ON g.id = c.grade_id
              WHERE p.week_id = ? AND p.status = 'approved'
           GROUP BY c.id, c.name, g.name, g.sort_order
           ORDER BY g.sort_order, c.sort_order, c.name",
            [$weekId]
        );
    }
}
