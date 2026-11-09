<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Models\ClassRoom;
use App\Services\Audit;

/** التصنيفات: الصف ← المادة ← المجال ← المهارة ← مؤشر الأداء */
final class TaxonomyController extends Controller
{
    public function index(Request $request): string
    {
        $grades   = ClassRoom::nafesGrades();
        $subjects = ClassRoom::subjects();
        $gradeId   = $request->int('grade') ?: (int) ($grades[0]['id'] ?? 0);
        $subjectId = $request->int('subject') ?: (int) ($subjects[0]['id'] ?? 0);

        $domains = DB::all('SELECT d.*, (SELECT COUNT(*) FROM questions q WHERE q.domain_id = d.id AND q.status = \'published\') AS q_count
                              FROM domains d WHERE d.subject_id = ? AND (d.grade_id = ? OR d.grade_id IS NULL) ORDER BY d.sort_order, d.id', [$subjectId, $gradeId]);
        $skills = [];
        $indicators = [];
        if ($domains) {
            [$in, $p] = DB::in(array_column($domains, 'id'));
            foreach (DB::all("SELECT s.*, (SELECT COUNT(*) FROM questions q WHERE q.skill_id = s.id AND q.status = 'published') AS q_count FROM skills s WHERE s.domain_id IN ($in) ORDER BY s.sort_order, s.id", $p) as $s) {
                $skills[(int) $s['domain_id']][] = $s;
            }
            foreach (DB::all("SELECT i.* FROM learning_indicators i JOIN skills s ON s.id = i.skill_id WHERE s.domain_id IN ($in) ORDER BY i.sort_order, i.id", $p) as $i) {
                $indicators[(int) $i['skill_id']][] = $i;
            }
        }
        return $this->view('taxonomy/index', [
            'title' => 'المجالات والمهارات', 'grades' => $grades, 'subjects' => $subjects, 'gradeId' => $gradeId, 'subjectId' => $subjectId,
            'domains' => $domains, 'skills' => $skills, 'indicators' => $indicators, 'canManage' => Auth::can('taxonomy.manage'),
        ]);
    }

    /** إضافة أو تعديل أو حذف عنصر — entity: domain|skill|indicator */
    public function save(Request $request): never
    {
        $entity = $request->str('entity');
        $action = $request->str('action', 'save');
        $id     = $request->int('id');
        $back   = '/taxonomy?' . http_build_query(['subject' => $request->int('subject'), 'grade' => $request->int('grade')]);
        $name   = $request->str('name');
        $code   = $request->str('code');

        [$table, $nameCol] = match ($entity) {
            'domain'    => ['domains', 'name'],
            'skill'     => ['skills', 'name'],
            'indicator' => ['learning_indicators', 'description'],
            default     => abort(422),
        };

        if ($action === 'delete') {
            $used = match ($entity) {
                'domain'    => (int) DB::value('SELECT COUNT(*) FROM questions WHERE domain_id = ?', [$id], 0),
                'skill'     => (int) DB::value('SELECT COUNT(*) FROM questions WHERE skill_id = ?', [$id], 0),
                'indicator' => (int) DB::value('SELECT COUNT(*) FROM questions WHERE indicator_id = ?', [$id], 0),
            };
            if ($used) {
                back_with_errors(["لا يمكن الحذف: العنصر مرتبط بـ $used سؤال. انقل الأسئلة لتصنيف آخر أولاً."], $back);
            }
            DB::delete($table, 'id = ?', [$id]);
            Audit::log('structure_update', $table, $id, 'حذف تصنيف');
            flash('success', 'تم الحذف.');
            redirect($back);
        }

        if ($name === '' || mb_strlen($name) > 255) {
            back_with_errors(['الاسم مطلوب (255 حرفاً كحد أقصى).'], $back);
        }
        $data = [$nameCol => $name, 'sort_order' => $request->int('sort_order')];
        if ($entity !== 'domain') {
            $data['code'] = $code !== '' ? mb_substr($code, 0, 30) : null;
        }
        try {
            if ($id) {
                DB::update($table, $data, 'id = :id', ['id' => $id]);
            } else {
                $parent = match ($entity) {
                    'domain'    => ['subject_id' => $request->int('subject'), 'grade_id' => $request->bool('all_grades') ? null : ($request->int('grade') ?: null)],
                    'skill'     => ['domain_id' => $request->int('parent_id')],
                    'indicator' => ['skill_id' => $request->int('parent_id')],
                };
                if (in_array(0, $parent, true) || ($entity === 'domain' && !$parent['subject_id'])) {
                    back_with_errors(['العنصر الأب غير محدد.'], $back);
                }
                if (!$data['sort_order']) {
                    $col = array_key_first($parent);
                    $data['sort_order'] = (int) DB::value("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM $table WHERE $col <=> ?", [$parent[$col]], 1);
                }
                $id = DB::insert($table, $data + $parent);
            }
        } catch (\PDOException $e) {
            back_with_errors(['يوجد عنصر بالاسم نفسه في المستوى ذاته.'], $back);
        }
        Audit::log('structure_update', $table, $id, $data);
        flash('success', 'تم الحفظ.');
        redirect($back);
    }

    /** بيانات المهارات والمؤشرات لنموذج السؤال (JSON) */
    public function tree(Request $request): never
    {
        $subjectId = $request->int('subject');
        $gradeId   = $request->int('grade');
        $domains = DB::all('SELECT id, name FROM domains WHERE subject_id = ? AND (grade_id = ? OR grade_id IS NULL) ORDER BY sort_order, id', [$subjectId, $gradeId]);
        foreach ($domains as &$d) {
            $d['skills'] = DB::all('SELECT id, code, name FROM skills WHERE domain_id = ? ORDER BY sort_order, id', [$d['id']]);
            foreach ($d['skills'] as &$s) {
                $s['indicators'] = DB::all('SELECT id, code, description FROM learning_indicators WHERE skill_id = ? ORDER BY sort_order, id', [$s['id']]);
            }
        }
        $passages = DB::all('SELECT id, title FROM passages WHERE subject_id = ? AND grade_id = ? ORDER BY title', [$subjectId, $gradeId]);
        json_response(['ok' => true, 'domains' => $domains, 'passages' => $passages]);
    }
}
