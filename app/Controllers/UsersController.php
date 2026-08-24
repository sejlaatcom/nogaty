<?php
/**
 * إدارة المستخدمين
 */
class UsersController
{
    public function index(): void
    {
        Auth::requirePermission('users.manage');
        $q    = Request::get('q');
        $role = Request::int('role');

        $where  = ['1=1'];
        $params = [];
        if ($q) {
            $where[] = likeAny(['u.full_name', 'u.username', 'u.email', 'u.job_title'], (string) $q, $params);
        }
        if ($role) {
            $where[] = 'u.role_id = :role';
            $params['role'] = $role;
        }

        $sql = 'SELECT u.*, r.name AS role_name, d.name AS department_name
                  FROM users u
                  JOIN roles r ON r.id = u.role_id
                  LEFT JOIN departments d ON d.id = u.department_id
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY u.full_name';

        View::render('users/index', [
            'title' => 'المستخدمون',
            'page'  => Paginator::make($sql, $params, Request::page()),
            'roles' => Database::all('SELECT id, name FROM roles ORDER BY id'),
            'q'     => $q,
            'role'  => $role,
        ]);
    }

    public function create(): void
    {
        Auth::requirePermission('users.manage');
        View::render('users/form', [
            'title' => 'إضافة مستخدم',
            'user'  => null,
            'lists' => $this->lists(),
        ]);
    }

    public function store(): void
    {
        Auth::requirePermission('users.manage');
        Csrf::check();

        $username = mb_substr((string) Request::post('username', ''), 0, 60);
        $password = (string) ($_POST['password'] ?? '');
        $fullName = mb_substr((string) Request::post('full_name', ''), 0, 150);

        $errors = [];
        if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $username)) {
            $errors[] = 'اسم المستخدم يجب أن يكون ٣ أحرف إنجليزية أو أرقام على الأقل.';
        }
        if (mb_strlen($fullName) < 3) {
            $errors[] = 'الاسم الكامل مطلوب.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'كلمة المرور يجب ألا تقل عن ٨ خانات.';
        }
        if (Database::value('SELECT 1 FROM users WHERE username = ?', [$username])) {
            $errors[] = 'اسم المستخدم مستخدم مسبقاً.';
        }
        if ($errors) {
            keepOld($_POST);
            flash('error', implode(' — ', $errors));
            redirect('users/create');
        }

        $id = Database::insert('users', [
            'username'             => $username,
            'password_hash'        => password_hash($password, PASSWORD_DEFAULT),
            'full_name'            => $fullName,
            'email'                => filter_var(Request::post('email'), FILTER_VALIDATE_EMAIL) ?: null,
            'phone'                => mb_substr((string) Request::post('phone', ''), 0, 40) ?: null,
            'job_title'            => mb_substr((string) Request::post('job_title', ''), 0, 120) ?: null,
            'national_id'          => mb_substr((string) Request::post('national_id', ''), 0, 30) ?: null,
            'department_id'        => Request::int('department_id') ?: null,
            'role_id'              => Request::int('role_id', 5),
            'is_active'            => Request::bool('is_active'),
            'must_change_password' => Request::bool('must_change_password'),
        ]);

        clearOld();
        Audit::log('create', 'user', $id, 'إضافة مستخدم : ' . $fullName);
        flash('success', 'تمت إضافة المستخدم بنجاح.');
        redirect('users/index');
    }

    public function edit(): void
    {
        Auth::requirePermission('users.manage');
        $id   = Request::int('id', 0);
        $user = Database::first('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$user) {
            flash('error', 'المستخدم غير موجود.');
            redirect('users/index');
        }
        View::render('users/form', [
            'title' => 'تعديل بيانات ' . $user['full_name'],
            'user'  => $user,
            'lists' => $this->lists(),
        ]);
    }

    public function update(): void
    {
        Auth::requirePermission('users.manage');
        Csrf::check();

        $id   = Request::int('id', 0);
        $user = Database::first('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$user) {
            flash('error', 'المستخدم غير موجود.');
            redirect('users/index');
        }

        $fields = [
            'full_name'            => mb_substr((string) Request::post('full_name', $user['full_name']), 0, 150),
            'email'                => filter_var(Request::post('email'), FILTER_VALIDATE_EMAIL) ?: null,
            'phone'                => mb_substr((string) Request::post('phone', ''), 0, 40) ?: null,
            'job_title'            => mb_substr((string) Request::post('job_title', ''), 0, 120) ?: null,
            'national_id'          => mb_substr((string) Request::post('national_id', ''), 0, 30) ?: null,
            'department_id'        => Request::int('department_id') ?: null,
            'role_id'              => Request::int('role_id', (int) $user['role_id']),
            'is_active'            => Request::bool('is_active'),
            'must_change_password' => Request::bool('must_change_password'),
            'updated_at'           => date('Y-m-d H:i:s'),
        ];

        // منع المستخدم من إيقاف حسابه أو تخفيض دوره بنفسه
        if ($id === Auth::id()) {
            $fields['is_active'] = 1;
            $fields['role_id']   = (int) $user['role_id'];
        }

        $password = (string) ($_POST['password'] ?? '');
        if ($password !== '') {
            if (strlen($password) < 8) {
                flash('error', 'كلمة المرور يجب ألا تقل عن ٨ خانات.');
                redirect('users/edit', ['id' => $id]);
            }
            $fields['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        Database::update('users', $fields, 'id = :id', ['id' => $id]);
        Audit::log('update', 'user', $id, 'تعديل بيانات المستخدم : ' . $fields['full_name']);
        flash('success', 'تم تحديث بيانات المستخدم.');
        redirect('users/index');
    }

    public function toggle(): void
    {
        Auth::requirePermission('users.manage');
        Csrf::check();
        $id = Request::int('id', 0);
        if ($id === Auth::id()) {
            flash('error', 'لا يمكنك إيقاف حسابك الشخصي.');
            redirect('users/index');
        }
        Database::run('UPDATE users SET is_active = 1 - is_active WHERE id = ?', [$id]);
        Audit::log('update', 'user', $id, 'تغيير حالة تفعيل المستخدم');
        flash('success', 'تم تغيير حالة الحساب.');
        redirect('users/index');
    }

    public function delete(): void
    {
        Auth::requirePermission('users.manage');
        Csrf::check();
        $id = Request::int('id', 0);
        if ($id === Auth::id()) {
            flash('error', 'لا يمكنك حذف حسابك الشخصي.');
            redirect('users/index');
        }
        $user = Database::first('SELECT * FROM users WHERE id = ?', [$id]);
        if ($user) {
            // إيقاف بدل الحذف للحفاظ على سلامة السجلات المرتبطة
            Database::update('users', ['is_active' => 0], 'id = :id', ['id' => $id]);
            Audit::log('delete', 'user', $id, 'إيقاف المستخدم : ' . $user['full_name']);
            flash('success', 'تم إيقاف حساب المستخدم (لا يُحذف نهائياً للحفاظ على سجل المعاملات).');
        }
        redirect('users/index');
    }

    private function lists(): array
    {
        return [
            'roles'       => Database::all('SELECT id, name FROM roles ORDER BY id'),
            'departments' => Tree::flatten(Tree::build(Database::all(
                'SELECT id, parent_id, name FROM departments WHERE is_active = 1 ORDER BY sort_order'))),
        ];
    }
}
