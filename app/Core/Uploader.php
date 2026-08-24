<?php
/**
 * رفع المرفقات وتخزينها بشكل آمن خارج مجلد الويب
 */
class Uploader
{
    /** تصنيف الامتدادات إلى مجموعات وسائط */
    public const GROUPS = [
        'document' => ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','rtf','csv','odt','ods','odp'],
        'image'    => ['jpg','jpeg','png','gif','bmp','webp','svg','tif','tiff'],
        'video'    => ['mp4','avi','mkv','mov','wmv','flv','webm','m4v','3gp'],
        'audio'    => ['mp3','wav','ogg','m4a','aac','wma','flac'],
        'archive'  => ['zip','rar','7z','tar','gz'],
    ];

    public static function mediaGroup(string $ext): string
    {
        $ext = strtolower($ext);
        foreach (self::GROUPS as $group => $exts) {
            if (in_array($ext, $exts, true)) {
                return $group;
            }
        }
        return 'other';
    }

    public static function allowedExtensions(): array
    {
        $raw = (string) setting('allowed_extensions', 'pdf,doc,docx,jpg,png');
        return array_values(array_filter(array_map(
            static fn($e) => strtolower(trim($e)),
            explode(',', $raw)
        )));
    }

    public static function maxBytes(): int
    {
        return ((int) setting('max_upload_mb', 50)) * 1024 * 1024;
    }

    /**
     * معالجة ملف مرفوع واحد.
     * @return array{ok:bool,message?:string,data?:array}
     */
    public static function store(array $file, string $subDir = 'general'): array
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['ok' => false, 'message' => 'بيانات الملف غير صالحة.'];
        }
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return ['ok' => false, 'message' => 'لم يتم اختيار أي ملف.'];
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['ok' => false, 'message' => 'حجم الملف يتجاوز الحد المسموح به في إعدادات الخادم.'];
            default:
                return ['ok' => false, 'message' => 'تعذر رفع الملف (رمز الخطأ: ' . $file['error'] . ').'];
        }

        if ($file['size'] <= 0) {
            return ['ok' => false, 'message' => 'الملف فارغ.'];
        }
        if ($file['size'] > self::maxBytes()) {
            return ['ok' => false, 'message' => 'حجم الملف يتجاوز ' . setting('max_upload_mb', 50) . ' ميجابايت.'];
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'message' => 'مصدر الملف غير موثوق.'];
        }

        $original = self::sanitizeName((string) $file['name']);
        $ext      = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        if ($ext === '' || !in_array($ext, self::allowedExtensions(), true)) {
            return ['ok' => false, 'message' => 'نوع الملف «' . htmlspecialchars($ext) . '» غير مسموح به.'];
        }
        // منع الملفات التنفيذية مهما كانت الإعدادات
        if (in_array($ext, ['php','php3','php4','php5','php7','phtml','phar','exe','sh','bat','cmd','com','cgi','pl','js','htaccess'], true)) {
            return ['ok' => false, 'message' => 'نوع الملف غير مسموح به لأسباب أمنية.'];
        }

        $mime = self::detectMime($file['tmp_name']) ?: ($file['type'] ?? 'application/octet-stream');

        $dir = STORAGE_PATH . '/uploads/' . trim($subDir, '/') . '/' . date('Y/m');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['ok' => false, 'message' => 'تعذر إنشاء مجلد التخزين. تحقق من صلاحيات مجلد storage.'];
        }

        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        $target = $dir . '/' . $stored;

        if (!@move_uploaded_file($file['tmp_name'], $target)) {
            return ['ok' => false, 'message' => 'تعذر حفظ الملف على الخادم.'];
        }
        @chmod($target, 0644);

        return ['ok' => true, 'data' => [
            'file_name'   => $original,
            'stored_name' => $stored,
            'file_path'   => str_replace(STORAGE_PATH . '/', '', $target),
            'ext'         => $ext,
            'mime_type'   => substr($mime, 0, 150),
            'media_group' => self::mediaGroup($ext),
            'file_size'   => (int) $file['size'],
            'checksum'    => hash_file('sha256', $target),
        ]];
    }

    /** تحويل حقل ملفات متعددة إلى مصفوفة ملفات مفردة */
    public static function normalizeMultiple(array $files): array
    {
        $out = [];
        if (!isset($files['name']) || !is_array($files['name'])) {
            return $out;
        }
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];
        }
        return $out;
    }

    public static function deleteFile(?string $relativePath): void
    {
        if (!$relativePath) {
            return;
        }
        $full = STORAGE_PATH . '/' . ltrim($relativePath, '/');
        $real = realpath($full);
        $base = realpath(STORAGE_PATH . '/uploads');
        if ($real && $base && str_starts_with($real, $base) && is_file($real)) {
            @unlink($real);
        }
    }

    public static function sanitizeName(string $name): string
    {
        $name = basename(str_replace(['\\', "\0"], '', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
        $name = str_replace(['"', "'", '<', '>', '|', '?', '*', ':'], '_', $name);
        return mb_substr(trim($name), 0, 250) ?: 'file';
    }

    private static function detectMime(string $path): ?string
    {
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) {
                $m = finfo_file($fi, $path);
                finfo_close($fi);
                return $m ?: null;
            }
        }
        return null;
    }

    /** تنسيق الحجم بصيغة مقروءة */
    public static function humanSize(?int $bytes): string
    {
        $bytes = (int) $bytes;
        if ($bytes <= 0) {
            return '-';
        }
        $units = ['بايت', 'ك.بايت', 'م.بايت', 'ج.بايت'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);
        return round($bytes / (1024 ** $i), $i ? 1 : 0) . ' ' . $units[$i];
    }
}
