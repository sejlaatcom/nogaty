<?php
declare(strict_types=1);

/** تقارير منصة نافس وتحليلاتها: نتائج الاختبار، تحليل الأسئلة، إتقان المهارات */
final class NafesReports
{
    /* ======================= نتائج اختبار ======================= */

    /**
     * نتائج طلاب اختبار: أفضل محاولة لكل طالب في الفصول المستهدفة (أو فصل محدد)،
     * مع الطلاب الذين لم يختبروا.
     */
    public static function testStudents(array $test, array $classroomIds): array
    {
        if (!$classroomIds) {
            return [];
        }
        $in = implode(',', array_map('intval', $classroomIds));
        $students = Database::all(
            "SELECT u.id, u.name, u.username, u.classroom_id, c.name AS class_name, g.name AS grade_name
               FROM users u
               JOIN classrooms c ON c.id = u.classroom_id
               JOIN grades g     ON g.id = c.grade_id
              WHERE u.role = 'student' AND u.is_active = 1 AND u.classroom_id IN ($in)
           ORDER BY g.sort_order, c.sort_order, u.name"
        );

        $attempts = [];
        foreach (Database::all(
            'SELECT * FROM nafes_attempts WHERE test_id = ? ORDER BY id',
            [$test['id']]
        ) as $attempt) {
            $attempts[(int) $attempt['student_id']][] = $attempt;
        }

        foreach ($students as &$student) {
            $list = $attempts[(int) $student['id']] ?? [];
            $best = null;
            foreach ($list as $attempt) {
                if ($attempt['status'] === 'submitted' && ($best === null || (float) $attempt['percent'] > (float) $best['percent'])) {
                    $best = $attempt;
                }
            }
            $student['attempts']    = $list;
            $student['best']        = $best;
            $student['in_progress'] = (bool) array_filter($list, static fn ($a) => $a['status'] === 'in_progress');
        }
        return $students;
    }

    /** إحصاءات مجمعة من صفوف الطلاب */
    public static function summarize(array $students): array
    {
        $percents = [];
        $levels   = array_fill_keys(array_keys(Nafes::LEVELS), 0);
        foreach ($students as $student) {
            if ($student['best']) {
                $p = (float) $student['best']['percent'];
                $percents[] = $p;
                $levels[Nafes::levelKey($p)]++;
            }
        }
        sort($percents);
        $n = count($percents);
        return [
            'students' => count($students),
            'done'     => $n,
            'missing'  => count($students) - $n,
            'avg'      => $n ? array_sum($percents) / $n : null,
            'median'   => $n ? ($n % 2 ? $percents[intdiv($n, 2)] : ($percents[$n / 2 - 1] + $percents[$n / 2]) / 2) : null,
            'max'      => $n ? max($percents) : null,
            'min'      => $n ? min($percents) : null,
            'levels'   => $levels,
            // نسبة المتمكنين فأعلى — المؤشر الأقرب لتقارير نافس
            'proficient_rate' => $n ? ($levels['advanced'] + $levels['proficient']) / $n * 100 : null,
        ];
    }

    /** معرفات أفضل محاولة مسلّمة لكل طالب (أساس تحليل الأسئلة والمهارات) */
    public static function bestAttemptIds(array $students): array
    {
        $ids = [];
        foreach ($students as $student) {
            if ($student['best']) {
                $ids[] = (int) $student['best']['id'];
            }
        }
        return $ids;
    }

    /** نسبة الإتقان لكل مهارة في مجموعة محاولات */
    public static function skillsFor(array $attemptIds): array
    {
        if (!$attemptIds) {
            return [];
        }
        return Database::all(
            'SELECT q.skill_id, COALESCE(sk.name, \'بدون مهارة\') AS skill_name, sk.code AS skill_code,
                    COUNT(*) AS total, SUM(aa.is_correct = 1) AS correct,
                    ROUND(SUM(aa.is_correct = 1) / COUNT(*) * 100, 1) AS percent
               FROM nafes_attempt_answers aa
               JOIN nafes_questions q ON q.id = aa.question_id
          LEFT JOIN nafes_skills sk   ON sk.id = q.skill_id
              WHERE aa.attempt_id IN (' . implode(',', $attemptIds) . ')
           GROUP BY q.skill_id, sk.name, sk.code
           ORDER BY percent'
        );
    }

