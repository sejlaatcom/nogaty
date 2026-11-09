<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Models\ClassRoom;
use App\Services\Audit;
use App\Services\Uploads;
use App\Validators\Validator;

/** النصوص القرائية التي يتبعها عدة أسئلة */
final class PassageController extends Controller
{
    public function index(Request $request): string
    {
        $subject = $request->int('subject');
        $grade   = $request->int('grade');
        $where = ['1 = 1'];
        $params = [];
        if ($subject) {
            $where[] = 'p.subject_id = ?';
            $params[] = $subject;
        }
        if ($grade) {
            $where[] = 'p.grade_id = ?';
            $params[] = $grade;
        }
        $passages = DB::all(
            'SELECT p.*, s.name AS subject_name, g.name AS grade_name, u.full_name AS author,
                    (SELECT COUNT(*) FROM questions q WHERE q.passage_id = p.id AND q.status <> \'archived\') AS q_count
               FROM passages p JOIN subjects s ON s.id = p.subject_id JOIN grades g ON g.id = p.grade_id LEFT JOIN users u ON u.id = p.created_by
              WHERE ' . implode(' AND ', $where) . ' ORDER BY p.id DESC',
            $params
        );
        return $this->view('passages/index', [
            'title' => 'النصوص القرائية', 'passages' => $passages, 'subjects' => ClassRoom::subjects(), 'grades' => ClassRoom::nafesGrades(),
            'filters' => compact('subject', 'grade'), 'canEdit' => Auth::can('questions.create'),
        ]);
    }

    public function form(Request $request): string
    {
        $id = (int) $request->param('id');
        $passage = $id ? $this->notFound(DB::first('SELECT * FROM passages WHERE id = ?', [$id])) : null;
        $media = $id ? DB::all('SELECT * FROM question_media WHERE passage_id = ?', [$id]) : [];
        $questions = $id ? DB::all('SELECT id, stem, type, status FROM questions WHERE passage_id = ? ORDER BY id', [$id]) : [];
        return $this->view('passages/form', [
            'title' => $passage ? 'تعديل نص' : 'نص قرائي جديد', 'passage' => $passage, 'media' => $media, 'questions' => $questions,
            'subjects' => ClassRoom::subjects(), 'grades' => ClassRoom::nafesGrades(),
        ]);
    }

    public function save(Request $request): never
    {
        $id = (int) $request->param('id');
        $passage = $id ? $this->notFound(DB::first('SELECT * FROM passages WHERE id = ?', [$id])) : null;
        if ($passage && (int) $passage['created_by'] !== Auth::id() && !Auth::can('questions.edit_any')) {
            abort(403, 'لا يمكنك تعديل نص أضافه مستخدم آخر.');
        }
        $data = ['title' => $request->str('title'), 'body' => trim((string) ($_POST['body'] ?? '')), 'source' => $request->str('source'),
                 'subject_id' => $request->int('subject_id'), 'grade_id' => $request->int('grade_id')];
        $errors = Validator::make($data, ['title' => 'required|max:200', 'body' => 'required|max:20000', 'subject_id' => 'required|int', 'grade_id' => 'required|int', 'source' => 'nullable|max:255'],
            ['title' => 'العنوان', 'body' => 'النص', 'subject_id' => 'المادة', 'grade_id' => 'الصف', 'source' => 'المصدر']);
        if ($errors) {
            back_with_errors($errors, $id ? "/passages/$id/edit" : '/passages/create');
        }
        $data['source'] = $data['source'] ?: null;
        if ($id) {
            DB::update('passages', $data, 'id = :id', ['id' => $id]);
        } else {
            $id = DB::insert('passages', $data + ['created_by' => Auth::id()]);
        }
        if ($file = $request->file('image')) {
            $saved = Uploads::storeImage($file, 'passages');
            if (isset($saved['error'])) {
                flash('warning', $saved['error']);
            } else {
                DB::insert('question_media', ['passage_id' => $id, 'file_path' => $saved['path'], 'mime' => $saved['mime'], 'size' => $saved['size'],
                    'alt_text' => $request->str('alt_text') ?: null, 'uploaded_by' => Auth::id()]);
            }
        }
        foreach ($request->ints('remove_media') as $mid) {
            $path = DB::value('SELECT file_path FROM question_media WHERE id = ? AND passage_id = ?', [$mid, $id]);
            if ($path) {
                DB::delete('question_media', 'id = ?', [$mid]);
                Uploads::delete($path);
            }
        }
        Audit::log('question_update', 'passages', $id, ['title' => $data['title']]);
        flash('success', 'تم حفظ النص. تُحدَّث الاختبارات الجديدة فقط؛ الاختبارات المنشأة تحتفظ بنسختها.');
        redirect("/passages/$id/edit");
    }

    public function destroy(Request $request): never
    {
        $id = (int) $request->param('id');
        $this->notFound(DB::first('SELECT id FROM passages WHERE id = ?', [$id]));
        $this->authorize('questions.edit_any');
        if (DB::value("SELECT 1 FROM questions WHERE passage_id = ? AND status <> 'archived' LIMIT 1", [$id])) {
            back_with_errors(['النص مرتبط بأسئلة؛ انقلها أو أرشفها أولاً.'], '/passages');
        }
        DB::delete('passages', 'id = ?', [$id]);
        flash('success', 'تم حذف النص.');
        redirect('/passages');
    }
}
