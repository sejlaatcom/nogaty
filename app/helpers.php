<?php
/**
 * دوال مساعدة عامة
 */

/** تهريب المخرجات لمنع XSS */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** بناء رابط داخلي */
function url(string $route = '', array $params = []): string
{
    $q = $route === '' ? [] : ['r' => $route];
    $q = array_merge($q, $params);
    return 'index.php' . ($q ? '?' . http_build_query($q) : '');
}

/** إعادة توجيه */
function redirect(string $route, array $params = []): void
{
    header('Location: ' . url($route, $params));
    exit;
}

/** رسالة فورية للجلسة التالية */
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function getFlashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/** قراءة إعداد من جدول settings مع تخزين مؤقت */
function setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (Database::all('SELECT setting_key, setting_value FROM settings') as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return array_key_exists($key, $cache) && $cache[$key] !== null && $cache[$key] !== ''
        ? $cache[$key] : $default;
}

function settingSet(string $key, $value): void
{
    Database::run(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        [$key, (string) $value]
    );
}

/** تنسيق التاريخ الميلادي */
function gdate(?string $date, bool $withTime = false): string
{
    if (!$date || $date === '0000-00-00' || str_starts_with((string)$date, '0000')) {
        return '-';
    }
    $ts = strtotime($date);
    return $ts ? date($withTime ? 'Y-m-d H:i' : 'Y-m-d', $ts) : '-';
}

/** تنسيق التاريخ حسب إعداد النظام (ميلادي / هجري / كلاهما) */
function smartDate(?string $date): string
{
    if (!$date || str_starts_with((string)$date, '0000')) {
        return '-';
    }
    $type = setting('date_type', 'both');
    $g    = gdate($date);
    $h    = Hijri::format($date);
    return match ($type) {
        'hijri'     => $h,
        'gregorian' => $g,
        default     => $g . ' م — ' . $h,
    };
}

/** الوقت النسبي : قبل ٣ دقائق */
function timeAgo(?string $datetime): string
{
    if (!$datetime) {
        return '-';
    }
    $ts = strtotime($datetime);
    if (!$ts) {
        return '-';
    }
    $diff = time() - $ts;
    if ($diff < 60)     return 'قبل لحظات';
    if ($diff < 3600)   return 'قبل ' . intdiv($diff, 60) . ' دقيقة';
    if ($diff < 86400)  return 'قبل ' . intdiv($diff, 3600) . ' ساعة';
    if ($diff < 2592000)return 'قبل ' . intdiv($diff, 86400) . ' يوم';
    return date('Y-m-d', $ts);
}

/** المسميات العربية للثوابت */
function labelDirection(?string $v): string
{
    return ['incoming' => 'وارد', 'outgoing' => 'صادر', 'internal' => 'داخلي'][$v] ?? '-';
}

function labelStatus(?string $v): string
{
    return [
        'draft'       => 'مسودة',
        'registered'  => 'مقيدة',
        'in_progress' => 'قيد الإجراء',
        'completed'   => 'منجزة',
        'archived'    => 'مؤرشفة',
        'closed'      => 'مغلقة',
    ][$v] ?? '-';
}

function labelPriority(?string $v): string
{
    return ['normal' => 'عادي', 'urgent' => 'عاجل', 'very_urgent' => 'عاجل جداً'][$v] ?? '-';
}

function labelConfidentiality(?string $v): string
{
    return ['normal' => 'عادي', 'confidential' => 'سري', 'top_secret' => 'سري للغاية'][$v] ?? '-';
}

function labelRoutingAction(?string $v): string
{
    return [
        'for_action'    => 'للإجراء اللازم',
        'for_info'      => 'للعلم',
        'for_review'    => 'للمراجعة',
        'for_approval'  => 'للاعتماد',
        'for_signature' => 'للتوقيع',
        'for_archive'   => 'للحفظ',
    ][$v] ?? '-';
}

function labelRoutingStatus(?string $v): string
{
    return ['pending' => 'بانتظار الاطلاع', 'seen' => 'تم الاطلاع', 'done' => 'تم الإنجاز', 'returned' => 'معادة'][$v] ?? '-';
}

function labelCorrespondentType(?string $v): string
{
    return [
        'government' => 'جهة حكومية',
        'education'  => 'جهة تعليمية',
        'company'    => 'شركة / مؤسسة',
        'school'     => 'مدرسة',
        'individual' => 'فرد',
        'internal'   => 'داخلي',
        'other'      => 'أخرى',
    ][$v] ?? '-';
}