    /**
     * تحليل الأسئلة: نسبة الإجابة الصحيحة (معامل السهولة)، معامل التمييز
     * (الفرق بين أعلى 27٪ وأدنى 27٪ من الطلاب)، وتوزيع اختيار البدائل.
     */
    public static function itemAnalysis(array $attemptIds): array
    {
        if (!$attemptIds) {
            return [];
        }
        $in = implode(',', $attemptIds);

        // ترتيب المحاولات حسب النتيجة لتحديد المجموعتين العليا والدنيا
        $ranked = array_map('intval', array_column(
            Database::all("SELECT id FROM nafes_attempts WHERE id IN ($in) ORDER BY percent DESC, id"), 'id'
        ));
        $groupSize = max(1, (int) round(count($ranked) * 0.27));
        $upper = array_slice($ranked, 0, $groupSize);
        $lower = array_slice($ranked, -$groupSize);

        $rows = Database::all(
            "SELECT aa.question_id, aa.attempt_id, aa.option_id, aa.is_correct
               FROM nafes_attempt_answers aa WHERE aa.attempt_id IN ($in)"
        );
        $items = [];
        foreach ($rows as $row) {
            $qid = (int) $row['question_id'];
            $items[$qid] ??= ['n' => 0, 'correct' => 0, 'blank' => 0, 'options' => [], 'upper' => 0, 'lower' => 0];
            $item = &$items[$qid];
            $item['n']++;
            if ((int) $row['is_correct'] === 1) {
                $item['correct']++;
                if (in_array((int) $row['attempt_id'], $upper, true)) {
                    $item['upper']++;
                }
                if (in_array((int) $row['attempt_id'], $lower, true)) {
                    $item['lower']++;
                }
            }
            if ($row['option_id'] === null) {
                $item['blank']++;
            } else {
                $item['options'][(int) $row['option_id']] = ($item['options'][(int) $row['option_id']] ?? 0) + 1;
            }
            unset($item);
        }

        $questions = $items ? Database::all(
            'SELECT q.id, q.stem, q.difficulty, sk.name AS skill_name FROM nafes_questions q
          LEFT JOIN nafes_skills sk ON sk.id = q.skill_id WHERE q.id IN (' . implode(',', array_keys($items)) . ')'
        ) : [];
        $options = QuestionBank::optionsFor(array_keys($items));

        $out = [];
        foreach ($questions as $question) {
            $item = $items[(int) $question['id']];
            $p    = $item['n'] ? $item['correct'] / $item['n'] * 100 : 0;
            $d    = ($item['upper'] - $item['lower']) / $groupSize;
            $out[] = $question + [
                'n'              => $item['n'],
                'p'              => round($p, 1),
                'discrimination' => round($d, 2),
                'blank'          => $item['blank'],
                'options'        => array_map(static fn ($o) => $o + ['chosen' => $item['options'][(int) $o['id']] ?? 0], $options[(int) $question['id']] ?? []),
                'flag'           => self::itemFlag($p, $d, count($ranked)),
            ];
        }
        usort($out, static fn ($a, $b) => $a['p'] <=> $b['p']);
        return $out;
    }

    /** ملاحظة على جودة السؤال */
    private static function itemFlag(float $p, float $d, int $n): string
    {
        if ($p < 30) {
            return 'صعب جداً — راجع صياغته أو أعد تدريس المهارة';
        }
        if ($p > 90) {
            return 'سهل جداً';
        }
        if ($n >= 10 && $d < 0.2) {
            return 'تمييز ضعيف — راجع البدائل';
        }
        return '';
    }

    /* ======================= الفصل والمهارات ======================= */

    /**
     * مصفوفة إتقان (طلاب × مهارات) لفصل في مادة من كل المحاولات المسلّمة
     * (اختبارات وتدريب). تُعيد [skills, students, cells[student][skill]]
     */
    public static function classSkillMatrix(int $classroomId, int $subjectId, bool $includePractice = true): array
    {
        $classroom = Plans::classroom($classroomId);
        if (!$classroom) {
            return [[], [], []];
        }
        $skills = [];
        foreach (QuestionBank::domainsWithSkills($subjectId, (int) $classroom['grade_id']) as $domain) {
            foreach ($domain['skills'] as $skill) {
                $skills[(int) $skill['id']] = $skill + ['domain_name' => $domain['name']];
            }
        }
        $students = Nafes::students($classroomId, null, '', true);

        $cells = [];
        $modeSql = $includePractice ? '' : " AND a.mode = 'test'";
        foreach (Database::all(
            "SELECT a.student_id, q.skill_id, COUNT(*) AS total, SUM(aa.is_correct = 1) AS correct
               FROM nafes_attempt_answers aa
               JOIN nafes_attempts a  ON a.id = aa.attempt_id
               JOIN nafes_questions q ON q.id = aa.question_id
               JOIN users u           ON u.id = a.student_id
              WHERE u.classroom_id = ? AND a.subject_id = ? AND a.status = 'submitted' AND q.skill_id IS NOT NULL $modeSql
           GROUP BY a.student_id, q.skill_id",
            [$classroomId, $subjectId]
        ) as $row) {
            $cells[(int) $row['student_id']][(int) $row['skill_id']] = [
                'total'   => (int) $row['total'],
                'correct' => (int) $row['correct'],
                'percent' => round((int) $row['correct'] / max(1, (int) $row['total']) * 100, 1),
            ];
        }
        return [$skills, $students, $cells];
    }

