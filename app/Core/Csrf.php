<?php
/**
 * حماية النماذج من التزوير عبر المواقع
 */
class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . self::token() . '">';
    }

    public static function verify(?string $token = null): bool
    {
        $token = $token ?? ($_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        return !empty($_SESSION['_csrf']) && is_string($token)
            && hash_equals($_SESSION['_csrf'], $token);
    }

    /** يتحقق أو يوقف التنفيذ */
    public static function check(): void
    {
        if (!self::verify()) {
            http_response_code(419);
            exit('انتهت صلاحية الجلسة أو رمز الحماية غير صحيح. يرجى إعادة تحميل الصفحة.');
        }
    }
}
