<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use Throwable;

/** سجل العمليات الحساسة */
final class Audit
{
    public const LABELS = [
        'login' => 'تسجيل دخول', 'logout' => 'تسجيل خروج', 'password_change' => 'تغيير كلمة المرور',
        'user_create' => 'إنشاء مستخدم', 'user_update' => 'تعديل مستخدم', 'user_toggle' => 'تفعيل/تعطيل حساب',
        'password_reset' => 'إعادة تعيين كلمة مرور', 'roles_update' => 'تعديل الصلاحيات',
        'student_create' => 'إضافة طالب', 'student_update' => 'تعديل طالب', 'student_transfer' => 'نقل طالب',
        'student_delete' => 'حذف طالب', 'teacher_create' => 'إضافة معلم', 'teacher_update' => 'تعديل معلم',
        'import' => 'استيراد بيانات', 'question_create' => 'إضافة سؤال', 'question_update' => 'تعديل سؤال',
        'question_status' => 'تغيير حالة سؤال', 'question_import' => 'استيراد أسئلة', 'question_export' => 'تصدير أسئلة',
        'test_create' => 'إنشاء اختبار', 'test_update' => 'تعديل اختبار', 'test_status' => 'تغيير حالة اختبار',
        'test_delete' => 'حذف اختبار', 'attempt_reset' => 'إعادة محاولة طالب', 'manual_grade' => 'تصحيح يدوي',
        'results_release' => 'إعلان النتائج', 'plan_create' => 'إنشاء خطة علاجية', 'plan_update' => 'تعديل خطة',
        'settings_update' => 'تعديل الإعدادات', 'backup_create' => 'نسخة احتياطية', 'backup_restore' => 'استعادة نسخة',
        'year_update' => 'تعديل العام الدراسي', 'structure_update' => 'تعديل الصفوف/الفصول/المواد',
        'assignment_update' => 'تعديل الإسناد', 'report_export' => 'تصدير تقرير', 'data_retention' => 'تطبيق سياسة الاحتفاظ',
    ];

    public static function log(string $action, ?string $entity = null, ?int $entityId = null, array|string|null $details = null): void
    {
        try {
            DB::insert('audit_logs', [
                'user_id'    => Auth::id(),
                'action'     => $action,
                'entity'     => $entity,
                'entity_id'  => $entityId,
                'details'    => is_array($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : $details,
                'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 250) : null,
            ]);
        } catch (Throwable) {
            // السجل لا يوقف العملية الأصلية
        }
    }
}
