<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Models\ClassRoom;
use App\Models\Student;
use App\Services\Analytics;
use App\Services\Audit;
use App\Services\Levels;
use App\Services\QuestionRenderer;
use App\Services\Settings;
use App\Services\TestEngine;

/** بوابة الطالب */
final class StudentPortalController extends Controller
{
    private function student(): array
    {
        $student = Student::byUser((int) Auth::id());
        if (!$student) {
            throw new HttpException(403, 'لا يوجد سجل طالب مرتبط بحسابك.');
        }
        return $student;
    }

    /** محاولة تخص الطالب الحالي فقط */
    private function ownAttempt(Request $request): array
    {
        $attempt = TestEngine::attempt((int) $request->param('id'));
        if (!$attempt || (int) $attempt['student_id'] !== $this->student()['id']) {
            throw new HttpException(404);
        }
        return $attempt;
    }

    public function home(Request $request): string
    {
        $student = $this->student();
        $tests = TestEngine::forStudent((int) $student['id']);
        $open = array_values(array_filter($tests, static fn ($t) => in_array($t['state'], ['available', 'in_progress'], true)));
        $results = DB::all(
            "SELECT r.*, t.title, t.type, s.name AS subject_name, a.id AS attempt_id FROM test_results r JOIN test_attempts a ON a.id = r.attempt_id
               JOIN tests t ON t.id = r.test_id LEFT JOIN subjects s ON s.id = t.subject_id
              WHERE r.student_id = ? ORDER BY r.created_at DESC LIMIT 5",
            [$student['id']]
        );
        return $this->view('student/home', [
            'title' => 'الرئيسية', 'student' => $student, 'open' => $open, 'results' => $results,
            'subjects' => Analytics::studentSubjects((int) $student['id']),
            'weak' => array_slice(Analytics::studentWeakSkills((int) $student['id']), 0, 4),
            'extra' => class_exists(\App\Services\Gamification::class) ? \App\Services\Gamification::summary((int) $student['id']) : null,
        ]);
    }

    public function tests(Request $request): string
    {
        $student = $this->student();
        return $this->view('student/tests', ['title' => 'اختباراتي', 'tests' => TestEngine::forStudent((int) $student['id'])]);
    }

    /** صفحة التعليمات قبل البدء */
    public function intro(Request $request): string
    {
        $student = $this->student();
        $test = TestEngine::find((int) $request->param('id'));
        if (!$test || !TestEngine::isAssignedTo((int) $test['id'], (int) $student['id']) || !in_array($test['status'], ['published', 'closed'], true)) {
            throw new HttpException(404);
        }
        $attempts = DB::all('SELECT * FROM test_attempts WHERE test_id = ? AND student_id = ? ORDER BY attempt_no', [$test['id'], $student['id']]);
        return $this->view('student/intro', ['title' => $test['title'], 'test' => $test, 'attempts' => $attempts, 'state' => TestEngine::stateFor($test, $attempts)]);
    }

    public function start(Request $request): never
    {
        $student = $this->student();
        try {
            $attemptId = TestEngine::start((int) $request->param('id'), (int) $student['id']);
        } catch (HttpException $ex) {
            // انتهاء المحاولات أو الموعد: رسالة واضحة في صفحة الاختبار بدلاً من صفحة خطأ
            if (!in_array($ex->status, [403, 422], true) || !TestEngine::isAssignedTo((int) $request->param('id'), (int) $student['id'])) {
                throw $ex;
            }
            flash('danger', $ex->getMessage());
            redirect('/student/tests/' . (int) $request->param('id'));
        }
        redirect('/student/attempts/' . $attemptId);
    }

    /** واجهة الاختبار */
    public function exam(Request $request): string
    {
        $attempt = $this->ownAttempt($request);
        if (TestEngine::isExpired($attempt)) {
            TestEngine::submit((int) $attempt['id'], true);
            flash('warning', 'انتهى وقت الاختبار، وتم تسليم إجاباتك المحفوظة تلقائياً.');
            redirect('/student/attempts/' . $attempt['id'] . '/done');
        }
        if ($attempt['status'] !== 'in_progress') {
            redirect('/student/attempts/' . $attempt['id'] . '/done');
        }
        $items = TestEngine::attemptItems((int) $attempt['id']);
        foreach ($items as &$item) {
            // لا تُرسل أي معلومة عن الإجابة الصحيحة إلى المتصفح، ولا أرقام الخيارات الحقيقية
            $item['snap'] = QuestionRenderer::stripAnswers($item['snap']);
            $item = TestEngine::maskItem((int) $attempt['id'], $item);
        }
        return $this->view('student/exam', [
            'title' => $attempt['title'], 'attempt' => $attempt, 'items' => $items, 'secondsLeft' => TestEngine::secondsLeft($attempt),
            'autosave' => max(10, Settings::int('autosave_seconds')),
        ], 'layouts/exam');
    }

    /** حفظ الإجابات (JSON) — يُستدعى تلقائياً وعند استعادة الاتصال */
    public function save(Request $request): never
    {
        $attempt = $this->ownAttempt($request);
        $payload = $request->json();
        $answers = is_array($payload['answers'] ?? null) ? $payload['answers'] : [];
        $flags = is_array($payload['flags'] ?? null) ? $payload['flags'] : [];
        $result = TestEngine::saveAnswers($attempt, $answers, $flags);
        json_response($result + ['server_time' => time()], $result['ok'] ? 200 : 409);
    }

