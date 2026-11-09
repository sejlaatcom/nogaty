<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use Throwable;

/** إعدادات المنصة المخزنة في قاعدة البيانات مع القيم الافتراضية */
final class Settings
{
    private static ?array $cache = null;

    public const DEFAULTS = [
        // المدرسة
        'school_name'        => 'مجمع الأمير سعود بن نايف التعليمي بالدمام',
        'school_section'     => 'القسم المتوسط والثانوي — بنين',
        'education_dept'     => 'الإدارة العامة للتعليم بالمنطقة الشرقية',
        'principal_name'     => '',
        'logo'               => '',
        'platform_title'     => 'منصة التدريب على اختبارات نافس',
        // الألوان (تُحقن كمتغيرات CSS)
        'color_primary'      => '#0b5394',
        'color_accent'       => '#0ea5b7',
        // الاختبارات
        'default_duration'   => '40',
        'default_attempts'   => '1',
        'default_visibility' => 'immediate',
        'practice_size'      => '10',
        'autosave_seconds'   => '20',
        // مستويات الأداء (٪)
        'level_advanced'     => '85',
        'level_proficient'   => '65',
        'level_basic'        => '50',
        'mastery_threshold'  => '65',     // حد إتقان المهارة
        'remedial_min_answers' => '3',    // أقل عدد إجابات للحكم على المهارة
        // الاستيراد
        'import_create_taxonomy' => '1',
        'import_default_status'  => 'review',
        // التقارير
        'report_disclaimer'  => 'تعكس المؤشرات أداء الطلاب في الاختبارات التدريبية المتاحة على المنصة، ولا تمثل نتيجة نافس الرسمية ولا تتنبأ بها.',
        'report_footer'      => '',
        // الملفات والنسخ الاحتياطي
        'upload_max_mb'      => '5',
        'backup_keep'        => '10',
        // الخصوصية والتحفيز
        'leaderboard_names'  => 'short',  // full | short | anonymous
        'gamification'       => '1',
        'weekly_goal'        => '30',     // عدد أسئلة التدريب الأسبوعية
        'retention_years'    => '3',
        // الصلاحيات
        'teacher_can_publish_questions' => '0',
        'teacher_sees_all_bank'         => '1',
    ];

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $values = [];
        try {
            foreach (DB::all('SELECT `key`, `value` FROM settings') as $row) {
                $values[$row['key']] = $row['value'];
            }
        } catch (Throwable) {
        }
        return self::$cache = $values + self::DEFAULTS;
    }

    public static function get(string $key, ?string $default = null): string
    {
        return (string) (self::all()[$key] ?? $default ?? '');
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    public static function bool(string $key): bool
    {
        return self::get($key) === '1';
    }

    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            DB::run(
                'INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
                [(string) $key, $value === null ? null : (string) $value]
            );
        }
        self::$cache = null;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
