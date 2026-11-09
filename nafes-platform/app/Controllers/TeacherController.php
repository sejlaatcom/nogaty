<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Models\ClassRoom;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Year;
use App\Services\Audit;
use App\Services\Spreadsheet;
use App\Validators\Validator;

/** إدارة المعلمين والوكلاء وإسنادهم */
final class TeacherController extends Controller
{
    public function index(Request $request): string
    {
        return $this->view('teachers/index', [
            'title' => 'المعلمون والإسناد', 'teachers' => Teacher::all(), 'canManage' => Auth::can('teachers.manage'),
        ]);
    }

    public function export(Request $request): never
    {
        $rows = [['الاسم الكامل', 'معرف المعلم', 'اسم المستخدم', 'البريد', 'التخصص', 'الأدوار', 'المواد', 'عدد الفصول', 'الحالة']];
        foreach (Teacher::all() as $t) {
            $rows[] = [$t['full_name'], $t['employee_number'], $t['username'], $t['email'] ?? '', $t['specialization'] ?? '',
                $t['role_names'] ?? '', $t['subjects'] ?? '', (int) $t['classes_count'], (int) $t['is_active'] ? 'نشط' : 'معطّل'];
        }
        Spreadsheet::downloadXlsx('المعلمون.xlsx', ['المعلمون' => $rows], setting('school_name') . ' — المعلمون');
    }

    public function create(Request $request): string
    {
        return $this->view('teachers/form', ['title' => 'إضافة معلم', 'teacher' => null, 'roles' => ['teacher']]);
    }

    public function edit(Request $request): string
    {
        $teacher = $this->notFound(Teacher::find((int) $request->param('id')));
        return $this->view('teachers/form', ['title' => 'تعديل بيانات المعلم', 'teacher' => $teacher, 'roles' => User::roleSlugs((int) $teacher['user_id'])]);
    }

    public function store(Request $request): never
    {
        $this->save($request, null);
    }

    public function update(Request $request): never
    {
        $this->save($request, $this->notFound(Teacher::find((int) $request->param('id'))));
    }

    private function save(Request $request, ?array $teacher): never
    {
        $id   = (int) ($teacher['id'] ?? 0);
        $back = $id ? "/teachers/$id/edit" : '/teachers/create';
        $data = [
            'full_name' => $request->str('full_name'), 'employee_number' => $request->str('employee_number'),
            'username' => mb_strtolower($request->str('username')), 'email' => $request->str('email'),
            'specialization' => $request->str('specialization'), 'password' => (string) ($_POST['password'] ?? ''),
            'is_active' => $request->bool('is_active') ? 1 : 0,
        ];
        $errors = Validator::make($data, [
            'full_name' => 'required|min:3|max:150', 'employee_number' => 'required|code', 'username' => 'required|username',
            'email' => 'nullable|email|max:150', 'specialization' => 'nullable|max:100',
        ], ['full_name' => 'الاسم الكامل', 'employee_number' => 'معرف المعلم', 'username' => 'اسم المستخدم', 'email' => 'البريد', 'specialization' => 'التخصص']);
        $roles = array_values(array_intersect(array_map('strval', $request->array('roles')), array_keys(Teacher::ROLES)));
        if (!$roles) {
            $errors[] = 'اختر الدور الوظيفي.';
        }
        if (!$errors) {
            if (Teacher::numberTaken($data['employee_number'], $id)) {
                $errors[] = 'معرف المعلم مستخدم مسبقاً.';
            }
            if (User::usernameTaken($data['username'], (int) ($teacher['user_id'] ?? 0))) {
                $errors[] = 'اسم المستخدم مستخدم لحساب آخر.';
            }
            if ($data['password'] !== '') {
                $errors = array_merge($errors, User::passwordErrors($data['password']));
            }
        }
        if ($errors) {
            back_with_errors($errors, $back);
        }

        if ($teacher) {
            DB::transaction(static function () use ($teacher, $data, $roles) {
                DB::update('users', ['full_name' => $data['full_name'], 'username' => $data['username'], 'email' => $data['email'] ?: null, 'is_active' => $data['is_active']],
                    'id = :id', ['id' => $teacher['user_id']]);
                DB::update('teachers', ['employee_number' => $data['employee_number'], 'specialization' => $data['specialization'] ?: null], 'id = :id', ['id' => $teacher['id']]);
                // الإبقاء على أدوار أخرى (مثل مدير النظام) وتعديل الأدوار الوظيفية فقط
                $other = array_diff(User::roleSlugs((int) $teacher['user_id']), array_keys(Teacher::ROLES));
                User::setRoles((int) $teacher['user_id'], array_merge($other, $roles));
                if ($data['password'] !== '') {
                    User::setPassword((int) $teacher['user_id'], $data['password'], true);
                }
            });
            Audit::log('teacher_update', 'teachers', $id, ['roles' => $roles]);
            flash('success', 'تم حفظ بيانات المعلم.');
        } else {
            $plain = $data['password'] !== '' ? $data['password'] : random_password(10);
            $id = Teacher::create(array_merge($data, ['password' => $plain]), $roles);
            Audit::log('teacher_create', 'teachers', $id, ['name' => $data['full_name']]);
            flash('success', "تمت إضافة المعلم. اسم المستخدم: {$data['username']} — كلمة المرور الأولية: $plain");
        }
        redirect('/teachers/' . $id);
    }

