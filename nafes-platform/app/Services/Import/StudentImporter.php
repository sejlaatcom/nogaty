<?php
declare(strict_types=1);

namespace App\Services\Import;

use App\Core\DB;
use App\Models\ClassRoom;
use App\Models\Student;
use App\Models\User;
use App\Models\Year;

/** استيراد الطلاب: يكتشف التكرار بالرقم المدرسي واسم المستخدم، ويحدّث الموجودين اختيارياً */
final class StudentImporter extends Importer
{
    private const STATUS_ALIASES = [
        'active' => ['نشط', 'فعال', 'active', '1'], 'inactive' => ['موقوف', 'معطل', 'inactive', '0'],
        'graduated' => ['متخرج', 'graduated'], 'transferred_out' => ['منقول', 'transferred'],
    ];

    public function title(): string
    {
        return 'استيراد الطلاب';
    }

    public function permission(): string
    {
        return 'students.import';
    }

    public function fields(): array
    {
        return [
            'full_name'      => ['الاسم الكامل', true, ['الاسم', 'اسم الطالب', 'name', 'student name'], 'اسم الطالب الرباعي'],
            'student_number' => ['الرقم المدرسي', true, ['رقم الطالب', 'معرف الطالب', 'السجل', 'student id', 'student number', 'رقم'], 'معرف فريد للطالب داخل المدرسة'],
            'username'       => ['اسم المستخدم', false, ['المستخدم', 'username', 'user'], 'إن تُرك فارغاً يُستخدم الرقم المدرسي'],
            'password'       => ['كلمة المرور', false, ['كلمة السر', 'password'], 'إن تُركت فارغة تُولَّد تلقائياً للطلاب الجدد'],
            'grade'          => ['الصف', false, ['الصف الدراسي', 'grade'], 'مثل: الثالث المتوسط'],
            'class'          => ['الفصل', false, ['الشعبة', 'class', 'section'], 'مثل: 1'],
            'status'         => ['الحالة', false, ['status'], 'نشط / موقوف'],
            'notes'          => ['ملاحظات', false, ['ملاحظة', 'notes'], 'ملاحظات إدارية اختيارية'],
        ];
    }

    public function example(): array
    {
        return ['عبدالله محمد سعد القحطاني', '1001', '', '', 'الثالث المتوسط', '1', 'نشط', ''];
    }

    public function options(): array
    {
        return [
            'default_class'  => ['الفصل الافتراضي (للسجلات التي لا تحدد صفاً وفصلاً)', 'class', ''],
            'update_existing'=> ['تحديث بيانات الطلاب الموجودين (بالرقم المدرسي)', 'bool', '1'],
            'create_classes' => ['إنشاء الفصول غير الموجودة تلقائياً', 'bool', '0'],
        ];
    }

    public function instructions(): array
    {
        return [
            'الأعمدة الإلزامية: الاسم الكامل والرقم المدرسي فقط — لا يُشترط رقم الهوية الوطنية.',
            'الطالب الموجود (نفس الرقم المدرسي) يُحدَّث إن اخترت ذلك، ولا يُكرَّر.',
            'اسم المستخدم بأحرف إنجليزية أو أرقام؛ يُستخدم الرقم المدرسي إن تُرك فارغاً.',
            'تُعرض كلمات المرور المولّدة مرة واحدة في تقرير الاستيراد لطباعة بطاقات الدخول.',
        ];
    }

