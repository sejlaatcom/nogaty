<?php
/**
 * التعامل مع روابط قوقل درايف :
 * استخراج معرف الملف وبناء روابط العرض والتحميل والمعاينة المصغّرة.
 */
class GDrive
{
    /** استخراج معرف الملف من أي صيغة رابط لقوقل درايف */
    public static function extractId(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        $patterns = [
            '#/file/d/([a-zA-Z0-9_-]{10,})#',              // .../file/d/ID/view
            '#/document/d/([a-zA-Z0-9_-]{10,})#',          // مستندات جوجل
            '#/spreadsheets/d/([a-zA-Z0-9_-]{10,})#',      // جداول جوجل
            '#/presentation/d/([a-zA-Z0-9_-]{10,})#',      // عروض جوجل
            '#/folders/([a-zA-Z0-9_-]{10,})#',             // مجلد
            '#[?&]id=([a-zA-Z0-9_-]{10,})#',               // uc?id=ID
            '#/d/([a-zA-Z0-9_-]{10,})#',                   // صيغة مختصرة
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $url, $m)) {
                return $m[1];
            }
        }
        // ربما أدخل المستخدم المعرف مباشرة
        if (preg_match('/^[a-zA-Z0-9_-]{20,}$/', $url)) {
            return $url;
        }
        return null;
    }

    /** نوع عنصر درايف : file / document / spreadsheet / presentation / folder */
    public static function detectType(string $url): string
    {
        if (str_contains($url, '/document/d/'))      return 'document';
        if (str_contains($url, '/spreadsheets/d/'))  return 'spreadsheet';
        if (str_contains($url, '/presentation/d/'))  return 'presentation';
        if (str_contains($url, '/folders/'))         return 'folder';
        return 'file';
    }

    /** رابط المعاينة داخل إطار iframe */
    public static function previewUrl(?string $id, string $type = 'file'): string
    {
        if (!$id) {
            return '';
        }
        switch ($type) {
            case 'document':     return "https://docs.google.com/document/d/{$id}/preview";
            case 'spreadsheet':  return "https://docs.google.com/spreadsheets/d/{$id}/preview";
            case 'presentation': return "https://docs.google.com/presentation/d/{$id}/preview";
            case 'folder':       return "https://drive.google.com/embeddedfolderview?id={$id}#grid";
            default:             return "https://drive.google.com/file/d/{$id}/preview";
        }
    }

    /** رابط التحميل المباشر */
    public static function downloadUrl(?string $id, string $type = 'file'): string
    {
        if (!$id) {
            return '';
        }
        switch ($type) {
            case 'document':     return "https://docs.google.com/document/d/{$id}/export?format=pdf";
            case 'spreadsheet':  return "https://docs.google.com/spreadsheets/d/{$id}/export?format=xlsx";
            case 'presentation': return "https://docs.google.com/presentation/d/{$id}/export/pptx";
            case 'folder':       return "https://drive.google.com/drive/folders/{$id}";
            default:             return "https://drive.google.com/uc?export=download&id={$id}";
        }
    }

    /** رابط الفتح في قوقل درايف */
    public static function openUrl(?string $id, string $type = 'file'): string
    {
        if (!$id) {
            return '';
        }
        switch ($type) {
            case 'document':     return "https://docs.google.com/document/d/{$id}/edit";
            case 'spreadsheet':  return "https://docs.google.com/spreadsheets/d/{$id}/edit";
            case 'presentation': return "https://docs.google.com/presentation/d/{$id}/edit";
            case 'folder':       return "https://drive.google.com/drive/folders/{$id}";
            default:             return "https://drive.google.com/file/d/{$id}/view";
        }
    }

    /** صورة مصغّرة */
    public static function thumbnailUrl(?string $id, int $width = 300): string
    {
        return $id ? "https://drive.google.com/thumbnail?id={$id}&sz=w{$width}" : '';
    }

    /** التحقق من أن الرابط يخص قوقل درايف / مستندات جوجل */
    public static function isDriveUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        return (bool) preg_match('/(^|\.)(drive|docs)\.google\.com$/i', $host);
    }
}
