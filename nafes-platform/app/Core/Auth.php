<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\Audit;

/** المصادقة والأدوار والصلاحيات (RBAC) */
final class Auth
{
    private static ?array $user = null;
    private static ?array $roles = null;
    private static ?array $permissions = null;
    private static ?array $profile = null;

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $id = $_SESSION['uid'] ?? null;
        if (!$id) {
            return null;
        }
        $user = DB::first('SELECT * FROM users WHERE id = ? AND is_active = 1', [$id]);
        if (!$user) {
            self::logout();
            return null;
        }
        return self::$user = $user;
    }

    public static function id(): ?int
    {
        return isset(self::user()['id']) ? (int) self::user()['id'] : null;
    }

    /** أسماء أدوار المستخدم: admin, principal, supervisor, teacher, student */
    public static function roles(): array
    {
        if (self::$roles !== null) {
            return self::$roles;
        }
        $id = self::id();
        return self::$roles = $id ? DB::column(
            'SELECT r.slug FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?',
            [$id]
        ) : [];
    }

    public static function hasRole(string ...$slugs): bool
    {
        return (bool) array_intersect($slugs, self::roles());
    }

    public static function permissions(): array
    {
        if (self::$permissions !== null) {
            return self::$permissions;
        }
        $id = self::id();
        return self::$permissions = $id ? DB::column(
            'SELECT DISTINCT p.slug FROM user_roles ur
               JOIN role_permissions rp ON rp.role_id = ur.role_id
               JOIN permissions p ON p.id = rp.permission_id
              WHERE ur.user_id = ?',
            [$id]
        ) : [];
    }

    /** يملك المستخدم أياً من الصلاحيات المذكورة */
    public static function can(string ...$permissions): bool
    {
        return (bool) array_intersect($permissions, self::permissions());
    }

    public static function isStudent(): bool
    {
        return self::hasRole('student') && count(self::roles()) === 1;
    }

    /** سجل الطالب أو المعلم المرتبط بالمستخدم */
    public static function profile(): array
    {
        if (self::$profile !== null) {
            return self::$profile;
        }
        $id = self::id();
        return self::$profile = [
            'student_id' => $id ? (int) DB::value('SELECT id FROM students WHERE user_id = ?', [$id], 0) : 0,
            'teacher_id' => $id ? (int) DB::value('SELECT id FROM teachers WHERE user_id = ?', [$id], 0) : 0,
        ];
    }

    public static function studentId(): int
    {
        return self::profile()['student_id'];
    }

    public static function teacherId(): int
    {
        return self::profile()['teacher_id'];
    }

    /** محاولة تسجيل الدخول مع تحديد عدد المحاولات — تُعيد [نجاح، رسالة] */
    public static function attempt(string $username, string $password, string $ip): array
    {
        $username = mb_strtolower(trim($username));
        $max      = (int) config('app.login.max_attempts', 8);
        $window   = (int) config('app.login.window_min', 15);

        $failures = (int) DB::value(
            'SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND created_at > (NOW() - INTERVAL ? MINUTE) AND (ip = ? OR username = ?)',
            [$window, $ip, $username],
            0
        );
        if ($failures >= $max) {
            return [false, "تم إيقاف المحاولات مؤقتاً بسبب تكرار الإدخال الخاطئ. حاول بعد $window دقيقة."];
        }

        $user = DB::first('SELECT * FROM users WHERE username = ?', [$username]);
        // تحقق بزمن ثابت تقريباً حتى لو لم يوجد المستخدم
        $ok = password_verify($password, $user['password_hash'] ?? '$2y$10$abcdefghijklmnopqrstuuDummyHashForTimingProtectionxx');
        $ok = $ok && $user !== null;

        DB::insert('login_attempts', ['username' => mb_substr($username, 0, 64), 'ip' => $ip, 'success' => $ok ? 1 : 0]);
        if (random_int(1, 50) === 1) {
            DB::run('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 2 DAY)');
        }

        if (!$ok) {
            return [false, 'اسم المستخدم أو كلمة المرور غير صحيحة.'];
        }
        if ((int) $user['is_active'] !== 1) {
            return [false, 'هذا الحساب معطّل، يرجى مراجعة إدارة المدرسة.'];
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            DB::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $user['id']]);
        }

        self::login($user);
        return [true, ''];
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        $_SESSION['uid'] = (int) $user['id'];
        unset($_SESSION['_csrf']);
        self::$user = $user;
        self::$roles = self::$permissions = self::$profile = null;
        DB::update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $user['id']]);
        Audit::log('login', 'users', (int) $user['id']);
    }

    public static function logout(): void
    {
        self::$user = null;
        self::$roles = self::$permissions = self::$profile = null;
        Session::destroy();
    }

    /** الصفحة الرئيسية حسب الدور */
    public static function home(): string
    {
        return self::isStudent() ? '/student' : '/dashboard';
    }

    /** لإعادة الحساب بعد تعديل أدوار المستخدم الحالي أو في الاختبارات */
    public static function reset(): void
    {
        self::$user = null;
        self::$roles = self::$permissions = self::$profile = null;
    }
}
