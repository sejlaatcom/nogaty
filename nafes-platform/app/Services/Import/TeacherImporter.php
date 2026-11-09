<?php
declare(strict_types=1);

namespace App\Services\Import;

use App\Core\DB;
use App\Models\ClassRoom;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Year;

/** استيراد المعلمين والوكلاء مع موادهم وفصولهم */
final class TeacherImporter extends Importer
{
    private const ROLE_ALIASES = [
        'teacher' => ['معلم', 'teacher', ''], 'supervisor' => ['وكيل', 'مشرف', 'وكيل/مشرف', 'supervisor'],
        'principal' => ['قائد', 'قائد المدرسة', 'مدير المدرسة', 'principal'],
    ];

    public function title(): string
    {
        return 'استيراد المعلمين';
    }

    public function permission(): string
    {
        return 'teachers.manage';
    }

    public function kind(): string
    {
        return 'teachers';
    }

    public function fields(): array
    {
        return [
            'full_name'       => ['الاسم الكامل', true, ['الاسم', 'اسم المعلم', 'name'], ''],
            'employee_number' => ['معرف المعلم', true, ['الرقم الوظيفي', 'رقم المعلم', 'employee id', 'رقم'], 'معرف فريد للمعلم'],
            'username'        => ['اسم المستخدم', false, ['username'], 'إن تُرك فارغاً: t + معرف المعلم'],
            'email'           => ['البريد الإلكتروني', false, ['البريد', 'email'], ''],
            'specialization'  => ['التخصص', false, ['specialization'], ''],
            'role'            => ['الدور الوظيفي', false, ['الدور', 'الوظيفة', 'role'], 'معلم / وكيل / قائد المدرسة'],
            'subjects'        => ['المواد', false, ['المادة', 'subjects'], 'مفصولة بفاصلة: الرياضيات، العلوم'],
            'classes'         => ['الفصول', false, ['الفصل', 'classes'], 'مفصولة بفاصلة بصيغة الصف/الفصل: الثالث المتوسط/1'],
            'password'        => ['كلمة المرور', false, ['password'], 'تُولد تلقائياً إن تُركت فارغة'],
        ];
    }

    public function example(): array
    {
        return ['خالد عبدالرحمن الدوسري', 'T101', '', 'khalid@example.com', 'رياضيات', 'معلم', 'الرياضيات', 'الثالث المتوسط/1، الثالث المتوسط/2', ''];
    }

    public function options(): array
    {
        return ['update_existing' => ['تحديث المعلمين الموجودين وإضافة الإسناد الجديد', 'bool', '1']];
    }

    public function instructions(): array
    {
        return [
            'الإلزامي: الاسم الكامل ومعرف المعلم.',
            'المواد والفصول اختيارية؛ يُسند المعلم لكل مادة في كل فصل مذكور (للعام الدراسي الحالي).',
            'الوكيل/المشرف بدون مواد يُسند للإشراف على الفصول المذكورة.',
        ];
    }

    private static function splitList(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[،,;|\n]+/u', $value) ?: [])));
    }

