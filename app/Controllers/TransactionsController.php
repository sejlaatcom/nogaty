<?php
/**
 * المعاملات : الصادر والوارد والمذكرات الداخلية
 */
class TransactionsController
{
    // =================================================================
    //  القائمة والبحث
    // =================================================================
    public function index(): void
    {
        Auth::requirePermission('transactions.view');

        $direction = pick(Request::get('direction'), ['incoming','outgoing','internal'], null);
        $filters   = $this->collectFilters();
        [$where, $params] = $this->buildWhere($filters, $direction);

        $sql = "SELECT t.*, c.name AS correspondent_name,
                       fd.name AS from_dept, td.name AS to_dept,
                       cat.name AS category_name, cat.color AS category_color,
                       u.full_name AS creator_name, f.name AS folder_name
                  FROM transactions t
                  LEFT JOIN correspondents c ON c.id = t.correspondent_id
                  LEFT JOIN departments fd ON fd.id = t.from_department_id
                  LEFT JOIN departments td ON td.id = t.to_department_id
                  LEFT JOIN categories cat ON cat.id = t.category_id
                  LEFT JOIN users u ON u.id = t.created_by
                  LEFT JOIN folders f ON f.id = t.folder_id
                 WHERE $where
                 ORDER BY t.transaction_date DESC, t.id DESC";

        $page = Paginator::make($sql, $params, Request::page());

        View::render('transactions/index', [
            'title'      => $direction ? 'المعاملات ال' . labelDirection($direction) . 'ة' : 'كل المعاملات',
            'direction'  => $direction,
            'page'       => $page,
            'filters'    => $filters,
            'lists'      => $this->lookupLists(),
        ]);
    }

    /** المعاملات المحالة إلي */
    public function inbox(): void
    {
        Auth::requirePermission('transactions.view');
        $status = pick(Request::get('status'), ['pending','seen','done','returned'], null);

        $where  = 'tr.to_user_id = :uid AND t.deleted_at IS NULL';
        $params = ['uid' => Auth::id()];
        if ($status) {
            $where .= ' AND tr.status = :st';
            $params['st'] = $status;
        }

        $sql = "SELECT tr.id AS routing_id, tr.action, tr.status AS routing_status, tr.instructions,
                       tr.due_date AS routing_due, tr.created_at AS routed_at,
                       t.*, u.full_name AS from_name, c.name AS correspondent_name
                  FROM transaction_routings tr
                  JOIN transactions t ON t.id = tr.transaction_id
                  LEFT JOIN users u ON u.id = tr.from_user_id
                  LEFT JOIN correspondents c ON c.id = t.correspondent_id
                 WHERE $where
                 ORDER BY FIELD(tr.status,'pending','seen','returned','done'),
                          FIELD(t.priority,'very_urgent','urgent','normal'), tr.id DESC";

        View::render('transactions/inbox', [
            'title'  => 'الوارد إليّ',
            'page'   => Paginator::make($sql, $params, Request::page()),
            'status' => $status,
        ]);
    }

    // =================================================================
    //  إضافة معاملة
    // =================================================================
    public function create(): void
    {
        Auth::requirePermission('transactions.create');
        $direction = pick(Request::get('direction'), ['incoming','outgoing','internal'], 'incoming');

        View::render('transactions/form', [
            'title'       => 'قيد معاملة ' . labelDirection($direction) . 'ة',
            'transaction' => null,
            'direction'   => $direction,
            'lists'       => $this->lookupLists(),
            'nextNo'      => $this->previewNumber($direction),
            'tags'        => '',
        ]);
    }

