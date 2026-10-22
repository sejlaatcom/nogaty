<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Models\ClassRoom;
use App\Services\Audit;
use App\Services\QuestionBank;
use App\Services\QuestionRenderer;
use App\Services\Settings;
use App\Services\Spreadsheet;
use App\Services\Uploads;

/** بنك الأسئلة */
final class QuestionController extends Controller
{
    private function filters(Request $request): array
    {
        $f = [];
        foreach (['subject', 'grade', 'domain', 'skill', 'difficulty', 'passage'] as $k) {
            $f[$k] = $request->int($k);
        }
        foreach (['type', 'cognitive', 'status', 'q'] as $k) {
            $f[$k] = $request->str($k);
        }
        $f['mine'] = $request->bool('mine') ? Auth::id() : 0;
        $f['include_archived'] = $f['status'] === 'archived';
        if (!Auth::can('questions.edit_any', 'questions.review') && !Settings::bool('teacher_sees_all_bank')) {
            $f['visibility_user'] = Auth::id();
        }
        return $f;
    }

    public function index(Request $request): string
    {
        $filters = $this->filters($request);
        $result  = QuestionBank::search($filters, max(1, $request->int('page', 1)));
        $counts  = DB::all("SELECT status, COUNT(*) n FROM questions GROUP BY status");
        return $this->view('questions/index', [
            'title' => 'بنك الأسئلة', 'result' => $result, 'filters' => $filters,
            'subjects' => ClassRoom::subjects(), 'grades' => ClassRoom::nafesGrades(),
            'domains' => $filters['subject'] ? DB::all('SELECT id, name FROM domains WHERE subject_id = ? ORDER BY sort_order', [$filters['subject']]) : [],
            'skills' => $filters['subject'] ? DB::all('SELECT s.id, s.code, s.name FROM skills s JOIN domains d ON d.id = s.domain_id WHERE d.subject_id = ?' . ($filters['domain'] ? ' AND d.id = ' . (int) $filters['domain'] : '') . ' ORDER BY d.sort_order, s.sort_order', [$filters['subject']]) : [],
            'counts' => array_column($counts, 'n', 'status'),
        ]);
    }

    public function create(Request $request): string
    {
        $q = [
            'id' => 0, 'type' => $request->str('type', 'single'), 'subject_id' => $request->int('subject'), 'grade_id' => $request->int('grade'),
            'skill_id' => $request->int('skill'), 'indicator_id' => 0, 'passage_id' => $request->int('passage'), 'stem' => '', 'explanation' => '',
            'common_mistakes' => '', 'difficulty' => 2, 'cognitive_level' => 'applying', 'points' => 1, 'manual_grading' => 0,
            'source' => '', 'usage_rights' => '', 'status' => 'draft', 'options' => [], 'media' => [],
        ];
        if (!$q['grade_id']) {
            $q['grade_id'] = (int) (ClassRoom::nafesGrades()[0]['id'] ?? 0);
        }
        if ($from = $request->int('copy')) {
            $source = $this->notFound(QuestionBank::find($from));
            $q = array_merge($source, ['id' => 0, 'status' => 'draft', 'media' => []]);
        }
        return $this->form($q, 'سؤال جديد');
    }

    public function edit(Request $request): string
    {
        $q = $this->notFound(QuestionBank::find((int) $request->param('id')));
        if (!QuestionBank::canEdit($q)) {
            abort(403, 'لا يمكنك تعديل سؤال أضافه مستخدم آخر — يمكنك نسخه وتعديل النسخة.');
        }
        return $this->form($q, 'تعديل السؤال #' . $q['id']);
    }

    private function form(array $q, string $title): string
    {
        $old = \App\Core\Session::get('_old_current');
        if ($old) {
            $q = array_merge($q, array_intersect_key($old, $q));
            $q['options'] = $this->optionsFromInput($old, $q['type'])[0] ?: $q['options'];
        }
        return $this->view('questions/form', [
            'title' => $title, 'q' => $q, 'subjects' => ClassRoom::subjects(), 'grades' => ClassRoom::nafesGrades(),
            'canPublish' => QuestionBank::canPublish(),
        ]);
    }

