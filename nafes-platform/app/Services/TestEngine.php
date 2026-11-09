<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Models\Student;
use App\Models\Year;
use PDOException;

/**
 * محرك الاختبارات: الإنشاء، الأسئلة المجمدة، التوليد بالمخطط، الإسناد، المحاولات، الحفظ، التسليم والتصحيح.
 */
final class TestEngine
{
    public const TYPES = [
        'diagnostic'   => 'تشخيصي',
        'training'     => 'تدريبي',
        'mock'         => 'محاكي شامل',
        'single_skill' => 'مهارة واحدة',
        'remedial'     => 'علاجي',
        'enrichment'   => 'إثرائي',
        'homework'     => 'واجب إلكتروني',
        'post'         => 'بعدي',
        'competition'  => 'مسابقة',
        'practice'     => 'تدريب ذاتي',
    ];

    public const STATUSES = [
        'draft'     => ['مسودة', 'secondary'],
        'published' => ['منشور', 'success'],
        'closed'    => ['مغلق', 'warning'],
        'archived'  => ['مؤرشف', 'secondary'],
    ];

    public const VISIBILITY = [
        'immediate'   => 'فور التسليم',
        'after_close' => 'بعد إغلاق الاختبار',
        'manual'      => 'عند إعلان المعلم',
    ];

    /** مهلة الخادم بعد انتهاء الوقت لاستقبال آخر حفظ (تأخر الشبكة) */
    public const GRACE_SECONDS = 30;

    /* ======================= الاختبار ======================= */

    public static function find(int $id): ?array
    {
        $test = DB::first(
            'SELECT t.*, s.name AS subject_name, g.name AS grade_name, u.full_name AS author_name, y.name AS year_name,
                    (SELECT COUNT(*) FROM test_questions tq WHERE tq.test_id = t.id) AS questions_count
               FROM tests t JOIN grades g ON g.id = t.grade_id JOIN academic_years y ON y.id = t.academic_year_id
          LEFT JOIN subjects s ON s.id = t.subject_id LEFT JOIN users u ON u.id = t.created_by
              WHERE t.id = ?',
            [$id]
        );
        if ($test) {
            $test['class_ids'] = array_map('intval', DB::column('SELECT class_id FROM test_assignments WHERE test_id = ? AND class_id IS NOT NULL', [$id]));
            $test['student_ids'] = array_map('intval', DB::column('SELECT student_id FROM test_assignments WHERE test_id = ? AND student_id IS NOT NULL', [$id]));
        }
        return $test;
    }

    public static function canManage(array $test): bool
    {
        return Auth::can('tests.manage_any') || (Auth::can('tests.create') && (int) $test['created_by'] === Auth::id());
    }

    /** المعلم يطّلع على اختبارات فصوله وما أنشأه */
    public static function canView(array $test): bool
    {
        if (Scope::all() || self::canManage($test)) {
            return true;
        }
        $classes = Scope::classIds();
        if (array_intersect($classes, $test['class_ids'])) {
            return true;
        }
        foreach ($test['student_ids'] as $sid) {
            if (Scope::canStudent($sid)) {
                return true;
            }
        }
        return false;
    }

    /** إنشاء/تعديل إعدادات الاختبار — يُعيد [id, errors] */
    public static function save(?int $id, array $d): array
    {
        $errors = [];
        if (trim((string) ($d['title'] ?? '')) === '') {
            $errors[] = 'اسم الاختبار مطلوب.';
        }
        if (!isset(self::TYPES[$d['type'] ?? '']) || ($d['type'] ?? '') === 'practice') {
            $errors[] = 'نوع الاختبار غير صالح.';
        }
        if (empty($d['grade_id'])) {
            $errors[] = 'الصف مطلوب.';
        }
        if ($d['type'] !== 'mock' && empty($d['subject_id'])) {
            $errors[] = 'المادة مطلوبة (يمكن ترك المادة فارغة في الاختبار المحاكي الشامل فقط).';
        }
        $allowedSubjects = Scope::subjectIds();
        if ($allowedSubjects !== null && !empty($d['subject_id']) && !in_array((int) $d['subject_id'], $allowedSubjects, true)) {
            $errors[] = 'المادة غير مسندة إليك.';
        }
        if (!empty($d['available_from']) && !empty($d['available_until']) && strtotime($d['available_until']) <= strtotime($d['available_from'])) {
            $errors[] = 'موعد الإغلاق يجب أن يكون بعد موعد الإتاحة.';
        }
        if ((int) ($d['duration_minutes'] ?? 0) < 0 || (int) ($d['duration_minutes'] ?? 0) > 600) {
            $errors[] = 'المدة يجب أن تكون بين 0 و600 دقيقة.';
        }
        if (!isset(self::VISIBILITY[$d['result_visibility'] ?? ''])) {
            $errors[] = 'خيار إظهار النتيجة غير صالح.';
        }
        if ($errors) {
            return [null, $errors];
        }

        $row = [
            'title' => trim($d['title']), 'description' => trim((string) ($d['description'] ?? '')) ?: null, 'type' => $d['type'],
            'grade_id' => (int) $d['grade_id'], 'subject_id' => !empty($d['subject_id']) ? (int) $d['subject_id'] : null,
            'duration_minutes' => (int) $d['duration_minutes'], 'available_from' => $d['available_from'] ?: null,
            'available_until' => $d['available_until'] ?: null, 'max_attempts' => max(0, min(20, (int) $d['max_attempts'])),
            'shuffle_questions' => !empty($d['shuffle_questions']) ? 1 : 0, 'shuffle_options' => !empty($d['shuffle_options']) ? 1 : 0,
            'result_visibility' => $d['result_visibility'], 'allow_review' => !empty($d['allow_review']) ? 1 : 0,
            'show_explanations' => !empty($d['show_explanations']) ? 1 : 0,
        ];
        if ($id) {
            $current = self::find($id);
            // تغيير الصف أو المادة يزيل الأسئلة غير المطابقة (فقط قبل وجود محاولات)
            if (((int) $current['grade_id'] !== $row['grade_id'] || (int) $current['subject_id'] !== (int) $row['subject_id']) && self::hasAttempts($id)) {
                return [null, ['لا يمكن تغيير الصف أو المادة بعد بدء الطلاب في الاختبار.']];
            }
            DB::update('tests', $row, 'id = :id', ['id' => $id]);
            DB::run('DELETE tq FROM test_questions tq JOIN questions q ON q.id = tq.question_id
                      WHERE tq.test_id = ? AND (q.grade_id <> ? OR (? IS NOT NULL AND q.subject_id <> ?))',
                [$id, $row['grade_id'], $row['subject_id'], $row['subject_id']]);
            self::recalcTotal($id);
        } else {
            $id = DB::insert('tests', $row + ['academic_year_id' => Year::currentId(), 'created_by' => Auth::id(), 'status' => 'draft',
                                               'remedial_plan_id' => $d['remedial_plan_id'] ?? null]);
        }
        return [$id, []];
    }

