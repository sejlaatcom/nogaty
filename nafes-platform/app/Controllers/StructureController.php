<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\DB;
use App\Core\Request;
use App\Models\ClassRoom;
use App\Models\Year;
use App\Services\Audit;
use App\Validators\Validator;

/** العام الدراسي والصفوف والفصول والمواد */
final class StructureController extends Controller
{
    public function index(Request $request): string
    {
        $yearId = $request->int('year') ?: Year::currentId();
        return $this->view('structure/index', [
            'title'    => 'الهيكل المدرسي',
            'tab'      => $request->str('tab', 'classes'),
            'years'    => DB::all('SELECT y.*, (SELECT COUNT(*) FROM classes c WHERE c.academic_year_id = y.id) AS classes_count,
                                          (SELECT COUNT(*) FROM test_results r WHERE r.academic_year_id = y.id) AS results_count
                                     FROM academic_years y ORDER BY y.starts_on DESC, y.id DESC'),
            'yearId'   => $yearId,
            'grades'   => DB::all('SELECT g.*, (SELECT COUNT(*) FROM classes c WHERE c.grade_id = g.id AND c.academic_year_id = ?) AS classes_count FROM grades g ORDER BY level_order', [Year::currentId()]),
            'classes'  => ClassRoom::forYear($yearId, false),
            'subjects' => DB::all('SELECT s.*, (SELECT COUNT(*) FROM questions q WHERE q.subject_id = s.id) AS questions_count FROM subjects s ORDER BY sort_order'),
        ]);
    }

    /* ---------------- الأعوام الدراسية ---------------- */
    public function saveYear(Request $request): never
    {
        $errors = Validator::make($_POST, ['name' => 'required|max:40', 'starts_on' => 'nullable|date', 'ends_on' => 'nullable|date'],
            ['name' => 'اسم العام', 'starts_on' => 'تاريخ البداية', 'ends_on' => 'تاريخ النهاية']);
        $id = $request->int('id');
        if (!$errors && DB::value('SELECT 1 FROM academic_years WHERE name = ? AND id <> ?', [$request->str('name'), $id])) {
            $errors[] = 'يوجد عام دراسي بالاسم نفسه.';
        }
        if ($errors) {
            back_with_errors($errors, '/structure?tab=years');
        }
        $data = ['name' => $request->str('name'), 'starts_on' => $request->str('starts_on') ?: null, 'ends_on' => $request->str('ends_on') ?: null];
        if ($id) {
            DB::update('academic_years', $data, 'id = :id', ['id' => $id]);
        } else {
            $copyFrom = Year::currentId();
            $id = DB::insert('academic_years', $data);
            // نسخ هيكل الفصول من العام الحالي
            if ($request->bool('copy_classes') && $copyFrom) {
                DB::run('INSERT IGNORE INTO classes (academic_year_id, grade_id, name, is_active) SELECT ?, grade_id, name, is_active FROM classes WHERE academic_year_id = ?', [$id, $copyFrom]);
            }
        }
        Audit::log('year_update', 'academic_years', $id, $data);
        flash('success', 'تم حفظ العام الدراسي.');
        redirect('/structure?tab=years');
    }

    public function setCurrentYear(Request $request): never
    {
        $id = (int) $request->param('id');
        $this->notFound(Year::find($id));
        DB::transaction(static function () use ($id) {
            DB::run('UPDATE academic_years SET is_current = 0');
            DB::update('academic_years', ['is_current' => 1, 'is_archived' => 0], 'id = :id', ['id' => $id]);
        });
        Year::flush();
        Audit::log('year_update', 'academic_years', $id, 'تعيين العام الحالي');
        flash('success', 'أصبح العام المحدد هو العام الدراسي الحالي. سجّل الطلاب في فصول العام الجديد من وحدة الطلاب (النقل الجماعي أو الاستيراد).');
        redirect('/structure?tab=years');
    }

    /** أرشفة عام: تُغلق تسجيلاته النشطة وتبقى نتائجه محفوظة للتقارير */
    public function archiveYear(Request $request): never
    {
        $id = (int) $request->param('id');
        $year = $this->notFound(Year::find($id));
        if ((int) $year['is_current'] === 1) {
            flash('danger', 'لا يمكن أرشفة العام الحالي — عيّن عاماً آخر حالياً أولاً.');
            redirect('/structure?tab=years');
        }
        DB::transaction(static function () use ($id) {
            DB::update('academic_years', ['is_archived' => 1], 'id = :id', ['id' => $id]);
            DB::run("UPDATE student_class_enrollments SET status = 'completed', left_on = COALESCE(left_on, CURDATE()) WHERE academic_year_id = ? AND status = 'active'", [$id]);
            DB::run("UPDATE tests SET status = 'archived' WHERE academic_year_id = ? AND status <> 'archived'", [$id]);
        });
        Audit::log('year_update', 'academic_years', $id, 'أرشفة');
        flash('success', 'تمت أرشفة العام. نتائجه محفوظة ويمكن الاطلاع عليها من التقارير باختيار العام.');
        redirect('/structure?tab=years');
    }

    /* ---------------- الصفوف ---------------- */
    public function saveGrade(Request $request): never
    {
        $errors = Validator::make($_POST, ['name' => 'required|max:80', 'stage' => 'required|in:primary,intermediate,secondary', 'level_order' => 'required|int'],
            ['name' => 'اسم الصف', 'stage' => 'المرحلة', 'level_order' => 'الترتيب']);
        $id = $request->int('id');
        if (!$errors && DB::value('SELECT 1 FROM grades WHERE name = ? AND id <> ?', [$request->str('name'), $id])) {
            $errors[] = 'الصف موجود مسبقاً.';
        }
        if ($errors) {
            back_with_errors($errors, '/structure?tab=grades');
        }
        $data = [
            'name' => $request->str('name'), 'stage' => $request->str('stage'), 'level_order' => $request->int('level_order'),
            'is_nafes_target' => $request->bool('is_nafes_target') ? 1 : 0, 'is_active' => $request->bool('is_active') ? 1 : 0,
        ];
        $id ? DB::update('grades', $data, 'id = :id', ['id' => $id]) : DB::insert('grades', $data);
        Audit::log('structure_update', 'grades', $id ?: null, $data);
        flash('success', 'تم حفظ الصف.');
        redirect('/structure?tab=grades');
    }

    /* ---------------- الفصول ---------------- */
    public function saveClass(Request $request): never
    {
        $yearId  = $request->int('year') ?: Year::currentId();
        $gradeId = $request->int('grade_id');
        $back    = '/structure?tab=classes&year=' . $yearId;
        $id      = $request->int('id');

        if ($request->str('mode') === 'bulk') {
            $count = max(1, min(20, $request->int('count')));
            $added = 0;
            for ($i = 1; $i <= $count; $i++) {
                $added += DB::run('INSERT IGNORE INTO classes (academic_year_id, grade_id, name) VALUES (?, ?, ?)', [$yearId, $gradeId, (string) $i])->rowCount();
            }
            flash('success', "أُضيف $added فصل.");
            redirect($back);
        }

        $name = $request->str('name');
        if ($name === '' || !$gradeId) {
            back_with_errors(['اسم الفصل والصف مطلوبان.'], $back);
        }
        if (DB::value('SELECT 1 FROM classes WHERE academic_year_id = ? AND grade_id = ? AND name = ? AND id <> ?', [$yearId, $gradeId, $name, $id])) {
            back_with_errors(['الفصل موجود مسبقاً في هذا الصف.'], $back);
        }
        if ($id) {
            DB::update('classes', ['name' => $name, 'grade_id' => $gradeId, 'is_active' => $request->bool('is_active') ? 1 : 0], 'id = :id', ['id' => $id]);
        } else {
            DB::insert('classes', ['academic_year_id' => $yearId, 'grade_id' => $gradeId, 'name' => $name]);
        }
        Audit::log('structure_update', 'classes', $id ?: null, ['name' => $name]);
        flash('success', 'تم حفظ الفصل.');
        redirect($back);
    }

    public function toggleClass(Request $request): never
    {
        $class = $this->notFound(ClassRoom::find((int) $request->param('id')));
        DB::update('classes', ['is_active' => (int) $class['is_active'] ? 0 : 1], 'id = :id', ['id' => $class['id']]);
        flash('success', (int) $class['is_active'] ? 'تم تعطيل الفصل.' : 'تم تفعيل الفصل.');
        redirect('/structure?tab=classes&year=' . $class['academic_year_id']);
    }

    public function deleteClass(Request $request): never
    {
        $class = $this->notFound(ClassRoom::find((int) $request->param('id')));
        $used = (int) DB::value('SELECT COUNT(*) FROM student_class_enrollments WHERE class_id = ?', [$class['id']], 0)
              + (int) DB::value('SELECT COUNT(*) FROM test_attempts WHERE class_id = ?', [$class['id']], 0);
        if ($used) {
            flash('warning', 'الفصل مرتبط بطلاب أو نتائج؛ تم تعطيله بدلاً من حذفه للحفاظ على السجلات.');
            DB::update('classes', ['is_active' => 0], 'id = :id', ['id' => $class['id']]);
        } else {
            DB::delete('classes', 'id = ?', [$class['id']]);
            flash('success', 'تم حذف الفصل.');
        }
        Audit::log('structure_update', 'classes', (int) $class['id'], 'حذف/تعطيل');
        redirect('/structure?tab=classes&year=' . $class['academic_year_id']);
    }

    /* ---------------- المواد ---------------- */
    public function saveSubject(Request $request): never
    {
        $errors = Validator::make($_POST, ['name' => 'required|max:80', 'code' => 'required|username', 'color' => 'nullable|hex'],
            ['name' => 'اسم المادة', 'code' => 'رمز المادة', 'color' => 'اللون']);
        $id = $request->int('id');
        if (!$errors && DB::value('SELECT 1 FROM subjects WHERE (name = ? OR code = ?) AND id <> ?', [$request->str('name'), mb_strtolower($request->str('code')), $id])) {
            $errors[] = 'اسم المادة أو رمزها مستخدم مسبقاً.';
        }
        if ($errors) {
            back_with_errors($errors, '/structure?tab=subjects');
        }
        $data = [
            'name' => $request->str('name'), 'code' => mb_strtolower($request->str('code')), 'color' => $request->str('color') ?: null,
            'icon' => preg_replace('/[^a-z0-9\-]/', '', $request->str('icon')) ?: null, 'sort_order' => $request->int('sort_order'),
            'is_active' => $request->bool('is_active') ? 1 : 0,
        ];
        $id ? DB::update('subjects', $data, 'id = :id', ['id' => $id]) : DB::insert('subjects', $data);
        Audit::log('structure_update', 'subjects', $id ?: null, $data);
        flash('success', 'تم حفظ المادة.');
        redirect('/structure?tab=subjects');
    }
}
