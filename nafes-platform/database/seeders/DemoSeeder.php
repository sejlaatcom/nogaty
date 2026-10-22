<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\DB;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Year;
use App\Services\QuestionBank;
use App\Services\TestEngine;

/**
 * بيانات تجريبية لاستكشاف المنصة — لا تُستخدم في بيئة الإنتاج.
 * كلمة المرور الموحدة لكل الحسابات التجريبية: Demo1234
 */
final class DemoSeeder
{
    public const PASSWORD = 'Demo1234';

    public static function run(): void
    {
        $admin = DB::first("SELECT u.* FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'admin' ORDER BY u.id LIMIT 1");
        if ($admin) {
            $_SESSION['uid'] = (int) $admin['id'];
            Auth::reset();
        }
        self::users();
        self::questions();
        if (method_exists(self::class, 'tests')) {
            self::tests();
        }
        unset($_SESSION['uid']);
        Auth::reset();
    }

    private static function users(): void
    {
        $classes = DB::all("SELECT c.id, c.name FROM classes c JOIN grades g ON g.id = c.grade_id WHERE g.name = 'الثالث المتوسط' AND c.academic_year_id = ? ORDER BY c.name + 0", [Year::currentId()]);
        $subjects = [];
        foreach (DB::all('SELECT id, code FROM subjects') as $s) {
            $subjects[$s['code']] = (int) $s['id'];
        }

        $staff = [
            ['principal', 'عبدالعزيز بن فهد السبيعي', 'P001', ['principal'], null],
            ['vice', 'ناصر بن سعد الدوسري', 'S001', ['supervisor'], null],
            ['math.t', 'خالد بن عبدالله القحطاني', 'T001', ['teacher'], 'math'],
            ['science.t', 'فيصل بن محمد الشهري', 'T002', ['teacher'], 'science'],
            ['reading.t', 'سلطان بن ماجد العتيبي', 'T003', ['teacher'], 'reading'],
        ];
        foreach ($staff as [$username, $name, $number, $roles, $subject]) {
            if (User::findByUsername($username)) {
                continue;
            }
            $teacherId = Teacher::create(['username' => $username, 'full_name' => $name, 'employee_number' => $number, 'password' => self::PASSWORD], $roles);
            DB::run("UPDATE users SET must_change_password = 0 WHERE username = ?", [$username]);
            foreach ($classes as $c) {
                if ($subject) {
                    Teacher::assign($teacherId, (int) $c['id'], $subjects[$subject] ?? null);
                } elseif ($roles === ['supervisor']) {
                    Teacher::assign($teacherId, (int) $c['id'], null);
                }
            }
        }

        $first = ['عبدالله', 'محمد', 'فيصل', 'سلمان', 'خالد', 'يزن', 'ريان', 'مشعل', 'عبدالرحمن', 'نواف', 'راكان', 'مازن', 'تركي', 'بدر', 'سعود', 'زياد'];
        $last  = ['العتيبي', 'القحطاني', 'الحربي', 'الغامدي', 'الشهري', 'الدوسري', 'المطيري', 'الزهراني', 'السبيعي', 'العنزي', 'الشمري', 'الهاجري'];
        $n = 0;
        foreach ($classes as $c) {
            for ($i = 0; $i < 10; $i++, $n++) {
                $number = (string) (90001 + $n);
                if (Student::numberTaken($number)) {
                    continue;
                }
                Student::create([
                    'full_name' => $first[$n % 16] . ' ' . $first[($n * 5 + 3) % 16] . ' ' . $last[($n * 7) % 12],
                    'student_number' => $number, 'username' => 'st' . $number, 'password' => self::PASSWORD,
                    'status' => 'active', 'class_id' => (int) $c['id'],
                ]);
            }
        }
        DB::run("UPDATE users SET must_change_password = 0 WHERE username LIKE 'st9%'");
    }

