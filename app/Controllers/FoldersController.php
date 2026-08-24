<?php
/**
 * شجرة الأرشفة : مجلدات بمستويات غير محدودة
 */
class FoldersController
{
    public function index(): void
    {
        Auth::requirePermission('folders.view');
        $folderId = Request::int('id');

        $all  = Database::all(
            'SELECT f.*, d.name AS department_name,
                    (SELECT COUNT(*) FROM documents dd WHERE dd.folder_id = f.id AND dd.deleted_at IS NULL) AS docs_count,
                    (SELECT COUNT(*) FROM transactions t WHERE t.folder_id = f.id AND t.deleted_at IS NULL) AS trx_count
               FROM folders f
               LEFT JOIN departments d ON d.id = f.department_id
              ORDER BY f.sort_order, f.name'
        );
        $tree = Tree::build($all);

        $current = null;
        $children = $documents = $transactions = [];
        if ($folderId) {
            $current = Database::first(
                'SELECT f.*, d.name AS department_name, u.full_name AS creator
                   FROM folders f
                   LEFT JOIN departments d ON d.id = f.department_id
                   LEFT JOIN users u ON u.id = f.created_by
                  WHERE f.id = ?', [$folderId]);
            if (!$current) {
                flash('error', 'المجلد غير موجود.');
                redirect('folders/index');
            }
            $children = array_values(array_filter($all,
                static fn($f) => (int) $f['parent_id'] === $folderId));

            $documents = Database::all(
                'SELECT d.*, u.full_name AS creator
                   FROM documents d LEFT JOIN users u ON u.id = d.created_by
                  WHERE d.folder_id = ? AND d.deleted_at IS NULL
                  ORDER BY d.id DESC LIMIT 100', [$folderId]);

            $transactions = Database::all(
                'SELECT t.*, c.name AS correspondent_name
                   FROM transactions t LEFT JOIN correspondents c ON c.id = t.correspondent_id
                  WHERE t.folder_id = ? AND t.deleted_at IS NULL
                  ORDER BY t.id DESC LIMIT 100', [$folderId]);
        }

        View::render('folders/index', [
            'title'        => 'شجرة الأرشفة',
            'tree'         => $tree,
            'current'      => $current,
            'children'     => $children,
            'documents'    => $documents,
            'transactions' => $transactions,
            'breadcrumb'   => $folderId ? Tree::breadcrumb('folders', $folderId) : [],
            'flatFolders'  => Tree::flatten($tree),
            'departments'  => Tree::flatten(Tree::build(Database::all(
                                'SELECT id, parent_id, name FROM departments WHERE is_active = 1 ORDER BY sort_order'))),
        ]);
    }

    public function store(): void
    {
        Auth::requirePermission('folders.manage');
        Csrf::check();

        $name = mb_substr((string) Request::post('name', ''), 0, 200);
        if (mb_strlen($name) < 2) {
            flash('error', 'اسم المجلد مطلوب.');
            redirect('folders/index', ['id' => Request::int('parent_id') ?: null]);
        }
        $parentId = Request::int('parent_id') ?: null;

        $id = Database::insert('folders', [
            'parent_id'     => $parentId,
            'name'          => $name,
            'code'          => mb_substr((string) Request::post('code', ''), 0, 40) ?: null,
            'description'   => mb_substr((string) Request::post('description', ''), 0, 500) ?: null,
            'department_id' => Request::int('department_id') ?: null,
            'color'         => mb_substr((string) Request::post('color', ''), 0, 20) ?: null,
            'sort_order'    => Request::int('sort_order', 0),
            'path'          => '/',
            'created_by'    => Auth::id(),
        ]);
        Tree::refreshPath($id);

        Audit::log('create', 'folder', $id, 'إنشاء مجلد أرشيف : ' . $name);
        flash('success', 'تم إنشاء المجلد «' . $name . '».');
        redirect('folders/index', ['id' => $id]);
    }

    public function update(): void
    {
        Auth::requirePermission('folders.manage');
        Csrf::check();

        $id     = Request::int('id', 0);
        $folder = Database::first('SELECT * FROM folders WHERE id = ?', [$id]);
        if (!$folder) {
            flash('error', 'المجلد غير موجود.');
            redirect('folders/index');
        }

        $newParent = Request::int('parent_id') ?: null;
        if ($newParent && ($newParent === $id || Tree::isDescendantOf('folders', $newParent, $id))) {
            flash('error', 'لا يمكن نقل المجلد إلى داخل أحد فروعه.');
            redirect('folders/index', ['id' => $id]);
        }

        Database::update('folders', [
            'parent_id'     => $newParent,
            'name'          => mb_substr((string) Request::post('name', $folder['name']), 0, 200),
            'code'          => mb_substr((string) Request::post('code', ''), 0, 40) ?: null,
            'description'   => mb_substr((string) Request::post('description', ''), 0, 500) ?: null,
            'department_id' => Request::int('department_id') ?: null,
            'color'         => mb_substr((string) Request::post('color', ''), 0, 20) ?: null,
            'sort_order'    => Request::int('sort_order', 0),
            'is_active'     => Request::bool('is_active'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);

        Tree::refreshPath($id);
        Audit::log('update', 'folder', $id, 'تعديل مجلد الأرشيف : ' . $folder['name']);
        flash('success', 'تم تحديث بيانات المجلد.');
        redirect('folders/index', ['id' => $id]);
    }

    public function delete(): void
    {
        Auth::requirePermission('folders.manage');
        Csrf::check();

        $id     = Request::int('id', 0);
        $folder = Database::first('SELECT * FROM folders WHERE id = ?', [$id]);
        if (!$folder) {
            redirect('folders/index');
        }

        $ids  = Tree::descendantIds('folders', $id);
        $in   = implode(',', array_map('intval', $ids));
        $docs = (int) Database::value("SELECT COUNT(*) FROM documents WHERE folder_id IN ($in) AND deleted_at IS NULL");
        $trx  = (int) Database::value("SELECT COUNT(*) FROM transactions WHERE folder_id IN ($in) AND deleted_at IS NULL");

        if ($docs > 0 || $trx > 0) {
            flash('error', "لا يمكن حذف المجلد لاحتوائه على {$docs} وثيقة و {$trx} معاملة. انقل محتوياته أولاً.");
            redirect('folders/index', ['id' => $id]);
        }

        Database::delete('folders', 'id = ?', [$id]); // الفروع تُحذف تلقائياً بالقيد
        Audit::log('delete', 'folder', $id, 'حذف مجلد الأرشيف : ' . $folder['name']);
        flash('success', 'تم حذف المجلد وفروعه الفارغة.');
        redirect('folders/index', ['id' => $folder['parent_id'] ?: null]);
    }

    /** شجرة المجلدات بصيغة JSON للاستخدام في الواجهة */
    public function tree(): void
    {
        Auth::requirePermission('folders.view');
        $rows = Database::all('SELECT id, parent_id, name, code FROM folders WHERE is_active = 1 ORDER BY sort_order, name');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(Tree::build($rows), JSON_UNESCAPED_UNICODE);
        exit;
    }
}