    /** استخراج الخيارات من مدخلات النموذج حسب النوع — يُعيد [options, accepted] */
    private function optionsFromInput(array $input, string $type): array
    {
        $rows = array_values(array_filter((array) ($input['opt'] ?? []), 'is_array'));
        $correctSingle = (string) ($input['correct'] ?? '');
        $correctMulti  = array_map('strval', (array) ($input['correct_multi'] ?? []));
        $options = [];
        foreach ($rows as $i => $row) {
            $options[] = [
                'body' => (string) ($row['body'] ?? ''), 'match_text' => (string) ($row['match'] ?? ''), 'feedback' => (string) ($row['feedback'] ?? ''),
                'is_correct' => match ($type) {
                    'single'   => $correctSingle === (string) $i,
                    'multiple' => in_array((string) $i, $correctMulti, true),
                    default    => false,
                },
            ];
        }
        if ($type === 'true_false') {
            $options = [['body' => 'صح', 'is_correct' => ($input['tf'] ?? '1') === '1']];
        }
        $accepted = preg_split('/\R/u', (string) ($input['accepted'] ?? '')) ?: [];
        return [$options, $accepted];
    }

    public function store(Request $request): never
    {
        $this->authorize('questions.create');
        $this->persist($request, null);
    }

    public function update(Request $request): never
    {
        $q = $this->notFound(QuestionBank::find((int) $request->param('id')));
        if (!QuestionBank::canEdit($q)) {
            abort(403);
        }
        $this->persist($request, $q);
    }