    public static function hasAttempts(int $testId): bool
    {
        return (bool) DB::value('SELECT 1 FROM test_attempts WHERE test_id = ? LIMIT 1', [$testId]);
    }

    /* ======================= الأسئلة ======================= */

    public static function questions(int $testId): array
    {
        $rows = DB::all(
            'SELECT tq.*, sk.name AS skill_name, sk.code AS skill_code, s.name AS subject_name,
                    (SELECT q.version FROM questions q WHERE q.id = tq.question_id) AS bank_version
               FROM test_questions tq LEFT JOIN skills sk ON sk.id = tq.skill_id JOIN subjects s ON s.id = tq.subject_id
              WHERE tq.test_id = ? ORDER BY tq.sort_order, tq.id',
            [$testId]
        );
        foreach ($rows as &$r) {
            $r['snap'] = json_decode($r['snapshot'], true);
        }
        return $rows;
    }

    /** إضافة أسئلة منشورة كنسخ مجمدة — تُعيد عدد المضاف */
    public static function addQuestions(int $testId, array $questionIds): int
    {
        $test = self::find($testId);
        $order = (int) DB::value('SELECT COALESCE(MAX(sort_order), 0) FROM test_questions WHERE test_id = ?', [$testId], 0);
        $added = 0;
        foreach (array_unique(array_map('intval', $questionIds)) as $qid) {
            $q = DB::first("SELECT id, grade_id, subject_id, points FROM questions WHERE id = ? AND status = 'published'", [$qid]);
            if (!$q || (int) $q['grade_id'] !== (int) $test['grade_id'] || ($test['subject_id'] && (int) $q['subject_id'] !== (int) $test['subject_id'])) {
                continue;
            }
            $snap = QuestionBank::snapshot($qid);
            try {
                DB::insert('test_questions', [
                    'test_id' => $testId, 'question_id' => $qid, 'question_version' => $snap['version'], 'subject_id' => $snap['subject_id'],
                    'domain_id' => $snap['domain_id'], 'skill_id' => $snap['skill_id'], 'type' => $snap['type'],
                    'snapshot' => json_encode($snap, JSON_UNESCAPED_UNICODE), 'points' => $q['points'], 'sort_order' => ++$order,
                ]);
                $added++;
            } catch (PDOException) {
                // السؤال مضاف مسبقاً
            }
        }
        self::recalcTotal($testId);
        return $added;
    }

    public static function removeQuestions(int $testId, array $tqIds): void
    {
        if ($tqIds) {
            [$in, $p] = DB::in(array_map('intval', $tqIds));
            DB::run("DELETE FROM test_questions WHERE test_id = ? AND id IN ($in)", array_merge([$testId], $p));
            self::recalcTotal($testId);
        }
    }

    /** تحديث نسخة سؤال في اختبار لم تبدأ محاولاته إلى نسخة البنك الحالية */
    public static function refreshQuestion(int $testId, int $tqId): bool
    {
        $tq = DB::first('SELECT * FROM test_questions WHERE id = ? AND test_id = ?', [$tqId, $testId]);
        if (!$tq || !$tq['question_id'] || DB::value('SELECT 1 FROM attempt_answers WHERE test_question_id = ? LIMIT 1', [$tqId])) {
            return false;
        }
        $snap = QuestionBank::snapshot((int) $tq['question_id']);
        DB::update('test_questions', ['snapshot' => json_encode($snap, JSON_UNESCAPED_UNICODE), 'question_version' => $snap['version'],
            'skill_id' => $snap['skill_id'], 'domain_id' => $snap['domain_id'], 'type' => $snap['type']], 'id = :id', ['id' => $tqId]);
        return true;
    }

    public static function setPoints(int $testId, array $points): void
    {
        foreach ($points as $tqId => $value) {
            $v = round(max(0.25, min(100, (float) $value)), 2);
            DB::update('test_questions', ['points' => $v], 'id = :id AND test_id = :tid', ['id' => (int) $tqId, 'tid' => $testId]);
        }
        self::recalcTotal($testId);
    }

