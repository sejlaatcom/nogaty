<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Models\User;
use App\Services\Audit;
use App\Validators\Validator;

/** إدارة حسابات المستخدمين والأدوار والصلاحيات */
final class UserController extends Controller
{
    public function index(Request $request): string
    {
        $role   = $request->str('role');
        $status = $request->str('status');
        $q      = $request->str('q');

        $where  = ['1 = 1'];
        $params = [];
        if ($role !== '') {
            $where[]  = 'EXISTS (SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id AND r.slug = ?)';
            $params[] = $role;
        } else {
            // الطلاب يُدارون من وحدة الطلاب
            $where[] = "NOT EXISTS (SELECT 1 FROM students s WHERE s.user_id = u.id)";
        }
        if ($status !== '') {
            $where[]  = 'u.is_active = ?';
            $params[] = $status === 'active' ? 1 : 0;
        }
        if ($q !== '') {
            $where[] = '(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%");
        }

        $users = DB::all(
            'SELECT u.*, (SELECT GROUP_CONCAT(r.name ORDER BY r.id SEPARATOR \'، \') FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id) AS role_names
               FROM users u WHERE ' . implode(' AND ', $where) . ' ORDER BY u.is_active DESC, u.full_name LIMIT 500',
            $params
        );

        return $this->view('users/index', [
            'title' => 'المستخدمون والصلاحيات', 'users' => $users,
            'roles' => DB::all('SELECT * FROM roles ORDER BY id'),
            'filters' => compact('role', 'status', 'q'),
        ]);
    }

    public function create(Request $request): string
    {
        return $this->view('users/form', ['title' => 'إضافة مستخدم', 'user' => null, 'userRoles' => [], 'roles' => $this->staffRoles()]);
    }

    public function edit(Request $request): string
    {
        $user = $this->notFound(User::find((int) $request->param('id')));
        return $this->view('users/form', [
            'title' => 'تعديل مستخدم', 'user' => $user, 'userRoles' => User::roleSlugs((int) $user['id']), 'roles' => $this->staffRoles(),
        ]);
    }

    public function store(Request $request): never
    {
        $this->save($request, null);
    }

    public function update(Request $request): never
    {
        $this->save($request, $this->notFound(User::find((int) $request->param('id'))));
    }

    private function save(Request $request, ?array $user): never
    {
        $id     = (int) ($user['id'] ?? 0);
        $back   = $id ? "/users/$id/edit" : '/users/create';
        $errors = Validator::make($_POST, [
            'full_name' => 'required|max:150', 'username' => 'required|username', 'email' => 'nullable|email|max:150',
        ], ['full_name' => 'الاسم الكامل', 'username' => 'اسم المستخدم', 'email' => 'البريد الإلكتروني']);

        $username = mb_strtolower($request->str('username'));
        if (!$errors && User::usernameTaken($username, $id)) {
            $errors[] = 'اسم المستخدم مستخدم مسبقاً.';
        }
        $roles = array_values(array_intersect(array_map('strval', $request->array('roles')), array_column($this->staffRoles(), 'slug')));
        if (!$roles) {
            $errors[] = 'اختر دوراً واحداً على الأقل.';
        }
        // منع المدير من إزالة دور الإدارة عن نفسه أو تعطيل حسابه
        if ($id === Auth::id() && (!in_array('admin', $roles, true) || !$request->bool('is_active'))) {
            $errors[] = 'لا يمكنك إزالة صلاحية الإدارة عن حسابك أو تعطيله.';
        }
        $password = (string) ($_POST['password'] ?? '');
        if ($password !== '') {
            $errors = array_merge($errors, User::passwordErrors($password));
        }
        if ($errors) {
            back_with_errors($errors, $back);
        }

        // الحفاظ على دور الطالب إن وُجد (لا يُدار من هنا)
        if ($user && in_array('student', User::roleSlugs($id), true)) {
            $roles[] = 'student';
        }

        if ($user) {
            DB::update('users', [
                'full_name' => $request->str('full_name'), 'username' => $username,
                'email' => $request->str('email') ?: null, 'is_active' => $request->bool('is_active') ? 1 : 0,
            ], 'id = :id', ['id' => $id]);
            User::setRoles($id, $roles);
            if ($password !== '') {
                User::setPassword($id, $password, true);
            }
            Audit::log('user_update', 'users', $id, ['roles' => $roles]);
            flash('success', 'تم حفظ بيانات المستخدم.');
        } else {
            $plain = $password !== '' ? $password : random_password(10);
            $id = User::create([
                'username' => $username, 'full_name' => $request->str('full_name'), 'email' => $request->str('email'),
                'password' => $plain, 'is_active' => $request->bool('is_active') ? 1 : 0, 'must_change_password' => 1,
            ], $roles);
            Audit::log('user_create', 'users', $id, ['roles' => $roles]);
            flash('success', "تم إنشاء الحساب. اسم المستخدم: $username — كلمة المرور الأولية: $plain (يُطلب تغييرها عند أول دخول)");
        }
        redirect('/users');
    }