    protected function check(array $row, array $options, array &$seen): array
    {
        $errors = [];
        $number   = trim($row['employee_number']);
        $username = mb_strtolower($row['username'] !== '' ? $row['username'] : 't' . preg_replace('/[^a-z0-9]/i', '', $number));

        if (!preg_match('/^[\p{L}\p{N}_.\-]{1,30}$/u', $number)) {
            $errors[] = 'معرف المعلم غير صالح';
        }
        if (!preg_match(User::USERNAME_RULE, $username)) {
            $errors[] = "اسم المستخدم «{$username}» غير صالح";
        }
        if ($row['email'] !== '' && !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'البريد الإلكتروني غير صحيح';
        }
        foreach (['n' => $number, 'u' => $username] as $k => $v) {
            if (isset($seen[$k][$v])) {
                $errors[] = 'مكرر في الملف (السطر ' . $seen[$k][$v] . ')';
            }
            $seen[$k][$v] = $row['_line'];
        }

        $role = null;
        foreach (self::ROLE_ALIASES as $key => $aliases) {
            if (in_array(mb_strtolower(trim($row['role'])), $aliases, true)) {
                $role = $key;
            }
        }
        if ($role === null) {
            $errors[] = 'الدور الوظيفي غير معروف';
            $role = 'teacher';
        }

        $subjectIds = [];
        foreach (self::splitList($row['subjects']) as $name) {
            $id = (int) DB::value('SELECT id FROM subjects WHERE name = ? OR code = ?', [$name, mb_strtolower($name)], 0);
            if ($id) {
                $subjectIds[] = $id;
            } else {
                $errors[] = "المادة غير موجودة: $name";
            }
        }
        $classIds = [];
        foreach (self::splitList($row['classes']) as $item) {
            [$grade, $class] = array_pad(array_map('trim', preg_split('#[/\\\\]#u', $item, 2) ?: []), 2, '');
            $id = ClassRoom::resolve($grade, $class, Year::currentId(), false);
            if ($id) {
                $classIds[] = $id;
            } else {
                $errors[] = "الفصل غير موجود: $item";
            }
        }
        if ($row['password'] !== '' && User::passwordErrors($row['password'])) {
            $errors[] = 'كلمة المرور لا تحقق السياسة';
        }

        $existing = DB::first('SELECT id, user_id FROM teachers WHERE employee_number = ?', [$number]);
        $action = 'create';
        if ($existing) {
            if (empty($options['update_existing'])) {
                $errors[] = 'المعلم موجود مسبقاً';
            } else {
                $action = 'update';
                if (User::usernameTaken($username, (int) $existing['user_id'])) {
                    $errors[] = 'اسم المستخدم مستخدم لحساب آخر';
                }
            }
        } elseif (User::usernameTaken($username)) {
            $errors[] = 'اسم المستخدم مستخدم لحساب آخر';
        }

        return [
            'action' => $errors ? 'reject' : $action, 'errors' => $errors, 'warnings' => [],
            'data' => [
                'full_name' => $row['full_name'], 'employee_number' => $number, 'username' => $username, 'email' => $row['email'],
                'specialization' => $row['specialization'], 'role' => $role, 'subject_ids' => $subjectIds, 'class_ids' => $classIds,
                'password' => $row['password'], 'existing' => $existing,
            ],
        ];
    }

    protected function persist(array $checked, array $options): array
    {
        $d = $checked['data'];
        $credentials = null;
        if ($checked['action'] === 'update') {
            $teacherId = (int) $d['existing']['id'];
            DB::update('users', ['full_name' => $d['full_name'], 'username' => $d['username'], 'email' => $d['email'] ?: null], 'id = :id', ['id' => $d['existing']['user_id']]);
            DB::update('teachers', ['specialization' => $d['specialization'] ?: null], 'id = :id', ['id' => $teacherId]);
            User::addRole((int) $d['existing']['user_id'], $d['role']);
            if ($d['password'] !== '') {
                User::setPassword((int) $d['existing']['user_id'], $d['password'], true);
            }
            $action = 'updated';
        } else {
            $plain = $d['password'] !== '' ? $d['password'] : random_password(10);
            $teacherId = Teacher::create($d + ['password' => $plain], [$d['role']]);
            $credentials = ['name' => $d['full_name'], 'number' => $d['employee_number'], 'username' => $d['username'], 'password' => $plain, 'class' => ''];
            $action = 'created';
        }
        foreach ($d['class_ids'] as $classId) {
            if ($d['subject_ids']) {
                foreach ($d['subject_ids'] as $subjectId) {
                    Teacher::assign($teacherId, $classId, $subjectId);
                }
            } else {
                Teacher::assign($teacherId, $classId, null);
            }
        }
        return ['action' => $action, 'credentials' => $credentials];
    }
}
