<?php
declare(strict_types=1);

/** إعدادات المدرسة المخزنة في قاعدة البيانات */
final class Settings
{
    private static ?array $cache = null;

    public const DEFAULTS = [
        'school_name'      => 'مدرسة النموذج الابتدائية',
        'ministry_name'    => 'وزارة التعليم',
        'country_name'     => 'المملكة العربية السعودية',
        'education_dept'   => 'الإدارة العامة للتعليم بمنطقة ...',
        'principal_title'  => 'مدير المدرسة',
        'principal_name'   => '',
        'stage'            => 'ابتدائي',
        'logo'             => '',
        'vision_logo'      => '',
        'sheet_title'      => 'الخطة الأسبوعية التعليمية',
        'family_message'   => 'نأمل متابعة التكليفات الواردة أعلاه ومراجعة دروس الأسبوع، تواصلكم مع المدرسة شريك أول في التحصيل.',
        'show_notes_row'   => '0',
        'show_teacher_name'=> '1',
        'public_enabled'   => '1',
        'footer_text'      => 'سجل الخطة الأسبوعية',
        'contact_email'    => '',
        // منصة نافس
        'nafes_enabled'        => '1',
        'nafes_title'          => 'منصة نافس للتدريب على الاختبارات الوطنية',
        'nafes_subject_ids'    => '',   // معرفات مواد نافس (فارغ = كل المواد)
        'nafes_grade_ids'      => '',   // معرفات صفوف نافس (فارغ = كل الصفوف)
        'nafes_level_advanced'   => '85',
        'nafes_level_proficient' => '65',
        'nafes_level_basic'      => '50',
        'nafes_practice_size'  => '10',
        'nafes_student_review' => '1',  // السماح للطالب بمراجعة إجاباته بعد التسليم
    ];

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $values = [];
        try {
            foreach (Database::all('SELECT `key`, `value` FROM `settings`') as $row) {
                $values[$row['key']] = $row['value'];
            }
        } catch (Throwable) {
            $values = [];
        }

        return self::$cache = $values + self::DEFAULTS;
    }

    public static function get(string $key, ?string $default = null): string
    {
        $all = self::all();
        $value = $all[$key] ?? $default ?? self::DEFAULTS[$key] ?? '';
        return (string) $value;
    }

    public static function bool(string $key): bool
    {
        return self::get($key) === '1';
    }

    public static function set(string $key, ?string $value): void
    {
        Database::run(
            'INSERT INTO `settings` (`key`, `value`) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
            ['k' => $key, 'v' => $value]
        );
        self::$cache = null;
    }

    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            self::set((string) $key, $value === null ? null : (string) $value);
        }
    }
}