    /** متوسط إتقان كل مهارة على مستوى الفصل من المصفوفة */
    public static function skillAverages(array $skills, array $cells): array
    {
        $out = [];
        foreach (array_keys($skills) as $skillId) {
            $correct = $total = 0;
            foreach ($cells as $row) {
                if (isset($row[$skillId])) {
                    $correct += $row[$skillId]['correct'];
                    $total   += $row[$skillId]['total'];
                }
            }
            $out[$skillId] = $total ? round($correct / $total * 100, 1) : null;
        }
        return $out;
    }

    /** مجموعات علاجية: لكل مهارة الطلاب دون حد «متمكن» فيها */
    public static function remedialGroups(array $skills, array $students, array $cells): array
    {
        $cut   = (float) Settings::get('nafes_level_proficient');
        $names = [];
        foreach ($students as $student) {
            $names[(int) $student['id']] = $student['name'];
        }
        $groups = [];
        foreach ($skills as $skillId => $skill) {
            $list = [];
            foreach ($cells as $studentId => $row) {
                if (isset($row[$skillId], $names[$studentId]) && $row[$skillId]['percent'] < $cut) {
                    $list[] = ['name' => $names[$studentId], 'percent' => $row[$skillId]['percent']];
                }
            }
            if ($list) {
                usort($list, static fn ($a, $b) => $a['percent'] <=> $b['percent']);
                $groups[$skillId] = ['skill' => $skill, 'students' => $list];
            }
        }
        uasort($groups, static fn ($a, $b) => count($b['students']) <=> count($a['students']));
        return $groups;
    }

    /* ======================= نظرة عامة ======================= */

    /** متوسط الأداء والمشاركة لكل فصل في مادة (من أفضل محاولة لكل طالب في الاختبارات) */
    public static function classOverview(array $classrooms, int $subjectId): array
    {
        $out = [];
        foreach ($classrooms as $classroom) {
            $rows = Database::all(
                "SELECT a.student_id, AVG(a.percent) AS avg_percent
                   FROM nafes_attempts a JOIN users u ON u.id = a.student_id
                  WHERE u.classroom_id = ? AND u.is_active = 1 AND a.subject_id = ? AND a.status = 'submitted' AND a.mode = 'test'
               GROUP BY a.student_id",
                [$classroom['id'], $subjectId]
            );
            $levels = array_fill_keys(array_keys(Nafes::LEVELS), 0);
            foreach ($rows as $row) {
                $levels[Nafes::levelKey((float) $row['avg_percent'])]++;
            }
            $students = (int) Database::value("SELECT COUNT(*) FROM users WHERE role = 'student' AND is_active = 1 AND classroom_id = ?", [$classroom['id']], 0);
            $practice = (int) Database::value(
                "SELECT COUNT(*) FROM nafes_attempts a JOIN users u ON u.id = a.student_id
                  WHERE u.classroom_id = ? AND a.subject_id = ? AND a.mode = 'practice' AND a.status = 'submitted'",
                [$classroom['id'], $subjectId],
                0
            );
            $avg = $rows ? array_sum(array_map(static fn ($r) => (float) $r['avg_percent'], $rows)) / count($rows) : null;
            $out[] = [
                'classroom' => $classroom,
                'students'  => $students,
                'tested'    => count($rows),
                'avg'       => $avg,
                'levels'    => $levels,
                'practice'  => $practice,
            ];
        }
        return $out;
    }

    /* ======================= تقرير الطالب ======================= */

    /** ملخص الطالب لكل مادة: متوسط الاختبارات، عدد المحاولات، التدريب */
    public static function studentSubjects(int $studentId): array
    {
        return Database::all(
            "SELECT s.id, s.name,
                    AVG(CASE WHEN a.mode = 'test' THEN a.percent END) AS test_avg,
                    SUM(a.mode = 'test') AS tests,
                    SUM(a.mode = 'practice') AS practices,
                    AVG(CASE WHEN a.mode = 'practice' THEN a.percent END) AS practice_avg
               FROM nafes_attempts a JOIN subjects s ON s.id = a.subject_id
              WHERE a.student_id = ? AND a.status = 'submitted'
           GROUP BY s.id, s.name ORDER BY s.sort_order",
            [$studentId]
        );
    }

    public static function studentAttempts(int $studentId): array
    {
        return Database::all(
            "SELECT a.*, t.title AS test_title, s.name AS subject_name, sk.name AS skill_name
               FROM nafes_attempts a
               JOIN subjects s      ON s.id = a.subject_id
          LEFT JOIN nafes_tests t   ON t.id = a.test_id
          LEFT JOIN nafes_skills sk ON sk.id = a.skill_id
              WHERE a.student_id = ? AND a.status = 'submitted'
           ORDER BY a.submitted_at DESC",
            [$studentId]
        );
    }
}