    public function submit(Request $request): never
    {
        $attempt = $this->ownAttempt($request);
        if ($attempt['status'] === 'in_progress') {
            // الإجابات المرسلة مع النموذج تُحفظ أولاً (تعمل حتى بدون جافاسكربت)
            $answers = is_array($_POST['a'] ?? null) ? $_POST['a'] : [];
            if ($answers && !TestEngine::isExpired($attempt)) {
                TestEngine::saveAnswers($attempt, $answers);
            }
            TestEngine::submit((int) $attempt['id'], TestEngine::isExpired($attempt));
            Audit::log('attempt_submit', 'test_attempts', (int) $attempt['id']);
        }
        redirect('/student/attempts/' . $attempt['id'] . '/done');
    }

    /** شاشة تأكيد التسليم */
    public function done(Request $request): string
    {
        $attempt = $this->ownAttempt($request);
        if ($attempt['status'] === 'in_progress') {
            redirect('/student/attempts/' . $attempt['id']);
        }
        $answered = (int) DB::value('SELECT COUNT(*) FROM attempt_answers WHERE attempt_id = ? AND answer IS NOT NULL', [$attempt['id']], 0);
        $total = (int) DB::value('SELECT COUNT(*) FROM attempt_answers WHERE attempt_id = ?', [$attempt['id']], 0);
        return $this->view('student/done', ['title' => 'تم التسليم', 'attempt' => $attempt, 'answered' => $answered, 'total' => $total,
            'visible' => TestEngine::resultVisible($attempt)]);
    }

    public function results(Request $request): string
    {
        $student = $this->student();
        $rows = DB::all(
            "SELECT a.id AS attempt_id, a.status, a.submitted_at, a.attempt_no, t.title, t.type, t.result_visibility, t.results_released,
                    t.status AS test_status, t.available_until, s.name AS subject_name, r.percent, r.level, r.score, r.max_score, r.is_final
               FROM test_attempts a JOIN tests t ON t.id = a.test_id LEFT JOIN subjects s ON s.id = t.subject_id
          LEFT JOIN test_results r ON r.attempt_id = a.id
              WHERE a.student_id = ? AND a.status <> 'in_progress' ORDER BY a.submitted_at DESC",
            [$student['id']]
        );
        foreach ($rows as &$r) {
            $r['visible'] = TestEngine::resultVisible($r + ['status' => $r['status']]);
        }
        return $this->view('student/results', ['title' => 'نتائجي', 'rows' => $rows]);
    }

    /** تقرير المحاولة للطالب */
    public function result(Request $request): string
    {
        $attempt = $this->ownAttempt($request);
        if ($attempt['status'] === 'in_progress') {
            redirect('/student/attempts/' . $attempt['id']);
        }
        if (!TestEngine::resultVisible($attempt)) {
            return $this->view('student/done', ['title' => $attempt['title'], 'attempt' => $attempt, 'answered' => null, 'total' => null, 'visible' => false]);
        }
        return $this->view('student/result', ['title' => 'نتيجة: ' . $attempt['title']] + Analytics::attemptReport((int) $attempt['id'], true));
    }

    public function practice(Request $request): string
    {
        $student = $this->student();
        $gradeId = (int) ($student['grade_id'] ?? 0);
        $mastery = Analytics::studentSkillMastery((int) $student['id']);
        $subjects = [];
        foreach (ClassRoom::subjects() as $s) {
            $domains = DB::all('SELECT id, name FROM domains WHERE subject_id = ? AND (grade_id = ? OR grade_id IS NULL) ORDER BY sort_order', [$s['id'], $gradeId]);
            foreach ($domains as &$d) {
                $d['skills'] = DB::all(
                    "SELECT s.id, s.code, s.name, (SELECT COUNT(*) FROM questions q WHERE q.skill_id = s.id AND q.status = 'published' AND q.grade_id = ?) AS available
                       FROM skills s WHERE s.domain_id = ? ORDER BY s.sort_order",
                    [$gradeId, $d['id']]
                );
            }
            $s['domains'] = $domains;
            $s['available'] = (int) DB::value("SELECT COUNT(*) FROM questions WHERE subject_id = ? AND grade_id = ? AND status = 'published'", [$s['id'], $gradeId], 0);
            if ($s['available']) {
                $subjects[] = $s;
            }
        }
        return $this->view('student/practice', ['title' => 'التدريب الذاتي', 'subjects' => $subjects, 'mastery' => $mastery,
            'size' => Settings::int('practice_size')]);
    }

    public function startPractice(Request $request): never
    {
        $student = $this->student();
        $attemptId = TestEngine::createPractice((int) $student['id'], $request->int('subject'), $request->int('skill'));
        redirect('/student/attempts/' . $attemptId);
    }

    public function progress(Request $request): string
    {
        $student = $this->student();
        return $this->view('student/progress', [
            'title' => 'تقدمي في المهارات', 'subjects' => Analytics::studentSubjects((int) $student['id']),
            'tree' => Analytics::studentSkillTree((int) $student['id'], (int) ($student['grade_id'] ?? 0)),
            'trend' => Analytics::studentTrend((int) $student['id']),
        ]);
    }
}
