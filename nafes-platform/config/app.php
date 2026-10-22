<?php
use App\Core\Env;

return [
    'name'        => Env::get('APP_NAME', 'منصة نافس التدريبية'),
    'env'         => Env::get('APP_ENV', 'production'),
    'debug'       => (bool) Env::get('APP_DEBUG', false),
    'url'         => rtrim((string) Env::get('APP_URL', ''), '/'),
    'key'         => Env::get('APP_KEY', ''),
    'timezone'    => Env::get('APP_TIMEZONE', 'Asia/Riyadh'),
    // false إن لم يدعم الخادم إعادة كتابة الروابط (تصبح الروابط /index.php/students)
    'pretty_urls' => (bool) Env::get('APP_PRETTY_URLS', true),
    'session'     => [
        'name'     => 'NAFES_SESSID',
        'lifetime' => (int) Env::get('SESSION_LIFETIME', 120),   // دقائق خمول
        'secure'   => Env::get('SESSION_SECURE', 'auto'),
    ],
    'uploads'     => [
        'max_mb'      => (int) Env::get('UPLOAD_MAX_MB', 5),
        'image_types' => ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'],
    ],
    'login'       => [
        'max_attempts' => 8,
        'window_min'   => 15,
    ],
];
