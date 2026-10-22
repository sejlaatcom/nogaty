<?php $action = $user ? url('/users/' . $user['id']) : url('/users'); ?>
<div class="page-header"><h1><?= e($title) ?></h1><a class="btn btn-light" href="<?= e(url('/users')) ?>">رجوع</a></div>
<form method="post" action="<?= e($action) ?>" class="card" data-once>
  <?= csrf_field() ?>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label required">الاسم الكامل</label><input class="form-control" name="full_name" value="<?= e(old('full_name', $user['full_name'] ?? '')) ?>" required maxlength="150"></div>
      <div class="col-md-3"><label class="form-label required">اسم المستخدم</label><input class="form-control ltr" name="username" value="<?= e(old('username', $user['username'] ?? '')) ?>" required pattern="[A-Za-z0-9_.\-]{3,64}"></div>
      <div class="col-md-3"><label class="form-label">البريد الإلكتروني</label><input type="email" class="form-control ltr" name="email" value="<?= e(old('email', $user['email'] ?? '')) ?>"></div>
      <div class="col-md-6"><label class="form-label"><?= $user ? 'كلمة مرور جديدة (اتركها فارغة لعدم التغيير)' : 'كلمة المرور الأولية (تُولد تلقائياً إن تُركت فارغة)' ?></label>
        <input type="password" class="form-control ltr" name="password" autocomplete="new-password"></div>
      <div class="col-md-6 d-flex align-items-end"><div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="act"<?= checked((int) ($user['is_active'] ?? 1) === 1) ?>>
        <label class="form-check-label" for="act">الحساب نشط</label></div></div>
    </div>
    <h3 class="h6 mt-4">الأدوار</h3>
    <div class="row g-2">
      <?php foreach ($roles as $r): ?>
        <div class="col-md-6 col-lg-3"><label class="option-item h-100">
          <input class="form-check-input" type="checkbox" name="roles[]" value="<?= e($r['slug']) ?>"<?= checked(in_array($r['slug'], $userRoles, true)) ?>>
          <span><strong class="d-block"><?= e($r['name']) ?></strong><small class="text-muted"><?= e($r['description']) ?></small></span>
        </label></div>
      <?php endforeach; ?>
    </div>
    <p class="form-text mt-2">لإسناد الفصول والمواد للمعلم أو الوكيل استخدم صفحة «المعلمون والإسناد».</p>
  </div>
  <div class="card-footer bg-transparent"><button class="btn btn-primary"><i class="bi bi-check2"></i> حفظ</button></div>
</form>