    public static function move(int $testId, int $tqId, int $dir): void
    {
        $ids = array_map('intval', DB::column('SELECT id FROM test_questions WHERE test_id = ? ORDER BY sort_order, id', [$testId]));
        $pos = array_search($tqId, $ids, true);
        $to = $pos === false ? -1 : $pos + ($dir < 0 ? -1 : 1);
        if ($to < 0 || $to >= count($ids)) {
            return;
        }
        [$ids[$pos], $ids[$to]] = [$ids[$to], $ids[$pos]];
        foreach ($ids as $i => $id) {
            DB::update('test_questions', ['sort_order' => $i + 1], 'id = :id', ['id' => $id]);
        }
    }

    public static function recalcTotal(int $testId): void
    {
        DB::run('UPDATE tests SET total_points = (SELECT COALESCE(SUM(points), 0) FROM test_questions WHERE test_id = ?) WHERE id = ?', [$testId, $testId]);
    }

    /**
     * مولّد الاختبار: $rows = [['skill' => id|0, 'domain' => id|0, 'count' => n, 'difficulty' => 0..3, 'cognitive' => ''], ...]
     * يسحب أسئلة منشورة عشوائياً غير مضافة. يُعيد [المضاف, ملاحظات النقص]
     */
    public static function generate(int $testId, array $rows): array
    {
        $test = self::find($testId);
        $existing = array_map('intval', DB::column('SELECT question_id FROM test_questions WHERE test_id = ? AND question_id IS NOT NULL', [$testId]));
        $picked = [];
        $notes = [];
        foreach ($rows as $row) {
            $count = max(0, min(50, (int) ($row['count'] ?? 0)));
            if (!$count) {
                continue;
            }
            [$where, $params] = QuestionBank::filterSql([
                'grade' => $test['grade_id'], 'subject' => $test['subject_id'] ?: ($row['subject'] ?? 0), 'skill' => $row['skill'] ?? 0,
                'domain' => $row['domain'] ?? 0, 'difficulty' => $row['difficulty'] ?? 0, 'cognitive' => $row['cognitive'] ?? '',
                'status' => 'published', 'exclude' => array_merge($existing, $picked) ?: [0],
            ]);
            $ids = array_map('intval', DB::column("SELECT q.id FROM questions q WHERE $where ORDER BY RAND() LIMIT $count", $params));
            if (count($ids) < $count) {
                $label = !empty($row['skill']) ? (string) DB::value('SELECT name FROM skills WHERE id = ?', [(int) $row['skill']])
                       : (!empty($row['domain']) ? (string) DB::value('SELECT name FROM domains WHERE id = ?', [(int) $row['domain']]) : 'المادة');
                $notes[] = "«{$label}»: طُلب $count وتوفر " . count($ids);
            }
            $picked = array_merge($picked, $ids);
        }
        $added = self::addQuestions($testId, $picked);
        DB::update('tests', ['blueprint' => json_encode($rows, JSON_UNESCAPED_UNICODE)], 'id = :id', ['id' => $testId]);
        return [$added, $notes];
    }

    /* ======================= الإسناد والنشر ======================= */

    public static function assign(int $testId, array $classIds, array $studentIds): void
    {
        DB::transaction(static function () use ($testId, $classIds, $studentIds) {
            DB::delete('test_assignments', 'test_id = ?', [$testId]);
            foreach (array_unique($classIds) as $cid) {
                DB::insert('test_assignments', ['test_id' => $testId, 'class_id' => (int) $cid, 'assigned_by' => Auth::id()]);
            }
            foreach (array_unique($studentIds) as $sid) {
                DB::insert('test_assignments', ['test_id' => $testId, 'student_id' => (int) $sid, 'assigned_by' => Auth::id()]);
            }
        });
    }

    /** طلاب الاختبار المستهدفون حالياً (فصول + أفراد) */
    public static function targetStudentIds(int $testId): array
    {
        return array_map('intval', DB::column(
            "SELECT DISTINCT s.id FROM test_assignments ta
               LEFT JOIN student_class_enrollments e ON e.class_id = ta.class_id AND e.status = 'active'
               JOIN students s ON s.id = COALESCE(ta.student_id, e.student_id)
              WHERE ta.test_id = ? AND s.status = 'active'",
            [$testId]
        ));
    }

    public static function setStatus(int $testId, string $status): array
    {
        $test = self::find($testId);
        if ($status === 'published') {
            $errors = [];
            if (!(int) $test['questions_count']) {
                $errors[] = 'أضف أسئلة قبل النشر.';
            }
            if (!$test['class_ids'] && !$test['student_ids']) {
                $errors[] = 'أسند الاختبار لفصل أو طلاب قبل النشر.';
            }
            if ($errors) {
                return $errors;
            }
        }
        DB::update('tests', ['status' => $status], 'id = :id', ['id' => $testId]);
        if ($status === 'published' && $test['status'] !== 'published' && $test['type'] !== 'practice') {
            $users = DB::column('SELECT user_id FROM students WHERE id IN (' . implode(',', self::targetStudentIds($testId) ?: [0]) . ')');
            Notifier::send($users, 'test_assigned', 'اختبار جديد: ' . $test['title'],
                ($test['available_until'] ? 'متاح حتى ' . dt($test['available_until']) : 'متاح الآن') . ($test['duration_minutes'] ? ' — المدة ' . $test['duration_minutes'] . ' دقيقة' : ''),
                '/student/tests', 'test-assigned-' . $testId);
        }
        if ($status === 'closed') {
            self::sweepExpired(1000, $testId);
        }
        return [];
    }

