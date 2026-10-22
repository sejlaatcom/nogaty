<div class="row g-3">
  <div class="col-lg-6">
    <form method="post" action="<?= e(url('/account')) ?>" class="card">
      <?= csrf_field() ?>
      <div class="card-header"><h2>بيانات الحساب</h2></div>
      <div class="card-body">
        <div class="mb-3"><label class="form-label">الاسم</label><input class="form-control" value="<?= e($user['full_name']) ?>" disabled></div>
        <div class="mb-3"><label class="form-label">اسم المستخدم</label><input class="form-control ltr" value="<?= e($user['username']) ?>" disabled></div>
        <?php if (!empty($profile['student_number'])): ?><div class="mb-3"><label class="form-label">الرقم المدرسي</label><input class="form-control" value="<?= e($profile['student_number']) ?>" disabled></div><?php endif; ?>
        <?php if (!empty($profile['employee_number'])): ?><div class="mb-3"><label class="form-label">معرف المعلم</label><input class="form-control" value="<?= e($profile['employee_number']) ?>" disabled></div><?php endif; ?>
        <div class="mb-3"><label class="form-label">البريد الإلكتروني</label><input type="email" class="form-control ltr" name="email" value="<?= e($user['email']) ?>"></div>
        <button class="btn btn-primary">حفظ</button>
      </div>
    </form>
  </div>
  <div class="col-lg-6">
    <div class="card"><div class="card-body">
      <p class="mb-1"><i class="bi bi-clock-history ms-1"></i>آخر دخول: <?= e(dt($user['last_login_at'])) ?></p>
      <p class="text-muted small mb-3">الاسم والبيانات الأساسية تعدّلها إدارة المدرسة.</p>
      <a class="btn btn-outline-primary" href="<?= e(url('/account/password')) ?>"><i class="bi bi-key"></i> تغيير كلمة المرور</a>
    </div></div>
  </div>
</div>
