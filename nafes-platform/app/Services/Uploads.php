<?php
declare(strict_types=1);

namespace App\Services;

/**
 * رفع الملفات خارج المجلد العام (storage/uploads) مع التحقق من النوع الفعلي والحجم.
 * تُخدم الملفات عبر MediaController بعد التحقق من الصلاحية.
 */
final class Uploads
{
    public const IMAGE_TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    public const SHEET_TYPES = ['xlsx', 'csv', 'txt'];

    public static function maxBytes(): int
    {
        return max(1, (int) Settings::get('upload_max_mb', '5')) * 1024 * 1024;
    }

    /** @return array{path?:string,mime?:string,size?:int,error?:string} */
    public static function storeImage(array $file, string $dir): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
            return ['error' => 'تعذر رفع الملف.'];
        }
        if ((int) $file['size'] > self::maxBytes()) {
            return ['error' => 'حجم الملف أكبر من المسموح (' . Settings::get('upload_max_mb') . ' ميجابايت).'];
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
        if (!isset(self::IMAGE_TYPES[$mime]) || @getimagesize($file['tmp_name']) === false) {
            return ['error' => 'نوع الملف غير مسموح — الصور المقبولة: PNG وJPG وGIF وWEBP.'];
        }
        $dir  = preg_replace('/[^a-z0-9_\-]/', '', $dir) ?: 'misc';
        $path = $dir . '/' . date('Y/m') . '/' . bin2hex(random_bytes(12)) . '.' . self::IMAGE_TYPES[$mime];
        $full = STORAGE_PATH . '/uploads/' . $path;
        if (!is_dir(dirname($full)) && !mkdir(dirname($full), 0750, true) && !is_dir(dirname($full))) {
            return ['error' => 'تعذر إنشاء مجلد الرفع.'];
        }
        if (!move_uploaded_file($file['tmp_name'], $full)) {
            return ['error' => 'تعذر حفظ الملف.'];
        }
        return ['path' => $path, 'mime' => $mime, 'size' => (int) $file['size']];
    }

    /** ملف استيراد (xlsx/csv) إلى storage/imports — يُعيد اسم الملف المخزن */
    public static function storeImport(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
            return ['error' => 'يرجى اختيار ملف.'];
        }
        if ((int) $file['size'] > 10 * 1024 * 1024) {
            return ['error' => 'حجم الملف أكبر من 10 ميجابايت.'];
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::SHEET_TYPES, true)) {
            return ['error' => 'صيغة الملف غير مدعومة — استخدم Excel (.xlsx) أو CSV.'];
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
        $okMime = $ext === 'xlsx'
            ? in_array($mime, ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'], true)
            : str_starts_with($mime, 'text/') || $mime === 'application/csv' || $mime === 'application/octet-stream';
        if (!$okMime) {
            return ['error' => 'محتوى الملف لا يطابق صيغته.'];
        }
        $name = bin2hex(random_bytes(10)) . '.' . $ext;
        if (!is_dir(STORAGE_PATH . '/imports')) {
            mkdir(STORAGE_PATH . '/imports', 0750, true);
        }
        if (!move_uploaded_file($file['tmp_name'], STORAGE_PATH . '/imports/' . $name)) {
            return ['error' => 'تعذر حفظ الملف.'];
        }
        // تنظيف ملفات الاستيراد الأقدم من يوم
        foreach (glob(STORAGE_PATH . '/imports/*') ?: [] as $old) {
            if (is_file($old) && filemtime($old) < time() - 86400) {
                @unlink($old);
            }
        }
        return ['name' => $name, 'original' => (string) $file['name']];
    }

    public static function path(string $relative): ?string
    {
        if (!preg_match('#^[a-z0-9_\-]+/[0-9]{4}/[0-9]{2}/[a-f0-9]{24}\.(png|jpg|gif|webp)$#', $relative)) {
            return null;
        }
        $full = STORAGE_PATH . '/uploads/' . $relative;
        return is_file($full) ? $full : null;
    }

    public static function delete(?string $relative): void
    {
        if ($relative && ($full = self::path($relative))) {
            @unlink($full);
        }
    }
}
