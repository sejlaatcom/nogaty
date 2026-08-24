<div class="card">
  <div class="card-body center" style="padding:3rem 1rem">
    <div style="font-size:3.5rem">🔍</div>
    <h2>الصفحة غير موجودة</h2>
    <p class="muted">المسار المطلوب غير معرّف في النظام<?php if (!empty($route)): ?> : <code><?= e($route) ?></code><?php endif; ?></p>
    <a href="<?= e(url('dashboard/index')) ?>" class="btn">العودة للوحة المعلومات</a>
  </div>
</div>
