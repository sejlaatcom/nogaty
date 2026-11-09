<?php
declare(strict_types=1);

namespace App\Core;

/** الجلسة: ملفات تعريف آمنة، انتهاء بالخمول، رسائل Flash، والقيم القديمة للنماذج */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') {
            return;
        }
        $secure = config('app.session.secure');
        $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_name((string) config('app.session.name', 'NAFES_SESSID'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => (base_path_url() ?: '') . '/',
            'httponly' => true,
            'secure'   => $secure === 'auto' ? $https : (bool) $secure,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();

        // انتهاء الجلسة بعد مدة الخمول
        $lifetime = (int) config('app.session.lifetime', 120) * 60;
        $last = $_SESSION['_last_activity'] ?? null;
        if ($last && time() - $last > $lifetime) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['_flash'][] = ['warning', 'انتهت جلستك بسبب عدم النشاط، يرجى تسجيل الدخول مجدداً.'];
        }
        $_SESSION['_last_activity'] = time();

        // نقل القيم القديمة للنموذج من الطلب السابق
        $_SESSION['_old_current'] = $_SESSION['_old_next'] ?? [];
        unset($_SESSION['_old_next']);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = [$type, $message];
    }

    public static function takeFlash(): array
    {
        $items = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $items;
    }

    public static function keepInput(array $input): void
    {
        unset($input['password'], $input['password_confirmation'], $input['_token']);
        $_SESSION['_old_next'] = $input;
    }

    public static function old(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_old_current'][$key] ?? $default;
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }
}