    protected function check(array $row, array $options, array &$seen): array
    {
        $errors = [];
        $warnings = [];
        $number   = preg_replace('/\s+/', '', $row['student_number']) ?? '';
        $username = mb_strtolower($row['username'] !== '' ? $row['username'] : $number);

        if (!preg_match('/^[\p{L}\p{N}_.\-]{1,30}$/u', $number)) {
            $errors[] = 'الرقم المدرسي يحتوي على رموز غير مسموحة';
        }
        if (!preg_match(User::USERNAME_RULE, $username)) {
            $errors[] = "اسم المستخدم «{$username}» غير صالح (أحرف إنجليزية أو أرقام، 3 فأكثر)";
        }
        if (mb_strlen($row['full_name']) < 3) {
            $errors[] = 'الاسم قصير جداً';
        }
        if (isset($seen['n'][$number])) {
            $errors[] = 'الرقم المدرسي مكرر في الملف (السطر ' . $seen['n'][$number] . ')';
        }
        if (isset($seen['u'][$username])) {
            $errors[] = 'اسم المستخدم مكرر في الملف (السطر ' . $seen['u'][$username] . ')';
        }
        $seen['n'][$number] = $row['_line'];
        $seen['u'][$username] = $row['_line'];

        $status = 'active';
        if ($row['status'] !== '') {
            $status = null;
            foreach (self::STATUS_ALIASES as $key => $aliases) {
                if (in_array(mb_strtolower($row['status']), $aliases, true)) {
                    $status = $key;
                }
            }
            if ($status === null) {
                $errors[] = 'الحالة غير معروفة';
                $status = 'active';
            }
        }
        if ($row['password'] !== '' && User::passwordErrors($row['password'])) {
            $errors[] = 'كلمة المرور لا تحقق السياسة (8 أحرف وأرقام)';
        }

        // الفصل
        $classId = null;
        if ($row['grade'] !== '' || $row['class'] !== '') {
            if ($row['grade'] === '' || $row['class'] === '') {
                $errors[] = 'حدد الصف والفصل معاً';
            } else {
                $classId = ClassRoom::resolve($row['grade'], $row['class'], Year::currentId(), false);
                if (!$classId) {
                    if (!empty($options['create_classes']) && DB::value('SELECT 1 FROM grades WHERE name = ?', [$row['grade']])) {
                        $warnings[] = 'سيُنشأ الفصل ' . $row['grade'] . ' / ' . $row['class'];
                    } else {
                        $errors[] = 'الصف أو الفصل غير موجود: ' . $row['grade'] . ' / ' . $row['class'];
                    }
                }
            }
        } elseif (!empty($options['default_class'])) {
            $classId = (int) $options['default_class'];
        } else {
            $warnings[] = 'بدون فصل — سجّله لاحقاً';
        }

        $existing = DB::first('SELECT s.id, s.user_id FROM students s WHERE s.student_number = ?', [$number]);
        $action = 'create';
        if ($existing) {
            if (empty($options['update_existing'])) {
                $errors[] = 'الطالب موجود مسبقاً (الرقم المدرسي) — فعّل خيار التحديث';
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
            'action'   => $errors ? 'reject' : $action,
            'errors'   => $errors,
            'warnings' => $warnings,
            'data'     => [
                'full_name' => $row['full_name'], 'student_number' => $number, 'username' => $username,
                'password' => $row['password'], 'status' => $status, 'notes' => $row['notes'], 'class_id' => $classId,
                'grade' => $row['grade'], 'class' => $row['class'], 'existing_id' => (int) ($existing['id'] ?? 0),
            ],
        ];
    }

    protected function persist(array $checked, array $options): array
    {
        $d = $checked['data'];
        if (!$d['class_id'] && $d['grade'] !== '' && !empty($options['create_classes'])) {
            $d['class_id'] = ClassRoom::resolve($d['grade'], $d['class'], Year::currentId(), true);
        }
        if ($checked['action'] === 'update') {
            $current = Student::find($d['existing_id']);
            Student::update($d['existing_id'], [
                'full_name' => $d['full_name'], 'student_number' => $d['student_number'], 'username' => $d['username'],
                'status' => $d['status'], 'notes' => $d['notes'] !== '' ? $d['notes'] : $current['notes'],
                'password' => $d['password'], 'class_id' => $d['class_id'],
            ]);
            return ['action' => 'updated', 'credentials' => $d['password'] !== '' ? $this->card($d, $d['password']) : null];
        }
        $plain = $d['password'] !== '' ? $d['password'] : random_password(8);
        Student::create($d + ['password' => $plain]);
        return ['action' => 'created', 'credentials' => $this->card($d, $plain)];
    }

    private function card(array $d, string $password): array
    {
        return ['name' => $d['full_name'], 'number' => $d['student_number'], 'username' => $d['username'], 'password' => $password,
                'class' => $d['class_id'] ? ClassRoom::label(ClassRoom::find((int) $d['class_id']) ?? []) : ''];
    }
}
