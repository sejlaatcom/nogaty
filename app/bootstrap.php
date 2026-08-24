<?php
/**
 * تهيئة النظام : المسارات، الإعدادات، الاتصال بقاعدة البيانات
 */
declare(strict_types=1);

define('BASE_PATH',    dirname(__DIR__));
define('APP_PATH',     BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('PUBLIC_PATH',  BASE_PATH . '/public');
define('APP_VERSION',  '1.0.0');

mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Riyadh');

// ------------------------- التحميل التلقائي للفئات -------------------------
spl_autoload_register(static function (string $class): void {
    foreach (['/Core/', '/Controllers/', '/Models/'] as $dir) {
        $file = APP_PATH . $dir . $class . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

require_once APP_PATH . '/helpers.php';

// ------------------------------- الإعدادات -------------------------------
$configFile = APP_PATH . '/config.php';
if (!is_file($configFile)) {
    if (PHP_SAPI !== 'cli' && is_dir(BASE_PATH . '/install')) {
        header('Location: ../install/index.php');
        exit;
    }
    exit("ملف الإعدادات app/config.php غير موجود. شغّل برنامج التنصيب أولاً.\n");
}
$config = require $configFile;

// -------------------------- عرض الأخطاء حسب البيئة --------------------------
if (!empty($config['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}
ini_set('log_errors', '1');
if (is_dir(STORAGE_PATH . '/logs')) {
    ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');
}

// -------------------------- الاتصال بقاعدة البيانات --------------------------
try {
    Database::connect($config['db']);
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('<div style="font-family:Tahoma;direction:rtl;padding:2rem">'
        . '<h2>تعذر الاتصال بقاعدة البيانات</h2><p>يرجى مراجعة إعدادات الاتصال في app/config.php.</p>'
        . (!empty($config['debug']) ? '<pre dir="ltr">' . htmlspecialchars($e->getMessage()) . '</pre>' : '')
        . '</div>');
}

// -------------------------------- الجلسة ---------------------------------
Auth::start();

// ------------------------- معالج الأخطاء غير الملتقطة -------------------------
set_exception_handler(static function (Throwable $e) use ($config): void {
    error_log('Uncaught: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (!empty($config['debug'])) {
        echo '<div style="font-family:Tahoma;direction:rtl;padding:2rem">'
           . '<h2>خطأ في النظام</h2><p>' . htmlspecialchars($e->getMessage()) . '</p>'
           . '<pre dir="ltr" style="background:#f5f5f5;padding:1rem;overflow:auto">'
           . htmlspecialchars($e->getTraceAsString()) . '</pre></div>';
    } else {
        echo '<div style="font-family:Tahoma;direction:rtl;padding:2rem;text-align:center">'
           . '<h2>حدث خطأ غير متوقع</h2><p>تم تسجيل الخطأ، يرجى المحاولة لاحقاً أو مراجعة مدير النظام.</p>'
           . '<a href="index.php">العودة للرئيسية</a></div>';
    }
});
