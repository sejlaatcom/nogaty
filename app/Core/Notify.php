<?php
/**
 * الإشعارات الداخلية للمستخدمين
 */
class Notify
{
    public static function send(int $userId, string $title, ?string $message = null, ?string $url = null, string $icon = 'bell'): void
    {
        if ($userId <= 0) {
            return;
        }
        try {
            Database::insert('notifications', [
                'user_id' => $userId,
                'title'   => mb_substr($title, 0, 200),
                'message' => $message ? mb_substr($message, 0, 500) : null,
                'url'     => $url ? mb_substr($url, 0, 300) : null,
                'icon'    => $icon,
            ]);
        } catch (Throwable $e) {
            error_log('Notify failed: ' . $e->getMessage());
        }
    }

    /** إشعار كل مستخدمي إدارة معينة */
    public static function sendToDepartment(int $departmentId, string $title, ?string $message = null, ?string $url = null): void
    {
        $users = Database::all('SELECT id FROM users WHERE department_id = ? AND is_active = 1', [$departmentId]);
        foreach ($users as $u) {
            self::send((int) $u['id'], $title, $message, $url);
        }
    }

    public static function unreadCount(?int $userId = null): int
    {
        $userId = $userId ?? Auth::id();
        if (!$userId) {
            return 0;
        }
        return (int) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]);
    }

    public static function latest(int $limit = 8, ?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        if (!$userId) {
            return [];
        }
        return Database::all(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ' . max(1, (int)$limit),
            [$userId]
        );
    }
}
