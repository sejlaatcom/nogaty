<?php
/**
 * ملف الإعدادات — انسخ هذا الملف باسم config.php ثم عدّل القيم.
 * (يقوم مثبّت النظام install.php بإنشائه تلقائياً)
 */
return [
    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'database' => 'weekly_plans',
        'username' => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],

    // اسم النظام الظاهر في المتصفح
    'app_name' => 'نظام الخطط الأسبوعية',

    // المنطقة الزمنية
    'timezone' => 'Asia/Riyadh',

    // إظهار تفاصيل الأخطاء (اجعلها false في الاستضافة الفعلية)
    'debug' => false,

    // مفتاح تشفير الجلسات (يُنشأ عشوائياً عند التثبيت)
    'app_key' => 'change-me',
];
