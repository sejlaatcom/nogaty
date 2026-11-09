<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Services\Notifier;

final class NotificationController extends Controller
{
    /** بيانات الجرس في الشريط العلوي (JSON) */
    public function feed(Request $request): never
    {
        if (Auth::isStudent() && random_int(1, 5) === 1) {
            Notifier::deadlineReminders();
        }
        $items = DB::all('SELECT id, type, title, body, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 8', [Auth::id()]);
        foreach ($items as &$item) {
            $item['ago'] = ago($item['created_at']);
            $item['is_read'] = (int) $item['is_read'] === 1;
        }
        json_response([
            'ok'     => true,
            'unread' => (int) DB::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [Auth::id()], 0),
            'items'  => $items,
        ]);
    }

    public function index(Request $request): string
    {
        $items = DB::all('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 200', [Auth::id()]);
        return $this->view('notifications/index', ['title' => 'الإشعارات', 'items' => $items]);
    }

    public function open(Request $request): never
    {
        $row = $this->notFound(DB::first('SELECT * FROM notifications WHERE id = ? AND user_id = ?', [(int) $request->param('id'), Auth::id()]));
        DB::update('notifications', ['is_read' => 1], 'id = :id', ['id' => $row['id']]);
        $link = (string) $row['link'];
        redirect($link !== '' && str_starts_with($link, '/') && !str_starts_with($link, '//') ? $link : '/notifications');
    }

    public function readAll(Request $request): never
    {
        DB::update('notifications', ['is_read' => 1], 'user_id = :uid AND is_read = 0', ['uid' => Auth::id()]);
        flash('success', 'تم تعليم جميع الإشعارات كمقروءة.');
        redirect('/notifications');
    }
}
