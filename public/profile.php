<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require APP_PATH . '/views/layout.php';

$user = Auth::requireLogin();
$area = match ($user['role']) { 'admin' => 'admin', 'student' => 'student', default => 'teacher' };

if (is_post()) {
    csrf_verify();

    if (input('action') === 'profile') {
        Database::update('users', [
            // اسم الطالب تعتمده الإدارة ولا يعدّله الطالب
            'name'  => $user['role'] === 'student' ? $user['name'] : ((string) input('name', '') ?: $user['name']),
            'email' => input('email') ?: null,
            'phone' => input('phone') ?: null,
        ], 'id = :id', ['id' => $user['id']]);
        flash('success', 'تم تحديث البيانات الشخصية.');
        redirect('profile.php');
    }

    if (input('action') === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if (!password_verify($current, $user['password_hash'])) {
            flash('error', 'كلمة المرور الحالية غير صحيحة.');
        } elseif (mb_strlen($new) < 6) {
            flash('error', 'كلمة المرور الجديدة يجب ألا تقل عن 6 أحرف.');
        } elseif ($new !== $confirm) {
            flash('error', 'كلمتا المرور غير متطابقتين.');
        } else {
            Database::update('users', [
                'password_hash'        => password_hash($new, PASSWORD_DEFAULT),
                'must_change_password' => 0,
            ], 'id = :id', ['id' => $user['id']]);
            Activity::log('password_change', 'users', (int) $user['id']);
            flash('success', 'تم تغيير كلمة المرور بنجاح.');
        }
        redirect('profile.php');
    }
}

page_start('حسابي', $area, 'profile');
?>
<div class="grid grid-2">
  <div class="card">
    <div class="card-head"><h2>البيانات الشخصية</h2></div>
    <div class="card-body">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="profile">
        <div class="field"><label>الاسم</label><input type="text" name="name" value="<?= h($user['name']) ?>" required <?= $user['role'] === 'student' ? 'disabled' : '' ?>></div>
        <div class="field"><label>اسم المستخدم</label><input type="text" value="<?= h($user['username']) ?>" disabled></div>
        <div class="field"><label>البريد الإلكتروني</label><input type="email" name="email" value="<?= h($user['email']) ?>"></div>
        <div class="field"><label><?= $user['role'] === 'student' ? 'جوال ولي الأمر' : 'الجوال' ?></label><input type="tel" name="phone" value="<?= h($user['phone']) ?>"></div>
        <button class="btn btn-primary" type="submit">حفظ</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2>تغيير كلمة المرور</h2></div>
    <div class="card-body">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="password">
        <div class="field"><label>كلمة المرور الحالية</label><input type="password" name="current_password" required></div>
        <div class="field"><label>كلمة المرور الجديدة</label><input type="password" name="new_password" required minlength="6"></div>
        <div class="field"><label>تأكيد كلمة المرور</label><input type="password" name="confirm_password" required minlength="6"></div>
        <button class="btn btn-primary" type="submit">تغيير كلمة المرور</button>
      </form>
    </div>
  </div>
</div>
<?php page_end($area); ?>