    public function store(): void
    {
        Auth::requirePermission('transactions.create');
        Csrf::check();

        $data   = $this->validate();
        $errors = $data['errors'];
        if ($errors) {
            keepOld($_POST);
            flash('error', implode(' — ', $errors));
            redirect('transactions/create', ['direction' => Request::post('direction', 'incoming')]);
        }
        $fields = $data['fields'];

        Database::begin();
        try {
            $fields['transaction_no'] = $this->nextNumber($fields['direction']);
            $fields['created_by']     = Auth::id();
            $fields['hijri_date']     = Hijri::format($fields['transaction_date']);

            $id = Database::insert('transactions', $fields);
            $this->syncTags($id, Request::post('tags'));

            $att = AttachmentService::saveFromRequest('transaction', $id, 'transactions');

            Database::commit();

            Audit::log('create', 'transaction', $id,
                'قيد معاملة ' . labelDirection($fields['direction']) . ' رقم ' . $fields['transaction_no']);

            if ($att['errors']) {
                flash('warning', 'تم حفظ المعاملة مع ملاحظات على المرفقات: ' . implode(' | ', $att['errors']));
            }
            clearOld();
            flash('success', 'تم قيد المعاملة برقم ' . $fields['transaction_no'] . ' بنجاح.');
            redirect('transactions/show', ['id' => $id]);
        } catch (Throwable $ex) {
            Database::rollback();
            error_log('store transaction: ' . $ex->getMessage());
            keepOld($_POST);
            flash('error', 'تعذر حفظ المعاملة. ' . $ex->getMessage());
            redirect('transactions/create', ['direction' => Request::post('direction', 'incoming')]);
        }
    }

    // =================================================================
    //  عرض معاملة
    // =================================================================
    public function show(): void
    {
        Auth::requirePermission('transactions.view');
        $id = Request::int('id', 0);

        $trx = Database::first(
            "SELECT t.*, c.name AS correspondent_name, c.type AS correspondent_type,
                    fd.name AS from_dept, td.name AS to_dept,
                    cat.name AS category_name, cat.color AS category_color,
                    f.name AS folder_name, ay.name AS year_name,
                    u.full_name AS creator_name, uu.full_name AS updater_name,
                    lt.transaction_no AS linked_no, lt.subject AS linked_subject, lt.direction AS linked_direction
               FROM transactions t
               LEFT JOIN correspondents c ON c.id = t.correspondent_id
               LEFT JOIN departments fd ON fd.id = t.from_department_id
               LEFT JOIN departments td ON td.id = t.to_department_id
               LEFT JOIN categories cat ON cat.id = t.category_id
               LEFT JOIN folders f ON f.id = t.folder_id
               LEFT JOIN academic_years ay ON ay.id = t.academic_year_id
               LEFT JOIN users u ON u.id = t.created_by
               LEFT JOIN users uu ON uu.id = t.updated_by
               LEFT JOIN transactions lt ON lt.id = t.linked_transaction_id
              WHERE t.id = ? AND t.deleted_at IS NULL",
            [$id]
        );
        if (!$trx) {
            flash('error', 'المعاملة غير موجودة أو تم حذفها.');
            redirect('transactions/index');
        }
        if (!$this->canAccess($trx)) {
            flash('error', 'لا تملك صلاحية الاطلاع على هذه المعاملة.');
            redirect('transactions/index');
        }

        // تعليم الإحالة كمقروءة
        Database::run(
            "UPDATE transaction_routings SET status='seen', seen_at = NOW()
              WHERE transaction_id = ? AND to_user_id = ? AND status = 'pending'",
            [$id, Auth::id()]
        );

        $routings = Database::all(
            "SELECT tr.*, uf.full_name AS from_name, ut.full_name AS to_name, d.name AS to_dept_name
               FROM transaction_routings tr
               LEFT JOIN users uf ON uf.id = tr.from_user_id
               LEFT JOIN users ut ON ut.id = tr.to_user_id
               LEFT JOIN departments d ON d.id = tr.to_department_id
              WHERE tr.transaction_id = ? ORDER BY tr.id",
            [$id]
        );

        Audit::log('view', 'transaction', $id, 'اطلاع على المعاملة ' . $trx['transaction_no']);

        View::render('transactions/show', [
            'title'       => 'معاملة ' . $trx['transaction_no'],
            'trx'         => $trx,
            'attachments' => AttachmentService::listFor('transaction', $id),
            'routings'    => $routings,
            'tags'        => $this->getTags($id),
            'myRouting'   => Database::first(
                "SELECT * FROM transaction_routings WHERE transaction_id = ? AND to_user_id = ? ORDER BY id DESC LIMIT 1",
                [$id, Auth::id()]),
            'lists'       => $this->lookupLists(),
            'shares'      => Database::all(
                "SELECT * FROM shares WHERE shareable_type='transaction' AND shareable_id = ? ORDER BY id DESC", [$id]),
        ]);
    }

