<?php
declare(strict_types=1);

namespace App\Core;

/** قارئ ملف .env بسيط (KEY=VALUE) */
final class Env
{
    private static array $values = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if (preg_match('/^"(.*)"$/s', $value, $m) || preg_match("/^'(.*)'$/s", $value, $m)) {
                $value = stripcslashes($m[1]);
            }
            self::$values[$key] = $value;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$values[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }
        return match (strtolower((string) $value)) {
            'true'  => true,
            'false' => false,
            'null'  => null,
            default => $value,
        };
    }

    /** كتابة ملف .env (يستخدمه المثبّت) */
    public static function write(string $file, array $values): bool
    {
        $lines = [];
        foreach ($values as $key => $value) {
            $value = (string) $value;
            $lines[] = $key . '=' . (preg_match('/[\s#"\'=]/', $value) ? '"' . addcslashes($value, "\"\\") . '"' : $value);
        }
        return file_put_contents($file, implode("\n", $lines) . "\n") !== false;
    }
}
