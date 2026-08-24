<div class="auth-card">
  <div class="auth-logo">🗂️</div>
  <h1><?= e(setting('app_name', 'نظام الأرشفة الإلكترونية')) ?></h1>
  <p class="sub"><?= e(setting('school_name', 'المدرسة')) ?><?php if (setting('education_admin')): ?><br><?= e(setting('education_admin')) ?><?php endif; ?></p>

  <form method="post" action="<?= e(url('auth/authenticate')) ?>" data-once>
    <?= Csrf::field() ?>
    <div class="field mb">
      <label for="username">اسم المستخدم</label>
      <input type="text" id="username" name="username" required autofocus autocomplete="username" dir="ltr">
    </div>
    <div class="field mb">
      <label for="password">كلمة المرور</label>
      <input type="password" id="password" name="password" required autocomplete="current-password" dir="ltr">
    </div>
    <button type="submit" class="btn block">دخول النظام</button>
  </form>

  <div class="auth-foot">
    الدخول متاح لمنسوبي المدرسة المصرّح لهم فقط<br>
    جميع عمليات الدخول والاطلاع مسجّلة في سجل الأحداث
  </div>
</div>
