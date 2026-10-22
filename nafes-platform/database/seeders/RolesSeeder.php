<?php
declare(strict_types=1);

use App\Core\DB;

/** الأدوار والصلاحيات الافتراضية — آمن لإعادة التشغيل (يضيف الناقص فقط) */
final class RolesSeeder
{
    public const PERMISSIONS = [
        // [slug, name, group]
        ['dashboard.view', 'عرض لوحة المؤشرات', 'عام'],
        ['scope.all', 'الاطلاع على كل الفصول (بدون قيد الإسناد)', 'عام'],
        ['users.manage', 'إدارة المستخدمين والأدوار', 'الإدارة'],
        ['settings.manage', 'إدارة الإعدادات', 'الإدارة'],
        ['backups.manage', 'النسخ الاحتياطي والاستعادة', 'الإدارة'],
        ['audit.view', 'مراجعة سجل العمليات', 'الإدارة'],
        ['structure.manage', 'إدارة العام الدراسي والصفوف والفصول والمواد', 'المدرسة'],
        ['students.view', 'عرض الطلاب', 'المدرسة'],
        ['students.manage', 'إضافة الطلاب وتعديلهم ونقلهم', 'المدرسة'],
        ['students.import', 'استيراد الطلاب', 'المدرسة'],
        ['teachers.view', 'عرض المعلمين', 'المدرسة'],
        ['teachers.manage', 'إدارة المعلمين والإسناد', 'المدرسة'],
        ['questions.view', 'عرض بنك الأسئلة', 'المحتوى'],
        ['questions.create', 'إضافة الأسئلة وتعديل أسئلته', 'المحتوى'],
        ['questions.edit_any', 'تعديل أي سؤال', 'المحتوى'],
        ['questions.review', 'مراجعة الأسئلة ونشرها', 'المحتوى'],
        ['questions.import', 'استيراد الأسئلة', 'المحتوى'],
        ['questions.export', 'تصدير الأسئلة بإجاباتها', 'المحتوى'],
        ['taxonomy.manage', 'إدارة المجالات والمهارات والمؤشرات', 'المحتوى'],
        ['tests.view', 'عرض الاختبارات', 'التقويم'],
        ['tests.create', 'إنشاء الاختبارات وإسنادها', 'التقويم'],
        ['tests.manage_any', 'إدارة أي اختبار', 'التقويم'],
        ['results.view', 'عرض النتائج', 'التقويم'],
        ['results.grade', 'التصحيح اليدوي وإعادة المحاولات', 'التقويم'],
        ['reports.view', 'عرض التقارير', 'التقارير'],
        ['reports.school', 'تقارير المدرسة والملخص التنفيذي', 'التقارير'],
        ['reports.export', 'تصدير التقارير', 'التقارير'],
        ['remedial.view', 'عرض الخطط العلاجية والإثرائية', 'الخطط'],
        ['remedial.manage', 'إنشاء الخطط ومتابعتها', 'الخطط'],
        ['gamification.view', 'عرض التحفيز والإنجازات', 'التحفيز'],
        ['gamification.manage', 'إدارة الشارات والتحديات', 'التحفيز'],
        ['student.portal', 'بوابة الطالب وأداء الاختبارات', 'الطالب'],
    ];

    public const ROLES = [
        'admin' => ['مدير النظام', 'صلاحيات كاملة لإدارة المنصة', '*'],
        'principal' => ['قائد المدرسة', 'متابعة مؤشرات المدرسة والتقارير والخطط', [
            'dashboard.view', 'scope.all', 'students.view', 'teachers.view', 'questions.view', 'tests.view', 'results.view',
            'reports.view', 'reports.school', 'reports.export', 'remedial.view', 'gamification.view', 'audit.view',
        ]],
        'supervisor' => ['وكيل / مشرف', 'متابعة الفصول المسندة ونتائجها وتقاريرها', [
            'dashboard.view', 'students.view', 'teachers.view', 'questions.view', 'tests.view', 'results.view',
            'results.grade', 'reports.view', 'reports.export', 'remedial.view', 'remedial.manage', 'gamification.view',
        ]],
        'teacher' => ['معلم', 'إعداد الاختبارات ومتابعة طلاب فصوله', [
            'dashboard.view', 'students.view', 'questions.view', 'questions.create', 'tests.view', 'tests.create',
            'results.view', 'results.grade', 'reports.view', 'reports.export', 'remedial.view', 'remedial.manage',
            'gamification.view', 'gamification.manage',
        ]],
        'student' => ['طالب', 'أداء الاختبارات والتدريب ومتابعة التقدم', ['student.portal']],
    ];

    public static function run(): void
    {
        foreach (self::PERMISSIONS as [$slug, $name, $group]) {
            DB::run('INSERT INTO permissions (slug, name, group_name) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), group_name = VALUES(group_name)', [$slug, $name, $group]);
        }
        $permIds = [];
        foreach (DB::all('SELECT id, slug FROM permissions') as $row) {
            $permIds[$row['slug']] = (int) $row['id'];
        }

        foreach (self::ROLES as $slug => [$name, $description, $perms]) {
            $existing = DB::value('SELECT id FROM roles WHERE slug = ?', [$slug]);
            $roleId = $existing ? (int) $existing : DB::insert('roles', ['slug' => $slug, 'name' => $name, 'description' => $description, 'is_system' => 1]);
            if ($existing && $slug !== 'admin') {
                continue; // لا نعيد كتابة صلاحيات دور عدّلها المسؤول
            }
            $list = $perms === '*' ? array_keys($permIds) : $perms;
            foreach ($list as $perm) {
                if (isset($permIds[$perm])) {
                    DB::run('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)', [$roleId, $permIds[$perm]]);
                }
            }
        }
    }
}
