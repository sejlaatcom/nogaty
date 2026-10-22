<?php
declare(strict_types=1);

/** اختبارات نافس: الإنشاء، النشر، محاولات الطلاب، والتصحيح الآلي */
final class Exams
{
    public const KINDS = [
        'diagnostic' => 'تشخيصي (قبلي)',
        'training'   => 'تدريبي',
        'mock'       => 'محاكاة لاختبار نافس',
        'final'      => 'ختامي (بعدي)',
    ];

    public const STATUSES = [
        'draft'     => 'مسودة',
        'published' => 'منشور',
        'closed'    => 'مغلق',
    ];

    /** مهلة إضافية بالثواني لاستقبال آخر إجابة بعد انتهاء الوقت (تأخر الشبكة) */
    private const GRACE_SECONDS = 30;

    /* ======================= الاختبارات ======================= */

    public static function find(int $id): ?array
    {
        $test = Database::first(
            'SELECT t.*, s.name AS subject_name, g.name AS grade_name, u.name AS author_name
               FROM nafes_tests t
               JOIN subjects s ON s.id = t.subject_id
               JOIN grades g   ON g.id = t.grade_id
          LEFT JOIN users u    ON u.id = t.created_by
              WHERE t.id = ?',
            [$id]
        );
        if (!$test) {
            return null;
        }
        $test['classroom_ids'] = array_map('intval', array_column(
            Database::all('SELECT classroom_id FROM nafes_test_classrooms WHERE test_id = ?', [$id]), 'classroom_id'
        ));
        return $test;
    }

