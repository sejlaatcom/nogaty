<div class="page-head"><h1>🔑 تغيير كلمة المرور</h1></div>

<div class="card" style="max-width:520px">
  <div class="card-body">
    <form method="post" action="<?= e(url('profile/changePassword')) ?>" data-once>
      <?= Csrf::field() ?>
      <div class="field mb">
        <label>كلمة المرور الحالية <span class="req">*</span></label>
        <input type="password" name="current_password" required dir="ltr" autocomplete="current-password">
      </div>
      <div class="field mb">
        <label>كلمة المرور الجديدة <span class="req">*</span></label>
        <input type="password" name="new_password" required minlength="8" dir="ltr" autocomplete="new-password">
        <span class="hint">٨ خانات فأكثر — يُفضّل مزج الأحرف والأرقام والرموز</span>
      </div>
      <div class="field mb">
        <label>تأكيد كلمة المرور الجديدة <span class="req">*</span></label>
        <input type="password" name="confirm_password" required minlength="8" dir="ltr" autocomplete="new-password">
      </div>
      <div class="form-actions">
        <button class="btn">حفظ كلمة المرور</button>
        <a class="btn outline" href="<?= e(url('profile/index')) ?>">إلغاء</a>
      </div>
    </form>
  </div>
</div>
