<?php
/**
 * سجل الأحداث
 */
class AuditController
{
    public function index(): void
    {
        Auth::requirePermission('audit.view');

        $filters = [
            'user'   => Request::int('user'),
            'action' => Request::get('action'),
            'entity' => Request::get('entity'),
            'from'   => cleanDate(Request::get('from')),
            'to'     => cleanDate(Request::get('to')),
            'q'      => Request::get('q'),
        ];

        $where  = ['1=1'];
        $params = [];
        if ($filters['user'])   { $where[] = 'a.user_id = :user';       $params['user']   = $filters['user']; }
        if ($filters['action']) { $where[] = 'a.action = :action';      $params['action'] = $filters['action']; }
        if ($filters['entity']) { $where[] = 'a.entity_type = :entity'; $params['entity'] = $filters['entity']; }
        if ($filters['from'])   { $where[] = 'a.created_at >= :dfrom';  $params['dfrom']  = $filters['from'] . ' 00:00:00'; }
        if ($filters['to'])     { $where[] = 'a.created_at <= :dto';    $params['dto']    = $filters['to'] . ' 23:59:59'; }
        if ($filters['q'])      { $where[] = 'a.description LIKE :q';   $params['q']      = '%' . $filters['q'] . '%'; }

        $sql = 'SELECT a.*, u.full_name AS user_name
                  FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY a.id DESC';

        View::render('audit/index', [
            'title'   => 'سجل الأحداث',
            'page'    => Paginator::make($sql, $params, Request::page(), 50),
            'filters' => $filters,
            'users'   => Database::all('SELECT id, full_name FROM users ORDER BY full_name'),
            'actions' => array_column(Database::all('SELECT DISTINCT action FROM audit_logs ORDER BY action'), 'action'),
            'entities'=> array_column(Database::all(
                'SELECT DISTINCT entity_type FROM audit_logs WHERE entity_type IS NOT NULL ORDER BY entity_type'), 'entity_type'),
        ]);
    }

    /** تنظيف السجلات القديمة */
    public function purge(): void
    {
        Auth::requirePermission('settings.manage');
        Csrf::check();
        $days = max(30, Request::int('days', 365));
        $n = Database::run('DELETE FROM audit_logs WHERE created_at < (NOW() - INTERVAL ? DAY)', [$days])->rowCount();
        Audit::log('delete', 'audit', null, "حذف $n سجل أقدم من $days يوم");
        flash('success', "تم حذف {$n} سجل قديم.");
        redirect('audit/index');
    }
}
