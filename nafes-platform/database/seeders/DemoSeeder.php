<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\DB;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Year;
use App\Services\QuestionBank;

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
    }
}