    /** قائمة الاختبارات: الإدارة ترى الكل، والمعلم يرى اختباراته واختبارات فصوله */
    public static function listFor(array $user, array $filters = []): array
    {
        $where  = ['1 = 1'];
        $params = [];
        if ($user['role'] !== 'admin') {
            $classIds = array_map(static fn ($c) => (int) $c['id'], Nafes::staffClassrooms($user));
            $where[]  = '(t.created_by = ?' . ($classIds ? ' OR t.id IN (SELECT test_id FROM nafes_test_classrooms WHERE classroom_id IN (' . implode(',', $classIds) . '))' : '') . ')';
            $params[] = $user['id'];
        }
        foreach (['subject' => 't.subject_id', 'grade' => 't.grade_id'] as $key => $column) {
            if (!empty($filters[$key])) {
                $where[]  = "$column = ?";
                $params[] = (int) $filters[$key];
            }
        }
        if (!empty($filters['status']) && isset(self::STATUSES[$filters['status']])) {
            $where[]  = 't.status = ?';
            $params[] = $filters['status'];
        }

        return Database::all(
            'SELECT t.*, s.name AS subject_name, g.name AS grade_name, u.name AS author_name,
                    (SELECT COUNT(*) FROM nafes_test_questions tq WHERE tq.test_id = t.id) AS questions_count,
                    (SELECT COUNT(DISTINCT a.student_id) FROM nafes_attempts a WHERE a.test_id = t.id AND a.status = \'submitted\') AS students_done,
                    (SELECT AVG(a.percent) FROM nafes_attempts a WHERE a.test_id = t.id AND a.status = \'submitted\') AS avg_percent,
                    (SELECT GROUP_CONCAT(CONCAT(cg.name, \' / \', c.name) ORDER BY cg.sort_order, c.sort_order SEPARATOR \'، \')
                       FROM nafes_test_classrooms tc JOIN classrooms c ON c.id = tc.classroom_id JOIN grades cg ON cg.id = c.grade_id
                      WHERE tc.test_id = t.id) AS classes_label
               FROM nafes_tests t
               JOIN subjects s ON s.id = t.subject_id
               JOIN grades g   ON g.id = t.grade_id
          LEFT JOIN users u    ON u.id = t.created_by
              WHERE ' . implode(' AND ', $where) . '
           ORDER BY t.id DESC',
            $params
        );
    }

    /** إدارة الاختبار: الإدارة أو منشئه */
    public static function canManage(array $user, array $test): bool
    {
        return $user['role'] === 'admin' || (int) $test['created_by'] === (int) $user['id'];
    }

    /** عرض نتائج الاختبار: من يديره أو معلم أحد الفصول المستهدفة */
    public static function canView(array $user, array $test): bool
    {
        if (self::canManage($user, $test)) {
            return true;
        }
        foreach (Nafes::staffClassrooms($user) as $classroom) {
            if (in_array((int) $classroom['id'], $test['classroom_ids'] ?? [], true)) {
                return true;
            }
        }
        return false;
    }

    /** حفظ بيانات الاختبار والفصول المستهدفة — تُعيد [id, errors] */
    public static function save(?int $id, array $data, array $classroomIds, array $user): array
    {
        $errors = [];
        if (trim((string) ($data['title'] ?? '')) === '') {
            $errors[] = 'عنوان الاختبار مطلوب.';
        }
        if (empty($data['subject_id']) || empty($data['grade_id'])) {
            $errors[] = 'المادة والصف مطلوبان.';
        }
        if (!empty($data['starts_at']) && !empty($data['ends_at']) && $data['ends_at'] <= $data['starts_at']) {
            $errors[] = 'وقت نهاية الاختبار يجب أن يكون بعد وقت بدايته.';
        }

        // الفصول المسموحة للمستخدم ومن الصف نفسه فقط
        $allowed = [];
        foreach (Nafes::staffClassrooms($user) as $classroom) {
            if ((int) $classroom['grade_id'] === (int) ($data['grade_id'] ?? 0)) {
                $allowed[] = (int) $classroom['id'];
            }
        }
        $classroomIds = array_values(array_intersect(array_map('intval', $classroomIds), $allowed));

        if ($errors) {
            return [null, $errors];
        }

        $row = [
            'title'             => trim((string) $data['title']),
            'subject_id'        => (int) $data['subject_id'],
            'grade_id'          => (int) $data['grade_id'],
            'kind'              => isset(self::KINDS[$data['kind'] ?? '']) ? $data['kind'] : 'training',
            'instructions'      => trim((string) ($data['instructions'] ?? '')) ?: null,
            'duration_min'      => max(0, min(600, (int) ($data['duration_min'] ?? 0))),
            'starts_at'         => $data['starts_at'] ?: null,
            'ends_at'           => $data['ends_at'] ?: null,
            'max_attempts'      => max(0, min(20, (int) ($data['max_attempts'] ?? 1))),
            'shuffle_questions' => !empty($data['shuffle_questions']) ? 1 : 0,
            'shuffle_options'   => !empty($data['shuffle_options']) ? 1 : 0,
            'show_review'       => !empty($data['show_review']) ? 1 : 0,
        ];

        $id = Database::transaction(static function () use ($id, $row, $classroomIds, $user) {
            if ($id) {
                Database::update('nafes_tests', $row, 'id = :tid', ['tid' => $id]);
                // تغيير الصف أو المادة يلغي الأسئلة غير المطابقة
                Database::run(
                    'DELETE tq FROM nafes_test_questions tq JOIN nafes_questions q ON q.id = tq.question_id
                      WHERE tq.test_id = ? AND (q.subject_id <> ? OR q.grade_id <> ?)',
                    [$id, $row['subject_id'], $row['grade_id']]
                );
            } else {
                $id = Database::insert('nafes_tests', $row + ['created_by' => $user['id'], 'status' => 'draft']);
            }
            Database::delete('nafes_test_classrooms', 'test_id = ?', [$id]);
            foreach ($classroomIds as $classroomId) {
                Database::insert('nafes_test_classrooms', ['test_id' => $id, 'classroom_id' => $classroomId]);
            }
            return $id;
        });

        return [$id, []];
    }

    public static function setStatus(int $testId, string $status): array
    {
        if (!isset(self::STATUSES[$status])) {
            return ['حالة غير صالحة.'];
        }
        if ($status === 'published') {
            $errors = [];
            if (self::questionCount($testId) === 0) {
                $errors[] = 'أضف أسئلة للاختبار قبل نشره.';
            }
            if (!Database::value('SELECT 1 FROM nafes_test_classrooms WHERE test_id = ? LIMIT 1', [$testId])) {
                $errors[] = 'حدد فصلاً واحداً على الأقل قبل النشر.';
            }
            if ($errors) {
                return $errors;
            }
        }
        Database::update('nafes_tests', ['status' => $status], 'id = :tid', ['tid' => $testId]);
        return [];
    }

    /** حذف الاختبار: إن كان له محاولات يُغلق فقط */
    public static function delete(int $testId): string
    {
        if (Database::value('SELECT 1 FROM nafes_attempts WHERE test_id = ? LIMIT 1', [$testId])) {
            Database::update('nafes_tests', ['status' => 'closed'], 'id = :tid', ['tid' => $testId]);
            return 'closed';
        }
        Database::delete('nafes_tests', 'id = ?', [$testId]);
        return 'deleted';
    }

    /** نسخ الاختبار بأسئلته (بدون الفصول والمحاولات) */
    public static function duplicate(int $testId, int $userId): int
    {
        $test = Database::first('SELECT * FROM nafes_tests WHERE id = ?', [$testId]);
        unset($test['id'], $test['created_at'], $test['updated_at']);
        $test['title']      = $test['title'] . ' (نسخة)';
        $test['status']     = 'draft';
        $test['created_by'] = $userId;
        $test['starts_at']  = null;
        $test['ends_at']    = null;
        $newId = Database::insert('nafes_tests', $test);
        Database::run(
            'INSERT INTO nafes_test_questions (test_id, question_id, sort_order) SELECT ?, question_id, sort_order FROM nafes_test_questions WHERE test_id = ?',
            [$newId, $testId]
        );
        return $newId;
    }

    /* ======================= أسئلة الاختبار ======================= */

    public static function questionCount(int $testId): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM nafes_test_questions WHERE test_id = ?', [$testId], 0);
    }

    /** أسئلة الاختبار بالترتيب مع المهارة والنص */
    public static function questions(int $testId): array
    {
        return Database::all(
            'SELECT q.*, tq.sort_order, sk.name AS skill_name, sk.code AS skill_code, p.title AS passage_title
               FROM nafes_test_questions tq
               JOIN nafes_questions q     ON q.id = tq.question_id
          LEFT JOIN nafes_skills sk       ON sk.id = q.skill_id
          LEFT JOIN nafes_passages p      ON p.id = q.passage_id
              WHERE tq.test_id = ?
           ORDER BY tq.sort_order, tq.question_id',
            [$testId]
        );
    }

    /** إضافة أسئلة (من المادة والصف نفسيهما فقط) — تُعيد عدد ما أضيف */
    public static function addQuestions(int $testId, array $questionIds): int
    {
        $test = Database::first('SELECT subject_id, grade_id FROM nafes_tests WHERE id = ?', [$testId]);
        $ids  = array_filter(array_map('intval', $questionIds));
        if (!$test || !$ids) {
            return 0;
        }
        $valid = array_map('intval', array_column(Database::all(
            'SELECT id FROM nafes_questions WHERE id IN (' . implode(',', $ids) . ') AND subject_id = ? AND grade_id = ?',
            [$test['subject_id'], $test['grade_id']]
        ), 'id'));

        $order = (int) Database::value('SELECT COALESCE(MAX(sort_order), 0) FROM nafes_test_questions WHERE test_id = ?', [$testId], 0);
        $added = 0;
        foreach ($ids as $id) {
            if (!in_array($id, $valid, true)) {
                continue;
            }
            $added += Database::run(
                'INSERT IGNORE INTO nafes_test_questions (test_id, question_id, sort_order) VALUES (?, ?, ?)',
                [$testId, $id, ++$order]
            )->rowCount();
        }
        return $added;
    }

    public static function removeQuestions(int $testId, array $questionIds): void
    {
        $ids = array_filter(array_map('intval', $questionIds));
        if ($ids) {
            Database::run('DELETE FROM nafes_test_questions WHERE test_id = ? AND question_id IN (' . implode(',', $ids) . ')', [$testId]);
        }
    }

    /** نقل سؤال لأعلى أو لأسفل */
    public static function moveQuestion(int $testId, int $questionId, int $direction): void
    {
        $ids = array_map('intval', array_column(self::questions($testId), 'id'));
        $pos = array_search($questionId, $ids, true);
        $to  = $pos === false ? false : $pos + ($direction < 0 ? -1 : 1);
        if ($to === false || $to < 0 || $to >= count($ids)) {
            return;
        }
        [$ids[$pos], $ids[$to]] = [$ids[$to], $ids[$pos]];
        foreach ($ids as $i => $id) {
            Database::run('UPDATE nafes_test_questions SET sort_order = ? WHERE test_id = ? AND question_id = ?', [$i + 1, $testId, $id]);
        }
    }

    /**
     * توليد أسئلة عشوائية من البنك.
     * $perSkill: [skill_id => عدد]، أو $count إجمالي مع مستوى صعوبة اختياري.
     */
    public static function generate(int $testId, array $perSkill, int $count = 0, int $difficulty = 0): int
    {
        $test = Database::first('SELECT subject_id, grade_id FROM nafes_tests WHERE id = ?', [$testId]);
        if (!$test) {
            return 0;
        }
        $existing = array_map('intval', array_column(
            Database::all('SELECT question_id FROM nafes_test_questions WHERE test_id = ?', [$testId]), 'question_id'
        ));

        $picked = [];
        $pick = static function (array $filters, int $limit) use (&$picked, $existing, $test): void {
            if ($limit <= 0) {
                return;
            }
            $filters += ['subject' => $test['subject_id'], 'grade' => $test['grade_id'], 'active' => 1,
                         'exclude' => array_merge($existing, $picked) ?: [0]];
            [$whereSql, $params] = QuestionBank::filterSql($filters);
            foreach (Database::all("SELECT q.id FROM nafes_questions q WHERE $whereSql ORDER BY RAND() LIMIT " . $limit, $params) as $row) {
                $picked[] = (int) $row['id'];
            }
        };

        foreach ($perSkill as $skillId => $n) {
            $pick(['skill' => (int) $skillId, 'difficulty' => $difficulty], (int) $n);
        }
        if ($count > 0) {
            $pick(['difficulty' => $difficulty], $count);
        }

        return self::addQuestions($testId, $picked);
    }

    /* ======================= الطالب والاختبارات المتاحة ======================= */

    /** اختبارات فصل الطالب المنشورة (والمغلقة التي اختبرها) مع حالة كل منها */
    public static function forStudent(array $student): array
    {
        if (empty($student['classroom_id'])) {
            return [];
        }
        $tests = Database::all(
            "SELECT t.*, s.name AS subject_name,
                    (SELECT COUNT(*) FROM nafes_test_questions tq WHERE tq.test_id = t.id) AS questions_count
               FROM nafes_tests t
               JOIN nafes_test_classrooms tc ON tc.test_id = t.id AND tc.classroom_id = ?
               JOIN subjects s ON s.id = t.subject_id
              WHERE t.status IN ('published', 'closed')
           ORDER BY (t.ends_at IS NULL), t.ends_at, t.id DESC",
            [$student['classroom_id']]
        );

        $out = [];
        foreach ($tests as $test) {
            foreach (Database::all("SELECT * FROM nafes_attempts WHERE test_id = ? AND student_id = ? AND status = 'in_progress'", [$test['id'], $student['id']]) as $open) {
                self::finalizeIfExpired($open);
            }
            $attempts = Database::all(
                'SELECT * FROM nafes_attempts WHERE test_id = ? AND student_id = ? ORDER BY id',
                [$test['id'], $student['id']]
            );
            if ($test['status'] === 'closed' && !$attempts) {
                continue;
            }
            $test['attempts'] = $attempts;
            $test['state']    = self::studentState($test, $attempts);
            $out[] = $test;
        }
        return $out;
    }

    /**
     * حالة الاختبار بالنسبة للطالب:
     * in_progress | available | upcoming | ended | done
     */
    public static function studentState(array $test, array $attempts): string
    {
        foreach ($attempts as $attempt) {
            if ($attempt['status'] === 'in_progress' && !self::isExpired($attempt)) {
                return 'in_progress';
            }
        }
        $now = date('Y-m-d H:i:s');
        $submitted = count($attempts);
        if ($test['status'] !== 'published' || ($test['ends_at'] && $test['ends_at'] < $now)) {
            return $submitted ? 'done' : 'ended';
        }
        if ($test['starts_at'] && $test['starts_at'] > $now) {
            return 'upcoming';
        }
        if ((int) $test['max_attempts'] > 0 && $submitted >= (int) $test['max_attempts']) {
            return 'done';
        }
        return 'available';
    }

    /** بدء محاولة (أو متابعة المحاولة الجارية) — تُعيد [attempt_id, خطأ] */
    public static function start(int $testId, array $student): array
    {
        $test = self::find($testId);
        if (!$test || !in_array((int) $student['classroom_id'], $test['classroom_ids'], true)) {
            return [null, 'الاختبار غير متاح لفصلك.'];
        }
        $attempts = Database::all('SELECT * FROM nafes_attempts WHERE test_id = ? AND student_id = ? ORDER BY id', [$testId, $student['id']]);
        foreach ($attempts as $attempt) {
            self::finalizeIfExpired($attempt);
        }
        $attempts = Database::all('SELECT * FROM nafes_attempts WHERE test_id = ? AND student_id = ? ORDER BY id', [$testId, $student['id']]);

        $state = self::studentState($test, $attempts);
        if ($state === 'in_progress') {
            foreach ($attempts as $attempt) {
                if ($attempt['status'] === 'in_progress') {
                    return [(int) $attempt['id'], ''];
                }
            }
        }
        if ($state !== 'available') {
            return [null, match ($state) {
                'upcoming' => 'لم يبدأ وقت الاختبار بعد.',
                'done'     => 'استنفدت عدد المحاولات المسموح بها.',
                default    => 'انتهى وقت الاختبار.',
            }];
        }

        $questions = self::questions($testId);
        if (!$questions) {
            return [null, 'لا يحتوي الاختبار على أسئلة.'];
        }

        $now      = new DateTimeImmutable('now');
        $deadline = (int) $test['duration_min'] > 0 ? $now->modify('+' . (int) $test['duration_min'] . ' minutes') : null;
        if ($test['ends_at']) {
            $ends = new DateTimeImmutable($test['ends_at']);
            $deadline = $deadline === null || $ends < $deadline ? $ends : $deadline;
        }

        $order = (int) $test['shuffle_questions'] === 1 ? self::shuffleKeepingPassages($questions) : $questions;
        $attemptId = self::createAttempt([
            'test_id'     => $testId,
            'subject_id'  => $test['subject_id'],
            'grade_id'    => $test['grade_id'],
            'mode'        => 'test',
            'deadline_at' => $deadline?->format('Y-m-d H:i:s'),
        ], $student, $order, (int) $test['shuffle_options'] === 1);

        return [$attemptId, ''];
    }

    /** بدء تدريب ذاتي على مادة (ومهارة اختيارية) من صف الطالب */
    public static function startPractice(array $student, int $subjectId, int $skillId = 0): array
    {
        $classroom = $student['classroom_id'] ? Plans::classroom((int) $student['classroom_id']) : null;
        if (!$classroom) {
            return [null, 'لم يتم تحديد فصلك الدراسي.'];
        }
        // تسليم أي تدريب سابق مفتوح قبل بدء تدريب جديد
        foreach (Database::all("SELECT * FROM nafes_attempts WHERE student_id = ? AND mode = 'practice' AND status = 'in_progress'", [$student['id']]) as $open) {
            self::submit((int) $open['id']);
        }

        $size = max(5, min(40, (int) Settings::get('nafes_practice_size')));
        [$whereSql, $params] = QuestionBank::filterSql([
            'subject' => $subjectId, 'grade' => $classroom['grade_id'], 'skill' => $skillId, 'active' => 1,
        ]);
        // أولوية للأسئلة التي لم يجبها الطالب أو أخطأ فيها سابقاً
        $questions = Database::all(
            "SELECT q.* FROM nafes_questions q
              WHERE $whereSql
           ORDER BY (SELECT COUNT(*) FROM nafes_attempt_answers aa JOIN nafes_attempts a ON a.id = aa.attempt_id
                      WHERE aa.question_id = q.id AND a.student_id = ? AND aa.is_correct = 1), RAND()
              LIMIT $size",
            array_merge($params, [$student['id']])
        );
        if (!$questions) {
            return [null, 'لا توجد أسئلة كافية لهذا الاختيار حتى الآن.'];
        }

        $attemptId = self::createAttempt([
            'test_id'    => null,
            'subject_id' => $subjectId,
            'grade_id'   => $classroom['grade_id'],
            'skill_id'   => $skillId ?: null,
            'mode'       => 'practice',
        ], $student, self::shuffleKeepingPassages($questions), true);

        return [$attemptId, ''];
    }

    private static function createAttempt(array $data, array $student, array $questions, bool $shuffleOptions): int
    {
        return Database::transaction(static function () use ($data, $student, $questions, $shuffleOptions) {
            $attemptId = Database::insert('nafes_attempts', $data + [
                'student_id'   => $student['id'],
                'classroom_id' => $student['classroom_id'],
                'status'       => 'in_progress',
                'started_at'   => date('Y-m-d H:i:s'),
                'total'        => count($questions),
            ]);
            $options = QuestionBank::optionsFor(array_column($questions, 'id'));
            foreach (array_values($questions) as $i => $question) {
                $optionIds = array_map(static fn ($o) => (int) $o['id'], $options[(int) $question['id']] ?? []);
                // لا تُخلط خيارات صح/خطأ
                if ($shuffleOptions && $question['type'] === 'mcq') {
                    shuffle($optionIds);
                }
                Database::insert('nafes_attempt_answers', [
                    'attempt_id'   => $attemptId,
                    'question_id'  => $question['id'],
                    'sort_order'   => $i + 1,
                    'option_order' => implode(',', $optionIds),
                ]);
            }
            return $attemptId;
        });
    }

    /** خلط الأسئلة مع إبقاء أسئلة النص الواحد متتالية */
    private static function shuffleKeepingPassages(array $questions): array
    {
        $groups = [];
        foreach ($questions as $question) {
            $key = $question['passage_id'] ? 'p' . $question['passage_id'] : 'q' . $question['id'];
            $groups[$key][] = $question;
        }
        $groups = array_values($groups);
        shuffle($groups);
        return array_merge(...$groups);
    }

    /* ======================= المحاولة ======================= */

    public static function attempt(int $id): ?array
    {
        return Database::first(
            'SELECT a.*, t.title AS test_title, t.kind, t.show_review, t.instructions, s.name AS subject_name,
                    sk.name AS skill_name, u.name AS student_name
               FROM nafes_attempts a
               JOIN subjects s      ON s.id = a.subject_id
               JOIN users u         ON u.id = a.student_id
          LEFT JOIN nafes_tests t   ON t.id = a.test_id
          LEFT JOIN nafes_skills sk ON sk.id = a.skill_id
              WHERE a.id = ?',
            [$id]
        );
    }

    public static function attemptTitle(array $attempt): string
    {
        if ($attempt['mode'] === 'practice') {
            return 'تدريب ذاتي — ' . $attempt['subject_name'] . ($attempt['skill_name'] ? ' — ' . $attempt['skill_name'] : '');
        }
        return (string) $attempt['test_title'];
    }

    /** أسئلة المحاولة بترتيبها مع الخيارات بالترتيب المعروض للطالب */
    public static function attemptQuestions(int $attemptId): array
    {
        $rows = Database::all(
            'SELECT aa.*, q.stem, q.type, q.image, q.explanation, q.difficulty, q.passage_id, q.skill_id,
                    sk.name AS skill_name, sk.code AS skill_code
               FROM nafes_attempt_answers aa
               JOIN nafes_questions q    ON q.id = aa.question_id
          LEFT JOIN nafes_skills sk      ON sk.id = q.skill_id
              WHERE aa.attempt_id = ?
           ORDER BY aa.sort_order',
            [$attemptId]
        );
        $options  = QuestionBank::optionsFor(array_column($rows, 'question_id'));
        $passages = [];
        foreach ($rows as &$row) {
            $byId = [];
            foreach ($options[(int) $row['question_id']] ?? [] as $option) {
                $byId[(int) $option['id']] = $option;
            }
            $ordered = [];
            foreach (array_filter(explode(',', (string) $row['option_order'])) as $optionId) {
                if (isset($byId[(int) $optionId])) {
                    $ordered[] = $byId[(int) $optionId];
                    unset($byId[(int) $optionId]);
                }
            }
            $row['options'] = array_merge($ordered, array_values($byId)); // خيارات أضيفت بعد بدء المحاولة
            if ($row['passage_id'] && !isset($passages[$row['passage_id']])) {
                $passages[$row['passage_id']] = QuestionBank::passage((int) $row['passage_id']);
            }
            $row['passage'] = $row['passage_id'] ? $passages[$row['passage_id']] : null;
        }
        return $rows;
    }

    public static function isExpired(array $attempt): bool
    {
        return $attempt['status'] === 'in_progress' && $attempt['deadline_at'] !== null
            && strtotime((string) $attempt['deadline_at']) + self::GRACE_SECONDS < time();
    }

    /** الثواني المتبقية (null = بدون وقت) */
    public static function secondsLeft(array $attempt): ?int
    {
        if ($attempt['deadline_at'] === null) {
            return null;
        }
        return max(0, strtotime((string) $attempt['deadline_at']) - time());
    }

    /** تسليم المحاولة تلقائياً إن انتهى وقتها */
    public static function finalizeIfExpired(array $attempt): bool
    {
        if (self::isExpired($attempt)) {
            self::submit((int) $attempt['id']);
            return true;
        }
        return false;
    }

    /** حفظ إجابة سؤال — تُعيد خطأ أو '' */
    public static function saveAnswer(array $attempt, int $questionId, ?int $optionId, ?bool $flagged = null): string
    {
        if ($attempt['status'] !== 'in_progress') {
            return 'تم تسليم هذا الاختبار.';
        }
        if (self::isExpired($attempt)) {
            self::submit((int) $attempt['id']);
            return 'انتهى وقت الاختبار وتم تسليمه.';
        }
        $answer = Database::first('SELECT * FROM nafes_attempt_answers WHERE attempt_id = ? AND question_id = ?', [$attempt['id'], $questionId]);
        if (!$answer) {
            return 'السؤال لا يتبع هذا الاختبار.';
        }
        $data = [];
        if ($optionId !== null) {
            if ($optionId > 0 && !Database::value('SELECT 1 FROM nafes_options WHERE id = ? AND question_id = ?', [$optionId, $questionId])) {
                return 'خيار غير صالح.';
            }
            $data['option_id']   = $optionId ?: null;
            $data['answered_at'] = date('Y-m-d H:i:s');
        }
        if ($flagged !== null) {
            $data['flagged'] = $flagged ? 1 : 0;
        }
        if ($data) {
            Database::update('nafes_attempt_answers', $data, 'id = :aid', ['aid' => $answer['id']]);
        }
        return '';
    }

    /** التسليم والتصحيح الآلي */
    public static function submit(int $attemptId): void
    {
        Database::transaction(static function () use ($attemptId) {
            $attempt = Database::first('SELECT * FROM nafes_attempts WHERE id = ? FOR UPDATE', [$attemptId]);
            if (!$attempt || $attempt['status'] !== 'in_progress') {
                return;
            }
            Database::run(
                'UPDATE nafes_attempt_answers aa
                    LEFT JOIN nafes_options o ON o.id = aa.option_id
                     SET aa.is_correct = COALESCE(o.is_correct, 0)
                  WHERE aa.attempt_id = ?',
                [$attemptId]
            );
            $total = (int) Database::value('SELECT COUNT(*) FROM nafes_attempt_answers WHERE attempt_id = ?', [$attemptId], 0);
            $score = (int) Database::value('SELECT COUNT(*) FROM nafes_attempt_answers WHERE attempt_id = ? AND is_correct = 1', [$attemptId], 0);

            Database::update('nafes_attempts', [
                'status'       => 'submitted',
                'submitted_at' => date('Y-m-d H:i:s'),
                'score'        => $score,
                'total'        => $total,
                'percent'      => $total > 0 ? round($score / $total * 100, 2) : 0,
            ], 'id = :aid', ['aid' => $attemptId]);
        });
    }

    /** نتيجة المحاولة لكل مهارة */
    public static function skillBreakdown(int $attemptId): array
    {
        return Database::all(
            'SELECT q.skill_id, COALESCE(sk.name, \'بدون مهارة\') AS skill_name, sk.code AS skill_code,
                    COUNT(*) AS total, SUM(aa.is_correct = 1) AS correct,
                    ROUND(SUM(aa.is_correct = 1) / COUNT(*) * 100, 1) AS percent
               FROM nafes_attempt_answers aa
               JOIN nafes_questions q    ON q.id = aa.question_id
          LEFT JOIN nafes_skills sk      ON sk.id = q.skill_id
              WHERE aa.attempt_id = ?
           GROUP BY q.skill_id, sk.name, sk.code
           ORDER BY percent, skill_name',
            [$attemptId]
        );
    }

    /** إتقان الطالب لكل مهارة من كل محاولاته المسلّمة: [skill_id => [correct, total, percent]] */
    public static function studentSkillStats(int $studentId, ?int $subjectId = null): array
    {
        $params = [$studentId];
        $extra  = '';
        if ($subjectId) {
            $extra    = ' AND a.subject_id = ?';
            $params[] = $subjectId;
        }
        $out = [];
        foreach (Database::all(
            "SELECT q.skill_id, COUNT(*) AS total, SUM(aa.is_correct = 1) AS correct
               FROM nafes_attempt_answers aa
               JOIN nafes_attempts a  ON a.id = aa.attempt_id
               JOIN nafes_questions q ON q.id = aa.question_id
              WHERE a.student_id = ? AND a.status = 'submitted' AND q.skill_id IS NOT NULL $extra
           GROUP BY q.skill_id",
            $params
        ) as $row) {
            $out[(int) $row['skill_id']] = [
                'correct' => (int) $row['correct'],
                'total'   => (int) $row['total'],
                'percent' => round((int) $row['correct'] / max(1, (int) $row['total']) * 100, 1),
            ];
        }
        return $out;
    }

    /** هل يرى الطالب الإجابات الصحيحة؟ */
    public static function canReview(array $attempt): bool
    {
        if ($attempt['status'] !== 'submitted') {
            return false;
        }
        if ($attempt['mode'] === 'practice') {
            return true;
        }
        return Settings::bool('nafes_student_review') && (int) $attempt['show_review'] === 1;
    }

    /** المدة المستغرقة بالدقائق والثواني */
    public static function durationLabel(array $attempt): string
    {
        if (!$attempt['submitted_at']) {
            return '—';
        }
        $seconds = max(0, strtotime((string) $attempt['submitted_at']) - strtotime((string) $attempt['started_at']));
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60) . ' دقيقة';
    }
}
