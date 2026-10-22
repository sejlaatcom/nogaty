<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Session;
use App\Models\ClassRoom;
use App\Models\Student;
use App\Models\User;
use App\Services\Audit;
use App\Services\Scope;
use App\Services\Spreadsheet;
use App\Validators\Validator;

/** إدارة الطلاب */
final class StudentController extends Controller
{
    private function filters(Request $request): array
    {
        return [
            'q' => $request->str('q'), 'grade' => $request->int('grade'), 'class' => $request->int('class'),
            'status' => $request->str('status'), 'account' => $request->str('account'), 'unassigned' => $request->bool('unassigned'),
        ];
    }

    public function index(Request $request): string
    {
        $filters = $this->filters($request);
        if ($filters['class']) {
            Scope::authorizeClass($filters['class']);
        }
        $result = Student::search($filters, max(1, $request->int('page', 1)), 50);
        return $this->view('students/index', [
            'title' => 'الطلاب', 'result' => $result, 'filters' => $filters,
            'classes' => ClassRoom::forYear(), 'grades' => ClassRoom::grades(),
            'canManage' => Auth::can('students.manage'), 'canImport' => Auth::can('students.import'),
            'cards' => Session::get('student_cards'),
        ]);
    }

    public function export(Request $request): never
    {
        $filters = $this->filters($request);
        $rows = [['الاسم الكامل', 'الرقم المدرسي', 'اسم المستخدم', 'الصف', 'الفصل', 'الحالة', 'الحساب', 'آخر دخول', 'ملاحظات']];
        foreach (Student::search($filters, 1, 10000)['rows'] as $s) {
            $rows[] = [$s['full_name'], $s['student_number'], $s['username'], $s['grade_name'] ?? '', $s['class_name'] ?? '',
                Student::STATUSES[$s['status']] ?? $s['status'], (int) $s['is_active'] ? 'نشط' : 'معطّل', $s['last_login_at'] ?? '', $s['notes'] ?? ''];
        }
        Audit::log('report_export', 'students', null, 'تصدير قائمة الطلاب');
        $request->str('format') === 'csv'
            ? Spreadsheet::downloadCsv('الطلاب.csv', $rows)
            : Spreadsheet::downloadXlsx('الطلاب.xlsx', ['الطلاب' => $rows], setting('school_name') . ' — قائمة الطلاب');
    }

    public function create(Request $request): string
    {
        return $this->view('students/form', ['title' => 'إضافة طالب', 'student' => null, 'classes' => ClassRoom::forYear(null, true, true),
            'defaultClass' => $request->int('class')]);
    }

    public function edit(Request $request): string
    {
        $student = $this->notFound(Student::find((int) $request->param('id')));
        Scope::authorizeStudent((int) $student['id']);
        return $this->view('students/form', ['title' => 'تعديل بيانات الطالب', 'student' => $student, 'classes' => ClassRoom::forYear(null, true, true), 'defaultClass' => 0]);
    }

    public function store(Request $request): never
    {
        $this->save($request, null);
    }

    public function update(Request $request): never
    {
        $student = $this->notFound(Student::find((int) $request->param('id')));
        Scope::authorizeStudent((int) $student['id']);
        $this->save($request, $student);
    }

    private function save(Request $request, ?array $student): never
    {
        $id   = (int) ($student['id'] ?? 0);
        $back = $id ? "/students/$id/edit" : '/students/create';
        $data = [
            'full_name' => $request->str('full_name'), 'student_number' => $request->str('student_number'),
            'username' => mb_strtolower($request->str('username') ?: $request->str('student_number')),
            'status' => $request->str('status', 'active'), 'notes' => $request->str('notes'),
            'class_id' => $request->int('class_id'), 'password' => (string) ($_POST['password'] ?? ''),
        ];
        $errors = Validator::make($data, [
            'full_name' => 'required|min:3|max:150', 'student_number' => 'required|code', 'username' => 'required|username',
            'status' => 'required|in:' . implode(',', array_keys(Student::STATUSES)), 'notes' => 'nullable|max:500',
        ], ['full_name' => 'الاسم الكامل', 'student_number' => 'الرقم المدرسي', 'username' => 'اسم المستخدم', 'status' => 'الحالة', 'notes' => 'الملاحظات']);
        if (!$errors) {
            if (Student::numberTaken($data['student_number'], $id)) {
                $errors[] = 'الرقم المدرسي مستخدم لطالب آخر.';
            }
            if (User::usernameTaken($data['username'], (int) ($student['user_id'] ?? 0))) {
                $errors[] = 'اسم المستخدم مستخدم لحساب آخر.';
            }
            if ($data['password'] !== '') {
                $errors = array_merge($errors, User::passwordErrors($data['password']));
            }
            if ($data['class_id']) {
                if (!Scope::canClass($data['class_id'])) {
                    $errors[] = 'الفصل المختار غير مسند إليك.';
                }
            } elseif (!$id) {
                $errors[] = 'اختر فصل الطالب.';
            }
        }
        if ($errors) {
            back_with_errors($errors, $back);
        }

        if ($student) {
            Student::update($id, $data);
            Audit::log('student_update', 'students', $id, ['name' => $data['full_name']]);
            flash('success', 'تم حفظ بيانات الطالب.');
        } else {
            $plain = $data['password'] !== '' ? $data['password'] : random_password(8);
            $id = Student::create(array_merge($data, ['password' => $plain]));
            Audit::log('student_create', 'students', $id, ['name' => $data['full_name']]);
            flash('success', "تمت إضافة الطالب. اسم المستخدم: {$data['username']} — كلمة المرور الأولية: $plain");
        }
        redirect('/students/' . $id);
    }

