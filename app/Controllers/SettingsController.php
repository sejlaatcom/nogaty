<?php
/**
 * إعدادات النظام
 */
class SettingsController
{
    private const KEYS = [
        'school_name','school_stage','education_admin','app_name','items_per_page','max_upload_mb',
        'allowed_extensions','transaction_no_format','seq_padding','date_type','enable_audit',
        'login_max_attempts','login_lockout_minutes','theme_color','school_logo',
    ];

    public function index(): void
    {
        Auth::requirePermission('settings.manage');

        $dbSize = (float) Database::value(
            'SELECT COALESCE(SUM(data_length + index_length),0) / 1048576
               FROM information_schema.TABLES WHERE table_schema = DATABASE()');

        View::render('settings/index', [
            'title'   => 'إعدادات النظام',
            'stats'   => [
                'db_size'      => round($dbSize, 2),
                'files_size'   => (int) Database::value("SELECT COALESCE(SUM(file_size),0) FROM attachments WHERE kind='file'"),
                'files_count'  => (int) Database::value("SELECT COUNT(*) FROM attachments WHERE kind='file'"),
                'drive_count'  => (int) Database::value("SELECT COUNT(*) FROM attachments WHERE kind='drive'"),
                'php_version'  => PHP_VERSION,
                'mysql_version'=> (string) Database::value('SELECT VERSION()'),
                'upload_max'   => ini_get('upload_max_filesize'),
                'post_max'     => ini_get('post_max_size'),
                'app_version'  => APP_VERSION,
            ],
            'sequences' => Database::all('SELECT * FROM sequences ORDER BY year DESC, name'),
        ]);
    }

    public function update(): void
    {
        Auth::requirePermission('settings.manage');
        Csrf::check();

        foreach (self::KEYS as $key) {
            if (!array_key_exists($key, $_POST)) {
                // خانات الاختيار غير المرسلة تعني إيقافها
                if (in_array($key, ['enable_audit'], true)) {
                    settingSet($key, '0');
                }
                continue;
            }
            $value = trim((string) $_POST[$key]);
            $value = match ($key) {
                'items_per_page'        => (string) max(5, min(200, (int) $value)),
                'max_upload_mb'         => (string) max(1, min(2048, (int) $value)),
                'seq_padding'           => (string) max(1, min(10, (int) $value)),
                'login_max_attempts'    => (string) max(0, min(50, (int) $value)),
                'login_lockout_minutes' => (string) max(1, min(1440, (int) $value)),
                'date_type'             => pick($value, ['gregorian','hijri','both'], 'both'),
                'enable_audit'          => $value ? '1' : '0',
                default                 => mb_substr($value, 0, 1000),
            };
            settingSet($key, $value);
        }

        Audit::log('update', 'settings', null, 'تحديث إعدادات النظام');
        flash('success', 'تم حفظ الإعدادات بنجاح.');
        redirect('settings/index');
    }

    /** تصفير عدادات ترقيم المعاملات لسنة جديدة */
    public function resetSequence(): void
    {
        Auth::requirePermission('settings.manage');
        Csrf::check();
        $name = pick(Request::post('name'), ['incoming','outgoing','internal'], 'incoming');
        $year = Request::int('year', (int) date('Y'));
        Database::run(
            'INSERT INTO sequences (name, year, current_no) VALUES (?, ?, 0)
             ON DUPLICATE KEY UPDATE current_no = 0', [$name, $year]);
        Audit::log('update', 'settings', null, "تصفير عداد الترقيم : $name / $year");
        flash('success', 'تم تصفير العداد.');
        redirect('settings/index');
    }
}