    /** ملف المعلم: الإسناد والطلاب */
    public function show(Request $request): string
    {
        $teacher = $this->notFound(Teacher::find((int) $request->param('id')));
        $assignments = Teacher::assignments((int) $teacher['id']);
        $classIds = array_values(array_unique(array_map(static fn ($a) => (int) $a['class_id'], $assignments)));
        $students = [];
        if ($classIds) {
            [$in, $params] = DB::in($classIds);
            $students = DB::all(
                "SELECT s.id, s.student_number, u.full_name, c.name AS class_name, g.name AS grade_name
                   FROM student_class_enrollments e JOIN students s ON s.id = e.student_id JOIN users u ON u.id = s.user_id
                   JOIN classes c ON c.id = e.class_id JOIN grades g ON g.id = c.grade_id
                  WHERE e.status = 'active' AND e.class_id IN ($in) ORDER BY g.level_order, c.name + 0, u.full_name",
                $params
            );
        }
        return $this->view('teachers/show', [
            'title' => $teacher['full_name'], 'teacher' => $teacher, 'assignments' => $assignments, 'students' => $students,
            'roles' => DB::column('SELECT r.name FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?', [$teacher['user_id']]),
            'classes' => ClassRoom::forYear(null, false, true), 'subjects' => ClassRoom::subjects(), 'canManage' => Auth::can('teachers.manage'),
        ]);
    }

    /** إضافة إسناد: عدة فصول × عدة مواد دفعة واحدة */
    public function assign(Request $request): never
    {
        $teacher = $this->notFound(Teacher::find((int) $request->param('id')));
        $classIds = $request->ints('class_ids');
        $subjectIds = $request->ints('subject_ids');
        $supervise = $request->bool('supervise');
        if (!$classIds || (!$subjectIds && !$supervise)) {
            back_with_errors(['اختر فصلاً واحداً على الأقل ومادة (أو إشرافاً على الفصل).'], '/teachers/' . $teacher['id']);
        }
        $added = 0;
        foreach ($classIds as $classId) {
            foreach ($subjectIds as $subjectId) {
                $added += (int) Teacher::assign((int) $teacher['id'], $classId, $subjectId);
            }
            if ($supervise) {
                $added += (int) Teacher::assign((int) $teacher['id'], $classId, null);
            }
        }
        Audit::log('assignment_update', 'teachers', (int) $teacher['id'], ['added' => $added]);
        flash('success', "تمت إضافة $added إسناد (يُتجاهل المكرر).");
        redirect('/teachers/' . $teacher['id']);
    }

    public function unassign(Request $request): never
    {
        $teacher = $this->notFound(Teacher::find((int) $request->param('id')));
        DB::delete('teacher_assignments', 'id = ? AND teacher_id = ?', [(int) $request->param('aid'), $teacher['id']]);
        Audit::log('assignment_update', 'teachers', (int) $teacher['id'], 'إلغاء إسناد');
        flash('success', 'تم إلغاء الإسناد.');
        redirect('/teachers/' . $teacher['id']);
    }

    public function toggle(Request $request): never
    {
        $teacher = $this->notFound(Teacher::find((int) $request->param('id')));
        if ((int) $teacher['user_id'] === Auth::id()) {
            back_with_errors(['لا يمكنك تعطيل حسابك.'], '/teachers');
        }
        DB::update('users', ['is_active' => (int) $teacher['is_active'] ? 0 : 1], 'id = :id', ['id' => $teacher['user_id']]);
        Audit::log('user_toggle', 'teachers', (int) $teacher['id']);
        flash('success', (int) $teacher['is_active'] ? 'تم تعطيل الحساب.' : 'تم تفعيل الحساب.');
        back('/teachers');
    }

    public function resetPassword(Request $request): never
    {
        $teacher = $this->notFound(Teacher::find((int) $request->param('id')));
        $plain = random_password(10);
        User::setPassword((int) $teacher['user_id'], $plain, true);
        Audit::log('password_reset', 'teachers', (int) $teacher['id']);
        flash('success', "كلمة المرور المؤقتة لـ {$teacher['full_name']}: $plain");
        back('/teachers');
    }

    /** نسخ إسناد العام السابق إلى العام الحالي (حسب أسماء الصفوف والفصول) */
    public function copyAssignments(Request $request): never
    {
        $current = Year::currentId();
        $previous = (int) DB::value('SELECT id FROM academic_years WHERE id <> ? ORDER BY starts_on DESC, id DESC LIMIT 1', [$current], 0);
        if (!$previous) {
            back_with_errors(['لا يوجد عام سابق للنسخ منه.'], '/teachers');
        }
        $count = DB::run(
            'INSERT IGNORE INTO teacher_assignments (teacher_id, class_id, subject_id, academic_year_id)
             SELECT ta.teacher_id, nc.id, ta.subject_id, ?
               FROM teacher_assignments ta JOIN classes oc ON oc.id = ta.class_id
               JOIN classes nc ON nc.grade_id = oc.grade_id AND nc.name = oc.name AND nc.academic_year_id = ?
              WHERE ta.academic_year_id = ?',
            [$current, $current, $previous]
        )->rowCount();
        Audit::log('assignment_update', 'teacher_assignments', null, "نسخ $count إسناد من العام السابق");
        flash('success', "تم نسخ $count إسناد من العام السابق.");
        redirect('/teachers');
    }
}
