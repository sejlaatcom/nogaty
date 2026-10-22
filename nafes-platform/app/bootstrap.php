<?php
/**
 * إقلاع التطبيق: التحميل التلقائي، متغيرات البيئة، الإعدادات، الجلسة ومعالجة الأخطاء.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('PUBLIC_PATH', BASE_PATH . '/public');

// Composer إن توفر، وإلا محمّل PSR-4 بسيط لنفس الهيكل (تعمل المنصة بدون Composer)
if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class): void {
        if (str_starts_with($class, 'App\\')) {
            $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
    require APP_PATH . '/Helpers/functions.php';
}

mb_internal_encoding('UTF-8');

App\Core\Env::load(BASE_PATH . '/.env');
App\Core\Config::load(BASE_PATH . '/config');

date_default_timezone_set((string) config('app.timezone', 'Asia/Riyadh'));

if (config('app.debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');
