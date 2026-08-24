<?php
/**
 * البحث الشامل في المعاملات والوثائق والمرفقات
 */
class SearchController
{
    public function index(): void
    {
        Auth::requireLogin();

        $q     = (string) (Request::get('q') ?? '');
        $scope = pick(Request::get('scope'), ['all','transactions','documents','attachments'], 'all');
        $results = ['transactions' => [], 'documents' => [], 'attachments' => []];
        $counts  = ['transactions' => 0, 'documents' => 0, 'attachments' => 0];

        if (mb_strlen($q) >= 2) {
            if ($scope === 'all' || $scope === 'transactions') {
                $extra  = '';
                $params = [];
                if (!Auth::can('transactions.view_all')) {
                    $extra = ' AND (t.created_by = :me_owner OR EXISTS (SELECT 1 FROM transaction_routings tr
                                WHERE tr.transaction_id = t.id AND tr.to_user_id = :me_routed))';
                    $params['me_owner']  = Auth::id();
                    $params['me_routed'] = Auth::id();
                }
                $match = likeAny(
                    ['t.subject', 't.body', 't.transaction_no', 't.reference_no', 't.keywords', 't.notes'],
                    $q, $params
                );
                $results['transactions'] = Database::all(
                    "SELECT t.id, t.transaction_no, t.direction, t.subject, t.transaction_date,
                            t.status, t.attachments_count, c.name AS correspondent_name
                       FROM transactions t
                       LEFT JOIN correspondents c ON c.id = t.correspondent_id
                      WHERE t.deleted_at IS NULL$extra AND $match
                      ORDER BY t.id DESC LIMIT 50", $params);
                $counts['transactions'] = count($results['transactions']);
            }

            if ($scope === 'all' || $scope === 'documents') {
                $params = [];
                $match  = likeAny(['d.title', 'd.description', 'd.doc_no', 'd.keywords'], $q, $params);
                $results['documents'] = Database::all(
                    "SELECT d.id, d.title, d.doc_no, d.doc_date, d.doc_type, d.attachments_count,
                            f.name AS folder_name
                       FROM documents d JOIN folders f ON f.id = d.folder_id
                      WHERE d.deleted_at IS NULL AND $match
                      ORDER BY d.id DESC LIMIT 50", $params);
                $counts['documents'] = count($results['documents']);
            }

            if ($scope === 'all' || $scope === 'attachments') {
                $params = [];
                $match  = likeAny(['a.title', 'a.file_name'], $q, $params);
                $results['attachments'] = Database::all(
                    "SELECT a.*, CASE WHEN a.attachable_type='transaction'
                              THEN (SELECT t.subject FROM transactions t WHERE t.id = a.attachable_id)
                              ELSE (SELECT d.title FROM documents d WHERE d.id = a.attachable_id) END AS owner_title
                       FROM attachments a
                      WHERE $match
                      ORDER BY a.id DESC LIMIT 50", $params);
                $counts['attachments'] = count($results['attachments']);
            }

            Audit::log('view', 'search', null, 'بحث عن : ' . mb_substr($q, 0, 100));
        }

        View::render('search/index', [
            'title'   => 'البحث الشامل',
            'q'       => $q,
            'scope'   => $scope,
            'results' => $results,
            'counts'  => $counts,
        ]);
    }

    /** البحث المتقدم بمعايير متعددة */
    public function advanced(): void
    {
        Auth::requirePermission('transactions.view');

        $lists = [
            'correspondents' => Database::all('SELECT id, name FROM correspondents WHERE is_active = 1 ORDER BY name'),
            'departments'    => Tree::flatten(Tree::build(Database::all(
                'SELECT id, parent_id, name FROM departments WHERE is_active = 1 ORDER BY sort_order'))),
            'folders'        => Tree::flatten(Tree::build(Database::all(
                'SELECT id, parent_id, name FROM folders WHERE is_active = 1 ORDER BY sort_order'))),
            'categories'     => Database::all('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name'),
            'years'          => Database::all('SELECT id, name FROM academic_years ORDER BY id DESC'),
            'tags'           => Database::all('SELECT id, name FROM tags ORDER BY name LIMIT 200'),
        ];

        View::render('search/advanced', [
            'title' => 'البحث المتقدم',
            'lists' => $lists,
        ]);
    }

    /** اقتراحات فورية للبحث (JSON) */
    public function suggest(): void
    {
        Auth::requireLogin();
        $q = (string) (Request::get('q') ?? '');
        $out = [];
        if (mb_strlen($q) >= 2) {
            $like = '%' . $q . '%';
            foreach (Database::all(
                "SELECT id, transaction_no, subject FROM transactions
                  WHERE deleted_at IS NULL AND (subject LIKE ? OR transaction_no LIKE ?)
                  ORDER BY id DESC LIMIT 6", [$like, $like]) as $r) {
                $out[] = [
                    'label' => $r['transaction_no'] . ' — ' . excerpt($r['subject'], 60),
                    'url'   => url('transactions/show', ['id' => $r['id']]),
                    'type'  => 'معاملة',
                ];
            }
            foreach (Database::all(
                "SELECT id, title FROM documents WHERE deleted_at IS NULL AND title LIKE ?
                  ORDER BY id DESC LIMIT 6", [$like]) as $r) {
                $out[] = [
                    'label' => excerpt($r['title'], 70),
                    'url'   => url('documents/show', ['id' => $r['id']]),
                    'type'  => 'وثيقة',
                ];
            }
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
