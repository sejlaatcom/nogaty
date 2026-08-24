<?php
/**
 * المرفقات : المعاينة والتحميل والحذف وإضافة روابط قوقل درايف
 */
class AttachmentsController
{
    /** تحميل مرفق مخزّن على الخادم */
    public function download(): void
    {
        Auth::requireLogin();
        $att = $this->find(Request::int('id', 0));

        if ($att['kind'] === 'drive') {
            Audit::log('download', 'attachment', (int) $att['id'], 'فتح رابط درايف : ' . $att['title']);
            $this->countDownload((int) $att['id']);
            header('Location: ' . GDrive::downloadUrl($att['drive_file_id'], $att['ext'] ?: 'file'));
            exit;
        }

        $path = $this->safePath($att['file_path']);
        if (!$path) {
            http_response_code(404);
            exit('الملف غير موجود على الخادم.');
        }
        Audit::log('download', 'attachment', (int) $att['id'], 'تحميل مرفق : ' . $att['file_name']);
        $this->countDownload((int) $att['id']);
        $this->stream($path, $att, true);
    }

    /** معاينة المرفق داخل المتصفح (PDF / صور / فيديو / صوت) */
    public function preview(): void
    {
        Auth::requireLogin();
        $att = $this->find(Request::int('id', 0));

        if ($att['kind'] === 'drive') {
            header('Location: ' . GDrive::previewUrl($att['drive_file_id'], $att['ext'] ?: 'file'));
            exit;
        }
        $path = $this->safePath($att['file_path']);
        if (!$path) {
            http_response_code(404);
            exit('الملف غير موجود على الخادم.');
        }
        $this->stream($path, $att, false);
    }

    /** صفحة عارض المرفق مع معلوماته */
    public function viewer(): void
    {
        Auth::requireLogin();
        $att   = $this->find(Request::int('id', 0));
        $links = AttachmentService::links($att);

        View::render('documents/viewer', [
            'title' => $att['title'] ?: $att['file_name'],
            'att'   => $att,
            'links' => $links,
        ]);
    }

    /** إضافة مرفقات لعنصر موجود */
    public function store(): void
    {
        Auth::requirePermission('attachments.upload');
        Csrf::check();

        $type = pick(Request::post('attachable_type'), ['transaction','document'], 'document');
        $id   = Request::int('attachable_id', 0);
        if (!$id) {
            flash('error', 'العنصر المستهدف غير محدد.');
            redirect('dashboard/index');
        }

        $res = AttachmentService::saveFromRequest($type, $id, $type === 'transaction' ? 'transactions' : 'documents');
        Audit::log('create', 'attachment', $id, 'إضافة ' . $res['saved'] . ' مرفق');

        if ($res['errors']) {
            flash('warning', implode(' | ', $res['errors']));
        }
        if ($res['saved']) {
            flash('success', 'تمت إضافة ' . $res['saved'] . ' مرفق بنجاح.');
        } elseif (!$res['errors']) {
            flash('error', 'لم يتم اختيار أي ملف أو رابط.');
        }
        redirect($type === 'transaction' ? 'transactions/show' : 'documents/show', ['id' => $id]);
    }

    public function delete(): void
    {
        Auth::requirePermission('attachments.delete');
        Csrf::check();

        $id  = Request::int('id', 0);
        $att = Database::first('SELECT * FROM attachments WHERE id = ?', [$id]);
        if (!$att) {
            flash('error', 'المرفق غير موجود.');
            redirect('dashboard/index');
        }
        $ownerType = $att['attachable_type'];
        $ownerId   = (int) $att['attachable_id'];

        AttachmentService::remove($id);
        flash('success', 'تم حذف المرفق.');
        redirect($ownerType === 'transaction' ? 'transactions/show' : 'documents/show', ['id' => $ownerId]);
    }

