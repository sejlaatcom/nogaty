<?php
declare(strict_types=1);

/** سجل العمليات */
final class Activity
{
    public static function log(string $action, ?string $entity = null, ?int $entityId = null, ?string $details = null): void
    {
        try {
            Database::insert('activity_log', [
                'user_id'   => Auth::id(),
                'action'    => $action,
                'entity'    => $entity,
                'entity_id' => $entityId,
                'details'   => $details === null ? null : mb_substr($details, 0, 500),
                'ip'        => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable) {
            // لا يجب أن يوقف السجل عمل النظام
        }
    }

    public static function recent(int $limit = 50): array
    {
        return Database::all(
            'SELECT l.*, u.name AS user_name
               FROM activity_log l
          LEFT JOIN users u ON u.id = l.user_id
           ORDER BY l.id DESC
              LIMIT ' . max(1, min(500, $limit))
        );
    }
}
