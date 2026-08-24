<?php
/**
 * المصادقة والصلاحيات (RBAC)
 */
class Auth
{
    private static ?array $user = null;
    private static ?array $permissions = null;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => !empty($_SERVER['HTTPS']),
            ]);
            session_name('ARCHIVESESSID');
            session_start();
        }
        // تجديد معرف الجلسة دورياً
        if (!isset($_SESSION['_regenerated'])) {
            $_SESSION['_regenerated'] = time();
        } elseif (time() - $_SESSION['_regenerated'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_regenerated'] = time();
        }
    }

    public static function attempt(string $username, string $password): array
    {
        $ip = Request::ip();
        $max     = (int) setting('login_max_attempts', 5);
        $lockout = (int) setting('login_lockout_minutes', 15);

        $fails = (int) Database::value(
            "SELECT COUNT(*) FROM login_attempts
              WHERE ip_address = ? AND success = 0 AND created_at > (NOW() - INTERVAL ? MINUTE)",
            [$ip, $lockout]
        );
        if ($max > 0 && $fails >= $max) {
            return ['ok' => false, 'message' => "تم إيقاف المحاولات مؤقتاً لمدة {$lockout} دقيقة بسبب تكرار محاولات الدخول الخاطئة."];
        }

        $user = Database::first(
            "SELECT * FROM users WHERE username = ? LIMIT 1",
            [$username]
        );

        $ok = $user && password_verify($password, $user['password_hash']);

        Database::insert('login_attempts', [
            'username'   => mb_substr($username, 0, 60),
            'ip_address' => $ip,
            'success'    => $ok ? 1 : 0,
        ]);

        if (!$ok) {
            return ['ok' => false, 'message' => 'اسم المستخدم أو كلمة المرور غير صحيحة.'];
        }
        if (!$user['is_active']) {
            return ['ok' => false, 'message' => 'الحساب موقوف، يرجى مراجعة مدير النظام.'];
        }

        // تحديث تجزئة كلمة المرور إن لزم
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Database::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)],
                'id = :id', ['id' => $user['id']]);
        }

        session_regenerate_id(true);
        $_SESSION['user_id']       = (int) $user['id'];
        $_SESSION['_regenerated']  = time();
        $_SESSION['login_time']    = time();

        Database::update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $ip,
        ], 'id = :id', ['id' => $user['id']]);

        Audit::log('login', 'user', (int)$user['id'], 'تسجيل دخول للنظام');

        return ['ok' => true, 'user' => $user];
    }

    public static function logout(): void
    {
        if (self::check()) {
            Audit::log('logout', 'user', self::id(), 'تسجيل خروج من النظام');
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        $u = Database::first(
            "SELECT u.*, r.name AS role_name, r.slug AS role_slug, d.name AS department_name
               FROM users u
               JOIN roles r ON r.id = u.role_id
               LEFT JOIN departments d ON d.id = u.department_id
              WHERE u.id = ? AND u.is_active = 1 LIMIT 1",
            [$_SESSION['user_id']]
        );
        self::$user = $u ?: null;
        return self::$user;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int) $u['id'] : null;
    }

    public static function isAdmin(): bool
    {
        $u = self::user();
        return $u && $u['role_slug'] === 'admin';
    }

    /** كل أكواد صلاحيات المستخدم الحالي */
    public static function permissions(): array
    {
        if (self::$permissions !== null) {
            return self::$permissions;
        }
        $u = self::user();
        if (!$u) {
            return self::$permissions = [];
        }
        $rows = Database::all(
            "SELECT p.code FROM permissions p
               JOIN role_permissions rp ON rp.permission_id = p.id
              WHERE rp.role_id = ?",
            [$u['role_id']]
        );
        return self::$permissions = array_column($rows, 'code');
    }

    public static function can(string $code): bool
    {
        if (self::isAdmin()) {
            return true;
        }
        return in_array($code, self::permissions(), true);
    }

    /** يتطلب تسجيل الدخول وإلا يحوّل لصفحة الدخول */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? '';
            redirect('auth/login');
        }
    }

    /** يتطلب صلاحية معينة */
    public static function requirePermission(string $code): void
    {
        self::requireLogin();
        if (!self::can($code)) {
            http_response_code(403);
            View::render('errors/403', ['permission' => $code], 'app');
            exit;
        }
    }
}