    /** معرض كل المرفقات مع تصفية حسب النوع */
    public function gallery(): void
    {
        Auth::requirePermission('documents.view');

        $media = pick(Request::get('media'), ['document','image','video','audio','archive','other'], null);
        $kind  = pick(Request::get('kind'), ['file','drive'], null);
        $q     = Request::get('q');

        $where  = ['1=1'];
        $params = [];
        if ($media) { $where[] = 'a.media_group = :media'; $params['media'] = $media; }
        if ($kind)  { $where[] = 'a.kind = :kind';         $params['kind']  = $kind; }
        if ($q) {
            $where[] = likeAny(['a.title', 'a.file_name'], (string) $q, $params);
        }

        $sql = 'SELECT a.*, u.full_name AS uploader_name,
                       CASE WHEN a.attachable_type = "transaction"
                            THEN (SELECT t.subject FROM transactions t WHERE t.id = a.attachable_id)
                            ELSE (SELECT d.title FROM documents d WHERE d.id = a.attachable_id) END AS owner_title
                  FROM attachments a
                  LEFT JOIN users u ON u.id = a.uploaded_by
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY a.id DESC';

        $stats = Database::all(
            'SELECT media_group, COUNT(*) AS total, COALESCE(SUM(file_size),0) AS size
               FROM attachments GROUP BY media_group');

        View::render('documents/gallery', [
            'title' => 'مكتبة المرفقات',
            'page'  => Paginator::make($sql, $params, Request::page()),
            'media' => $media,
            'kind'  => $kind,
            'q'     => $q,
            'stats' => $stats,
        ]);
    }

    // ------------------------------ داخلي ------------------------------
    private function find(int $id): array
    {
        $att = Database::first('SELECT * FROM attachments WHERE id = ?', [$id]);
        if (!$att) {
            http_response_code(404);
            exit('المرفق غير موجود.');
        }
        return $att;
    }

    private function countDownload(int $id): void
    {
        Database::run('UPDATE attachments SET downloads = downloads + 1 WHERE id = ?', [$id]);
    }

    /** التأكد من أن المسار داخل مجلد التخزين فقط */
    private function safePath(?string $relative): ?string
    {
        if (!$relative) {
            return null;
        }
        $real = realpath(STORAGE_PATH . '/' . ltrim($relative, '/'));
        $base = realpath(STORAGE_PATH . '/uploads');
        return ($real && $base && str_starts_with($real, $base) && is_file($real)) ? $real : null;
    }

    /** إرسال الملف مع دعم الاستئناف للفيديو والصوت */
    private function stream(string $path, array $att, bool $forceDownload): void
    {
        $size = filesize($path);
        $mime = $att['mime_type'] ?: 'application/octet-stream';
        $name = $att['file_name'] ?: basename($path);

        // منع تنفيذ المحتوى داخل المتصفح
        $inlineOk = in_array($att['media_group'], ['image','video','audio'], true)
                    || strtolower((string) $att['ext']) === 'pdf';
        $disposition = ($forceDownload || !$inlineOk) ? 'attachment' : 'inline';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($name)
            . '"; filename*=UTF-8\'\'' . rawurlencode($name));
        header('Accept-Ranges: bytes');
        header('Cache-Control: private, max-age=0, must-revalidate');

        $start = 0;
        $end   = $size - 1;
        if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
            if ($m[1] !== '') { $start = (int) $m[1]; }
            if ($m[2] !== '') { $end   = min((int) $m[2], $size - 1); }
            if ($start > $end || $start >= $size) {
                header('HTTP/1.1 416 Range Not Satisfiable');
                header("Content-Range: bytes */$size");
                exit;
            }
            header('HTTP/1.1 206 Partial Content');
            header("Content-Range: bytes $start-$end/$size");
        }
        header('Content-Length: ' . ($end - $start + 1));

        $fp = fopen($path, 'rb');
        fseek($fp, $start);
        $remaining = $end - $start + 1;
        while ($remaining > 0 && !feof($fp)) {
            $chunk = fread($fp, (int) min(8192, $remaining));
            if ($chunk === false) {
                break;
            }
            echo $chunk;
            $remaining -= strlen($chunk);
            flush();
        }
        fclose($fp);
        exit;
    }
}
