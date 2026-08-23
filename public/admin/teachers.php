<?php
declare(strict_types=1);
define('APP_AREA', 'admin');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';

Auth::requireAdmin();

if (is_post()) {
    csrf_verify();
    $action = (string) input('action', '');
    $id     = input_int('id');

    if ($action === 'save') {
        $name     = (string) input('name', '');
        $username = strtolower((string) input('username', ''));
        $password = (string) ($_POST['password'] ?? '');
        $role     = input('role') === 'admin' ? 'admin' : 'teacher';

        if ($name === '' || $username === '') {
            flash('error', 'الاسم واسم المستخدم مطلوبان.');
            redirect('admin/teachers.php');
        }
        if (!preg_match('/^[a-z0-9_.-]{3,64}$/', $username)) {
            flash('error', 'اسم المستخدم يجب أن يكون بالإنجليزية (أحرف وأرقام) وبطول 3 أحرف فأكثر.');
            redirect('admin/teachers.php');
        }

        $duplicate = Database::value('SELECT id FROM users WHERE username = ? AND id <> ?', [$username, $id]);
        if ($duplicate) {
            flash('error', 'اسم المستخدم مستخدم مسبقاً.');
            redirect('admin/teachers.php' . ($id ? '?edit=' . $id : ''));
        }

        $data = [
            'name'        => $name,
            'username'    => $username,
            'email'       => input('email') ?: null,
            'phone'       => input('phone') ?: null,
            'national_id' => input('national_id') ?: null,
            'job_title'   => input('job_title') ?: ($role === 'admin' ? 'إدارة' : 'معلم'),
            'role'        => $role,
            'is_active'   => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($id) {
            if ($password !== '') {
                $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                $data['must_change_password'] = 1;
            }
            Database::update('users', $data, 'id = :uid', ['uid' => $id]);
            Activity::log('user_update', 'users', $id, $name);
            flash('success', 'تم تحديث بيانات ' . $name . '.');
        } else {
            $plain = $password !== '' ? $password : random_password();
            $data['password_hash'] = password_hash($plain, PASSWORD_DEFAULT);
            $data['must_change_password'] = 1;
            $newId = Database::insert('users', $data);
            Activity::log('user_create', 'users', $newId, $name);
            flash('success', 'تمت إضافة ' . $name . ' — اسم المستخدم: ' . $username . ' وكلمة المرور: ' . $plain);
        }
        redirect('admin/teachers.php');
    }

    if ($action === 'reset_password' && $id) {
        $plain = random_password();
        Database::update('users', [
            'password_hash'        => password_hash($plain, PASSWORD_DEFAULT),
            'must_change_password' => 1,
        ], 'id = :uid', ['uid' => $id]);
        Activity::log('password_reset', 'users', $id);
        flash('success', 'كلمة المرور الجديدة: ' . $plain);
        redirect('admin/teachers.php');
    }

    if ($action === 'toggle' && $id) {
        Database::run('UPDATE users SET is_active = 1 - is_active WHERE id = ? AND id <> ?', [$id, Auth::id()]);
        flash('success', 'تم تغيير حالة الحساب.');
        redirect('admin/teachers.php');
    }

    if ($action === 'delete' && $id) {
        if ($id === Auth::id()) {
            flash('error', 'لا يمكنك حذف حسابك الحالي.');
        } else {
            $plans = (int) Database::value('SELECT COUNT(*) FROM plans WHERE teacher_id = ?', [$id], 0);
            if ($plans > 0) {
                Database::update('users', ['is_active' => 0], 'id = :uid', ['uid' => $id]);
                flash('warning', 'للمعلم خطط مسجلة، تم إيقاف الحساب بدلاً من حذفه للحفاظ على السجلات.');
            } else {
                Database::delete('users', 'id = ?', [$id]);
                Activity::log('user_delete', 'users', $id);
                flash('success', 'تم حذف الحساب.');
            }
        }
        redirect('admin/teachers.php');
    }
}

$editId = input_int('edit');
$edit   = $editId ? Database::first('SELECT * FROM users WHERE id = ?', [$editId]) : null;

$users = Database::all(
    "SELECT u.*, (SELECT COUNT(*) FROM assignments a WHERE a.teacher_id = u.id) AS assignments_count
       FROM users u ORDER BY u.role DESC, u.is_active DESC, u.name"
);

page_start('المعلمون والمستخدمون', 'admin', 'teachers');
?>

<div class="card">
  <div class="card-head"><h2><?= $edit ? 'تعديل بيانات: ' . h($edit['name']) : 'إضافة معلم / مستخدم' ?></h2>
    <?php if ($edit): ?><a class="btn btn-sm btn-outline" href="<?= h(url('admin/teachers.php')) ?>">إلغاء التعديل</a><?php endif; ?>
  </div>
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <div class="grid grid-3">
        <div class="field"><label>الاسم الكامل *</label><input type="text" name="name" value="<?= h($edit['name'] ?? '') ?>" required></div>
        <div class="field"><label>اسم المستخدم (إنجليزي) *</label><input type="text" name="username" value="<?= h($edit['username'] ?? '') ?>" required></div>
        <div class="field">
          <label>كلمة المرور <?= $edit ? '(اتركها فارغة لعدم التغيير)' : '(تُنشأ تلقائياً إن تُركت فارغة)' ?></label>
          <input type="text" name="password" autocomplete="new-password">
        </div>
        <div class="field"><label>الجوال</label><input type="tel" name="phone" value="<?= h($edit['phone'] ?? '') ?>"></div>
        <div class="field"><label>البريد الإلكتروني</label><input type="email" name="email" value="<?= h($edit['email'] ?? '') ?>"></div>
        <div class="field"><label>رقم الهوية</label><input type="text" name="national_id" value="<?= h($edit['national_id'] ?? '') ?>"></div>
        <div class="field"><label>المسمى الوظيفي</label><input type="text" name="job_title" value="<?= h($edit['job_title'] ?? 'معلم') ?>"></div>
        <div class="field">
          <label>الصلاحية</label>
          <select name="role">
            <option value="teacher" <?= ($edit['role'] ?? 'teacher') === 'teacher' ? 'selected' : '' ?>>معلم</option>
            <option value="admin"   <?= ($edit['role'] ?? '') === 'admin' ? 'selected' : '' ?>>إدارة المدرسة</option>
          </select>
        </div>
        <div class="field">
          <label>الحالة</label>
          <label class="flex"><input type="checkbox" name="is_active" style="width:auto"
                 <?= (int) ($edit['is_active'] ?? 1) === 1 ? 'checked' : '' ?>> الحساب نشط</label>
        </div>
      </div>
      <button class="btn btn-primary" type="submit"><?= $edit ? 'حفظ التعديلات' : 'إضافة المستخدم' ?></button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h2>قائمة المستخدمين (<?= count($users) ?>)</h2>
    <div class="flex">
      <a class="btn btn-sm btn-outline" href="<?= h(url('admin/transfer.php#import-teachers')) ?>">استيراد من ملف</a>
      <a class="btn btn-sm btn-outline" href="<?= h(url('admin/transfer.php?export=teachers&format=xlsx')) ?>">تصدير Excel</a>
    </div>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr><th>الاسم</th><th>اسم المستخدم</th><th>الصلاحية</th><th>الجوال</th><th>المواد المسندة</th><th>آخر دخول</th><th>الحالة</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
          <tr>
            <td><strong><?= h($user['name']) ?></strong><br><small><?= h($user['job_title'] ?: '') ?></small></td>
            <td><?= h($user['username']) ?></td>
            <td><?= $user['role'] === 'admin' ? '<span class="badge badge-teal">إدارة</span>' : 'معلم' ?></td>
            <td class="text-muted"><?= h($user['phone'] ?: '—') ?></td>
            <td><?= (int) $user['assignments_count'] ?></td>
            <td class="text-muted nowrap"><?= h($user['last_login_at'] ?: 'لم يدخل بعد') ?></td>
            <td><?= (int) $user['is_active'] === 1 ? '<span class="badge badge-green">نشط</span>' : '<span class="badge badge-gray">موقوف</span>' ?></td>
            <td class="actions">
              <a class="btn btn-sm btn-outline" href="<?= h(url('admin/teachers.php?edit=' . (int) $user['id'])) ?>">تعديل</a>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                <button class="btn btn-sm btn-outline" name="action" value="reset_password"
                        data-confirm="إنشاء كلمة مرور جديدة لهذا المستخدم؟">كلمة مرور جديدة</button>
                <button class="btn btn-sm btn-outline" name="action" value="toggle">
                  <?= (int) $user['is_active'] === 1 ? 'إيقاف' : 'تفعيل' ?>
                </button>
                <button class="btn btn-sm btn-danger" name="action" value="delete"
                        data-confirm="حذف هذا المستخدم نهائياً؟">حذف</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php page_end('admin'); ?>
