<?php
/**
 * التعامل مع مدخلات الطلب
 */
class Request
{
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    /** قيمة من GET أو POST مع تنظيف المسافات */
    public static function input(string $key, $default = null)
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? $default;
        if (is_string($v)) {
            $v = trim($v);
            if ($v === '') {
                return $default === null ? null : $default;
            }
        }
        return $v;
    }

    public static function post(string $key, $default = null)
    {
        $v = $_POST[$key] ?? $default;
        return is_string($v) ? trim($v) : $v;
    }

    public static function get(string $key, $default = null)
    {
        $v = $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $v;
    }

    public static function int(string $key, ?int $default = null): ?int
    {
        $v = self::input($key);
        if ($v === null || $v === '') {
            return $default;
        }
        return (int) $v;
    }

    public static function bool(string $key): int
    {
        $v = self::input($key);
        return in_array($v, ['1', 1, 'on', 'true', true], true) ? 1 : 0;
    }

    /** مصفوفة من المدخلات */
    public static function array(string $key): array
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? [];
        return is_array($v) ? $v : [];
    }

    public static function ip(): string
    {
        return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
    }

    public static function userAgent(): string
    {
        return substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300);
    }

    public static function isAjax(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }

    /** الصفحة الحالية للتقسيم */
    public static function page(): int
    {
        $p = (int) ($_GET['page'] ?? 1);
        return $p < 1 ? 1 : $p;
    }
}
