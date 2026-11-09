<?php
declare(strict_types=1);

use App\Core\DB;

/** البيانات الأساسية: العام الدراسي، الصفوف، الفصول، المواد، التصنيفات، الشارات */
final class BaseSeeder
{
    public static function run(array $options = []): void
    {
        $yearId = self::year();
        self::grades();
        self::subjects();
        self::classes($yearId, (int) ($options['classes'] ?? 3));
        self::taxonomy();
        self::badges();
    }

    /** العام الدراسي الحالي (هجري) */
    public static function year(): int
    {
        $hijri = self::hijriYear();
        // العام الدراسي يبدأ تقريباً في صفر؛ نستخدم العام الهجري الحالي
        $name  = $hijri . 'هـ';
        $id = (int) DB::value('SELECT id FROM academic_years WHERE name = ?', [$name], 0);
        if (!$id) {
            DB::run('UPDATE academic_years SET is_current = 0');
            $start = (int) date('n') >= 8 ? date('Y') . '-08-15' : (date('Y') - 1) . '-08-15';
            $id = DB::insert('academic_years', [
                'name' => $name, 'starts_on' => $start,
                'ends_on' => date('Y-m-d', strtotime($start . ' +10 months')), 'is_current' => 1,
            ]);
        }
        return $id;
    }

    public static function hijriYear(): int
    {
        if (class_exists('IntlDateFormatter')) {
            $fmt = new IntlDateFormatter('en_US@calendar=islamic-umalqura', IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'Asia/Riyadh', IntlDateFormatter::TRADITIONAL, 'y');
            $year = (int) $fmt->format(time());
            if ($year > 1400) {
                return $year;
            }
        }
        return (int) floor((time() / 86400 + 2440587.5 - 1948439.5) / 354.36667) + 1;
    }

    public static function grades(): void
    {
        $grades = [
            // [الاسم، المرحلة، الترتيب، مستهدف بنافس، مفعّل]
            ['الثالث الابتدائي', 'primary', 3, 1, 0],
            ['السادس الابتدائي', 'primary', 6, 1, 0],
            ['الأول المتوسط', 'intermediate', 7, 0, 1],
            ['الثاني المتوسط', 'intermediate', 8, 0, 1],
            ['الثالث المتوسط', 'intermediate', 9, 1, 1],
            ['الأول الثانوي', 'secondary', 10, 0, 1],
            ['الثاني الثانوي', 'secondary', 11, 0, 1],
            ['الثالث الثانوي', 'secondary', 12, 0, 1],
        ];
        foreach ($grades as [$name, $stage, $order, $target, $active]) {
            DB::run(
                'INSERT INTO grades (name, stage, level_order, is_nafes_target, is_active) VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE level_order = VALUES(level_order)',
                [$name, $stage, $order, $target, $active]
            );
        }
    }

    public static function subjects(): void
    {
        $subjects = [
            ['القراءة', 'reading', '#0ea5b7', 'book-half', 1],
            ['الرياضيات', 'math', '#0b5394', 'calculator', 2],
            ['العلوم', 'science', '#16803c', 'flask', 3],
        ];
        foreach ($subjects as [$name, $code, $color, $icon, $order]) {
            DB::run(
                'INSERT INTO subjects (name, code, color, icon, sort_order) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order)',
                [$name, $code, $color, $icon, $order]
            );
        }
    }

    /** فصول الثالث المتوسط للعام الحالي */
    public static function classes(int $yearId, int $count): void
    {
        $gradeId = (int) DB::value("SELECT id FROM grades WHERE name = 'الثالث المتوسط'", [], 0);
        for ($i = 1; $i <= $count; $i++) {
            DB::run('INSERT IGNORE INTO classes (academic_year_id, grade_id, name) VALUES (?, ?, ?)', [$yearId, $gradeId, (string) $i]);
        }
    }

    public static function taxonomy(): void
    {
        $gradeId = (int) DB::value("SELECT id FROM grades WHERE name = 'الثالث المتوسط'", [], 0);
        $data = require __DIR__ . '/data/taxonomy.php';
        foreach ($data as $code => $domains) {
            $subjectId = (int) DB::value('SELECT id FROM subjects WHERE code = ?', [$code], 0);
            if (!$subjectId) {
                continue;
            }
            $dOrder = 0;
            foreach ($domains as $domainName => $skills) {
                $domainId = (int) DB::value('SELECT id FROM domains WHERE subject_id = ? AND grade_id = ? AND name = ?', [$subjectId, $gradeId, $domainName], 0)
                    ?: DB::insert('domains', ['subject_id' => $subjectId, 'grade_id' => $gradeId, 'name' => $domainName, 'sort_order' => ++$dOrder]);
                $sOrder = 0;
                foreach ($skills as [$skillCode, $skillName, $indicators]) {
                    $skillId = (int) DB::value('SELECT id FROM skills WHERE domain_id = ? AND name = ?', [$domainId, $skillName], 0)
                        ?: DB::insert('skills', ['domain_id' => $domainId, 'code' => $skillCode, 'name' => $skillName, 'sort_order' => ++$sOrder]);
                    foreach ($indicators as $i => $text) {
                        DB::run('INSERT IGNORE INTO learning_indicators (skill_id, code, description, sort_order) VALUES (?, ?, ?, ?)',
                            [$skillId, $skillCode . '-' . ($i + 1), $text, $i + 1]);
                    }
                }
            }
        }
    }

    public static function badges(): void
    {
        $badges = [
            ['first_test', 'البداية الموفقة', 'أكمل أول اختبار على المنصة', 'flag', '#0ea5b7', 'tests_completed', 1, 10],
            ['tests_5', 'المثابر', 'أكمل خمسة اختبارات', 'journal-check', '#0b5394', 'tests_completed', 5, 25],
            ['practice_10', 'المتدرب النشط', 'أنهى عشر جلسات تدريب ذاتي', 'lightning-charge', '#e8780c', 'practice_count', 10, 25],
            ['perfect', 'العلامة الكاملة', 'حصل على 100٪ في اختبار', 'star-fill', '#d4a017', 'perfect_score', 1, 30],
            ['improver', 'صاعد بثبات', 'تحسنت نتيجته 15 نقطة مئوية أو أكثر عن محاولة سابقة في المادة', 'graph-up-arrow', '#16803c', 'improvement', 15, 30],
            ['skills_5', 'متقن المهارات', 'أتقن خمس مهارات', 'patch-check', '#6d3fd1', 'skills_mastered', 5, 40],
            ['weekly_goal', 'هدف الأسبوع', 'حقق هدف التدريب الأسبوعي', 'calendar-check', '#0ea5b7', 'weekly_goal', 1, 20],
            ['plan_done', 'تجاوز التحدي', 'تحسن مستواه بعد خطة علاجية', 'heart-pulse', '#cf2e2e', 'plan_improved', 1, 40],
        ];
        foreach ($badges as [$code, $name, $desc, $icon, $color, $type, $value, $points]) {
            DB::run(
                'INSERT INTO badges (code, name, description, icon, color, criteria_type, criteria_value, points) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE name = VALUES(name)',
                [$code, $name, $desc, $icon, $color, $type, $value, $points]
            );
        }
    }
}