    private function persist(Request $request, ?array $existing): never
    {
        $type = $request->str('type');
        [$options, $accepted] = $this->optionsFromInput($_POST, $type);
        $status = $request->str('status', 'draft');
        if (!isset(QuestionBank::STATUSES[$status]) || $status === 'archived') {
            $status = 'draft';
        }
        $data = [
            'type' => $type, 'subject_id' => $request->int('subject_id'), 'grade_id' => $request->int('grade_id'),
            'skill_id' => $request->int('skill_id') ?: null, 'indicator_id' => $request->int('indicator_id') ?: null,
            'passage_id' => $request->int('passage_id') ?: null, 'stem' => (string) ($_POST['stem'] ?? ''),
            'explanation' => (string) ($_POST['explanation'] ?? ''), 'common_mistakes' => (string) ($_POST['common_mistakes'] ?? ''),
            'difficulty' => $request->int('difficulty', 2), 'cognitive_level' => $request->str('cognitive_level', 'applying'),
            'points' => (float) $request->str('points', '1'), 'manual_grading' => $request->bool('manual_grading'),
            'source' => $request->str('source'), 'usage_rights' => $request->str('usage_rights'), 'status' => $status,
        ];
        $back = $existing ? '/questions/' . $existing['id'] . '/edit' : '/questions/create';
        [$id, $errors] = QuestionBank::save($existing ? (int) $existing['id'] : null, $data, $options, $accepted,
            $request->bool('allow_duplicate'), $request->str('change_note') ?: null);
        if ($errors) {
            back_with_errors($errors, $back);
        }

        // الوسائط
        foreach ($_FILES['media']['name'] ?? [] as $i => $name) {
            if (($_FILES['media']['error'][$i] ?? 4) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $file = ['name' => $name, 'tmp_name' => $_FILES['media']['tmp_name'][$i], 'size' => $_FILES['media']['size'][$i], 'error' => $_FILES['media']['error'][$i]];
            $saved = Uploads::storeImage($file, 'questions');
            if (isset($saved['error'])) {
                flash('warning', $saved['error']);
                continue;
            }
            DB::insert('question_media', ['question_id' => $id, 'file_path' => $saved['path'], 'mime' => $saved['mime'], 'size' => $saved['size'],
                'alt_text' => $request->str('media_alt') ?: null, 'uploaded_by' => Auth::id()]);
        }
        foreach ($request->ints('remove_media') as $mid) {
            $path = DB::value('SELECT file_path FROM question_media WHERE id = ? AND question_id = ?', [$mid, $id]);
            if ($path) {
                DB::delete('question_media', 'id = ?', [$mid]);
                if (!DB::value('SELECT 1 FROM question_media WHERE file_path = ?', [$path])) {
                    Uploads::delete($path);
                }
            }
        }

        Audit::log($existing ? 'question_update' : 'question_create', 'questions', (int) $id);
        $saved = QuestionBank::find((int) $id);
        $msg = $existing ? 'تم حفظ التعديلات (النسخة ' . $saved['version'] . ').' : 'تمت إضافة السؤال.';
        if ($status === 'published' && $saved['status'] === 'review') {
            $msg .= ' أُرسل للمراجعة قبل النشر.';
        }
        flash('success', $msg . ' الاختبارات المنشأة سابقاً تحتفظ بنسختها ولا تتأثر.');
        redirect($request->str('next') === 'new'
            ? '/questions/create?' . http_build_query(['type' => $type, 'subject' => $data['subject_id'], 'grade' => $data['grade_id'], 'skill' => $data['skill_id'], 'passage' => $data['passage_id']])
            : '/questions/' . $id);
    }

    public function show(Request $request): string
    {
        $q = $this->notFound(QuestionBank::find((int) $request->param('id')));
        $snapshot = QuestionBank::snapshot((int) $q['id']);
        $usage = DB::all('SELECT t.id, t.title, t.status FROM test_questions tq JOIN tests t ON t.id = tq.test_id WHERE tq.question_id = ? ORDER BY t.id DESC LIMIT 20', [$q['id']]);
        $stats = DB::first(
            'SELECT COUNT(*) n, SUM(aa.is_correct = 1) correct FROM attempt_answers aa JOIN test_questions tq ON tq.id = aa.test_question_id
              JOIN test_attempts a ON a.id = aa.attempt_id WHERE tq.question_id = ? AND a.status IN (\'submitted\',\'graded\')',
            [$q['id']]
        );
        return $this->view('questions/show', [
            'title' => 'السؤال #' . $q['id'], 'q' => $q, 'snapshot' => $snapshot, 'usage' => $usage, 'stats' => $stats,
            'revisions' => DB::all('SELECT r.*, u.full_name FROM question_revisions r LEFT JOIN users u ON u.id = r.changed_by WHERE r.question_id = ? ORDER BY r.version DESC', [$q['id']]),
            'canEdit' => QuestionBank::canEdit($q), 'canPublish' => QuestionBank::canPublish(),
        ]);
    }

    /** معاينة فورية من النموذج قبل الحفظ (JSON) */
    public function previewRender(Request $request): never
    {
        $type = $request->str('type');
        [$options, $accepted] = $this->optionsFromInput($_POST, $type);
        if ($type === 'true_false') {
            $isTrue = !empty($options[0]['is_correct']);
            $options = [['body' => 'صح', 'is_correct' => $isTrue], ['body' => 'خطأ', 'is_correct' => !$isTrue]];
        }
        if ($type === 'short_answer') {
            $options = array_map(static fn ($a) => ['body' => $a, 'is_correct' => true], array_filter(array_map('trim', $accepted)));
        }
        $i = 0;
        $q = [
            'type' => $type, 'stem' => (string) ($_POST['stem'] ?? ''), 'explanation' => (string) ($_POST['explanation'] ?? ''),
            'common_mistakes' => (string) ($_POST['common_mistakes'] ?? ''), 'manual' => $request->bool('manual_grading'), 'media' => [],
            'options' => array_map(static function ($o) use (&$i) {
                $i++;
                return ['id' => $i, 'body' => (string) $o['body'], 'match_text' => $o['match_text'] ?? '', 'is_correct' => !empty($o['is_correct']), 'position' => $i, 'feedback' => $o['feedback'] ?? ''];
            }, array_values(array_filter($options, static fn ($o) => trim((string) $o['body']) !== ''))),
        ];
        $passage = $request->int('passage_id') ? DB::first('SELECT id, title, body FROM passages WHERE id = ?', [$request->int('passage_id')]) : null;
        $student = QuestionRenderer::passage($passage) . QuestionRenderer::stem($q) . QuestionRenderer::body(QuestionRenderer::stripAnswers($q), 'take', 'preview');
        json_response(['ok' => true, 'student' => $student, 'teacher' => QuestionRenderer::body($q, 'preview')]);
    }

    public function duplicate(Request $request): never
    {
        $this->authorize('questions.create');
        $q = $this->notFound(QuestionBank::find((int) $request->param('id')));
        $id = QuestionBank::duplicate((int) $q['id']);
        Audit::log('question_create', 'questions', $id, 'نسخ من #' . $q['id']);
        flash('success', 'تم إنشاء نسخة كمسودة يمكنك تعديلها.');
        redirect("/questions/$id/edit");
    }

    public function status(Request $request): never
    {
        $ids = $request->ints('ids') ?: [(int) $request->param('id')];
        $status = $request->str('status');
        if (in_array($status, ['published', 'archived'], true)) {
            $this->authorize('questions.review', 'questions.edit_any');
        }
        $done = 0;
        foreach ($ids as $id) {
            $q = QuestionBank::find($id);
            if (!$q) {
                continue;
            }
            if ($status === 'review' && !QuestionBank::canEdit($q)) {
                continue;
            }
            if (QuestionBank::setStatus($id, $status, $request->str('note') ?: null)) {
                $done++;
            }
        }
        Audit::log('question_status', 'questions', count($ids) === 1 ? $ids[0] : null, ['status' => $status, 'count' => $done]);
        flash($done ? 'success' : 'warning', $done ? "تم تحديث حالة $done سؤال إلى «" . QuestionBank::STATUSES[$status][0] . '».' : 'لم يتم تحديث أي سؤال.');
        back('/questions');
    }

    public function destroy(Request $request): never
    {
        $q = $this->notFound(QuestionBank::find((int) $request->param('id')));
        if (!QuestionBank::canEdit($q)) {
            abort(403);
        }
        $result = QuestionBank::delete((int) $q['id']);
        Audit::log('question_status', 'questions', (int) $q['id'], $result);
        flash('success', $result === 'deleted' ? 'تم حذف السؤال.' : 'السؤال مستخدم في اختبارات، فتمت أرشفته بدلاً من حذفه.');
        redirect('/questions');
    }

    /** استعادة نسخة سابقة (تُحفظ النسخة الحالية في السجل) */
    public function restore(Request $request): never
    {
        $q = $this->notFound(QuestionBank::find((int) $request->param('id')));
        if (!QuestionBank::canEdit($q)) {
            abort(403);
        }
        $rev = $this->notFound(DB::first('SELECT * FROM question_revisions WHERE id = ? AND question_id = ?', [(int) $request->param('rid'), $q['id']]));
        $snap = json_decode($rev['snapshot'], true);
        $accepted = $snap['type'] === 'short_answer' ? array_column($snap['options'], 'body') : [];
        if ($snap['type'] === 'true_false') {
            $snap['options'] = [['body' => 'صح', 'is_correct' => (bool) ($snap['options'][0]['is_correct'] ?? true)]];
        }
        [, $errors] = QuestionBank::save((int) $q['id'], $snap + ['status' => $q['status']], $snap['options'], $accepted, true, 'استعادة النسخة ' . $rev['version']);
        if ($errors) {
            back_with_errors($errors, '/questions/' . $q['id']);
        }
        Audit::log('question_update', 'questions', (int) $q['id'], 'استعادة النسخة ' . $rev['version']);
        flash('success', 'تمت استعادة النسخة ' . $rev['version'] . '.');
        redirect('/questions/' . $q['id']);
    }

    public function export(Request $request): never
    {
        $this->authorize('questions.export');
        $rows = QuestionBank::exportRows($this->filters($request));
        Audit::log('question_export', 'questions', null, ['count' => count($rows) - 1]);
        $request->str('format') === 'csv'
            ? Spreadsheet::downloadCsv('بنك-الأسئلة.csv', $rows)
            : Spreadsheet::downloadXlsx('بنك-الأسئلة.xlsx', ['الأسئلة' => $rows]);
    }
}
