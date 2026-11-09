<?php
/**
 * ترقية قاعدة البيانات من سطر الأوامر (بعد تحديث ملفات النظام):
 *   php tools/migrate.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('يُشغَّل من سطر الأوامر فقط.');
}

require __DIR__ . '/../app/bootstrap.php';

foreach (Migrator::run() as $line) {
    echo '✓ ' . $line . PHP_EOL;
}
