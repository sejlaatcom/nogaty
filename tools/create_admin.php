<?php
/**
 * إنشاء أو إعادة تعيين حساب مدير من سطر الأوامر:
 *   php tools/create_admin.php <username> <password> ["الاسم"]
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("هذا الملف يعمل من سطر الأوامر فقط.\n");
}

require __DIR__ . '/../app/bootstrap.php';

$username = $argv[1] ?? '';
$password = $argv[2] ?? '';
$name     = $argv[3] ?? 'مدير المدرسة';

if ($username === '' || mb_strlen($password) < 6) {
    exit("الاستخدام: php tools/create_admin.php <username> <password> [\"الاسم\"]\n");
}

$existing = Database::first('SELECT id FROM users WHERE username = ?', [$username]);
$hash = password_hash($password, PASSWORD_DEFAULT);

if ($existing) {
    Database::update('users', ['password_hash' => $hash, 'role' => 'admin', 'is_active' => 1], 'id = :id', ['id' => $existing['id']]);
    echo "تم تحديث الحساب: $username\n";
} else {
    Database::insert('users', [
        'name'          => $name,
        'username'      => $username,
        'password_hash' => $hash,
        'role'          => 'admin',
        'job_title'     => 'مدير المدرسة',
        'is_active'     => 1,
    ]);
    echo "تم إنشاء الحساب: $username\n";
}
