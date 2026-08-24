<?php
/**
 * الملف الشخصي للمستخدم
 */
class ProfileController
{
    public function index(): void
    {
        Auth::requireLogin();
        $id = Auth::id();

        View::render('profile/index', [
            'title'    => 'ملفي الشخصي',
            'user'     => Auth::user(),
            'stats'    => [
                'created'   => (int) Database::value('SELECT COUNT(*) FROM transactions WHERE created_by = ? AND deleted_at IS NULL', [$id]),
                'documents' => (int) Database::value('SELECT COUNT(*) FROM documents WHERE created_by = ? AND deleted_at IS NULL', [$id]),
                'pending'   => (int) Database::value("SELECT COUNT(*) FROM transaction_routings WHERE to_user_id = ? AND status IN ('pending','seen')", [$id]),
                'done'      => (int) Database::value("SELECT COUNT(*) FROM transaction_routings WHERE to_user_id = ? AND status = 'done'", [$id]),
            ],
            'lastLogins' => Database::all(
                "SELECT * FROM audit_logs WHERE user_id = ? AND action = 'login' ORDER BY id DESC LIMIT 10", [$id]),
            'permissions' => Database::all(
                'SELECT p.name, p.group_name FROM permissions p
                   JOIN role_permissions rp ON rp.permission_id = p.id
                  WHERE rp.role_id = ? ORDER BY p.group_name, p.id', [Auth::user()['role_id']]),
        ]);
    }

    public function update(): void
    {
        Auth::requireLogin();
        Csrf::check();

        Database::update('users', [
            'full_name'  => mb_substr((string) Request::post('full_name', Auth::user()['full_name']), 0, 150),
            'email'      => filter_var(Request::post('email'), FILTER_VALIDATE_EMAIL) ?: null,
            'phone'      => mb_substr((string) Request::post('phone', ''), 0, 40) ?: null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => Auth::id()]);

        Audit::log('update', 'user', Auth::id(), 'تحديث الملف الشخصي');
        flash('success', 'تم تحديث بياناتك الشخصية.');
        redirect('profile/index');
    }

    public function password(): void
    {
        Auth::requireLogin();
        View::render('profile/password', ['title' => 'تغيير كلمة المرور']);
    }

    public function changePassword(): void
    {
        Auth::requireLogin();
        Csrf::check();

        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        $user = Database::first('SELECT password_hash FROM users WHERE id = ?', [Auth::id()]);
        if (!$user || !password_verify($current, $user['password_hash'])) {
            flash('error', 'كلمة المرور الحالية غير صحيحة.');
            redirect('profile/password');
        }
        if (strlen($new) < 8) {
            flash('error', 'كلمة المرور الجديدة يجب ألا تقل عن ٨ خانات.');
            redirect('profile/password');
        }
        if ($new !== $confirm) {
            flash('error', 'كلمتا المرور غير متطابقتين.');
            redirect('profile/password');
        }

        Database::update('users', [
            'password_hash'        => password_hash($new, PASSWORD_DEFAULT),
            'must_change_password' => 0,
            'updated_at'           => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => Auth::id()]);

        Audit::log('update', 'user', Auth::id(), 'تغيير كلمة المرور');
        flash('success', 'تم تغيير كلمة المرور بنجاح.');
        redirect('profile/index');
    }
}
