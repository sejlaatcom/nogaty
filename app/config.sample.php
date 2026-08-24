<?php
/**
 * نموذج ملف الإعدادات — انسخه باسم config.php وعدّل بياناته،
 * أو استخدم برنامج التنصيب /install/index.php لتوليده تلقائياً.
 */
return [
    'app_url' => '',      // مثال : https://school.example.com
    'debug'   => false,   // فعّله أثناء التطوير فقط

    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'database' => 'school_archive',
        'username' => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],
];
