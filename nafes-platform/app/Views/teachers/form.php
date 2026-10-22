<?php use App\Models\Teacher; $t = $teacher; ?>
<div class="page-header"><h1><?= e($title) ?></h1><a class="btn btn-light" href="<?= e(url($t ? '/teachers/' . $t['id'] : '/teachers')) ?>">رجوع</a></div>
<form method="post" action="<?= e(url($t ? '/teachers/' . $t['id'] : '/teachers')) ?>" class="card" data-once>
  <?= csrf_field() ?>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label required">الاسم الكامل</label><input class="form-control" name="full_name" value="<?= e(old('full_name', $t['full_name'] ?? '')) ?>" required></div>
      <div class="col-md-3"><label class="form-label required">معرف المعلم</label><input class="form-control" name="employee_number" value="<?= e(old('employee_number', $t['employee_number'] ?? '')) ?>" required></div>
      <div class="col-md-3"><label class="form-label required">اسم المستخدم</label><input class="form-control ltr" name="username" value="<?= e(old('username', $t['username'] ?? '')) ?>" required pattern="[A-Za-z0-9_.\-]{3,64}"></div>
      <div class="col-md-4"><label class="form-label">البريد الإلكتروني</label><input type="email" class="form-control ltr" name="email" value="<?= e(old('email', $t['email'] ?? '')) ?>"></div>
      <div class="col-md-4"><label class="form-label">التخصص</label><input class="form-control" name="specialization" value="<?= e(old('specialization', $t['specialization'] ?? '')) ?>"></div>
      <div class="col-md-4"><label class="form-label"><?= $t ? 'كلمة مرور جديدة (اختياري)' : 'كلمة المرور الأولية (تُولد إن تُركت فارغة)' ?></label><input type="password" class="form-control ltr" name="password" autocomplete="new-password"></div>
      <div class="col-md-8"><label class="form-label required">الدور الوظيفي</label>
        <div class="d-flex gap-3 flex-wrap"><?php foreach (Teacher::ROLES as $k => $v): ?>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="roles[]" value="<?= $k ?>" id="r<?= $k ?>"<?= checked(in_array($k, $roles, true)) ?>><label class="form-check-label" for="r<?= $k ?>"><?= $v ?></label></div>
        <?php endforeach; ?></div></div>
      <div class="col-md-4"><div class="form-check form-switch mt-4"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ia"<?= checked((int) ($t['is_active'] ?? 1)) ?>><label class="form-check-label" for="ia">الحساب نشط</label></div></div>
    </div>
  </div>
  <div class="card-footer bg-transparent"><button class="btn btn-primary"><i class="bi bi-check2"></i> حفظ</button>
    <span class="text-muted small ms-2">بعد الحفظ تنتقل لصفحة الإسناد لتحديد الفصول والمواد.</span></div>
</form>