    public static function duplicate(int $testId): int
    {
        $t = DB::first('SELECT * FROM tests WHERE id = ?', [$testId]);
        unset($t['id'], $t['created_at'], $t['updated_at']);
        $t = array_merge($t, ['title' => $t['title'] . ' (نسخة)', 'status' => 'draft', 'created_by' => Auth::id(), 'academic_year_id' => Year::currentId(),
                              'available_from' => null, 'available_until' => null, 'results_released' => 0, 'remedial_plan_id' => null]);
        $new = DB::insert('tests', $t);
        DB::run('INSERT INTO test_questions (test_id, question_id, question_version, subject_id, domain_id, skill_id, type, snapshot, points, sort_order)
                 SELECT ?, question_id, question_version, subject_id, domain_id, skill_id, type, snapshot, points, sort_order FROM test_questions WHERE test_id = ?', [$new, $testId]);
        return $new;
    }

    /* ======================= جهة الطالب ======================= */

    /** هل الاختبار مسند للطالب (عبر فصله الحالي أو فردياً)؟ */
    public static function isAssignedTo(int $testId, int $studentId): bool
    {
        $classId = Student::currentClassId($studentId);
        return (bool) DB::value(
            'SELECT 1 FROM test_assignments WHERE test_id = ? AND (student_id = ? OR (class_id IS NOT NULL AND class_id = ?)) LIMIT 1',
            [$testId, $studentId, $classId ?? 0]
        );
    }

    /** الاختبارات المسندة للطالب مع حالتها */
    public static function forStudent(int $studentId, bool $includePractice = false): array
    {
        self::sweepExpired(50, null, $studentId);
        $classId = Student::currentClassId($studentId) ?? 0;
        $tests = DB::all(
            "SELECT DISTINCT t.*, s.name AS subject_name, s.color AS subject_color,
                    (SELECT COUNT(*) FROM test_questions tq WHERE tq.test_id = t.id) AS questions_count
               FROM tests t JOIN test_assignments ta ON ta.test_id = t.id
          LEFT JOIN subjects s ON s.id = t.subject_id
              WHERE (ta.student_id = ? OR ta.class_id = ?) AND t.status IN ('published','closed')" . ($includePractice ? '' : " AND t.type <> 'practice'") . '
           ORDER BY (t.available_until IS NULL), t.available_until, t.id DESC',
            [$studentId, $classId]
        );
        foreach ($tests as &$t) {
            $t['attempts'] = DB::all(
                'SELECT a.*, r.percent, r.level, r.score, r.max_score, r.is_final FROM test_attempts a LEFT JOIN test_results r ON r.attempt_id = a.id
                  WHERE a.test_id = ? AND a.student_id = ? ORDER BY a.attempt_no',
                [$t['id'], $studentId]
            );
            $t['state'] = self::stateFor($t, $t['attempts']);
        }
        return $tests;
    }

    /** حالة الاختبار للطالب: in_progress | available | upcoming | done | missed */
    public static function stateFor(array $test, array $attempts): string
    {
        foreach ($attempts as $a) {
            if ($a['status'] === 'in_progress' && !self::isExpired($a)) {
                return 'in_progress';
            }
        }
        $now = time();
        $done = count(array_filter($attempts, static fn ($a) => $a['status'] !== 'in_progress'));
        if ($test['status'] !== 'published' || ($test['available_until'] && strtotime($test['available_until']) < $now)) {
            return $done ? 'done' : 'missed';
        }
        if ($test['available_from'] && strtotime($test['available_from']) > $now) {
            return 'upcoming';
        }
        if ((int) $test['max_attempts'] > 0 && $done >= (int) $test['max_attempts']) {
            return 'done';
        }
        return 'available';
    }

    /** بدء محاولة أو استئناف الجارية — يُعيد معرف المحاولة */
    public static function start(int $testId, int $studentId): int
    {
        $test = self::find($testId);
        if (!$test || !self::isAssignedTo($testId, $studentId)) {
            throw new HttpException(403, 'هذا الاختبار غير مسند إليك.');
        }
        self::sweepExpired(20, $testId, $studentId);
        $attempts = DB::all('SELECT * FROM test_attempts WHERE test_id = ? AND student_id = ? ORDER BY attempt_no', [$testId, $studentId]);
        $state = self::stateFor($test, $attempts);
        if ($state === 'in_progress') {
            foreach ($attempts as $a) {
                if ($a['status'] === 'in_progress') {
                    return (int) $a['id'];
                }
            }
        }
        if ($state !== 'available') {
            throw new HttpException(403, match ($state) {
                'upcoming' => 'لم يحن موعد إتاحة الاختبار بعد.',
                'done'     => 'استنفدت عدد المحاولات المسموح بها.',
                default    => 'انتهى موعد الاختبار.',
            });
        }
        $questions = self::questions($testId);
        if (!$questions) {
            throw new HttpException(422, 'لا يحتوي الاختبار على أسئلة.');
        }

        $now = time();
        $deadline = (int) $test['duration_minutes'] > 0 ? $now + (int) $test['duration_minutes'] * 60 : null;
        if ($test['available_until']) {
            $close = strtotime($test['available_until']);
            $deadline = $deadline === null ? $close : min($deadline, $close);
        }
        $order = (int) $test['shuffle_questions'] ? self::shuffleGroups($questions) : $questions;

        try {
            return DB::transaction(static function () use ($test, $studentId, $attempts, $deadline, $order) {
                $attemptId = DB::insert('test_attempts', [
                    'test_id' => $test['id'], 'student_id' => $studentId, 'class_id' => Student::currentClassId($studentId),
                    'academic_year_id' => $test['academic_year_id'], 'attempt_no' => count($attempts) + 1, 'status' => 'in_progress',
                    'started_at' => date('Y-m-d H:i:s'), 'deadline_at' => $deadline ? date('Y-m-d H:i:s', $deadline) : null,
                    'last_activity_at' => date('Y-m-d H:i:s'), 'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
                foreach (array_values($order) as $i => $tq) {
                    $ids = array_map(static fn ($o) => (int) $o['id'], $tq['snap']['options']);
                    $main = $ids;
                    $right = [];
                    if ($tq['type'] === 'ordering') {
                        do {
                            shuffle($main);
                        } while (count($main) > 1 && $main === $ids); // لا يُعرض الترتيب الصحيح
                    } elseif ($tq['type'] === 'matching') {
                        $right = $ids;
                        shuffle($right);
                    } elseif ((int) $test['shuffle_options'] && in_array($tq['type'], ['single', 'multiple'], true)) {
                        shuffle($main);
                    }
                    DB::insert('attempt_answers', [
                        'attempt_id' => $attemptId, 'test_question_id' => $tq['id'], 'sort_order' => $i + 1,
                        'option_order' => json_encode(['main' => $main, 'right' => $right]),
                    ]);
                }
                return $attemptId;
            });
        } catch (PDOException $e) {
            // ضغطة مزدوجة متزامنة: قيد (test, student, attempt_no) يمنع محاولة مكررة — نستأنف القائمة
            $existing = DB::value("SELECT id FROM test_attempts WHERE test_id = ? AND student_id = ? AND status = 'in_progress' ORDER BY id DESC LIMIT 1", [$test['id'], $studentId]);
            if ($existing) {
                return (int) $existing;
            }
            throw $e;
        }
    }

    /** خلط الأسئلة مع إبقاء أسئلة النص الواحد متتالية */
    private static function shuffleGroups(array $questions): array
    {
        $groups = [];
        foreach ($questions as $q) {
            $key = !empty($q['snap']['passage']['id']) ? 'p' . $q['snap']['passage']['id'] : 'q' . $q['id'];
            $groups[$key][] = $q;
        }
        $groups = array_values($groups);
        shuffle($groups);
        return array_merge(...$groups);
    }

    public static function attempt(int $id): ?array
    {
        return DB::first(
            'SELECT a.*, t.title, t.type AS test_type, t.description, t.duration_minutes, t.allow_review, t.show_explanations,
                    t.result_visibility, t.results_released, t.status AS test_status, t.available_until, t.subject_id, t.created_by AS test_owner,
                    s.name AS subject_name, u.full_name AS student_name, st.user_id AS student_user_id, st.student_number,
                    c.name AS class_name, g.name AS grade_name
               FROM test_attempts a JOIN tests t ON t.id = a.test_id JOIN students st ON st.id = a.student_id JOIN users u ON u.id = st.user_id
          LEFT JOIN subjects s ON s.id = t.subject_id LEFT JOIN classes c ON c.id = a.class_id LEFT JOIN grades g ON g.id = c.grade_id
              WHERE a.id = ?',
            [$id]
        );
    }

    /** أسئلة المحاولة بترتيبها مع لقطة السؤال والإجابة المحفوظة */
    public static function attemptItems(int $attemptId): array
    {
        $rows = DB::all(
            'SELECT aa.*, tq.snapshot, tq.points AS max_points, tq.type, tq.skill_id, tq.domain_id, tq.subject_id
               FROM attempt_answers aa JOIN test_questions tq ON tq.id = aa.test_question_id
              WHERE aa.attempt_id = ? ORDER BY aa.sort_order',
            [$attemptId]
        );
        foreach ($rows as &$r) {
            $r['snap'] = json_decode($r['snapshot'], true);
            $r['ans'] = json_decode((string) $r['answer'], true) ?: [];
            $r['order'] = json_decode((string) $r['option_order'], true) ?: [];
            unset($r['snapshot']);
        }
        return $rows;
    }

    /* ======================= إخفاء معرفات الخيارات ======================= */

    /**
     * رقم معتم لكل خيار داخل المحاولة: أرقام الخيارات الحقيقية تتبع ترتيب إدخالها
     * (وقد تكشف الإجابة في المزاوجة والترتيب)، لذا لا تظهر في صفحة الاختبار مطلقاً.
     */
    public static function optionToken(int $attemptId, int $optionId): int
    {
        $key = (string) config('app.key') ?: (string) config('database.password') . __FILE__;
        return (int) hexdec(substr(hash_hmac('sha256', $attemptId . ':' . $optionId, $key), 0, 7)) + 1;
    }

    /** نسخة من بند المحاولة بمعرفات معتمة (للعرض أثناء الاختبار) */
    public static function maskItem(int $attemptId, array $item): array
    {
        $t = static fn ($id) => self::optionToken($attemptId, (int) $id);
        foreach ($item['snap']['options'] as &$o) {
            $o['id'] = $t($o['id']);
        }
        unset($o);
        $item['order'] = array_map(static fn ($ids) => array_map($t, (array) $ids), $item['order']);
        $a = $item['ans'];
        if (isset($a['option'])) {
            $a['option'] = $t($a['option']);
        }
        if (isset($a['options'])) {
            $a['options'] = array_map($t, $a['options']);
        }
        if (isset($a['order'])) {
            $a['order'] = array_map($t, $a['order']);
        }
        if (isset($a['pairs'])) {
            $pairs = [];
            foreach ($a['pairs'] as $l => $r) {
                $pairs[$t($l)] = $t($r);
            }
            $a['pairs'] = $pairs;
        }
        $item['ans'] = $a;
        return $item;
    }

    /** تحويل إجابة مرسلة بالمعرفات المعتمة إلى المعرفات الحقيقية */
    private static function unmask(int $attemptId, array $snap, mixed $raw): mixed
    {
        $map = [];
        foreach ($snap['options'] as $o) {
            $map[self::optionToken($attemptId, (int) $o['id'])] = (int) $o['id'];
        }
        $id = static fn ($v) => is_scalar($v) && $v !== '' ? ($map[(int) $v] ?? 0) : 0;
        if ($snap['type'] === 'short_answer') {
            return $raw;
        }
        if (!is_array($raw)) {
            return $id($raw);
        }
        if ($snap['type'] === 'matching') {
            $out = [];
            foreach ($raw as $l => $r) {
                if ($id($l)) {
                    $out[$id($l)] = $id($r);
                }
            }
            return $out;
        }
        return array_map($id, array_values($raw));
    }

    public static function isExpired(array $attempt): bool
    {
        return $attempt['status'] === 'in_progress' && $attempt['deadline_at'] !== null
            && strtotime((string) $attempt['deadline_at']) + self::GRACE_SECONDS < time();
    }

    public static function secondsLeft(array $attempt): ?int
    {
        return $attempt['deadline_at'] === null ? null : max(0, strtotime((string) $attempt['deadline_at']) - time());
    }

    /**
     * حفظ إجابات (تلقائي أو عند التسليم). $answers: [test_question_id => raw]، $flags: [test_question_id => 0|1]
     * التحقق من الوقت على الخادم: لا يُقبل أي حفظ بعد الموعد + المهلة.
     */
    public static function saveAnswers(array $attempt, array $answers, array $flags = []): array
    {
        if ($attempt['status'] !== 'in_progress') {
            return ['ok' => false, 'closed' => true, 'error' => 'تم تسليم هذا الاختبار.'];
        }
        if (self::isExpired($attempt)) {
            self::submit((int) $attempt['id'], true);
            return ['ok' => false, 'closed' => true, 'error' => 'انتهى الوقت وتم تسليم الاختبار تلقائياً بإجاباتك المحفوظة.'];
        }
        $items = [];
        foreach (self::attemptItems((int) $attempt['id']) as $item) {
            $items[(int) $item['test_question_id']] = $item;
        }
        $saved = 0;
        foreach ($answers as $tqId => $raw) {
            $item = $items[(int) $tqId] ?? null;
            if (!$item) {
                continue;
            }
            $value = Grader::normalizeInput($item['type'], self::unmask((int) $attempt['id'], $item['snap'], $raw), $item['snap']);
            DB::update('attempt_answers', [
                'answer' => $value ? json_encode($value, JSON_UNESCAPED_UNICODE) : null, 'answered_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $item['id']]);
            $saved++;
        }
        foreach ($flags as $tqId => $flag) {
            if (isset($items[(int) $tqId])) {
                DB::update('attempt_answers', ['flagged' => $flag ? 1 : 0], 'id = :id', ['id' => $items[(int) $tqId]['id']]);
            }
        }
        DB::update('test_attempts', ['last_activity_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $attempt['id']]);
        return ['ok' => true, 'saved' => $saved, 'seconds' => self::secondsLeft($attempt)];
    }

    /**
     * التسليم والتصحيح — آمن من التكرار: يقفل صف المحاولة، ولا يصحح إلا مرة واحدة،
     * والقيد الفريد على test_results.attempt_id يمنع احتساب النتيجة مرتين.
     */
    public static function submit(int $attemptId, bool $auto = false): void
    {
        $graded = DB::transaction(static function () use ($attemptId, $auto) {
            $attempt = DB::first('SELECT * FROM test_attempts WHERE id = ? FOR UPDATE', [$attemptId]);
            if (!$attempt || $attempt['status'] !== 'in_progress') {
                return false;
            }
            $needsManual = false;
            foreach (self::attemptItems($attemptId) as $item) {
                $g = Grader::grade($item['snap'], $item['ans'], (float) $item['max_points']);
                $needsManual = $needsManual || $g['manual'];
                DB::update('attempt_answers', [
                    'score' => $g['score'], 'is_correct' => $g['correct'], 'needs_manual' => $g['manual'] ? 1 : 0,
                ], 'id = :id', ['id' => $item['id']]);
            }
            $end = time();
            if ($auto && $attempt['deadline_at']) {
                $end = min($end, strtotime($attempt['deadline_at']));
            }
            DB::update('test_attempts', [
                'status' => $needsManual ? 'needs_grading' : 'graded', 'submitted_at' => date('Y-m-d H:i:s', $end),
                'auto_submitted' => $auto ? 1 : 0, 'duration_seconds' => max(0, $end - strtotime($attempt['started_at'])),
            ], 'id = :id', ['id' => $attemptId]);
            self::computeResult($attemptId);
            return true;
        });
        if ($graded) {
            self::afterGrading($attemptId);
        }
    }

    /** حساب النتيجة الإجمالية ونتائج المهارات (يُستدعى بعد التسليم أو التصحيح اليدوي) */
    public static function computeResult(int $attemptId): void
    {
        $a = DB::first('SELECT * FROM test_attempts WHERE id = ?', [$attemptId]);
        $items = self::attemptItems($attemptId);
        $score = $max = 0.0;
        $correct = $wrong = $blank = 0;
        $pending = false;
        $skills = [];
        foreach ($items as $it) {
            $max += (float) $it['max_points'];
            $score += (float) ($it['score'] ?? 0);
            $isBlank = Grader::isBlank($it['type'], $it['ans']);
            if ((int) $it['needs_manual'] === 1) {
                $pending = true;
            } elseif ($isBlank) {
                $blank++;
            } elseif ((int) $it['is_correct'] === 1) {
                $correct++;
            } else {
                $wrong++;
            }
            $key = ($it['skill_id'] ?? 0) . '-' . ($it['domain_id'] ?? 0) . '-' . $it['subject_id'];
            $skills[$key] ??= ['subject_id' => (int) $it['subject_id'], 'domain_id' => $it['domain_id'] ? (int) $it['domain_id'] : null,
                               'skill_id' => $it['skill_id'] ? (int) $it['skill_id'] : null, 'score' => 0.0, 'max' => 0.0];
            if ((int) $it['needs_manual'] !== 1) {
                $skills[$key]['score'] += (float) ($it['score'] ?? 0);
                $skills[$key]['max'] += (float) $it['max_points'];
            }
        }
        $percent = $max > 0 ? round($score / $max * 100, 2) : 0;
        $row = [
            'test_id' => $a['test_id'], 'student_id' => $a['student_id'], 'class_id' => $a['class_id'], 'academic_year_id' => $a['academic_year_id'],
            'score' => round($score, 2), 'max_score' => round($max, 2), 'percent' => $percent, 'correct_count' => $correct,
            'wrong_count' => $wrong, 'blank_count' => $blank, 'level' => Levels::key($percent), 'is_final' => $pending ? 0 : 1,
        ];
        $resultId = (int) DB::value('SELECT id FROM test_results WHERE attempt_id = ?', [$attemptId], 0);
        if ($resultId) {
            DB::update('test_results', $row, 'id = :id', ['id' => $resultId]);
            DB::delete('result_skill_scores', 'result_id = ?', [$resultId]);
        } else {
            $resultId = DB::insert('test_results', $row + ['attempt_id' => $attemptId]);
        }
        foreach ($skills as $s) {
            if ($s['max'] <= 0) {
                continue;
            }
            DB::insert('result_skill_scores', [
                'result_id' => $resultId, 'student_id' => $a['student_id'], 'subject_id' => $s['subject_id'], 'domain_id' => $s['domain_id'],
                'skill_id' => $s['skill_id'], 'score' => round($s['score'], 2), 'max_score' => round($s['max'], 2),
                'percent' => round($s['score'] / $s['max'] * 100, 2),
            ]);
        }
        if (!$pending && $a['status'] === 'needs_grading') {
            DB::update('test_attempts', ['status' => 'graded'], 'id = :id', ['id' => $attemptId]);
        }
    }

    /** إجراءات ما بعد التصحيح: الإشعارات والتحفيز */
    private static function afterGrading(int $attemptId): void
    {
        $a = self::attempt($attemptId);
        $result = DB::first('SELECT * FROM test_results WHERE attempt_id = ?', [$attemptId]);
        if (!$a || !$result) {
            return;
        }
        if ($a['status'] === 'needs_grading') {
            $teachers = $a['class_id'] ? Notifier::teacherUsersForClass((int) $a['class_id'], $a['subject_id'] ? (int) $a['subject_id'] : null) : [];
            Notifier::send(array_merge($teachers, [(int) $a['test_owner']]), 'grading_needed', 'إجابات بانتظار التصحيح اليدوي',
                $a['title'] . ' — ' . $a['student_name'], '/grading', 'grading-' . $a['test_id'] . '-' . date('Ymd'));
        }
        if ($a['test_type'] !== 'practice' && self::resultVisible($a)) {
            Notifier::send([(int) $a['student_user_id']], 'result_ready', 'نتيجتك في: ' . $a['title'],
                'حصلت على ' . pct((float) $result['percent']) . ' — المستوى: ' . Levels::label($result['level']), '/student/results/' . $attemptId, 'result-' . $attemptId);
        }
        if ($result['level'] === 'below' && (int) $result['is_final'] === 1 && $a['class_id'] && $a['test_type'] !== 'practice') {
            Notifier::send(Notifier::teacherUsersForClass((int) $a['class_id'], $a['subject_id'] ? (int) $a['subject_id'] : null), 'student_support',
                'طالب يحتاج إلى متابعة: ' . $a['student_name'], $a['title'] . ' — ' . pct((float) $result['percent']), '/attempts/' . $attemptId, 'support-' . $attemptId);
        }
        if (class_exists(Gamification::class)) {
            Gamification::onResult($attemptId);
        }
    }

    /** هل يرى الطالب نتيجته الآن؟ */
    public static function resultVisible(array $a): bool
    {
        if ($a['status'] === 'in_progress') {
            return false;
        }
        return match ($a['result_visibility']) {
            'immediate'   => true,
            'after_close' => $a['test_status'] !== 'published' || ($a['available_until'] && strtotime($a['available_until']) < time()),
            default       => (bool) $a['results_released'],
        };
    }

    /** تسليم المحاولات التي انتهى وقتها (بإجاباتها المحفوظة) */
    public static function sweepExpired(int $limit = 100, ?int $testId = null, ?int $studentId = null): int
    {
        $where = "status = 'in_progress' AND deadline_at IS NOT NULL AND deadline_at < (NOW() - INTERVAL " . self::GRACE_SECONDS . ' SECOND)';
        $params = [];
        if ($testId) {
            $where .= ' AND test_id = ?';
            $params[] = $testId;
        }
        if ($studentId) {
            $where .= ' AND student_id = ?';
            $params[] = $studentId;
        }
        $ids = DB::column("SELECT id FROM test_attempts WHERE $where LIMIT " . (int) $limit, $params);
        // الاختبار المغلق يُسلّم محاولاته الجارية حتى بدون مدة
        if ($testId && DB::value("SELECT 1 FROM tests WHERE id = ? AND status IN ('closed','archived')", [$testId])) {
            $ids = array_merge($ids, DB::column("SELECT id FROM test_attempts WHERE test_id = ? AND status = 'in_progress'", [$testId]));
        }
        foreach (array_unique($ids) as $id) {
            self::submit((int) $id, true);
        }
        return count(array_unique($ids));
    }

    /** إعادة محاولة لطالب: حذف محاولاته في الاختبار (تُحذف نتائجها تبعاً) */
    public static function resetAttempts(int $testId, int $studentId): int
    {
        return DB::delete('test_attempts', 'test_id = ? AND student_id = ?', [$testId, $studentId]);
    }

    /** التصحيح اليدوي لإجابة */
    public static function gradeManually(int $answerId, float $score, ?string $note): void
    {
        $row = DB::first('SELECT aa.*, tq.points FROM attempt_answers aa JOIN test_questions tq ON tq.id = aa.test_question_id WHERE aa.id = ?', [$answerId]);
        if (!$row) {
            throw new HttpException(404);
        }
        $score = round(max(0, min((float) $row['points'], $score)), 2);
        DB::update('attempt_answers', [
            'score' => $score, 'is_correct' => $score >= (float) $row['points'] ? 1 : 0, 'needs_manual' => 0,
            'grader_note' => $note ? mb_substr($note, 0, 500) : null, 'graded_by' => Auth::id(), 'graded_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $answerId]);
        self::computeResult((int) $row['attempt_id']);
        $a = self::attempt((int) $row['attempt_id']);
        if ($a['status'] === 'graded' && self::resultVisible($a)) {
            Notifier::send([(int) $a['student_user_id']], 'result_ready', 'اكتمل تصحيح: ' . $a['title'], null, '/student/results/' . $a['id'], 'result-final-' . $a['id']);
        }
    }

    /* ======================= التدريب الذاتي ======================= */

    /** إنشاء جلسة تدريب ذاتي للطالب على مادة/مهارة، مع أولوية لما لم يتقنه */
    public static function createPractice(int $studentId, int $subjectId, int $skillId = 0): int
    {
        $classId = Student::currentClassId($studentId);
        $gradeId = $classId ? (int) DB::value('SELECT grade_id FROM classes WHERE id = ?', [$classId]) : 0;
        if (!$gradeId) {
            throw new HttpException(422, 'لم يتم تسجيلك في فصل دراسي بعد.');
        }
        $size = max(5, min(30, Settings::int('practice_size')));
        [$where, $params] = QuestionBank::filterSql(['grade' => $gradeId, 'subject' => $subjectId, 'skill' => $skillId, 'status' => 'published']);
        $ids = array_map('intval', DB::column(
            "SELECT q.id FROM questions q WHERE $where
              ORDER BY (SELECT COUNT(*) FROM attempt_answers aa JOIN test_questions tq ON tq.id = aa.test_question_id
                         JOIN test_attempts at ON at.id = aa.attempt_id WHERE tq.question_id = q.id AND at.student_id = ? AND aa.is_correct = 1), RAND()
              LIMIT $size",
            array_merge($params, [$studentId])
        ));
        if (!$ids) {
            throw new HttpException(422, 'لا توجد أسئلة منشورة كافية لهذا الاختيار حتى الآن.');
        }
        $skillName = $skillId ? (string) DB::value('SELECT name FROM skills WHERE id = ?', [$skillId]) : '';
        $testId = DB::insert('tests', [
            'title' => 'تدريب ذاتي' . ($skillName ? ': ' . $skillName : ''), 'type' => 'practice', 'academic_year_id' => Year::currentId(),
            'grade_id' => $gradeId, 'subject_id' => $subjectId, 'duration_minutes' => 0, 'max_attempts' => 1, 'shuffle_questions' => 1,
            'shuffle_options' => 1, 'result_visibility' => 'immediate', 'allow_review' => 1, 'show_explanations' => 1, 'status' => 'published',
            'created_by' => Auth::id(),
        ]);
        self::addQuestions($testId, $ids);
        DB::insert('test_assignments', ['test_id' => $testId, 'student_id' => $studentId, 'assigned_by' => Auth::id()]);
        return self::start($testId, $studentId);
    }
}
