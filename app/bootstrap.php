<?php
/**
 * نقطة الإقلاع لكل صفحات النظام.
 * كل ملف في مجلد public يبدأ بـ: require __DIR__ . '/../app/bootstrap.php';
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_PATH', APP_ROOT . '/app');
define('CONFIG_PATH', APP_ROOT . '/config');
define('PUBLIC_PATH', APP_ROOT . '/public');
define('STORAGE_PATH', APP_ROOT . '/storage');

mb_internal_encoding('UTF-8');

/* ------------------------------------------------------------------ */
/* الإعدادات                                                            */
/* ------------------------------------------------------------------ */
$configFile = CONFIG_PATH . '/config.php';

if (!is_file($configFile)) {
    // لم يتم التثبيت بعد
    if (!defined('APP_INSTALLER')) {
        $prefix = defined('APP_AREA') && APP_AREA !== '' ? '../' : '';
        header('Location: ' . $prefix . 'install.php');
        exit;
    }
    $config = [];
} else {
    $config = require $configFile;
}

$config += [
    'app_name' => 'نظام الخطط الأسبوعية',
    'timezone' => 'Asia/Riyadh',
    'debug'    => false,
    'app_key'  => 'change-me',
];

date_default_timezone_set($config['timezone']);

if (!empty($config['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

/* ------------------------------------------------------------------ */
/* رابط النظام الأساسي (يعمل داخل مجلد فرعي بدون تعديل)                  */
/* ------------------------------------------------------------------ */
$script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$dir    = rtrim(str_replace('\\', '/', dirname($script)), '/');
if (defined('APP_AREA') && APP_AREA !== '') {
    $dir = (string) preg_replace('#/' . preg_quote(APP_AREA, '#') . '$#', '', $dir);
}
define('BASE_URL', $dir === '/' ? '' : $dir);

/* ------------------------------------------------------------------ */
/* المكتبات                                                             */
/* ------------------------------------------------------------------ */
require APP_PATH . '/helpers.php';
require APP_PATH . '/lib/Database.php';
require APP_PATH . '/lib/Settings.php';
require APP_PATH . '/lib/Auth.php';
require APP_PATH . '/lib/Hijri.php';
require APP_PATH . '/lib/Plans.php';
require APP_PATH . '/lib/Activity.php';
require APP_PATH . '/lib/Xlsx.php';
require APP_PATH . '/lib/Csv.php';
require APP_PATH . '/lib/Importer.php';
require APP_PATH . '/lib/Migrator.php';
require APP_PATH . '/lib/Nafes.php';
require APP_PATH . '/lib/QuestionBank.php';

Database::configure($config['db'] ?? []);
$GLOBALS['config'] = $config;

/* ------------------------------------------------------------------ */
/* الجلسة                                                               */
/* ------------------------------------------------------------------ */
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL === '' ? '/' : BASE_URL . '/',
        'httponly' => true,
        'secure'   => $https,
        'samesite' => 'Lax',
    ]);
    session_name('WPLAN_SESSID');
    session_start();
}
