<div class="card">
  <div class="card-body center" style="padding:3rem 1rem">
    <div style="font-size:3.5rem">🔐</div>
    <h2>لا تملك الصلاحية</h2>
    <p class="muted">
      هذه الصفحة تتطلب صلاحية غير ممنوحة لدورك الحالي
      <?php if (!empty($permission)): ?><br><code><?= e($permission) ?></code><?php endif; ?>
    </p>
    <p class="small muted">راجع مدير النظام لمنحك الصلاحية اللازمة.</p>
    <a href="<?= e(url('dashboard/index')) ?>" class="btn">العودة للوحة المعلومات</a>
  </div>
</div>
