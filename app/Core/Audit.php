<?php
/**
 * سجل الأحداث
 */
class Audit
{
    public static function log(string $action, ?string $entityType = null, ?int $entityId = null, ?string $description = null): void
    {
        if (!setting('enable_audit', '1')) {
            return;
        }
        try {
            Database::insert('audit_logs', [
                'user_id'     => Auth::id(),
                'action'      => mb_substr($action, 0, 60),
                'entity_type' => $entityType ? mb_substr($entityType, 0, 60) : null,
                'entity_id'   => $entityId,
                'description' => $description ? mb_substr($description, 0, 500) : null,
                'ip_address'  => Request::ip(),
                'user_agent'  => Request::userAgent(),
            ]);
        } catch (Throwable $e) {
            error_log('Audit log failed: ' . $e->getMessage());
        }
    }

    public static function actionLabel(string $action): string
    {
        return [
            'login'    => 'تسجيل دخول',
            'logout'   => 'تسجيل خروج',
            'create'   => 'إضافة',
            'update'   => 'تعديل',
            'delete'   => 'حذف',
            'restore'  => 'استرجاع',
            'view'     => 'اطلاع',
            'download' => 'تحميل',
            'share'    => 'مشاركة',
            'route'    => 'إحالة',
            'export'   => 'تصدير',
            'print'    => 'طباعة',
        ][$action] ?? $action;
    }
}
