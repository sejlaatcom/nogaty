<?php $isEdit = !empty($user); ?>
<div class="page-head">
  <h1><?= e($title) ?></h1>
  <span class="spacer"></span>
  <a class="btn ghost" href="<?= e(url('users/index')) ?>">↩︎ رجوع</a>
</div>

<form method="post" action="<?= e($isEdit ? url('users/update') : url('users/store')) ?>" class="card" data-once>
  <?= Csrf::field() ?>
  <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $user['id'] ?>"><?php endif; ?>
  <div class="card-head"><h3>بيانات المستخدم</h3></div>
  <div class="card-body">
    <div class="form-grid">
      <div class="field">
        <label>الاسم الكامل <span class="req">*</span></label>
        <input type="text" name="full_name" required maxlength="150" value="<?= e(old('full_name', $user['full_name'] ?? '')) ?>">
      </div>
      <div class="field">
        <label>اسم الدخول <span class="req">*</span></label>
        <input type="text" name="username" dir="ltr" <?= $isEdit ? 'readonly' : 'required' ?>
               value="<?= e(old('username', $user['username'] ?? '')) ?>" pattern="[A-Za-z0-9._\-]{3,60}">
        <span class="hint">أحرف إنجليزية وأرقام فقط<?= $isEdit ? ' — لا يمكن تعديله' : '' ?></span>
      </div>
      <div class="field">
        <label>كلمة المرور <?= $isEdit ? '' : '<span class="req">*</span>' ?></label>
        <input type="password" name="password" dir="ltr" <?= $isEdit ? '' : 'required' ?> minlength="8" autocomplete="new-password">
        <span class="hint"><?= $isEdit ? 'اتركه فارغاً للإبقاء على كلمة المرور الحالية' : '٨ خانات فأكثر' ?></span>
      </div>
      <div class="field">
        <label>المسمى الوظيفي</label>
        <input type="text" name="job_title" maxlength="120" value="<?= e(old('job_title', $user['job_title'] ?? '')) ?>"
               placeholder="معلم / وكيل / كاتب">
      </div>
      <div class="field">
        <label>الإدارة</label>
        <select name="department_id">
          <option value="">— غير محدد —</option>
          <?php foreach ($lists['departments'] as $d): ?>
            <option value="<?= (int) $d['id'] ?>" <?= (int) ($user['department_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['_label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>الدور والصلاحيات <span class="req">*</span></label>
        <select name="role_id" required <?= $isEdit && (int) $user['id'] === Auth::id() ? 'disabled' : '' ?>>
          <?php foreach ($lists['roles'] as $r): ?>
            <option value="<?= (int) $r['id'] ?>" <?= (int) ($user['role_id'] ?? 5) === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($isEdit && (int) $user['id'] === Auth::id()): ?>
          <span class="hint">لا يمكنك تغيير دورك بنفسك</span>
        <?php endif; ?>
      </div>
      <div class="field"><label>البريد الإلكتروني</label>
        <input type="email" name="email" dir="ltr" value="<?= e(old('email', $user['email'] ?? '')) ?>"></div>
      <div class="field"><label>الجوال</label>
        <input type="tel" name="phone" dir="ltr" value="<?= e(old('phone', $user['phone'] ?? '')) ?>"></div>
      <div class="field"><label>رقم الهوية</label>
        <input type="text" name="national_id" dir="ltr" value="<?= e(old('national_id', $user['national_id'] ?? '')) ?>"></div>
      <div class="field full">
        <label class="check">
          <input type="checkbox" name="is_active" value="1" <?= !$isEdit || $user['is_active'] ? 'checked' : '' ?>>
          الحساب نشط ويمكنه الدخول
        </label>
        <label class="check">
          <input type="checkbox" name="must_change_password" value="1" <?= !empty($user['must_change_password']) ? 'checked' : '' ?>>
          إجباره على تغيير كلمة المرور عند أول دخول
        </label>
      </div>
    </div>
  </div>
  <div class="card-foot">
    <div class="form-actions">
      <button class="btn">💾 حفظ</button>
      <a class="btn outline" href="<?= e(url('users/index')) ?>">إلغاء</a>
    </div>
  </div>
</form>