    public function show(Request $request): string
    {
        $student = $this->notFound(Student::find((int) $request->param('id')));
        Scope::authorizeStudent((int) $student['id']);
        $results = DB::all(
            'SELECT r.*, t.title, t.type, s.name AS subject_name, c.name AS class_name, g.name AS grade_name, y.name AS year_name
               FROM test_results r JOIN tests t ON t.id = r.test_id LEFT JOIN subjects s ON s.id = t.subject_id
          LEFT JOIN classes c ON c.id = r.class_id LEFT JOIN grades g ON g.id = c.grade_id
               JOIN academic_years y ON y.id = r.academic_year_id
              WHERE r.student_id = ? ORDER BY r.created_at DESC LIMIT 50',
            [$student['id']]
        );
        return $this->view('students/show', [
            'title' => $student['full_name'], 'student' => $student, 'history' => Student::history((int) $student['id']),
            'results' => $results, 'classes' => ClassRoom::forYear(null, true, true), 'canManage' => Auth::can('students.manage'),
        ]);
    }

    /** نقل طالب إلى فصل آخر (يحتفظ بالتسجيل السابق ونتائجه) */
    public function transfer(Request $request): never
    {
        $student = $this->notFound(Student::find((int) $request->param('id')));
        Scope::authorizeStudent((int) $student['id']);
        $classId = $request->int('class_id');
        Scope::authorizeClass($classId);
        Student::enroll((int) $student['id'], $classId);
        Audit::log('student_transfer', 'students', (int) $student['id'], ['from' => $student['class_id'], 'to' => $classId]);
        flash('success', 'تم نقل الطالب. نتائجه السابقة تبقى مرتبطة بفصله السابق.');
        redirect('/students/' . $student['id']);
    }

    /** عمليات جماعية: نقل، تفعيل، تعطيل، كلمات مرور جديدة */
    public function bulk(Request $request): never
    {
        $ids = $request->ints('ids');
        $action = $request->str('action');
        if (!$ids) {
            flash('warning', 'حدد طالباً واحداً على الأقل.');
            back('/students');
        }
        foreach ($ids as $id) {
            Scope::authorizeStudent($id);
        }
        [$in, $params] = DB::in($ids);
        switch ($action) {
            case 'move':
                $classId = $request->int('class_id');
                Scope::authorizeClass($classId);
                DB::transaction(static function () use ($ids, $classId) {
                    foreach ($ids as $id) {
                        Student::enroll($id, $classId);
                    }
                });
                Audit::log('student_transfer', 'students', null, ['count' => count($ids), 'to' => $classId]);
                flash('success', 'تم نقل ' . count($ids) . ' طالب.');
                break;
            case 'activate':
            case 'deactivate':
                $active = $action === 'activate' ? 1 : 0;
                DB::run("UPDATE users SET is_active = ? WHERE id IN (SELECT user_id FROM students WHERE id IN ($in))", array_merge([$active], $params));
                DB::run("UPDATE students SET status = ? WHERE id IN ($in) AND status IN ('active','inactive')", array_merge([$active ? 'active' : 'inactive'], $params));
                Audit::log('user_toggle', 'students', null, ['count' => count($ids), 'active' => $active]);
                flash('success', 'تم تحديث حالة ' . count($ids) . ' حساب.');
                break;
            case 'passwords':
                $cards = [];
                foreach ($ids as $id) {
                    $s = Student::find($id);
                    $plain = random_password(8);
                    User::setPassword((int) $s['user_id'], $plain, true);
                    $cards[] = ['name' => $s['full_name'], 'number' => $s['student_number'], 'username' => $s['username'], 'password' => $plain,
                                'class' => $s['class_id'] ? ClassRoom::label($s) : ''];
                }
                Session::put('student_cards', $cards);
                Audit::log('password_reset', 'students', null, ['count' => count($ids)]);
                flash('success', 'تم إنشاء كلمات مرور جديدة لـ ' . count($ids) . ' طالب — اطبع البطاقات الآن (تظهر مرة واحدة).');
                break;
            default:
                flash('warning', 'إجراء غير معروف.');
        }
        back('/students');
    }

    public function cards(Request $request): string
    {
        $cards = Session::get('student_cards', []);
        Session::forget('student_cards');
        if (!$cards) {
            redirect('/students');
        }
        return $this->view('students/cards', ['title' => 'بطاقات دخول الطلاب', 'reportTitle' => 'بطاقات دخول الطلاب', 'cards' => $cards], 'layouts/print');
    }

    /** حذف طالب: إن كانت له نتائج يُعطّل فقط حفاظاً على السجلات */
    public function destroy(Request $request): never
    {
        $student = $this->notFound(Student::find((int) $request->param('id')));
        Scope::authorizeStudent((int) $student['id']);
        if (DB::value('SELECT 1 FROM test_attempts WHERE student_id = ? LIMIT 1', [$student['id']])) {
            DB::update('users', ['is_active' => 0], 'id = :id', ['id' => $student['user_id']]);
            DB::update('students', ['status' => 'inactive'], 'id = :id', ['id' => $student['id']]);
            flash('warning', 'للطالب نتائج محفوظة؛ تم تعطيل حسابه بدلاً من حذفه. يُطبَّق الحذف النهائي وفق سياسة الاحتفاظ بالبيانات.');
        } else {
            DB::delete('users', 'id = ?', [$student['user_id']]);
            flash('success', 'تم حذف الطالب.');
        }
        Audit::log('student_delete', 'students', (int) $student['id'], ['name' => $student['full_name']]);
        redirect('/students');
    }
}