    private static function questions(): void
    {
        $data = require __DIR__ . '/data/questions.php';
        $gradeId = (int) DB::value("SELECT id FROM grades WHERE name = 'الثالث المتوسط'");
        $readingId = (int) DB::value("SELECT id FROM subjects WHERE code = 'reading'");
        $passages = [];
        foreach ($data['passages'] as $key => [$title, $body]) {
            $passages[$key] = (int) DB::value('SELECT id FROM passages WHERE title = ?', [$title], 0)
                ?: DB::insert('passages', ['subject_id' => $readingId, 'grade_id' => $gradeId, 'title' => $title, 'body' => $body, 'source' => 'نص تجريبي من إعداد المنصة', 'created_by' => Auth::id()]);
        }
        foreach (['math', 'science', 'reading'] as $code) {
            $subjectId = (int) DB::value('SELECT id FROM subjects WHERE code = ?', [$code]);
            foreach ($data[$code] as $row) {
                [$skillCode, $type, $stem, $opts, $correct, $difficulty, $cognitive, $explanation] = $row;
                $passageKey = $row[8] ?? null;
                $skillId = (int) DB::value(
                    'SELECT s.id FROM skills s JOIN domains d ON d.id = s.domain_id WHERE d.subject_id = ? AND s.code = ?',
                    [$subjectId, $skillCode],
                    0
                );
                $options = [];
                $accepted = [];
                switch ($type) {
                    case 'single':
                    case 'multiple':
                        foreach ($opts as $i => $body) {
                            $options[] = ['body' => $body, 'is_correct' => is_array($correct) ? in_array($i, $correct, true) : $i === $correct];
                        }
                        break;
                    case 'true_false':
                        $options = [['body' => 'صح', 'is_correct' => $correct]];
                        break;
                    case 'matching':
                        $options = array_map(static fn ($p) => ['body' => $p[0], 'match_text' => $p[1]], $opts);
                        break;
                    case 'ordering':
                        $options = array_map(static fn ($b) => ['body' => $b], $opts);
                        break;
                    case 'short_answer':
                        $accepted = $opts;
                        break;
                }
                QuestionBank::save(null, [
                    'type' => $type, 'subject_id' => $subjectId, 'grade_id' => $gradeId, 'skill_id' => $skillId ?: null,
                    'passage_id' => $passageKey ? $passages[$passageKey] : null, 'stem' => $stem, 'explanation' => $explanation,
                    'difficulty' => $difficulty, 'cognitive_level' => $cognitive, 'points' => 1, 'status' => 'published',
                    'source' => 'أسئلة تجريبية من إعداد المنصة', 'usage_rights' => 'للاستخدام الداخلي في المدرسة',
                ], $options, $accepted);
            }
        }
        // أسئلة العرض التجريبي معتمدة مباشرة (عند التشغيل من سطر الأوامر دون جلسة مدير تُحفظ كـ«قيد المراجعة»)
        DB::run("UPDATE questions SET status = 'published' WHERE status = 'review' AND source = 'أسئلة تجريبية من إعداد المنصة'");
    }

    /**
     * اختبارات تجريبية منشورة مع محاولات طلاب محاكاة عبر محرك الاختبارات نفسه
     * (تشخيصي ثم بعدي لكل مادة) لتظهر التقارير ولوحة المؤشرات ببيانات قابلة للاستكشاف.
     */
    private static function tests(): void
    {
        $gradeId = (int) DB::value("SELECT id FROM grades WHERE is_nafes_target = 1 AND level_order = 9 LIMIT 1", [], 0)
            ?: (int) DB::value('SELECT id FROM grades WHERE is_nafes_target = 1 ORDER BY level_order DESC LIMIT 1', [], 0);
        $classIds = array_map('intval', DB::column('SELECT id FROM classes WHERE grade_id = ? AND academic_year_id = ?', [$gradeId, Year::currentId()]));
        if (!$gradeId || !$classIds || DB::value("SELECT 1 FROM tests WHERE title LIKE 'تجريبي:%' LIMIT 1")) {
            return;
        }
        $teachers = ['reading' => 'reading.t', 'math' => 'math.t', 'science' => 'science.t'];
        $students = array_map('intval', DB::column("SELECT s.id FROM students s JOIN student_class_enrollments e ON e.student_id = s.id AND e.status = 'active' WHERE e.class_id IN (" . implode(',', $classIds) . ')'));
        // قدرة ثابتة لكل طالب (0.3–0.95) ليكون التحسن بين التشخيصي والبعدي متسقاً
        mt_srand(2024);
        $ability = [];
        foreach ($students as $sid) {
            $ability[$sid] = 0.3 + mt_rand(0, 65) / 100;
        }
        foreach ($teachers as $code => $username) {
            $subjectId = (int) DB::value('SELECT id FROM subjects WHERE code = ?', [$code], 0);
            $owner = (int) DB::value('SELECT id FROM users WHERE username = ?', [$username], 0) ?: null;
            if (!$subjectId || !$owner) {
                continue;
            }
            // يُنشأ الاختبار بصلاحيات معلم المادة نفسه (نطاق فصوله ومادته)
            $previous = $_SESSION['uid'] ?? null;
            $_SESSION['uid'] = $owner;
            Auth::reset();
            foreach ([['diagnostic', 'تجريبي: اختبار تشخيصي', 35, 0.0], ['post', 'تجريبي: اختبار بعدي', 10, 0.12]] as [$type, $title, $daysAgo, $gain]) {
                [$testId] = TestEngine::save(null, [
                    'title' => $title . ' — ' . DB::value('SELECT name FROM subjects WHERE id = ?', [$subjectId]), 'description' => 'اختبار من البيانات التجريبية.',
                    'type' => $type, 'grade_id' => $gradeId, 'subject_id' => $subjectId, 'duration_minutes' => 30, 'available_from' => null,
                    'available_until' => null, 'max_attempts' => 1, 'shuffle_questions' => 1, 'shuffle_options' => 1,
                    'result_visibility' => 'immediate', 'allow_review' => 1, 'show_explanations' => 1,
                ]);
                if (!$testId) {
                    continue;
                }
                TestEngine::generate((int) $testId, [['count' => 10]]);
                TestEngine::assign((int) $testId, $classIds, []);
                if (TestEngine::setStatus((int) $testId, 'published')) {
                    continue;
                }
                foreach ($students as $sid) {
                    if (mt_rand(1, 100) > 85) {
                        continue; // بعض الطلاب لم يؤدوا الاختبار
                    }
                    self::simulateAttempt((int) $testId, $sid, min(0.98, $ability[$sid] + $gain), $daysAgo);
                }
            }
            $_SESSION['uid'] = $previous;
            Auth::reset();
        }
    }

