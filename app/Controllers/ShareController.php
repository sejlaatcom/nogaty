<?php
/**
 * مشاركة الوثائق والمعاملات عبر روابط آمنة
 */
class ShareController
{
    /** إنشاء رابط مشاركة */
    public function create(): void
    {
        Auth::requirePermission('shares.manage');
        Csrf::check();

        $type = pick(Request::post('shareable_type'), ['transaction','document'], 'document');
        $id   = Request::int('shareable_id', 0);
        if (!$id) {
            flash('error', 'العنصر غير محدد.');
            redirect('dashboard/index');
        }

        $days     = max(1, min(365, Request::int('expires_days', 7)));
        $password = (string) ($_POST['share_password'] ?? '');

        $token = bin2hex(random_bytes(24));
        Database::insert('shares', [
            'token'          => $token,
            'shareable_type' => $type,
            'shareable_id'   => $id,
            'password_hash'  => $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null,
            'allow_download' => Request::bool('allow_download'),
            'expires_at'     => date('Y-m-d H:i:s', strtotime("+$days days")),
            'created_by'     => Auth::id(),
        ]);

        Audit::log('share', $type, $id, 'إنشاء رابط مشاركة صالح لمدة ' . $days . ' يوم');
        flash('success', 'تم إنشاء رابط المشاركة. انسخه من قائمة الروابط أدناه.');
        redirect($type === 'transaction' ? 'transactions/show' : 'documents/show', ['id' => $id]);
    }

    public function revoke(): void
    {
        Auth::requirePermission('shares.manage');
        Csrf::check();
        $id    = Request::int('id', 0);
        $share = Database::first('SELECT * FROM shares WHERE id = ?', [$id]);
        if ($share) {
            Database::delete('shares', 'id = ?', [$id]);
            Audit::log('delete', 'share', $id, 'إلغاء رابط مشاركة');
            flash('success', 'تم إلغاء رابط المشاركة.');
            redirect($share['shareable_type'] === 'transaction' ? 'transactions/show' : 'documents/show',
                ['id' => (int) $share['shareable_id']]);
        }
        redirect('dashboard/index');
    }

    /** عرض العنصر المشارك للزائر عبر الرابط */
    public function view(): void
    {
        $token = (string) (Request::get('token') ?? '');
        $share = $this->findValid($token);

        if ($share['password_hash'] && empty($_SESSION['share_ok'][$token])) {
            View::render('share/unlock', ['title' => 'رابط محمي', 'token' => $token], 'auth');
            return;
        }

        Database::run('UPDATE shares SET views = views + 1 WHERE id = ?', [$share['id']]);

        if ($share['shareable_type'] === 'transaction') {
            $item = Database::first(
                'SELECT t.*, c.name AS correspondent_name, cat.name AS category_name
                   FROM transactions t
                   LEFT JOIN correspondents c ON c.id = t.correspondent_id
                   LEFT JOIN categories cat ON cat.id = t.category_id
                  WHERE t.id = ? AND t.deleted_at IS NULL', [$share['shareable_id']]);
        } else {
            $item = Database::first(
                'SELECT d.*, f.name AS folder_name FROM documents d
                   JOIN folders f ON f.id = d.folder_id
                  WHERE d.id = ? AND d.deleted_at IS NULL', [$share['shareable_id']]);
        }
        if (!$item) {
            $this->fail('العنصر المشارك لم يعد متاحاً.');
        }

        View::render('share/view', [
            'title'       => 'وثيقة مشاركة',
            'share'       => $share,
            'item'        => $item,
            'type'        => $share['shareable_type'],
            'attachments' => AttachmentService::listFor($share['shareable_type'], (int) $share['shareable_id']),
        ], 'auth');
    }

    public function unlock(): void
    {
        Csrf::check();
        $token = (string) (Request::post('token') ?? '');
        $share = $this->findValid($token);

        $password = (string) ($_POST['password'] ?? '');
        if (!$share['password_hash'] || !password_verify($password, $share['password_hash'])) {
            flash('error', 'كلمة المرور غير صحيحة.');
            redirect('share/view', ['token' => $token]);
        }
        $_SESSION['share_ok'][$token] = true;
        redirect('share/view', ['token' => $token]);
    }

    /** تحميل مرفق عبر رابط المشاركة */
    public function download(): void
    {
        $token = (string) (Request::get('token') ?? '');
        $share = $this->findValid($token);

        if (!$share['allow_download']) {
            $this->fail('التحميل غير مسموح به لهذا الرابط.');
        }
        if ($share['password_hash'] && empty($_SESSION['share_ok'][$token])) {
            redirect('share/view', ['token' => $token]);
        }

        $attId = Request::int('att', 0);
        $att   = Database::first(
            'SELECT * FROM attachments WHERE id = ? AND attachable_type = ? AND attachable_id = ?',
            [$attId, $share['shareable_type'], $share['shareable_id']]);
        if (!$att) {
            $this->fail('المرفق غير موجود ضمن هذه المشاركة.');
        }

        Database::run('UPDATE attachments SET downloads = downloads + 1 WHERE id = ?', [$attId]);

        if ($att['kind'] === 'drive') {
            header('Location: ' . GDrive::downloadUrl($att['drive_file_id'], $att['ext'] ?: 'file'));
            exit;
        }

        $real = realpath(STORAGE_PATH . '/' . ltrim((string) $att['file_path'], '/'));
        $base = realpath(STORAGE_PATH . '/uploads');
        if (!$real || !$base || !str_starts_with($real, $base) || !is_file($real)) {
            $this->fail('الملف غير موجود على الخادم.');
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . ($att['mime_type'] ?: 'application/octet-stream'));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode((string) $att['file_name']));
        header('Content-Length: ' . filesize($real));
        readfile($real);
        exit;
    }

    // ------------------------------ داخلي ------------------------------
    private function findValid(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            $this->fail('رابط المشاركة غير صالح.');
        }
        $share = Database::first('SELECT * FROM shares WHERE token = ?', [$token]);
        if (!$share) {
            $this->fail('رابط المشاركة غير موجود أو تم إلغاؤه.');
        }
        if ($share['expires_at'] && strtotime($share['expires_at']) < time()) {
            $this->fail('انتهت صلاحية رابط المشاركة.');
        }
        return $share;
    }

    private function fail(string $message): void
    {
        http_response_code(404);
        View::render('share/error', ['title' => 'تعذر الوصول', 'message' => $message], 'auth');
        exit;
    }
}