    public function resetPassword(Request $request): never
    {
        $user = $this->notFound(User::find((int) $request->param('id')));
        $plain = random_password(10);
        User::setPassword((int) $user['id'], $plain, true);
        Audit::log('password_reset', 'users', (int) $user['id']);
        flash('success', "كلمة المرور المؤقتة لـ {$user['full_name']}: $plain — يُطلب تغييرها عند الدخول.");
        back('/users');
    }

    public function toggle(Request $request): never
    {
        $user = $this->notFound(User::find((int) $request->param('id')));
        if ((int) $user['id'] === Auth::id()) {
            flash('danger', 'لا يمكنك تعطيل حسابك.');
            back('/users');
        }
        DB::update('users', ['is_active' => (int) $user['is_active'] === 1 ? 0 : 1], 'id = :id', ['id' => $user['id']]);
        Audit::log('user_toggle', 'users', (int) $user['id']);
        flash('success', (int) $user['is_active'] === 1 ? 'تم تعطيل الحساب.' : 'تم تفعيل الحساب.');
        back('/users');
    }

    /** مصفوفة الأدوار والصلاحيات */
    public function roles(Request $request): string
    {
        $roles = DB::all('SELECT r.*, (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id) AS users_count FROM roles r ORDER BY r.id');
        $perms = DB::all('SELECT * FROM permissions ORDER BY group_name, id');
        $map = [];
        foreach (DB::all('SELECT role_id, permission_id FROM role_permissions') as $row) {
            $map[(int) $row['role_id']][(int) $row['permission_id']] = true;
        }
        return $this->view('users/roles', ['title' => 'الأدوار والصلاحيات', 'roles' => $roles, 'perms' => $perms, 'map' => $map]);
    }

    public function saveRoles(Request $request): never
    {
        $matrix = $request->array('perm');
        $roles  = DB::all('SELECT * FROM roles');
        DB::transaction(static function () use ($roles, $matrix) {
            foreach ($roles as $role) {
                if ($role['slug'] === 'admin') {
                    continue; // مدير النظام يملك كل الصلاحيات دائماً
                }
                DB::delete('role_permissions', 'role_id = ?', [$role['id']]);
                foreach (array_keys((array) ($matrix[$role['id']] ?? [])) as $permId) {
                    DB::run('INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT ?, id FROM permissions WHERE id = ?', [$role['id'], (int) $permId]);
                }
            }
        });
        Audit::log('roles_update', 'roles');
        flash('success', 'تم حفظ صلاحيات الأدوار.');
        redirect('/users/roles');
    }

    private function staffRoles(): array
    {
        return DB::all("SELECT * FROM roles WHERE slug <> 'student' ORDER BY id");
    }
}