function labelMediaGroup(?string $v): string
{
    return [
        'document' => 'مستند',
        'image'    => 'صورة',
        'video'    => 'فيديو',
        'audio'    => 'صوت',
        'archive'  => 'ملف مضغوط',
        'other'    => 'أخرى',
    ][$v] ?? 'ملف';
}

/** فئة CSS للحالة */
function statusClass(?string $v): string
{
    return [
        'draft'       => 'muted',
        'registered'  => 'info',
        'in_progress' => 'warn',
        'completed'   => 'ok',
        'archived'    => 'purple',
        'closed'      => 'muted',
    ][$v] ?? 'muted';
}

function priorityClass(?string $v): string
{
    return ['normal' => 'muted', 'urgent' => 'warn', 'very_urgent' => 'danger'][$v] ?? 'muted';
}

/** أيقونة الملف حسب الامتداد */
function fileIcon(?string $ext): string
{
    $ext = strtolower((string) $ext);
    return match (true) {
        $ext === 'pdf'                                   => '📕',
        in_array($ext, ['doc','docx','odt','rtf'], true)  => '📘',
        in_array($ext, ['xls','xlsx','csv','ods'], true)  => '📗',
        in_array($ext, ['ppt','pptx','odp'], true)        => '📙',
        in_array($ext, ['jpg','jpeg','png','gif','bmp','webp','svg'], true) => '🖼️',
        in_array($ext, ['mp4','avi','mkv','mov','wmv','webm'], true)        => '🎬',
        in_array($ext, ['mp3','wav','ogg','m4a','aac'], true)               => '🎵',
        in_array($ext, ['zip','rar','7z','tar','gz'], true)                 => '🗜️',
        default                                          => '📄',
    };
}

/** اختصار نص طويل */
function excerpt(?string $text, int $len = 90): string
{
    $text = trim(strip_tags((string) $text));
    return mb_strlen($text) > $len ? mb_substr($text, 0, $len) . '…' : $text;
}

/** تحويل الأرقام إلى صيغة مقروءة */
function num($n): string
{
    return number_format((float) $n, 0, '.', ',');
}

/** قيمة قديمة للنموذج بعد فشل التحقق */
function old(string $key, $default = '')
{
    $data = $_SESSION['_old'] ?? [];
    return $data[$key] ?? $default;
}

function keepOld(array $data): void
{
    $_SESSION['_old'] = $data;
}

function clearOld(): void
{
    unset($_SESSION['_old']);
}

/** التحقق من صلاحية في القوالب */
function can(string $code): bool
{
    return Auth::can($code);
}

/** فئة نشطة لعنصر القائمة */
function activeIf(string $prefix): string
{
    $r = strtolower((string) ($_GET['r'] ?? 'dashboard/index'));
    return str_starts_with($r, strtolower($prefix)) ? ' active' : '';
}

/** تحويل الوسوم النصية إلى مصفوفة */
function parseTags(?string $raw): array
{
    if (!$raw) {
        return [];
    }
    $parts = preg_split('/[,،]+/u', $raw);
    $out   = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '' && mb_strlen($p) <= 80) {
            $out[$p] = $p;
        }
    }
    return array_values($out);
}

/** تنظيف تاريخ من نموذج */
function cleanDate($value): ?string
{
    if (!$value) {
        return null;
    }
    $ts = strtotime((string) $value);
    return $ts ? date('Y-m-d', $ts) : null;
}

/** قيمة من قائمة مسموحة وإلا الافتراضي */
function pick($value, array $allowed, $default)
{
    return in_array($value, $allowed, true) ? $value : $default;
}

/**
 * يبني شرط بحث LIKE على عدة أعمدة مع معرّفات فريدة للمعاملات.
 * ضروري لأن الاستعلامات المُهيّأة أصلياً في PDO لا تسمح بتكرار نفس المعرّف.
 */
function likeAny(array $columns, string $term, array &$params, string $prefix = 'q'): string
{
    $parts = [];
    foreach (array_values($columns) as $i => $col) {
        $key = $prefix . $i;
        $parts[] = $col . ' LIKE :' . $key;
        $params[$key] = '%' . $term . '%';
    }
    return '(' . implode(' OR ', $parts) . ')';
}
