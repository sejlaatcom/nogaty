<?php
declare(strict_types=1);

namespace App\Helpers;

use App\Core\Auth;

/** القائمة الجانبية حسب صلاحيات المستخدم */
final class Navigation
{
    public static function items(): array
    {
        if (Auth::isStudent()) {
            return [
                ['الرئيسية', '/student', 'house-door'],
                ['اختباراتي', '/student/tests', 'journal-check'],
                ['التدريب الذاتي', '/student/practice', 'lightning-charge'],
                ['نتائجي', '/student/results', 'bar-chart-line'],
                ['تقدمي في المهارات', '/student/progress', 'graph-up-arrow'],
                ['خطتي التدريبية', '/student/plan', 'clipboard2-check'],
                ['إنجازاتي', '/student/achievements', 'trophy'],
            ];
        }

        $groups = [
            'الرئيسية' => [
                ['لوحة المؤشرات', '/dashboard', 'speedometer2', 'dashboard.view'],
            ],
            'المدرسة' => [
                ['الطلاب', '/students', 'people', 'students.view'],
                ['المعلمون والإسناد', '/teachers', 'person-workspace', 'teachers.view'],
                ['الهيكل المدرسي', '/structure', 'diagram-3', 'structure.manage'],
            ],
            'المحتوى' => [
                ['بنك الأسئلة', '/questions', 'collection', 'questions.view'],
                ['المجالات والمهارات', '/taxonomy', 'diagram-2', 'questions.view'],
                ['النصوص القرائية', '/passages', 'book', 'questions.view'],
            ],
            'التقويم' => [
                ['الاختبارات', '/tests', 'ui-checks', 'tests.view'],
                ['التصحيح اليدوي', '/grading', 'pencil-square', 'results.grade'],
                ['التقارير والتحليلات', '/reports', 'clipboard-data', 'reports.view'],
                ['الخطط العلاجية والإثرائية', '/plans', 'heart-pulse', 'remedial.view'],
                ['التحفيز والإنجازات', '/motivation', 'award', 'gamification.view'],
            ],
            'الإدارة' => [
                ['المستخدمون والصلاحيات', '/users', 'shield-lock', 'users.manage'],
                ['الإعدادات', '/settings', 'gear', 'settings.manage'],
                ['النسخ الاحتياطي', '/backups', 'cloud-arrow-down', 'backups.manage'],
                ['سجل العمليات', '/audit', 'list-check', 'audit.view'],
            ],
        ];

        $out = [];
        foreach ($groups as $title => $items) {
            $visible = array_values(array_filter($items, static fn ($i) => Auth::can($i[3])));
            if ($visible) {
                $out[] = ['section' => $title];
                foreach ($visible as $item) {
                    $out[] = $item;
                }
            }
        }
        return $out;
    }

    /** هل الرابط نشط للمسار الحالي؟ */
    public static function active(string $href, string $current): bool
    {
        if ($href === '/student' || $href === '/dashboard') {
            return $current === $href;
        }
        return $current === $href || str_starts_with($current, $href . '/');
    }
}
