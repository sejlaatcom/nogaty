<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\HttpException;
use App\Core\Session;
use App\Core\View;
use App\Services\Settings;

/* ------------------------------------------------------------------ */
/* عام                                                                 */
/* ------------------------------------------------------------------ */
function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

function setting(string $key, ?string $default = null): string
{
    return Settings::get($key, $default);
}

/** ترميز آمن للإخراج (حماية XSS) */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** المسار الأساسي للتطبيق على الخادم (يعمل داخل مجلد فرعي) */
function base_path_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $dir    = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return $base = ($dir === '/' || $dir === '.') ? '' : $dir;
}

function url(string $path = '/', array $query = []): string
{
    $path = '/' . ltrim($path, '/');
    $prefix = base_path_url() . (config('app.pretty_urls', true) ? '' : '/index.php');
    return $prefix . ($path === '/' ? '/' : $path) . ($query ? '?' . http_build_query($query) : '');
}

function asset(string $path): string
{
    $file = PUBLIC_PATH . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr((string) filemtime($file), -6) : '1';
    return base_path_url() . '/assets/' . ltrim($path, '/') . '?v=' . $v;
}

function redirect(string $path, int $status = 302): never
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)), true, $status);
    exit;
}

/** العودة للصفحة السابقة (داخل الموقع فقط) */
function back(string $fallback = '/'): never
{
    $ref  = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($ref !== '' && parse_url($ref, PHP_URL_HOST) === $host) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($fallback);
}

function abort(int $status, string $message = ''): never
{
    throw new HttpException($status, $message);
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function view(string $template, array $data = [], ?string $layout = 'layouts/app'): string
{
    return View::render($template, $data, $layout);
}

/* ------------------------------------------------------------------ */
/* النماذج والجلسة                                                     */
/* ------------------------------------------------------------------ */
function csrf_token(): string
{
    return Session::csrfToken();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function old(string $key, mixed $default = ''): mixed
{
    return Session::old($key, $default);
}

function flash(string $type, string $message): void
{
    Session::flash($type, $message);
}

/** إعادة النموذج مع الأخطاء والقيم المدخلة */
function back_with_errors(array $errors, string $fallback = '/'): never
{
    Session::keepInput($_POST);
    foreach ($errors as $error) {
        flash('danger', $error);
    }
    back($fallback);
}

function selected(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked(mixed $condition): string
{
    return $condition ? ' checked' : '';
}

/* ------------------------------------------------------------------ */
/* المستخدم والصلاحيات                                                 */
/* ------------------------------------------------------------------ */
function auth_user(): ?array
{
    return Auth::user();
}

function can(string ...$permissions): bool
{
    return Auth::can(...$permissions);
}

/* ------------------------------------------------------------------ */
/* التنسيق                                                              */
/* ------------------------------------------------------------------ */
function pct(?float $value, int $decimals = 0): string
{
    return $value === null ? '—' : number_format($value, $decimals) . '٪';
}

function num(int|float|null $value, int $decimals = 0): string
{
    return $value === null ? '—' : number_format((float) $value, $decimals);
}

/** تاريخ ووقت مختصر */
function dt(?string $value, bool $withTime = true): string
{
    if (!$value) {
        return '—';
    }
    $time = strtotime($value);
    return $time ? date($withTime ? 'Y/m/d H:i' : 'Y/m/d', $time) : '—';
}

/** منذ متى: «قبل 5 دقائق» */
function ago(?string $value): string
{
    if (!$value || !($time = strtotime($value))) {
        return '—';
    }
    $diff = time() - $time;
    return match (true) {
        $diff < 60     => 'الآن',
        $diff < 3600   => 'قبل ' . intdiv($diff, 60) . ' دقيقة',
        $diff < 86400  => 'قبل ' . intdiv($diff, 3600) . ' ساعة',
        $diff < 604800 => 'قبل ' . intdiv($diff, 86400) . ' يوم',
        default        => dt($value, false),
    };
}

function duration_label(?int $seconds): string
{
    if ($seconds === null) {
        return '—';
    }
    return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60) . ' د';
}

function str_limit(?string $text, int $limit = 80): string
{
    $text = trim(preg_replace('/\s+/u', ' ', (string) $text) ?? '');
    return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit) . '…' : $text;
}

/** اسم الطالب حسب إعداد الخصوصية في لوحات الإنجاز */
function display_name(string $fullName, ?string $mode = null): string
{
    $mode ??= setting('leaderboard_names');
    $parts = preg_split('/\s+/u', trim($fullName)) ?: [$fullName];
    return match ($mode) {
        'full'      => $fullName,
        'anonymous' => 'طالب ' . mb_substr(md5($fullName), 0, 4),
        default     => $parts[0] . (count($parts) > 1 ? ' ' . mb_substr(end($parts), 0, 1) . '.' : ''),
    };
}

/**
 * نص منسق آمن للأسئلة والنصوص: يُرمّز أولاً ثم يدعم:
 *  **غامق**، \frac{أ}{ب}، \sqrt{س}، س^{2}، س_{1}، وجداول بخط | عمود | عمود |
 */
function rich_text(?string $text): string
{
    $text = (string) $text;
    if ($text === '') {
        return '';
    }
    $lines  = preg_split('/\R/u', $text) ?: [];
    $html   = [];
    $table  = [];
    $flush  = static function () use (&$table, &$html): void {
        if (!$table) {
            return;
        }
        $rows = array_values(array_filter($table, static fn ($r) => !preg_match('/^\|?\s*:?-{2,}/u', $r)));
        $out  = '<div class="table-responsive"><table class="table table-bordered table-sm rich-table">';
        foreach ($rows as $i => $row) {
            $cells = array_map('trim', explode('|', trim($row, " |\t")));
            $tag   = $i === 0 ? 'th' : 'td';
            $out  .= '<tr>' . implode('', array_map(static fn ($c) => "<$tag>" . rich_inline($c) . "</$tag>", $cells)) . '</tr>';
        }
        $html[] = $out . '</table></div>';
        $table  = [];
    };
    foreach ($lines as $line) {
        if (preg_match('/^\s*\|.*\|\s*$/u', $line)) {
            $table[] = $line;
            continue;
        }
        $flush();
        $html[] = rich_inline($line) . '<br>';
    }
    $flush();
    $out = implode("\n", $html);
    return preg_replace('/(<br>\s*)+$/u', '', $out) ?? $out;
}

/** نص مبسط بدون رموز التنسيق (للقوائم والملخصات والتصدير) */
function plain_text(string $text): string
{
    $text = preg_replace(['/\\\\frac\{([^{}]*)\}\{([^{}]*)\}/u', '/\\\\sqrt\{([^{}]*)\}/u', '/\^\{([^{}]*)\}/u', '/_\{([^{}]*)\}/u', '/\*\*(.+?)\*\*/u'],
        ['$1/$2', '√$1', '^$1', '$1', '$1'], $text) ?? $text;
    return trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? $text);
}