    private static function simulateAttempt(int $testId, int $studentId, float $p, int $daysAgo): void
    {
        $attemptId = TestEngine::start($testId, $studentId);
        foreach (TestEngine::attemptItems($attemptId) as $item) {
            $q = $item['snap'];
            $right = mt_rand(1, 100) <= $p * 100;
            $correct = array_values(array_filter($q['options'], static fn ($o) => $o['is_correct']));
            $other = array_values(array_filter($q['options'], static fn ($o) => !$o['is_correct']));
            $ids = array_map(static fn ($o) => (int) $o['id'], $q['options']);
            $answer = match ($q['type']) {
                'single', 'true_false' => ['option' => (int) (($right || !$other ? $correct[0] : $other[array_rand($other)])['id'] ?? 0)],
                'multiple' => ['options' => $right ? array_map(static fn ($o) => (int) $o['id'], $correct) : [(int) ($other[0]['id'] ?? $ids[0])]],
                'matching' => ['pairs' => array_combine($ids, $right ? $ids : array_reverse($ids))],
                'ordering' => (static function () use ($q, $right, $ids) {
                    $o = $q['options'];
                    usort($o, static fn ($a, $b) => $a['position'] <=> $b['position']);
                    $order = array_map(static fn ($x) => (int) $x['id'], $o);
                    return ['order' => $right ? $order : array_reverse($order), 'touched' => 1];
                })(),
                'short_answer' => ['text' => $right ? (string) ($q['options'][0]['body'] ?? '') : '؟'],
                default => [],
            };
            DB::update('attempt_answers', ['answer' => json_encode($answer, JSON_UNESCAPED_UNICODE)], 'id = :id', ['id' => $item['id']]);
        }
        TestEngine::submit($attemptId);
        // توزيع أوقات المحاولات على أيام سابقة لتظهر الاتجاهات الزمنية
        $start = time() - $daysAgo * 86400 + mt_rand(0, 4 * 86400);
        $duration = mt_rand(8, 28) * 60;
        DB::update('test_attempts', ['started_at' => date('Y-m-d H:i:s', $start), 'deadline_at' => date('Y-m-d H:i:s', $start + 1800),
            'submitted_at' => date('Y-m-d H:i:s', $start + $duration), 'duration_seconds' => $duration], 'id = :id', ['id' => $attemptId]);
        DB::run('UPDATE test_results SET created_at = ?, updated_at = ? WHERE attempt_id = ?', [date('Y-m-d H:i:s', $start + $duration), date('Y-m-d H:i:s', $start + $duration), $attemptId]);
    }
}
