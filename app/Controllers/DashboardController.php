<?php
/**
 * لوحة المعلومات والإحصائيات
 */
class DashboardController
{
    public function index(): void
    {
        Auth::requirePermission('dashboard.view');
        $userId = Auth::id();
        $seeAll = Auth::can('transactions.view_all');

        // --- بطاقات الإحصاء ---
        $scope = $seeAll ? '' : ' AND (t.created_by = :uid_owner OR EXISTS (
                SELECT 1 FROM transaction_routings tr
                 WHERE tr.transaction_id = t.id AND tr.to_user_id = :uid_routed))';
        $p = $seeAll ? [] : ['uid_owner' => $userId, 'uid_routed' => $userId];

        $stats = [
            'incoming'    => (int) Database::value("SELECT COUNT(*) FROM transactions t WHERE t.deleted_at IS NULL AND t.direction='incoming'$scope", $p),
            'outgoing'    => (int) Database::value("SELECT COUNT(*) FROM transactions t WHERE t.deleted_at IS NULL AND t.direction='outgoing'$scope", $p),
            'internal'    => (int) Database::value("SELECT COUNT(*) FROM transactions t WHERE t.deleted_at IS NULL AND t.direction='internal'$scope", $p),
            'in_progress' => (int) Database::value("SELECT COUNT(*) FROM transactions t WHERE t.deleted_at IS NULL AND t.status='in_progress'$scope", $p),
            'completed'   => (int) Database::value("SELECT COUNT(*) FROM transactions t WHERE t.deleted_at IS NULL AND t.status='completed'$scope", $p),
            'overdue'     => (int) Database::value("SELECT COUNT(*) FROM transactions t WHERE t.deleted_at IS NULL AND t.due_date IS NOT NULL AND t.due_date < CURDATE() AND t.status NOT IN ('completed','closed','archived')$scope", $p),
            'documents'   => (int) Database::value('SELECT COUNT(*) FROM documents WHERE deleted_at IS NULL'),
            'attachments' => (int) Database::value('SELECT COUNT(*) FROM attachments'),
            'folders'     => (int) Database::value('SELECT COUNT(*) FROM folders WHERE is_active = 1'),
            'users'       => (int) Database::value('SELECT COUNT(*) FROM users WHERE is_active = 1'),
            'drive_links' => (int) Database::value("SELECT COUNT(*) FROM attachments WHERE kind = 'drive'"),
            'storage'     => (int) Database::value("SELECT COALESCE(SUM(file_size),0) FROM attachments WHERE kind='file'"),
        ];

        // --- المعاملات المحالة إلي وبانتظار إجرائي ---
        $myTasks = Database::all(
            "SELECT tr.*, t.transaction_no, t.subject, t.direction, t.priority, t.transaction_date,
                    u.full_name AS from_name
               FROM transaction_routings tr
               JOIN transactions t ON t.id = tr.transaction_id AND t.deleted_at IS NULL
               LEFT JOIN users u ON u.id = tr.from_user_id
              WHERE tr.to_user_id = ? AND tr.status IN ('pending','seen')
              ORDER BY FIELD(t.priority,'very_urgent','urgent','normal'), tr.id DESC
              LIMIT 10",
            [$userId]
        );

        // --- أحدث المعاملات ---
        $latest = Database::all(
            "SELECT t.*, c.name AS correspondent_name, u.full_name AS creator
               FROM transactions t
               LEFT JOIN correspondents c ON c.id = t.correspondent_id
               LEFT JOIN users u ON u.id = t.created_by
              WHERE t.deleted_at IS NULL" . ($seeAll ? '' : ' AND t.created_by = :uid') . "
              ORDER BY t.id DESC LIMIT 8",
            $seeAll ? [] : ['uid' => $userId]
        );

        // --- أحدث الوثائق ---
        $latestDocs = Database::all(
            "SELECT d.*, f.name AS folder_name
               FROM documents d
               JOIN folders f ON f.id = d.folder_id
              WHERE d.deleted_at IS NULL
              ORDER BY d.id DESC LIMIT 6"
        );

        // --- إحصاء آخر ٦ أشهر لرسم بياني ---
        $chart = Database::all(
            "SELECT DATE_FORMAT(transaction_date, '%Y-%m') AS ym,
                    SUM(direction='incoming') AS incoming,
                    SUM(direction='outgoing') AS outgoing,
                    SUM(direction='internal') AS internal
               FROM transactions
              WHERE deleted_at IS NULL AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
              GROUP BY ym ORDER BY ym"
        );

        // --- التوزيع حسب التصنيف ---
        $byCategory = Database::all(
            "SELECT COALESCE(c.name,'بدون تصنيف') AS name, COALESCE(c.color,'#94a3b8') AS color, COUNT(*) AS total
               FROM transactions t
               LEFT JOIN categories c ON c.id = t.category_id
              WHERE t.deleted_at IS NULL
              GROUP BY t.category_id, c.name, c.color
              ORDER BY total DESC LIMIT 8"
        );

        View::render('dashboard/index', [
            'title'      => 'لوحة المعلومات',
            'stats'      => $stats,
            'myTasks'    => $myTasks,
            'latest'     => $latest,
            'latestDocs' => $latestDocs,
            'chart'      => $chart,
            'byCategory' => $byCategory,
        ]);
    }
}
