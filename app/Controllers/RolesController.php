<?php
/**
 * الأدوار والصلاحيات
 */
class RolesController
{
    public function index(): void
    {
        Auth::requirePermission('roles.manage');
        View::render('roles/index', [
            'title' => 'الأدوار والصلاحيات',
            'roles' => Database::all(
                'SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS users_count,
                        (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS perms_count
                   FROM roles r ORDER BY r.id'),
        ]);
    }

    public function edit(): void
    {
        Auth::requirePermission('roles.manage');
        $id   = Request::int('id', 0);
        $role = Database::first('SELECT * FROM roles WHERE id = ?', [$id]);
        if (!$role) {
            flash('error', 'الدور غير موجود.');
            redirect('roles/index');
        }
        $all = Database::all('SELECT * FROM permissions ORDER BY id');
        $grouped = [];
        foreach ($all as $p) {
            $grouped[$p['group_name']][] = $p;
        }

        View::render('roles/form', [
            'title'    => 'صلاحيات الدور : ' . $role['name'],
            'role'     => $role,
            'grouped'  => $grouped,
            'assigned' => array_map('intval', array_column(
                Database::all('SELECT permission_id FROM role_permissions WHERE role_id = ?', [$id]), 'permission_id')),
        ]);
    }

    public function store(): void
    {
        Auth::requirePermission('roles.manage');
        Csrf::check();
        $name = mb_substr((string) Request::post('name', ''), 0, 100);
        $slug = preg_replace('/[^a-z0-9_]/', '', strtolower((string) Request::post('slug', '')));
        if (mb_strlen($name) < 2 || $slug === '') {
            flash('error', 'اسم الدور والمعرف البرمجي مطلوبان.');
            redirect('roles/index');
        }
        if (Database::value('SELECT 1 FROM roles WHERE slug = ?', [$slug])) {
            flash('error', 'المعرف البرمجي مستخدم مسبقاً.');
            redirect('roles/index');
        }
        $id = Database::insert('roles', [
            'name'        => $name,
            'slug'        => $slug,
            'description' => mb_substr((string) Request::post('description', ''), 0, 255) ?: null,
        ]);
        Audit::log('create', 'role', $id, 'إضافة دور : ' . $name);
        flash('success', 'تمت إضافة الدور، حدد صلاحياته الآن.');
        redirect('roles/edit', ['id' => $id]);
    }

    public function update(): void
    {
        Auth::requirePermission('roles.manage');
        Csrf::check();

        $id   = Request::int('id', 0);
        $role = Database::first('SELECT * FROM roles WHERE id = ?', [$id]);
        if (!$role) {
            redirect('roles/index');
        }
        if ($role['slug'] === 'admin') {
            flash('error', 'لا يمكن تعديل صلاحيات دور مدير النظام.');
            redirect('roles/index');
        }

        $name = mb_substr((string) Request::post('name', $role['name']), 0, 100);
        Database::update('roles', [
            'name'        => $name,
            'description' => mb_substr((string) Request::post('description', ''), 0, 255) ?: null,
        ], 'id = :id', ['id' => $id]);

        Database::delete('role_permissions', 'role_id = ?', [$id]);
        $valid = array_map('intval', array_column(Database::all('SELECT id FROM permissions'), 'id'));
        foreach (Request::array('permissions') as $pid) {
            $pid = (int) $pid;
            if (in_array($pid, $valid, true)) {
                Database::run('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)', [$id, $pid]);
            }
        }
        Audit::log('update', 'role', $id, 'تعديل صلاحيات الدور : ' . $name);
        flash('success', 'تم حفظ صلاحيات الدور.');
        redirect('roles/index');
    }

    public function delete(): void
    {
        Auth::requirePermission('roles.manage');
        Csrf::check();
        $id   = Request::int('id', 0);
        $role = Database::first('SELECT * FROM roles WHERE id = ?', [$id]);
        if (!$role) {
            redirect('roles/index');
        }
        if ($role['is_system']) {
            flash('error', 'لا يمكن حذف أدوار النظام الأساسية.');
            redirect('roles/index');
        }
        if (Database::value('SELECT 1 FROM users WHERE role_id = ?', [$id])) {
            flash('error', 'لا يمكن حذف الدور لوجود مستخدمين مرتبطين به.');
            redirect('roles/index');
        }
        Database::delete('roles', 'id = ?', [$id]);
        Audit::log('delete', 'role', $id, 'حذف الدور : ' . $role['name']);
        flash('success', 'تم حذف الدور.');
        redirect('roles/index');
    }
}