    // =================================================================
    //  تعديل
    // =================================================================
    public function edit(): void
    {
        Auth::requirePermission('transactions.edit');
        $id  = Request::int('id', 0);
        $trx = Database::first('SELECT * FROM transactions WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$trx) {
            flash('error', 'المعاملة غير موجودة.');
            redirect('transactions/index');
        }

        View::render('transactions/form', [
            'title'       => 'تعديل المعاملة ' . $trx['transaction_no'],
            'transaction' => $trx,
            'direction'   => $trx['direction'],
            'lists'       => $this->lookupLists(),
            'nextNo'      => $trx['transaction_no'],
            'attachments' => AttachmentService::listFor('transaction', $id),
            'tags'        => implode('، ', $this->getTags($id)),
        ]);
    }

    public function update(): void
    {
        Auth::requirePermission('transactions.edit');
        Csrf::check();

        $id  = Request::int('id', 0);
        $trx = Database::first('SELECT * FROM transactions WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$trx) {
            flash('error', 'المعاملة غير موجودة.');
            redirect('transactions/index');
        }

        $data = $this->validate();
        if ($data['errors']) {
            keepOld($_POST);
            flash('error', implode(' — ', $data['errors']));
            redirect('transactions/edit', ['id' => $id]);
        }
        $fields = $data['fields'];
        unset($fields['direction']); // لا يُغيّر نوع المعاملة بعد القيد
        $fields['updated_by'] = Auth::id();
        $fields['updated_at'] = date('Y-m-d H:i:s');
        $fields['hijri_date'] = Hijri::format($fields['transaction_date']);

        Database::begin();
        try {
            Database::update('transactions', $fields, 'id = :id', ['id' => $id]);
            $this->syncTags($id, Request::post('tags'));
            $att = AttachmentService::saveFromRequest('transaction', $id, 'transactions');
            Database::commit();

            Audit::log('update', 'transaction', $id, 'تعديل المعاملة ' . $trx['transaction_no']);
            if ($att['errors']) {
                flash('warning', implode(' | ', $att['errors']));
            }
            clearOld();
            flash('success', 'تم تحديث بيانات المعاملة بنجاح.');
            redirect('transactions/show', ['id' => $id]);
        } catch (Throwable $ex) {
            Database::rollback();
            flash('error', 'تعذر تحديث المعاملة. ' . $ex->getMessage());
            redirect('transactions/edit', ['id' => $id]);
        }
    }

