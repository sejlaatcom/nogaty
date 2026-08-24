<?php
/**
 * الإشعارات
 */
class NotificationsController
{
    public function index(): void
    {
        Auth::requireLogin();
        $sql = 'SELECT * FROM notifications WHERE user_id = :uid ORDER BY id DESC';
        View::render('notifications/index', [
            'title' => 'الإشعارات',
            'page'  => Paginator::make($sql, ['uid' => Auth::id()], Request::page(), 30),
        ]);
    }

    public function read(): void
    {
        Auth::requireLogin();
        $id = Request::int('id', 0);
        $n  = Database::first('SELECT * FROM notifications WHERE id = ? AND user_id = ?', [$id, Auth::id()]);
        if (!$n) {
            redirect('notifications/index');
        }
        Database::update('notifications', ['is_read' => 1], 'id = :id', ['id' => $id]);
        if ($n['url']) {
            header('Location: ' . $n['url']);
            exit;
        }
        redirect('notifications/index');
    }

    public function readAll(): void
    {
        Auth::requireLogin();
        Csrf::check();
        Database::run('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [Auth::id()]);
        flash('success', 'تم تعليم جميع الإشعارات كمقروءة.');
        redirect('notifications/index');
    }

    /** عدد غير المقروء بصيغة JSON للتحديث الحي */
    public function count(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['count' => Notify::unreadCount()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
