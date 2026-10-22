<?php $steps = ['رفع الملف', 'مطابقة الأعمدة', 'المعاينة والتحقق', 'التقرير']; ?>
<div class="d-flex gap-2 mb-3 flex-wrap">
  <?php foreach ($steps as $i => $label): ?>
    <span class="badge rounded-pill <?= $i + 1 === $step ? 'bg-primary' : ($i + 1 < $step ? 'badge-soft-success' : 'badge-soft-secondary') ?> px-3 py-2">
      <?= $i + 1 < $step ? '<i class="bi bi-check2"></i>' : $i + 1 ?> <?= $label ?></span>
    <?php if ($i < 3): ?><i class="bi bi-chevron-left text-muted align-self-center"></i><?php endif; ?>
  <?php endforeach; ?>
</div>
