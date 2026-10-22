<?php
declare(strict_types=1);

namespace App\Core;

/** بيانات الطلب الحالي */
final class Request
{
    public array $params = [];   // معاملات المسار {id}

    public function method(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper((string) $_POST['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }
        return $method;
    }

    public function isPost(): bool
    {
        return $this->method() !== 'GET';
    }

    /** المسار بعد إزالة مجلد التطبيق و index.php */
    public function path(): string
    {
        $uri  = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $base = base_path_url();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        if (str_starts_with($uri, '/index.php')) {
            $uri = substr($uri, 10);
        }
        $uri = '/' . trim(rawurldecode($uri), '/');
        return $uri;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    public function str(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $key): bool
    {
        return in_array($this->input($key), ['1', 'on', 'true', 1, true], true);
    }

    /** مصفوفة أعداد صحيحة موجبة (مثل ids[]) */
    public function ints(string $key): array
    {
        $value = $this->input($key, []);
        return array_values(array_unique(array_filter(array_map('intval', (array) $value), static fn ($v) => $v > 0)));
    }

    public function array(string $key): array
    {
        $value = $this->input($key, []);
        return is_array($value) ? $value : [];
    }

    public function file(string $key): ?array
    {
        $file = $_FILES[$key] ?? null;
        if (!$file || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $file;
    }

    public function wantsJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    public function json(): array
    {
        $data = json_decode((string) file_get_contents('php://input'), true);
        return is_array($data) ? $data : [];
    }

    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /** للمعاملات المسار */
    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    /** قيمة الاستعلام الحالي مع تعديل (للفلاتر والترقيم) */
    public function query(array $merge = []): array
    {
        return array_filter(array_merge($_GET, $merge), static fn ($v) => $v !== '' && $v !== null && $v !== []);
    }
}
