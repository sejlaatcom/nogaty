<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Models\ClassRoom;
use App\Models\Year;
use App\Services\Analytics;
use App\Services\Audit;
use App\Services\Notifier;
use App\Services\QuestionBank;
use App\Services\Scope;
use App\Services\Settings;
use App\Services\TestEngine;

/** إدارة الاختبارات للمعلمين والإدارة */
final class TestController extends Controller
{
    private function load(Request $request, bool $manage = false): array
    {
        $test = TestEngine::find((int) $request->param('id'));
        if (!$test || $test['type'] === 'practice') {
            throw new HttpException(404);
        }
        if ($manage ? !TestEngine::canManage($test) : !TestEngine::canView($test)) {
            throw new HttpException(403, $manage ? 'هذا الاختبار أنشأه مستخدم آخر.' : 'لا تملك صلاحية الاطلاع على هذا الاختبار.');
        }
        return $test;
    }

    public function index(Request $request): string
    {
        $f = ['type' => $request->str('type'), 'status' => $request->str('status'), 'subject' => $request->int('subject'), 'q' => $request->str('q'), 'mine' => $request->bool('mine')];
        $where = ["t.type <> 'practice'", 't.academic_year_id = ?'];
        $params = [$request->int('year') ?: Year::currentId()];
        if (!Scope::all() && !Auth::can('tests.manage_any')) {
            [$in, $p] = DB::in(Scope::classIds());
            $where[] = "(t.created_by = ? OR t.id IN (SELECT test_id FROM test_assignments WHERE class_id IN ($in)))";
            $params = array_merge($params, [Auth::id()], $p);
        }
        foreach (['type' => 't.type', 'status' => 't.status'] as $k => $col) {
            if ($f[$k] !== '') {
                $where[] = "$col = ?";
                $params[] = $f[$k];
            }
        }
        if ($f['subject']) {
            $where[] = 't.subject_id = ?';
            $params[] = $f['subject'];
        }
        if ($f['q'] !== '') {
            $where[] = 't.title LIKE ?';
            $params[] = '%' . $f['q'] . '%';
        }
        if ($f['mine']) {
            $where[] = 't.created_by = ?';
            $params[] = Auth::id();
        }
        $tests = DB::all(
            'SELECT t.*, s.name AS subject_name, g.name AS grade_name, u.full_name AS author,
                    (SELECT COUNT(*) FROM test_questions tq WHERE tq.test_id = t.id) AS q_count,
                    (SELECT COUNT(DISTINCT a.student_id) FROM test_attempts a WHERE a.test_id = t.id AND a.status <> \'in_progress\') AS done_count,
                    (SELECT AVG(r.percent) FROM test_results r WHERE r.test_id = t.id) AS avg_percent,
                    (SELECT GROUP_CONCAT(CONCAT(cg.name, \' / \', c.name) SEPARATOR \'، \') FROM test_assignments ta JOIN classes c ON c.id = ta.class_id JOIN grades cg ON cg.id = c.grade_id WHERE ta.test_id = t.id) AS classes,
                    (SELECT COUNT(*) FROM test_assignments ta WHERE ta.test_id = t.id AND ta.student_id IS NOT NULL) AS individual
               FROM tests t JOIN grades g ON g.id = t.grade_id LEFT JOIN subjects s ON s.id = t.subject_id LEFT JOIN users u ON u.id = t.created_by
              WHERE ' . implode(' AND ', $where) . ' ORDER BY t.id DESC LIMIT 300',
            $params
        );
        return $this->view('tests/index', ['title' => 'الاختبارات', 'tests' => $tests, 'filters' => $f, 'subjects' => ClassRoom::subjects()]);
    }

    public function create(Request $request): string
    {
        $test = [
            'id' => 0, 'title' => '', 'description' => '', 'type' => $request->str('type', 'training'), 'grade_id' => (int) (ClassRoom::nafesGrades()[0]['id'] ?? 0),
            'subject_id' => $request->int('subject'), 'duration_minutes' => Settings::int('default_duration'), 'available_from' => null, 'available_until' => null,
            'max_attempts' => Settings::int('default_attempts'), 'shuffle_questions' => 1, 'shuffle_options' => 1,
            'result_visibility' => Settings::get('default_visibility'), 'allow_review' => 1, 'show_explanations' => 1,
        ];
        return $this->view('tests/form', ['title' => 'اختبار جديد', 'test' => $test, 'grades' => ClassRoom::nafesGrades(), 'subjects' => $this->allowedSubjects()]);
    }

    public function edit(Request $request): string
    {
        $test = $this->load($request, true);
        return $this->view('tests/form', ['title' => 'إعدادات: ' . $test['title'], 'test' => $test, 'grades' => ClassRoom::nafesGrades(), 'subjects' => $this->allowedSubjects()]);
    }

    private function allowedSubjects(): array
    {
        $allowed = Scope::subjectIds();
        return array_values(array_filter(ClassRoom::subjects(), static fn ($s) => $allowed === null || in_array((int) $s['id'], $allowed, true)));
    }

    public function store(Request $request): never
    {
        $this->persist($request, null);
    }

    public function update(Request $request): never
    {
        $this->persist($request, $this->load($request, true));
    }

    private function persist(Request $request, ?array $test): never
    {
        $dt = static function (string $v): ?string {
            $t = $v !== '' ? strtotime($v) : false;
            return $t ? date('Y-m-d H:i:s', $t) : null;
        };
        $data = [
            'title' => $request->str('title'), 'description' => $request->str('description'), 'type' => $request->str('type'),
            'grade_id' => $request->int('grade_id'), 'subject_id' => $request->int('subject_id'), 'duration_minutes' => $request->int('duration_minutes'),
            'available_from' => $dt($request->str('available_from')), 'available_until' => $dt($request->str('available_until')),
            'max_attempts' => $request->int('max_attempts', 1), 'shuffle_questions' => $request->bool('shuffle_questions'),
            'shuffle_options' => $request->bool('shuffle_options'), 'result_visibility' => $request->str('result_visibility'),
            'allow_review' => $request->bool('allow_review'), 'show_explanations' => $request->bool('show_explanations'),
        ];
        [$id, $errors] = TestEngine::save($test ? (int) $test['id'] : null, $data);
        if ($errors) {
            back_with_errors($errors, $test ? '/tests/' . $test['id'] . '/edit' : '/tests/create');
        }
        Audit::log($test ? 'test_update' : 'test_create', 'tests', (int) $id, ['title' => $data['title']]);
        flash('success', $test ? 'تم حفظ الإعدادات.' : 'تم إنشاء الاختبار — أضف الأسئلة وحدد الفصول ثم انشره.');
        redirect('/tests/' . $id);
    }

    /** منشئ الاختبار: الأسئلة والمولّد والإسناد */
    public function show(Request $request): string
    {
        $test = $this->load($request);
        $questions = TestEngine::questions((int) $test['id']);
        $existing = array_filter(array_map(static fn ($q) => (int) $q['question_id'], $questions));
        $bankFilters = [
            'grade' => $test['grade_id'], 'subject' => $test['subject_id'] ?: $request->int('bsubject'), 'skill' => $request->int('bskill'),
            'difficulty' => $request->int('bdiff'), 'type' => $request->str('btype'), 'q' => $request->str('bq'), 'status' => 'published', 'exclude' => $existing,
        ];
        $bank = TestEngine::canManage($test) ? QuestionBank::search($bankFilters, max(1, $request->int('bpage', 1)), 12) : null;

        // التوزيع الحالي
        $dist = ['skills' => [], 'difficulty' => [1 => 0, 2 => 0, 3 => 0], 'types' => []];
        foreach ($questions as $q) {
            $label = $q['skill_name'] ? ($q['skill_code'] ? $q['skill_code'] . ' ' : '') . $q['skill_name'] : 'بدون مهارة';
            $dist['skills'][$label] = ($dist['skills'][$label] ?? 0) + 1;
            $dist['difficulty'][(int) $q['snap']['difficulty']]++;
            $dist['types'][$q['type']] = ($dist['types'][$q['type']] ?? 0) + 1;
        }

        // المهارات المتاحة للمولّد مع عدد الأسئلة المنشورة
        $skills = DB::all(
            "SELECT sk.id, sk.code, sk.name, d.name AS domain_name, d.id AS domain_id, sub.name AS subject_name,
                    (SELECT COUNT(*) FROM questions q WHERE q.skill_id = sk.id AND q.status = 'published' AND q.grade_id = ?) AS available
               FROM skills sk JOIN domains d ON d.id = sk.domain_id JOIN subjects sub ON sub.id = d.subject_id
              WHERE (d.grade_id = ? OR d.grade_id IS NULL) AND (? IS NULL OR d.subject_id = ?)
           ORDER BY sub.sort_order, d.sort_order, sk.sort_order",
            [$test['grade_id'], $test['grade_id'], $test['subject_id'], $test['subject_id']]
        );
        $classes = array_values(array_filter(ClassRoom::forYear((int) $test['academic_year_id'], true, true), static fn ($c) => (int) $c['grade_id'] === (int) $test['grade_id']));
        $students = [];
        if ($classes) {
            [$in, $p] = DB::in(array_column($classes, 'id'));
            $students = DB::all(
                "SELECT s.id, u.full_name, c.name AS class_name FROM student_class_enrollments e JOIN students s ON s.id = e.student_id
                   JOIN users u ON u.id = s.user_id JOIN classes c ON c.id = e.class_id WHERE e.status = 'active' AND e.class_id IN ($in) ORDER BY c.name + 0, u.full_name",
                $p
            );
        }
        return $this->view('tests/show', [
            'title' => $test['title'], 'test' => $test, 'questions' => $questions, 'bank' => $bank, 'dist' => $dist, 'skills' => $skills,
            'classes' => $classes, 'students' => $students, 'canManage' => TestEngine::canManage($test), 'hasAttempts' => TestEngine::hasAttempts((int) $test['id']),
            'bankSkills' => DB::all('SELECT s.id, s.code, s.name FROM skills s JOIN domains d ON d.id = s.domain_id WHERE (d.grade_id = ? OR d.grade_id IS NULL) AND (? IS NULL OR d.subject_id = ?) ORDER BY d.sort_order, s.sort_order', [$test['grade_id'], $test['subject_id'], $test['subject_id']]),
            'bankSubjects' => ClassRoom::subjects(),
        ]);
    }

    /** عمليات المنشئ: إضافة/حذف/ترتيب/درجات/توليد/تحديث نسخة */
    public function questions(Request $request): never
    {
        $test = $this->load($request, true);
        $id = (int) $test['id'];
        $back = '/tests/' . $id . '#questions';
        // أزرار الترتيب والتحديث داخل نموذج الأسئلة ترسل "move:رقم:اتجاه" أو "refresh:رقم"
        $parts = explode(':', $request->str('action'));
        $action = $parts[0];
        $tqId = (int) ($parts[1] ?? $request->int('tq_id'));
        if (TestEngine::hasAttempts($id) && in_array($action, ['add', 'remove', 'generate', 'points', 'refresh'], true)) {
            back_with_errors(['بدأ طلاب في هذا الاختبار؛ لا يمكن تعديل أسئلته حفاظاً على عدالة النتائج. انسخ الاختبار لإنشاء نسخة معدلة.'], $back);
        }
        switch ($action) {
            case 'add':
                $n = TestEngine::addQuestions($id, $request->ints('qids'));
                flash('success', "أضيف $n سؤال (نسخة مجمدة لا تتأثر بتعديل البنك لاحقاً).");
                break;
            case 'remove':
                TestEngine::removeQuestions($id, $request->ints('tq'));
                flash('success', 'تمت إزالة الأسئلة المحددة.');
                break;
            case 'points':
                TestEngine::setPoints($id, $request->array('points'));
                flash('success', 'تم حفظ الدرجات.');
                break;
            case 'move':
                TestEngine::move($id, $tqId, (int) ($parts[2] ?? $request->int('dir')));
                break;
            case 'refresh':
                $ok = TestEngine::refreshQuestion($id, $tqId);
                flash($ok ? 'success' : 'warning', $ok ? 'تم تحديث السؤال لآخر نسخة في البنك.' : 'تعذر التحديث.');
                break;
            case 'generate':
                $rows = [];
                foreach ($request->array('bp') as $row) {
                    if ((int) ($row['count'] ?? 0) > 0) {
                        $rows[] = ['skill' => (int) ($row['skill'] ?? 0), 'domain' => (int) ($row['domain'] ?? 0), 'count' => (int) $row['count'],
                                   'difficulty' => (int) ($row['difficulty'] ?? 0), 'cognitive' => (string) ($row['cognitive'] ?? '')];
                    }
                }
                if ($request->int('random_count') > 0) {
                    $rows[] = ['count' => $request->int('random_count'), 'difficulty' => $request->int('random_difficulty')];
                }
                [$added, $notes] = TestEngine::generate($id, $rows);
                flash($added ? 'success' : 'warning', "ولّد المولّد $added سؤالاً من البنك." . ($notes ? ' نقص: ' . implode('؛ ', $notes) : ''));
                break;
        }
        Audit::log('test_update', 'tests', $id, 'أسئلة: ' . $action);
        redirect($back);
    }

    public function assign(Request $request): never
    {
        $test = $this->load($request, true);
        $classIds = array_values(array_filter($request->ints('class_ids'), [Scope::class, 'canClass']));
        $studentIds = array_values(array_filter($request->ints('student_ids'), [Scope::class, 'canStudent']));
        TestEngine::assign((int) $test['id'], $classIds, $studentIds);
        if ($test['status'] === 'published') {
            $users = DB::column('SELECT user_id FROM students WHERE id IN (' . implode(',', TestEngine::targetStudentIds((int) $test['id']) ?: [0]) . ')');
            Notifier::send($users, 'test_assigned', 'اختبار جديد: ' . $test['title'], null, '/student/tests', 'test-assigned-' . $test['id']);
        }
        Audit::log('test_update', 'tests', (int) $test['id'], ['classes' => $classIds, 'students' => count($studentIds)]);
        flash('success', 'تم حفظ الإسناد: ' . count($classIds) . ' فصل و' . count($studentIds) . ' طالب بشكل فردي.');
        redirect('/tests/' . $test['id'] . '#assign');
    }

    public function status(Request $request): never
    {
        $test = $this->load($request, true);
        $status = $request->str('status');
        if (!isset(TestEngine::STATUSES[$status])) {
            abort(422);
        }
        $errors = TestEngine::setStatus((int) $test['id'], $status);
        if ($errors) {
            back_with_errors($errors, '/tests/' . $test['id']);
        }
        Audit::log('test_status', 'tests', (int) $test['id'], $status);
        flash('success', 'حالة الاختبار الآن: ' . TestEngine::STATUSES[$status][0] . ($status === 'published' ? ' — أُشعر الطلاب.' : ''));
        redirect('/tests/' . $test['id']);
    }

    public function release(Request $request): never
    {
        $test = $this->load($request, true);
        DB::update('tests', ['results_released' => 1], 'id = :id', ['id' => $test['id']]);
        $rows = DB::all("SELECT a.id, s.user_id FROM test_attempts a JOIN students s ON s.id = a.student_id WHERE a.test_id = ? AND a.status <> 'in_progress'", [$test['id']]);
        foreach ($rows as $r) {
            Notifier::send([(int) $r['user_id']], 'result_ready', 'أُعلنت نتيجة: ' . $test['title'], null, '/student/results/' . $r['id'], 'result-' . $r['id']);
        }
        Audit::log('results_release', 'tests', (int) $test['id']);
        flash('success', 'تم إعلان النتائج وإشعار ' . count($rows) . ' طالب.');
        redirect('/tests/' . $test['id'] . '/results');
    }

    public function duplicate(Request $request): never
    {
        $test = $this->load($request);
        $this->authorize('tests.create');
        $id = TestEngine::duplicate((int) $test['id']);
        Audit::log('test_create', 'tests', $id, 'نسخ من #' . $test['id']);
        flash('success', 'تم نسخ الاختبار كمسودة — حدّث الإعدادات والإسناد ثم انشره.');
        redirect('/tests/' . $id);
    }

    public function destroy(Request $request): never
    {
        $test = $this->load($request, true);
        if (TestEngine::hasAttempts((int) $test['id'])) {
            DB::update('tests', ['status' => 'archived'], 'id = :id', ['id' => $test['id']]);
            flash('warning', 'للاختبار محاولات ونتائج؛ تمت أرشفته بدلاً من حذفه.');
        } else {
            DB::delete('tests', 'id = ?', [$test['id']]);
            flash('success', 'تم حذف الاختبار.');
        }
        Audit::log('test_delete', 'tests', (int) $test['id']);
        redirect('/tests');
    }

    /** نتائج الاختبار (ملخص سريع — التحليل التفصيلي في مركز التقارير) */
    public function results(Request $request): string
    {
        $test = $this->load($request);
        TestEngine::sweepExpired(200, (int) $test['id']);
        $targets = TestEngine::targetStudentIds((int) $test['id']);
        $rows = [];
        if ($targets) {
            [$in, $p] = DB::in($targets);
            [$scopeSql, $sp] = Scope::classFilter('e.class_id');
            $rows = DB::all(
                "SELECT s.id AS student_id, s.student_number, u.full_name, c.name AS class_name, g.name AS grade_name,
                        (SELECT COUNT(*) FROM test_attempts a WHERE a.test_id = ? AND a.student_id = s.id) AS attempts,
                        (SELECT a.status FROM test_attempts a WHERE a.test_id = ? AND a.student_id = s.id ORDER BY a.attempt_no DESC LIMIT 1) AS last_status,
                        (SELECT a.id FROM test_attempts a JOIN test_results r ON r.attempt_id = a.id WHERE a.test_id = ? AND a.student_id = s.id ORDER BY r.percent DESC LIMIT 1) AS best_attempt,
                        (SELECT MAX(r.percent) FROM test_results r WHERE r.test_id = ? AND r.student_id = s.id) AS best_percent
                   FROM students s JOIN users u ON u.id = s.user_id
              LEFT JOIN student_class_enrollments e ON e.student_id = s.id AND e.status = 'active'
              LEFT JOIN classes c ON c.id = e.class_id LEFT JOIN grades g ON g.id = c.grade_id
                  WHERE s.id IN ($in) AND ($scopeSql OR NOT EXISTS (SELECT 1 FROM test_assignments ta WHERE ta.test_id = ? AND ta.class_id IS NOT NULL))
               ORDER BY c.name + 0, u.full_name",
                array_merge([$test['id'], $test['id'], $test['id'], $test['id']], $p, $sp, [$test['id']])
            );
        }
        $done = array_filter($rows, static fn ($r) => $r['best_percent'] !== null);
        $avg = $done ? array_sum(array_map(static fn ($r) => (float) $r['best_percent'], $done)) / count($done) : null;
        return $this->view('tests/results', ['title' => 'نتائج: ' . $test['title'], 'test' => $test, 'rows' => $rows, 'avg' => $avg,
            'done' => count($done), 'canManage' => TestEngine::canManage($test)]);
    }

    public function resetAttempt(Request $request): never
    {
        $test = $this->load($request);
        $this->authorize('results.grade');
        $studentId = $request->int('student_id');
        Scope::authorizeStudent($studentId);
        $n = TestEngine::resetAttempts((int) $test['id'], $studentId);
        Audit::log('attempt_reset', 'tests', (int) $test['id'], ['student_id' => $studentId, 'attempts' => $n]);
        flash('success', "تم حذف $n محاولة؛ يستطيع الطالب إعادة الاختبار.");
        redirect('/tests/' . $test['id'] . '/results');
    }

    /** عرض محاولة طالب للمعلم */
    public function attempt(Request $request): string
    {
        $attempt = TestEngine::attempt((int) $request->param('id'));
        if (!$attempt) {
            throw new HttpException(404);
        }
        Scope::authorizeStudent((int) $attempt['student_id']);
        if ($attempt['status'] === 'in_progress') {
            return $this->view('tests/attempt_progress', ['title' => 'محاولة جارية', 'attempt' => $attempt,
                'answered' => (int) DB::value('SELECT COUNT(*) FROM attempt_answers WHERE attempt_id = ? AND answer IS NOT NULL', [$attempt['id']], 0),
                'total' => (int) DB::value('SELECT COUNT(*) FROM attempt_answers WHERE attempt_id = ?', [$attempt['id']], 0)]);
        }
        return $this->view('tests/attempt', ['title' => $attempt['student_name'] . ' — ' . $attempt['title']] + Analytics::attemptReport((int) $attempt['id'], false));
    }

    /** نسخة ورقية للطباعة (مع مفتاح الإجابات اختيارياً) */
    public function paper(Request $request): string
    {
        $test = $this->load($request);
        return $this->view('tests/paper', ['title' => $test['title'], 'reportTitle' => $test['title'], 'test' => $test,
            'questions' => TestEngine::questions((int) $test['id']), 'key' => $request->bool('key') && TestEngine::canManage($test),
            'reportMeta' => ($test['subject_name'] ?? 'شامل') . ' — ' . $test['grade_name']], 'layouts/print');
    }

    /* ======================= التصحيح اليدوي ======================= */

    public function gradingQueue(Request $request): string
    {
        [$scopeSql, $params] = Scope::classFilter('a.class_id');
        $where = "aa.needs_manual = 1 AND $scopeSql";
        if ($request->int('attempt')) {
            $where .= ' AND a.id = ?';
            $params[] = $request->int('attempt');
        }
        $items = DB::all(
            "SELECT aa.id, aa.answer, aa.attempt_id, tq.snapshot, tq.points, t.title, u.full_name, c.name AS class_name
               FROM attempt_answers aa JOIN test_attempts a ON a.id = aa.attempt_id JOIN tests t ON t.id = a.test_id
               JOIN test_questions tq ON tq.id = aa.test_question_id JOIN students s ON s.id = a.student_id JOIN users u ON u.id = s.user_id
          LEFT JOIN classes c ON c.id = a.class_id
              WHERE $where ORDER BY a.submitted_at LIMIT 100",
            $params
        );
        foreach ($items as &$it) {
            $it['snap'] = json_decode($it['snapshot'], true);
            $it['ans'] = json_decode((string) $it['answer'], true) ?: [];
        }
        return $this->view('tests/grading', ['title' => 'التصحيح اليدوي', 'items' => $items]);
    }

    public function grade(Request $request): never
    {
        $row = DB::first('SELECT a.student_id FROM attempt_answers aa JOIN test_attempts a ON a.id = aa.attempt_id WHERE aa.id = ? AND aa.needs_manual = 1', [(int) $request->param('id')]);
        if (!$row) {
            throw new HttpException(404, 'الإجابة غير موجودة أو صُححت مسبقاً.');
        }
        Scope::authorizeStudent((int) $row['student_id']);
        TestEngine::gradeManually((int) $request->param('id'), (float) $request->str('score'), $request->str('note') ?: null);
        Audit::log('manual_grade', 'attempt_answers', (int) $request->param('id'), ['score' => $request->str('score')]);
        flash('success', 'تم حفظ الدرجة وتحديث نتيجة الطالب.');
        back('/grading');
    }
}
