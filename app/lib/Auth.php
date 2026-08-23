<?php
declare(strict_types=1);

/** المصادقة والصلاحيات */
final class Auth
{
    private const MAX_ATTEMPTS = 8;      // محاولات خاطئة
    private const WINDOW_MIN   = 15;     // خلال 15 دقيقة

    private static ?array $user = null;

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
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
        $user = Database::first('SELECT * FROM users WHERE id = ? AND is_active = 1', [$id]);
        if (!$user) {
            self::logout();
            return null;
        }
        return self::$user = $user;
    }

    public static function role(): ?string
    {
        $user = self::user();
        return $user['role'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    /** محاولة تسجيل الدخول */
    public static function attempt(string $username, string $password): array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (self::tooManyAttempts($ip, $username)) {
            return [false, 'تم إيقاف المحاولات مؤقتاً بسبب تكرار الإدخال الخاطئ. حاول بعد ' . self::WINDOW_MIN . ' دقيقة.'];
        }

        $user = Database::first('SELECT * FROM users WHERE username = ?', [$username]);
        $ok   = $user && password_verify($password, $user['password_hash']);

        self::recordAttempt($username, $ip, $ok);

        if (!$ok) {
            return [false, 'اسم المستخدم أو كلمة المرور غير صحيحة.'];
        }
        if ((int) $user['is_active'] !== 1) {
            return [false, 'هذا الحساب موقوف، يرجى مراجعة إدارة المدرسة.'];
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Database::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $user['id']]);
        }

        self::login($user);
        return [true, ''];
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['uid']  = (int) $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
        self::$user = $user;

        Database::update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $user['id']]);
        Activity::log('login', 'users', (int) $user['id']);
    }

    public static function logout(): void
    {
        self::$user = null;
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /** يشترط تسجيل الدخول */
    public static function requireLogin(): array
    {
        $user = self::user();
        if (!$user) {
            $_SESSION['_intended'] = $_SERVER['REQUEST_URI'] ?? null;
            redirect('login.php');
        }
        return $user;
    }

    /** يشترط دوراً محدداً */
    public static function requireRole(string $role): array
    {
        $user = self::requireLogin();
        if ($user['role'] !== $role) {
            http_response_code(403);
            exit('ليس لديك صلاحية للوصول لهذه الصفحة.');
        }
        return $user;
    }

    public static function requireAdmin(): array
    {
        return self::requireRole('admin');
    }

    public static function requireTeacher(): array
    {
        return self::requireRole('teacher');
    }

    /** لوحة التحكم المناسبة لدور المستخدم */
    public static function home(): string
    {
        return self::isAdmin() ? 'admin/index.php' : 'teacher/index.php';
    }

    private static function tooManyAttempts(string $ip, string $username): bool
    {
        $count = (int) Database::value(
            'SELECT COUNT(*) FROM login_attempts
              WHERE success = 0 AND created_at > (NOW() - INTERVAL ? MINUTE)
                AND (ip = ? OR username = ?)',
            [self::WINDOW_MIN, $ip, $username],
            0
        );
        return $count >= self::MAX_ATTEMPTS;
    }

    private static function recordAttempt(string $username, string $ip, bool $success): void
    {
        Database::insert('login_attempts', [
            'username' => mb_substr($username, 0, 64),
            'ip'       => $ip,
            'success'  => $success ? 1 : 0,
        ]);
        // تنظيف السجلات القديمة
        Database::run('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)');
    }
}
