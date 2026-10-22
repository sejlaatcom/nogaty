<div class="row justify-content-center">
  <div class="col-lg-6">
    <?php if ($forced): ?>
      <div class="alert alert-warning"><i class="bi bi-shield-lock ms-1"></i>لحماية حسابك، يجب تغيير كلمة المرور الأولية قبل المتابعة.</div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/account/password')) ?>" class="card" data-once>
      <?= csrf_field() ?>
      <div class="card-header"><h2>تغيير كلمة المرور</h2></div>
      <div class="card-body">
        <div class="mb-3"><label class="form-label required">كلمة المرور الحالية</label><input type="password" class="form-control ltr" name="current_password" required autocomplete="current-password"></div>
        <div class="mb-3"><label class="form-label required">كلمة المرور الجديدة</label><input type="password" class="form-control ltr" name="password" minlength="8" required autocomplete="new-password">
          <div class="form-text">8 أحرف على الأقل وتحتوي على أحرف وأرقام.</div></div>
        <div class="mb-3"><label class="form-label required">تأكيد كلمة المرور</label><input type="password" class="form-control ltr" name="password_confirmation" minlength="8" required autocomplete="new-password"></div>
        <button class="btn btn-primary">حفظ كلمة المرور</button>
      </div>
    </form>
  </div>
</div>
