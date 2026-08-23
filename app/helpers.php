<?php
declare(strict_types=1);

/** أيام الأسبوع الدراسي (1 = الأحد ... 5 = الخميس) */
const WEEK_DAYS = [
    1 => 'الأحد',
    2 => 'الاثنين',
    3 => 'الثلاثاء',
    4 => 'الأربعاء',
    5 => 'الخميس',
];

/** بنود الخطة لكل يوم */
const PLAN_FIELDS = [
    'topic'     => 'موضوع الدرس',
    'objective' => 'الهدف التعليمي',
    'homework'  => 'الواجبات والمهام الأدائية',
    'notes'     => 'ملاحظات / إثراء',
];

const PLAN_STATUSES = [
    'draft'     => 'مسودة',
    'submitted' => 'بانتظار الاعتماد',
    'approved'  => 'معتمدة',
    'rejected'  => 'معادة للتعديل',
];

/** ترميز آمن للإخراج */
function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** بناء رابط داخل النظام */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function redirect(string $path): never
{
    $target = str_starts_with($path, 'http') ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

/** قراءة قيمة من مصفوفة الطلب */
function input(string $key, ?string $default = null): ?string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function input_int(string $key, int $default = 0): int
{
    $value = $_POST[$key] ?? $_GET[$key] ?? null;
    return is_numeric($value) ? (int) $value : $default;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/* ------------------------------------------------------------------ */
/* الرسائل السريعة (Flash)                                              */
/* ------------------------------------------------------------------ */
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flash_take(): array
{
    $items = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $items;
}

/* ------------------------------------------------------------------ */
/* حماية CSRF                                                           */
/* ------------------------------------------------------------------ */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . h(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = $_POST['_token'] ?? $_GET['_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('انتهت صلاحية الصفحة، يرجى تحديث الصفحة والمحاولة مرة أخرى.');
    }
}

/* ------------------------------------------------------------------ */
/* أدوات عرض                                                            */
/* ------------------------------------------------------------------ */
function status_label(string $status): string
{
    return PLAN_STATUSES[$status] ?? $status;
}

function status_class(string $status): string
{
    return match ($status) {
        'approved'  => 'badge badge-green',
        'submitted' => 'badge badge-amber',
        'rejected'  => 'badge badge-red',
        default     => 'badge badge-gray',
    };
}

/** تحويل الأرقام إلى أرقام عربية (١٢٣) */
function ar_digits(string $text): string
{
    return strtr($text, ['0' => '٠','1' => '١','2' => '٢','3' => '٣','4' => '٤','5' => '٥','6' => '٦','7' => '٧','8' => '٨','9' => '٩']);
}

/** ترتيب الأسبوع بالحروف: الأول، الثاني ... */
function week_ordinal(int $n): string
{
    $names = [1 => 'الأول', 'الثاني', 'الثالث', 'الرابع', 'الخامس', 'السادس', 'السابع', 'الثامن',
              'التاسع', 'العاشر', 'الحادي عشر', 'الثاني عشر', 'الثالث عشر', 'الرابع عشر', 'الخامس عشر',
              'السادس عشر', 'السابع عشر', 'الثامن عشر', 'التاسع عشر', 'العشرون'];
    return $names[$n] ?? (string) $n;
}

/** تاريخ اليوم داخل الأسبوع (day_index 1..5) بالميلادي */
function week_day_date(string $weekStart, int $dayIndex): string
{
    $date = new DateTimeImmutable($weekStart);
    return $date->modify('+' . ($dayIndex - 1) . ' day')->format('Y-m-d');
}

function nl2br_e(?string $text): string
{
    return nl2br(h($text));
}

/** اسم المعلم مسبوقاً بلقب مختصر */
function teacher_short(?string $name): string
{
    if ($name === null || $name === '') {
        return '—';
    }
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    return 'أ. ' . ($parts[0] ?? $name);
}

/** رقم عشوائي لكلمة مرور مبدئية */
function random_password(int $length = 8): string
{
    $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $out;
}

/** تنظيف اسم ملف للتصدير */
function safe_filename(string $name): string
{
    $name = preg_replace('/[\/\\\\:*?"<>|]+/u', '-', $name) ?? $name;
    return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
}

/** عرض جزء من نص طويل */
function str_short(?string $text, int $limit = 60): string
{
    $text = trim((string) $text);
    if ($text === '') {
        return '—';
    }
    return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit) . '…' : $text;
}

/** إرسال ترويسات تنزيل ملف */
function download_headers(string $filename, string $mime): void
{
    $ascii = preg_replace('/[^\x20-\x7E]/', '_', $filename) ?: 'export';
    header('Content-Type: ' . $mime);
    header("Content-Disposition: attachment; filename=\"{$ascii}\"; filename*=UTF-8''" . rawurlencode($filename));
    header('Cache-Control: max-age=0, must-revalidate');
    header('Pragma: public');
}