    // =================================================================
    //  الحذف والاسترجاع
    // =================================================================
    public function delete(): void
    {
        Auth::requirePermission('transactions.delete');
        Csrf::check();
        $id  = Request::int('id', 0);
        $trx = Database::first('SELECT * FROM transactions WHERE id = ?', [$id]);
        if ($trx) {
            Database::update('transactions', ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
            Audit::log('delete', 'transaction', $id, 'حذف المعاملة ' . $trx['transaction_no'] . ' (إلى المحذوفات)');
            flash('success', 'تم نقل المعاملة إلى المحذوفات.');
        }
        redirect('transactions/index');
    }

    public function trash(): void
    {
        Auth::requirePermission('transactions.delete');
        $sql = "SELECT t.*, u.full_name AS creator_name
                  FROM transactions t LEFT JOIN users u ON u.id = t.created_by
                 WHERE t.deleted_at IS NOT NULL ORDER BY t.deleted_at DESC";
        View::render('transactions/trash', [
            'title' => 'سلة المحذوفات',
            'page'  => Paginator::make($sql, [], Request::page()),
        ]);
    }

    public function restore(): void
    {
        Auth::requirePermission('transactions.delete');
        Csrf::check();
        $id = Request::int('id', 0);
        Database::update('transactions', ['deleted_at' => null], 'id = :id', ['id' => $id]);
        Audit::log('restore', 'transaction', $id, 'استرجاع معاملة من المحذوفات');
        flash('success', 'تم استرجاع المعاملة.');
        redirect('transactions/trash');
    }

    public function forceDelete(): void
    {
        Auth::requirePermission('transactions.delete');
        Csrf::check();
        $id = Request::int('id', 0);
        AttachmentService::removeAllFor('transaction', $id);
        Database::delete('transactions', 'id = ? AND deleted_at IS NOT NULL', [$id]);
        Audit::log('delete', 'transaction', $id, 'حذف نهائي للمعاملة');
        flash('success', 'تم الحذف النهائي للمعاملة ومرفقاتها.');
        redirect('transactions/trash');
    }

    // =================================================================
    //  الإحالة والتحويل
    // =================================================================
    public function route(): void
    {
        Auth::requirePermission('transactions.route');
        Csrf::check();

        $id  = Request::int('transaction_id', 0);
        $trx = Database::first('SELECT * FROM transactions WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$trx) {
            flash('error', 'المعاملة غير موجودة.');
            redirect('transactions/index');
        }

        $toUsers = array_filter(array_map('intval', Request::array('to_user_id')));
        $toDept  = Request::int('to_department_id');
        $action  = pick(Request::post('action_type'),
            ['for_action','for_info','for_review','for_approval','for_signature','for_archive'], 'for_action');

        if (!$toUsers && !$toDept) {
            flash('error', 'يجب اختيار موظف أو إدارة للإحالة.');
            redirect('transactions/show', ['id' => $id]);
        }

        // إذا اختيرت إدارة دون موظفين، تُحال لكل موظفي الإدارة
        if (!$toUsers && $toDept) {
            $rows    = Database::all('SELECT id FROM users WHERE department_id = ? AND is_active = 1', [$toDept]);
            $toUsers = array_map(static fn($r) => (int) $r['id'], $rows);
            if (!$toUsers) {
                flash('error', 'لا يوجد موظفون نشطون في الإدارة المختارة.');
                redirect('transactions/show', ['id' => $id]);
            }
        }

        $instructions = mb_substr((string) Request::post('instructions', ''), 0, 1000);
        $due          = cleanDate(Request::post('due_date'));
        $count        = 0;

        foreach ($toUsers as $uid) {
            Database::insert('transaction_routings', [
                'transaction_id'   => $id,
                'from_user_id'     => Auth::id(),
                'to_user_id'       => $uid,
                'to_department_id' => $toDept ?: null,
                'action'           => $action,
                'instructions'     => $instructions ?: null,
                'due_date'         => $due,
            ]);
            Notify::send($uid,
                'إحالة معاملة : ' . $trx['transaction_no'],
                labelRoutingAction($action) . ' — ' . excerpt($trx['subject'], 70),
                url('transactions/show', ['id' => $id]), 'inbox');
            $count++;
        }

        if ($trx['status'] === 'registered' || $trx['status'] === 'draft') {
            Database::update('transactions', ['status' => 'in_progress'], 'id = :id', ['id' => $id]);
        }

        Audit::log('route', 'transaction', $id, "إحالة المعاملة {$trx['transaction_no']} إلى {$count} مستخدم");
        flash('success', "تمت إحالة المعاملة إلى {$count} مستخدم.");
        redirect('transactions/show', ['id' => $id]);
    }

    /** رد المحال إليه على الإحالة */
    public function respond(): void
    {
        Auth::requireLogin();
        Csrf::check();

        $routingId = Request::int('routing_id', 0);
        $routing   = Database::first('SELECT * FROM transaction_routings WHERE id = ?', [$routingId]);
        if (!$routing || (int) $routing['to_user_id'] !== Auth::id()) {
            flash('error', 'الإحالة غير موجودة أو ليست موجهة إليك.');
            redirect('transactions/inbox');
        }

        $status   = pick(Request::post('routing_status'), ['seen','done','returned'], 'done');
        $response = mb_substr((string) Request::post('response', ''), 0, 1000);

        Database::update('transaction_routings', [
            'status'       => $status,
            'response'     => $response ?: null,
            'completed_at' => in_array($status, ['done','returned'], true) ? date('Y-m-d H:i:s') : null,
        ], 'id = :id', ['id' => $routingId]);

        if ($routing['from_user_id']) {
            $trx = Database::first('SELECT transaction_no, subject FROM transactions WHERE id = ?', [$routing['transaction_id']]);
            Notify::send((int) $routing['from_user_id'],
                'رد على إحالة : ' . ($trx['transaction_no'] ?? ''),
                labelRoutingStatus($status) . ' — ' . excerpt($response ?: ($trx['subject'] ?? ''), 70),
                url('transactions/show', ['id' => $routing['transaction_id']]), 'reply');
        }

        // إذا أنجزت كل الإحالات تصبح المعاملة منجزة
        $pending = (int) Database::value(
            "SELECT COUNT(*) FROM transaction_routings WHERE transaction_id = ? AND status IN ('pending','seen')",
            [$routing['transaction_id']]
        );
        if ($pending === 0) {
            Database::update('transactions', ['status' => 'completed'], 'id = :id AND status = "in_progress"',
                ['id' => $routing['transaction_id']]);
        }

        Audit::log('update', 'routing', $routingId, 'رد على إحالة : ' . labelRoutingStatus($status));
        flash('success', 'تم تسجيل ردك على الإحالة.');
        redirect('transactions/show', ['id' => (int) $routing['transaction_id']]);
    }

    /** تغيير حالة المعاملة */
    public function changeStatus(): void
    {
        Auth::requirePermission('transactions.edit');
        Csrf::check();
        $id     = Request::int('id', 0);
        $status = pick(Request::post('status'),
            ['draft','registered','in_progress','completed','archived','closed'], 'registered');

        Database::update('transactions', ['status' => $status, 'updated_by' => Auth::id(),
            'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
        Audit::log('update', 'transaction', $id, 'تغيير الحالة إلى : ' . labelStatus($status));
        flash('success', 'تم تغيير حالة المعاملة إلى : ' . labelStatus($status));
        redirect('transactions/show', ['id' => $id]);
    }

    // =================================================================
    //  الطباعة والتصدير
    // =================================================================
    public function printView(): void
    {
        Auth::requirePermission('transactions.export');
        $id  = Request::int('id', 0);
        $trx = Database::first(
            "SELECT t.*, c.name AS correspondent_name, fd.name AS from_dept, td.name AS to_dept,
                    cat.name AS category_name, u.full_name AS creator_name
               FROM transactions t
               LEFT JOIN correspondents c ON c.id = t.correspondent_id
               LEFT JOIN departments fd ON fd.id = t.from_department_id
               LEFT JOIN departments td ON td.id = t.to_department_id
               LEFT JOIN categories cat ON cat.id = t.category_id
               LEFT JOIN users u ON u.id = t.created_by
              WHERE t.id = ? AND t.deleted_at IS NULL", [$id]
        );
        if (!$trx) {
            flash('error', 'المعاملة غير موجودة.');
            redirect('transactions/index');
        }
        Audit::log('print', 'transaction', $id, 'طباعة المعاملة ' . $trx['transaction_no']);

        View::render('transactions/print', [
            'title'       => 'طباعة المعاملة',
            'trx'         => $trx,
            'attachments' => AttachmentService::listFor('transaction', $id),
            'routings'    => Database::all(
                "SELECT tr.*, ut.full_name AS to_name, uf.full_name AS from_name
                   FROM transaction_routings tr
                   LEFT JOIN users ut ON ut.id = tr.to_user_id
                   LEFT JOIN users uf ON uf.id = tr.from_user_id
                  WHERE tr.transaction_id = ? ORDER BY tr.id", [$id]),
            'barcode'     => Barcode::svg($trx['transaction_no']),
        ], null);
    }

    public function export(): void
    {
        Auth::requirePermission('transactions.export');
        $direction = pick(Request::get('direction'), ['incoming','outgoing','internal'], null);
        [$where, $params] = $this->buildWhere($this->collectFilters(), $direction);

        $rows = Database::all(
            "SELECT t.transaction_no, t.direction, t.reference_no, t.subject, t.transaction_date, t.hijri_date,
                    c.name AS correspondent, fd.name AS from_dept, td.name AS to_dept,
                    cat.name AS category, t.priority, t.confidentiality, t.status, t.due_date,
                    f.name AS folder, u.full_name AS creator, t.attachments_count
               FROM transactions t
               LEFT JOIN correspondents c ON c.id = t.correspondent_id
               LEFT JOIN departments fd ON fd.id = t.from_department_id
               LEFT JOIN departments td ON td.id = t.to_department_id
               LEFT JOIN categories cat ON cat.id = t.category_id
               LEFT JOIN folders f ON f.id = t.folder_id
               LEFT JOIN users u ON u.id = t.created_by
              WHERE $where ORDER BY t.transaction_date DESC, t.id DESC LIMIT 10000",
            $params
        );

        Audit::log('export', 'transaction', null, 'تصدير ' . count($rows) . ' معاملة إلى ملف CSV');

        $filename = 'transactions-' . date('Ymd-His') . '.csv';
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM لدعم العربية في Excel
        $this->csvRow($out, ['رقم المعاملة','النوع','رقم الجهة','الموضوع','التاريخ الميلادي','التاريخ الهجري',
            'الجهة','من إدارة','إلى إدارة','التصنيف','الأهمية','السرية','الحالة','تاريخ الاستحقاق',
            'مجلد الأرشيف','المُدخل','عدد المرفقات']);
        foreach ($rows as $r) {
            $this->csvRow($out, [
                $r['transaction_no'], labelDirection($r['direction']), $r['reference_no'], $r['subject'],
                $r['transaction_date'], $r['hijri_date'], $r['correspondent'], $r['from_dept'], $r['to_dept'],
                $r['category'], labelPriority($r['priority']), labelConfidentiality($r['confidentiality']),
                labelStatus($r['status']), $r['due_date'], $r['folder'], $r['creator'], $r['attachments_count'],
            ]);
        }
        fclose($out);
        exit;
    }

    /**
     * كتابة صف CSV مع تحييد الصيغ التي قد ينفّذها Excel
     */
    private function csvRow($handle, array $fields): void
    {
        $safe = array_map(static function ($v) {
            $v = (string) ($v ?? '');
            return $v !== '' && str_contains("=+-@\t\r", $v[0]) ? "'" . $v : $v;
        }, $fields);
        fputcsv($handle, $safe, ',', '"', '');
    }

    // =================================================================
    //  دوال داخلية
    // =================================================================
    private function collectFilters(): array
    {
        return [
            'q'          => Request::get('q'),
            'status'     => pick(Request::get('status'), ['draft','registered','in_progress','completed','archived','closed'], null),
            'priority'   => pick(Request::get('priority'), ['normal','urgent','very_urgent'], null),
            'conf'       => pick(Request::get('conf'), ['normal','confidential','top_secret'], null),
            'category'   => Request::int('category'),
            'corr'       => Request::int('corr'),
            'folder'     => Request::int('folder'),
            'dept'       => Request::int('dept'),
            'year'       => Request::int('year'),
            'from'       => cleanDate(Request::get('from')),
            'to'         => cleanDate(Request::get('to')),
            'overdue'    => Request::get('overdue') ? 1 : 0,
            'has_attach' => Request::get('has_attach') ? 1 : 0,
        ];
    }

    private function buildWhere(array $f, ?string $direction): array
    {
        $where  = ['t.deleted_at IS NULL'];
        $params = [];

        if ($direction) {
            $where[] = 't.direction = :direction';
            $params['direction'] = $direction;
        }
        if (!Auth::can('transactions.view_all')) {
            $where[] = '(t.created_by = :me_owner OR EXISTS (SELECT 1 FROM transaction_routings tr
                          WHERE tr.transaction_id = t.id AND tr.to_user_id = :me_routed))';
            $params['me_owner']  = Auth::id();
            $params['me_routed'] = Auth::id();
        }
        if (!empty($f['q'])) {
            $where[] = likeAny(
                ['t.subject', 't.body', 't.transaction_no', 't.reference_no', 't.keywords'],
                (string) $f['q'], $params
            );
        }
        foreach ([['status','status'], ['priority','priority'], ['conf','confidentiality']] as [$key, $col]) {
            if (!empty($f[$key])) {
                $where[] = "t.$col = :$key";
                $params[$key] = $f[$key];
            }
        }
        foreach ([['category','category_id'], ['corr','correspondent_id'], ['year','academic_year_id']] as [$key, $col]) {
            if (!empty($f[$key])) {
                $where[] = "t.$col = :$key";
                $params[$key] = $f[$key];
            }
        }
        if (!empty($f['folder'])) {
            $ids = Tree::descendantIds('folders', (int) $f['folder']);
            $where[] = 't.folder_id IN (' . implode(',', array_map('intval', $ids)) . ')';
        }
        if (!empty($f['dept'])) {
            $where[] = '(t.from_department_id = :dept_from OR t.to_department_id = :dept_to)';
            $params['dept_from'] = $f['dept'];
            $params['dept_to']   = $f['dept'];
        }
        if (!empty($f['from'])) {
            $where[] = 't.transaction_date >= :dfrom';
            $params['dfrom'] = $f['from'];
        }
        if (!empty($f['to'])) {
            $where[] = 't.transaction_date <= :dto';
            $params['dto'] = $f['to'];
        }
        if (!empty($f['overdue'])) {
            $where[] = "t.due_date IS NOT NULL AND t.due_date < CURDATE()
                        AND t.status NOT IN ('completed','closed','archived')";
        }
        if (!empty($f['has_attach'])) {
            $where[] = 't.attachments_count > 0';
        }
        return [implode(' AND ', $where), $params];
    }

    private function validate(): array
    {
        $errors = [];
        $subject = (string) Request::post('subject', '');
        if (mb_strlen($subject) < 3) {
            $errors[] = 'موضوع المعاملة مطلوب (٣ أحرف على الأقل).';
        }
        $date = cleanDate(Request::post('transaction_date'));
        if (!$date) {
            $errors[] = 'تاريخ المعاملة مطلوب.';
        }
        $direction = pick(Request::post('direction'), ['incoming','outgoing','internal'], 'incoming');

        $fields = [
            'direction'          => $direction,
            'reference_no'       => mb_substr((string) Request::post('reference_no', ''), 0, 80) ?: null,
            'subject'            => mb_substr($subject, 0, 500),
            'body'               => Request::post('body') ?: null,
            'transaction_date'   => $date ?: date('Y-m-d'),
            'received_date'      => cleanDate(Request::post('received_date')),
            'correspondent_id'   => Request::int('correspondent_id') ?: null,
            'from_department_id' => Request::int('from_department_id') ?: null,
            'to_department_id'   => Request::int('to_department_id') ?: null,
            'folder_id'          => Request::int('folder_id') ?: null,
            'category_id'        => Request::int('category_id') ?: null,
            'academic_year_id'   => Request::int('academic_year_id') ?: null,
            'priority'           => pick(Request::post('priority'), ['normal','urgent','very_urgent'], 'normal'),
            'confidentiality'    => pick(Request::post('confidentiality'), ['normal','confidential','top_secret'], 'normal'),
            'status'             => pick(Request::post('status'),
                                      ['draft','registered','in_progress','completed','archived','closed'], 'registered'),
            'due_date'           => cleanDate(Request::post('due_date')),
            'keywords'           => mb_substr((string) Request::post('keywords', ''), 0, 500) ?: null,
            'notes'              => Request::post('notes') ?: null,
            'linked_transaction_id' => Request::int('linked_transaction_id') ?: null,
        ];
        return ['errors' => $errors, 'fields' => $fields];
    }

    /** توليد رقم المعاملة التالي بشكل آمن */
    private function nextNumber(string $direction): string
    {
        $year = (int) date('Y');
        Database::run(
            'INSERT INTO sequences (name, year, current_no) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE current_no = current_no + 1',
            [$direction, $year]
        );
        $seq = (int) Database::value('SELECT current_no FROM sequences WHERE name = ? AND year = ?', [$direction, $year]);
        return $this->formatNumber($direction, $year, $seq);
    }

    private function previewNumber(string $direction): string
    {
        $year = (int) date('Y');
        $seq  = (int) Database::value('SELECT current_no FROM sequences WHERE name = ? AND year = ?', [$direction, $year]);
        return $this->formatNumber($direction, $year, $seq + 1);
    }

    private function formatNumber(string $direction, int $year, int $seq): string
    {
        $prefix  = ['incoming' => 'و', 'outgoing' => 'ص', 'internal' => 'د'][$direction] ?? 'م';
        $padding = max(1, (int) setting('seq_padding', 5));
        $format  = (string) setting('transaction_no_format', '{TYPE}-{YEAR}-{SEQ}');
        return strtr($format, [
            '{TYPE}' => $prefix,
            '{YEAR}' => (string) $year,
            '{HYEAR}'=> (string) Hijri::currentYear(),
            '{SEQ}'  => str_pad((string) $seq, $padding, '0', STR_PAD_LEFT),
        ]);
    }

    private function canAccess(array $trx): bool
    {
        if (Auth::can('transactions.view_all')) {
            return true;
        }
        if ((int) $trx['created_by'] === Auth::id()) {
            return true;
        }
        return (bool) Database::value(
            'SELECT 1 FROM transaction_routings WHERE transaction_id = ? AND to_user_id = ? LIMIT 1',
            [$trx['id'], Auth::id()]
        );
    }

    private function syncTags(int $id, ?string $raw): void
    {
        Database::delete('taggables', "taggable_type = 'transaction' AND taggable_id = ?", [$id]);
        foreach (parseTags($raw) as $name) {
            Database::run('INSERT IGNORE INTO tags (name) VALUES (?)', [$name]);
            $tagId = (int) Database::value('SELECT id FROM tags WHERE name = ?', [$name]);
            if ($tagId) {
                Database::run('INSERT IGNORE INTO taggables (tag_id, taggable_type, taggable_id) VALUES (?, ?, ?)',
                    [$tagId, 'transaction', $id]);
            }
        }
    }

    private function getTags(int $id): array
    {
        $rows = Database::all(
            "SELECT tg.name FROM tags tg JOIN taggables t ON t.tag_id = tg.id
              WHERE t.taggable_type = 'transaction' AND t.taggable_id = ?", [$id]);
        return array_column($rows, 'name');
    }

    /** القوائم المساعدة للنماذج */
    private function lookupLists(): array
    {
        return [
            'correspondents' => Database::all('SELECT id, name, type FROM correspondents WHERE is_active = 1 ORDER BY name'),
            'departments'    => Tree::flatten(Tree::build(Database::all(
                                    'SELECT id, parent_id, name FROM departments WHERE is_active = 1 ORDER BY sort_order, name'))),
            'folders'        => Tree::flatten(Tree::build(Database::all(
                                    'SELECT id, parent_id, name FROM folders WHERE is_active = 1 ORDER BY sort_order, name'))),
            'categories'     => Database::all('SELECT id, name, color FROM categories WHERE is_active = 1 ORDER BY name'),
            'years'          => Database::all('SELECT id, name, is_current FROM academic_years ORDER BY id DESC'),
            'users'          => Database::all(
                                    'SELECT u.id, u.full_name, d.name AS dept FROM users u
                                     LEFT JOIN departments d ON d.id = u.department_id
                                     WHERE u.is_active = 1 ORDER BY u.full_name'),
        ];
    }
}
