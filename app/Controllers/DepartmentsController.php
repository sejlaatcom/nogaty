<?php
/**
 * الهيكل التنظيمي للمدرسة
 */
class DepartmentsController
{
    public function index(): void
    {
        Auth::requirePermission('departments.manage');
        $rows = Database::all(
            'SELECT d.*, u.full_name AS manager_name,
                    (SELECT COUNT(*) FROM users us WHERE us.department_id = d.id AND us.is_active = 1) AS users_count
               FROM departments d
               LEFT JOIN users u ON u.id = d.manager_id
              ORDER BY d.sort_order, d.name');

        View::render('departments/index', [
            'title' => 'الهيكل التنظيمي',
            'tree'  => Tree::build($rows),
            'flat'  => Tree::flatten(Tree::build($rows)),
            'users' => Database::all('SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name'),
        ]);
    }

    public function store(): void
    {
        Auth::requirePermission('departments.manage');
        Csrf::check();
        $name = mb_substr((string) Request::post('name', ''), 0, 150);
        if (mb_strlen($name) < 2) {
            flash('error', 'اسم الإدارة مطلوب.');
            redirect('departments/index');
        }
        $id = Database::insert('departments', [
            'parent_id'   => Request::int('parent_id') ?: null,
            'name'        => $name,
            'code'        => mb_substr((string) Request::post('code', ''), 0, 40) ?: null,
            'manager_id'  => Request::int('manager_id') ?: null,
            'description' => mb_substr((string) Request::post('description', ''), 0, 500) ?: null,
            'sort_order'  => Request::int('sort_order', 0),
        ]);
        Audit::log('create', 'department', $id, 'إضافة إدارة : ' . $name);
        flash('success', 'تمت إضافة الإدارة.');
        redirect('departments/index');
    }

    public function update(): void
    {
        Auth::requirePermission('departments.manage');
        Csrf::check();
        $id  = Request::int('id', 0);
        $dep = Database::first('SELECT * FROM departments WHERE id = ?', [$id]);
        if (!$dep) {
            redirect('departments/index');
        }
        $parent = Request::int('parent_id') ?: null;
        if ($parent && ($parent === $id || Tree::isDescendantOf('departments', $parent, $id))) {
            flash('error', 'لا يمكن ربط الإدارة بأحد فروعها.');
            redirect('departments/index');
        }
        Database::update('departments', [
            'parent_id'   => $parent,
            'name'        => mb_substr((string) Request::post('name', $dep['name']), 0, 150),
            'code'        => mb_substr((string) Request::post('code', ''), 0, 40) ?: null,
            'manager_id'  => Request::int('manager_id') ?: null,
            'description' => mb_substr((string) Request::post('description', ''), 0, 500) ?: null,
            'sort_order'  => Request::int('sort_order', 0),
            'is_active'   => Request::bool('is_active'),
        ], 'id = :id', ['id' => $id]);
        Audit::log('update', 'department', $id, 'تعديل إدارة : ' . $dep['name']);
        flash('success', 'تم تحديث بيانات الإدارة.');
        redirect('departments/index');
    }

    public function delete(): void
    {
        Auth::requirePermission('departments.manage');
        Csrf::check();
        $id  = Request::int('id', 0);
        $dep = Database::first('SELECT * FROM departments WHERE id = ?', [$id]);
        if (!$dep) {
            redirect('departments/index');
        }
        $users = (int) Database::value('SELECT COUNT(*) FROM users WHERE department_id = ?', [$id]);
        $subs  = (int) Database::value('SELECT COUNT(*) FROM departments WHERE parent_id = ?', [$id]);
        if ($users > 0 || $subs > 0) {
            flash('error', "لا يمكن حذف الإدارة : بها {$users} موظف و {$subs} إدارة فرعية.");
            redirect('departments/index');
        }
        Database::delete('departments', 'id = ?', [$id]);
        Audit::log('delete', 'department', $id, 'حذف إدارة : ' . $dep['name']);
        flash('success', 'تم حذف الإدارة.');
        redirect('departments/index');
    }
}
