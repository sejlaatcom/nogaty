<div class="row g-2">
  <?php foreach ($cards as $c): ?>
    <div class="col-4" style="break-inside:avoid"><div class="border rounded-3 p-2 small" style="border-style:dashed!important">
      <strong class="d-block"><?= e($c['name']) ?></strong>
      <span class="text-muted"><?= e($c['class']) ?> — <?= e($c['number']) ?></span><br>
      اسم المستخدم: <b class="ltr"><?= e($c['username']) ?></b><br>كلمة المرور: <b class="ltr"><?= e($c['password']) ?></b><br>
      <span class="text-muted">يُطلب تغيير كلمة المرور عند أول دخول</span>
    </div></div>
  <?php endforeach; ?>
</div>