function rich_inline(string $line): string
{
    $s = e($line);
    $s = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $s) ?? $s;
    $s = preg_replace('/\\\\frac\{([^{}]*)\}\{([^{}]*)\}/u', '<span class="frac"><span>$1</span><span>$2</span></span>', $s) ?? $s;
    $s = preg_replace('/\\\\sqrt\{([^{}]*)\}/u', '<span class="sqrt">√<span>$1</span></span>', $s) ?? $s;
    $s = preg_replace('/\^\{([^{}]*)\}/u', '<sup>$1</sup>', $s) ?? $s;
    $s = preg_replace('/_\{([^{}]*)\}/u', '<sub>$1</sub>', $s) ?? $s;
    return $s;
}

/** إرسال ترويسات تنزيل ملف */
function download_headers(string $filename, string $mime): void
{
    $ascii = preg_replace('/[^\x20-\x7E]/', '_', $filename) ?: 'download';
    header('Content-Type: ' . $mime);
    header("Content-Disposition: attachment; filename=\"$ascii\"; filename*=UTF-8''" . rawurlencode($filename));
    header('Cache-Control: no-store, max-age=0');
}

function safe_filename(string $name): string
{
    $name = preg_replace('/[\/\\\\:*?"<>|]+/u', '-', $name) ?? $name;
    return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
}

function random_password(int $length = 8): string
{
    // أحرف وأرقام بدون المتشابهة (0/o، 1/l) مع ضمان وجود حرف ورقم
    $letters = 'abcdefghjkmnpqrstuvwxyz';
    $digits  = '23456789';
    $all     = $letters . $digits;
    $out = $letters[random_int(0, strlen($letters) - 1)] . $digits[random_int(0, strlen($digits) - 1)];
    for ($i = 2; $i < $length; $i++) {
        $out .= $all[random_int(0, strlen($all) - 1)];
    }
    return str_shuffle($out);
}

/** توحيد النص العربي للمقارنة (الإجابات القصيرة واكتشاف التكرار) */
function normalize_ar(string $text): string
{
    $text = mb_strtolower(trim($text));
    $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text) ?? $text; // التشكيل والتطويل
    $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه', 'ؤ' => 'و', 'ئ' => 'ي',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.', '،' => ',']);
    $text = preg_replace('/[[:punct:]«»؟؛]+/u', ' ', $text) ?? $text;
    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}
