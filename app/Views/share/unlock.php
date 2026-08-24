<div class="auth-card">
  <div class="auth-logo">🔒</div>
  <h1>رابط محمي</h1>
  <p class="sub">أدخل كلمة المرور للاطلاع على المحتوى المشارك</p>
  <form method="post" action="<?= e(url('share/unlock')) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <div class="field mb">
      <label>كلمة المرور</label>
      <input type="password" name="password" required autofocus dir="ltr">
    </div>
    <button class="btn block">فتح</button>
  </form>
</div>
