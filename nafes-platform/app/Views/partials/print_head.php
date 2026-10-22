<div class="print-head">
  <div class="small">المملكة العربية السعودية<br>وزارة التعليم<br><?= e(setting('education_dept')) ?></div>
  <div class="center">
    <h1><?= e($reportTitle ?? $title ?? '') ?></h1>
    <div class="small"><?= e(setting('school_name')) ?> — <?= e(setting('school_section')) ?></div>
    <?php if (!empty($reportMeta)): ?><div class="small text-muted"><?= e($reportMeta) ?></div><?php endif; ?>
  </div>
  <?php if (setting('logo') !== ''): ?><img src="<?= e(url('/media/logo')) ?>" alt=""><?php else: ?><div style="width:70px"></div><?php endif; ?>
</div>
<div class="d-flex justify-content-between small text-muted mb-3">
  <span>تاريخ التقرير: <?= e(date('Y/m/d H:i')) ?></span>
  <span>العام الدراسي: <?= e(\App\Models\Year::current()['name'] ?? '') ?></span>
</div>
