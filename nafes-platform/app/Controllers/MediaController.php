<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Services\Uploads;

/** خدمة الملفات المرفوعة من خارج المجلد العام */
final class MediaController extends Controller
{
    public function logo(Request $request): never
    {
        $this->send(Uploads::path(setting('logo')), 86400);
    }

    /** وسائط الأسئلة والنصوص — للمستخدمين المسجلين فقط */
    public function show(Request $request): never
    {
        $media = DB::first('SELECT * FROM question_media WHERE id = ?', [(int) $request->param('id')]);
        $this->send($media ? Uploads::path($media['file_path']) : null, 3600);
    }

    private function send(?string $path, int $maxAge): never
    {
        if (!$path) {
            throw new HttpException(404);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
        if (!str_starts_with($mime, 'image/')) {
            throw new HttpException(404);
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=' . $maxAge);
        header("Content-Security-Policy: default-src 'none'");
        readfile($path);
        exit;
    }
}
