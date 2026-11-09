<div class="auth-page">
  <section class="auth-hero">
    <div>
      <div class="d-flex align-items-center gap-3 mb-5">
        <span class="auth-logo mb-0" style="background:rgba(255,255,255,.15);color:#fff">
          <?php if (setting('logo') !== ''): ?><img src="<?= e(url('/media/logo')) ?>" alt=""><?php else: ?><i class="bi bi-mortarboard-fill"></i><?php endif; ?>
        </span>
        <div><strong class="d-block"><?= e(setting('school_name')) ?></strong><small class="opacity-75"><?= e(setting('school_section')) ?></small></div>
      </div>
      <h1 class="mb-3"><?= e(setting('platform_title')) ?></h1>
      <p class="opacity-75 mb-4">تدريب منظم على مهارات القراءة والرياضيات والعلوم، باختبارات تشخيصية وتدريبية ومحاكية، وتقارير تبيّن نقاط القوة والمهارات التي تحتاج إلى دعم.</p>
      <ul class="list-unstyled features">
        <li><i class="bi bi-check2-circle ms-2"></i>اختبارات إلكترونية بتصحيح فوري وحفظ تلقائي للإجابات</li>
        <li><i class="bi bi-check2-circle ms-2"></i>تحليل الإتقان لكل مجال ومهارة</li>
        <li><i class="bi bi-check2-circle ms-2"></i>خطط علاجية وإثرائية مبنية على النتائج</li>
      </ul>
    </div>
    <small class="opacity-75"><?= e(setting('education_dept')) ?></small>
  </section>
  <section class="auth-form">
    <form class="auth-card" method="post" action="<?= e(url('/login')) ?>" data-once>
      <?= csrf_field() ?>
      <div class="auth-logo d-lg-none">
        <?php if (setting('logo') !== ''): ?><img src="<?= e(url('/media/logo')) ?>" alt=""><?php else: ?><i class="bi bi-mortarboard-fill"></i><?php endif; ?>
      </div>
      <h2 class="h4 mb-1">تسجيل الدخول</h2>
      <p class="text-muted mb-4 small d-lg-none"><?= e(setting('school_name')) ?></p>
      <p class="text-muted mb-4 small d-none d-lg-block">للطلاب والمعلمين وإدارة المدرسة</p>
      <?php include APP_PATH . '/Views/partials/flash.php'; ?>
      <div class="mb-3">
        <label class="form-label" for="username">اسم المستخدم</label>
        <div class="input-group"><span class="input-group-text"><i class="bi bi-person"></i></span>
          <input class="form-control ltr text-start" id="username" name="username" value="<?= e(old('username')) ?>" required autofocus autocomplete="username"></div>
      </div>
      <div class="mb-4">
        <label class="form-label" for="password">كلمة المرور</label>
        <div class="input-group"><span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input type="password" class="form-control ltr text-start" id="password" name="password" required autocomplete="current-password">
          <button class="btn btn-outline-secondary" type="button" onclick="var p=document.getElementById('password');p.type=p.type==='password'?'text':'password'" aria-label="إظهار كلمة المرور"><i class="bi bi-eye"></i></button></div>
      </div>
      <button class="btn btn-primary w-100 py-2" type="submit">دخول</button>
      <p class="text-muted small mt-4 mb-0">نسيت كلمة المرور؟ راجع معلمك أو مسؤول المنصة لإعادة تعيينها.</p>
    </form>
  </section>
</div>
