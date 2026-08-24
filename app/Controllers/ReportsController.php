<?php
/**
 * التقارير والإحصائيات
 */
class ReportsController
{
    public function index(): void
    {
        Auth::requirePermission('reports.view');

        $from = cleanDate(Request::get('from')) ?: date('Y-01-01');
        $to   = cleanDate(Request::get('to'))   ?: date('Y-m-d');
        $p    = ['from' => $from, 'to' => $to];

        $summary = Database::first(
            "SELECT COUNT(*) AS total,
                    SUM(direction='incoming') AS incoming,
                    SUM(direction='outgoing') AS outgoing,
                    SUM(direction='internal') AS internal,
                    SUM(status='completed')   AS completed,
                    SUM(status='in_progress') AS in_progress,
                    SUM(priority='very_urgent') AS very_urgent,
                    SUM(confidentiality<>'normal') AS confidential
               FROM transactions
              WHERE deleted_at IS NULL AND transaction_date BETWEEN :from AND :to", $p);

        $monthly = Database::all(
            "SELECT DATE_FORMAT(transaction_date,'%Y-%m') AS ym,
                    SUM(direction='incoming') AS incoming,
                    SUM(direction='outgoing') AS outgoing,
                    SUM(direction='internal') AS internal,
                    COUNT(*) AS total
               FROM transactions
              WHERE deleted_at IS NULL AND transaction_date BETWEEN :from AND :to
              GROUP BY ym ORDER BY ym", $p);

        $byCorrespondent = Database::all(
            "SELECT COALESCE(c.name,'غير محدد') AS name, COUNT(*) AS total
               FROM transactions t LEFT JOIN correspondents c ON c.id = t.correspondent_id
              WHERE t.deleted_at IS NULL AND t.transaction_date BETWEEN :from AND :to
              GROUP BY t.correspondent_id, c.name ORDER BY total DESC LIMIT 15", $p);

        $byDepartment = Database::all(
            "SELECT d.name, COUNT(*) AS total
               FROM transactions t
               JOIN departments d ON d.id = t.to_department_id OR d.id = t.from_department_id
              WHERE t.deleted_at IS NULL AND t.transaction_date BETWEEN :from AND :to
              GROUP BY d.id, d.name ORDER BY total DESC LIMIT 15", $p);

        $byCategory = Database::all(
            "SELECT COALESCE(c.name,'بدون تصنيف') AS name, COALESCE(c.color,'#94a3b8') AS color, COUNT(*) AS total
               FROM transactions t LEFT JOIN categories c ON c.id = t.category_id
              WHERE t.deleted_at IS NULL AND t.transaction_date BETWEEN :from AND :to
              GROUP BY t.category_id, c.name, c.color ORDER BY total DESC", $p);

        $byStatus = Database::all(
            "SELECT status, COUNT(*) AS total FROM transactions
              WHERE deleted_at IS NULL AND transaction_date BETWEEN :from AND :to
              GROUP BY status", $p);

        $byUser = Database::all(
            "SELECT u.full_name, COUNT(*) AS total
               FROM transactions t JOIN users u ON u.id = t.created_by
              WHERE t.deleted_at IS NULL AND t.transaction_date BETWEEN :from AND :to
              GROUP BY u.id, u.full_name ORDER BY total DESC LIMIT 15", $p);

        $folderStats = Database::all(
            "SELECT f.id, f.name,
                    (SELECT COUNT(*) FROM documents d WHERE d.folder_id = f.id AND d.deleted_at IS NULL) AS docs,
                    (SELECT COUNT(*) FROM transactions t WHERE t.folder_id = f.id AND t.deleted_at IS NULL) AS trx
               FROM folders f
              ORDER BY (docs + trx) DESC LIMIT 15");

        $attachStats = Database::all(
            "SELECT media_group, kind, COUNT(*) AS total, COALESCE(SUM(file_size),0) AS size
               FROM attachments GROUP BY media_group, kind ORDER BY total DESC");

        $overdue = Database::all(
            "SELECT t.id, t.transaction_no, t.subject, t.due_date, t.direction,
                    DATEDIFF(CURDATE(), t.due_date) AS days_late
               FROM transactions t
              WHERE t.deleted_at IS NULL AND t.due_date IS NOT NULL AND t.due_date < CURDATE()
                AND t.status NOT IN ('completed','closed','archived')
              ORDER BY t.due_date LIMIT 30");

        View::render('reports/index', [
            'title'           => 'التقارير والإحصائيات',
            'from'            => $from,
            'to'              => $to,
            'summary'         => $summary,
            'monthly'         => $monthly,
            'byCorrespondent' => $byCorrespondent,
            'byDepartment'    => $byDepartment,
            'byCategory'      => $byCategory,
            'byStatus'        => $byStatus,
            'byUser'          => $byUser,
            'folderStats'     => $folderStats,
            'attachStats'     => $attachStats,
            'overdue'         => $overdue,
        ]);
    }

    /** تقرير حركة المعاملات جاهز للطباعة */
    public function movement(): void
    {
        Auth::requirePermission('reports.view');
        $from      = cleanDate(Request::get('from')) ?: date('Y-m-01');
        $to        = cleanDate(Request::get('to'))   ?: date('Y-m-d');
        $direction = pick(Request::get('direction'), ['incoming','outgoing','internal'], null);

        $where  = ['t.deleted_at IS NULL', 't.transaction_date BETWEEN :from AND :to'];
        $params = ['from' => $from, 'to' => $to];
        if ($direction) {
            $where[] = 't.direction = :dir';
            $params['dir'] = $direction;
        }

        $rows = Database::all(
            'SELECT t.*, c.name AS correspondent_name, d.name AS to_dept, u.full_name AS creator
               FROM transactions t
               LEFT JOIN correspondents c ON c.id = t.correspondent_id
               LEFT JOIN departments d ON d.id = t.to_department_id
               LEFT JOIN users u ON u.id = t.created_by
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY t.transaction_date, t.id LIMIT 2000', $params);

        Audit::log('print', 'report', null, "تقرير حركة المعاملات من $from إلى $to");

        View::render('reports/movement', [
            'title'     => 'تقرير حركة المعاملات',
            'rows'      => $rows,
            'from'      => $from,
            'to'        => $to,
            'direction' => $direction,
        ], null);
    }
}
