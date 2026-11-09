<div class="row g-3">
  <?php foreach ([['students', 'الطلاب', 'people'], ['teachers', 'المعلمون', 'person-workspace'], ['classes', 'الفصول', 'grid-3x3-gap'], ['questions', 'الأسئلة المنشورة', 'collection']] as [$k, $l, $i]): ?>
    <div class="col-6 col-lg-3"><div class="card stat-card"><span class="icon"><i class="bi bi-<?= $i ?>"></i></span><div><div class="value"><?= num($stats[$k]) ?></div><div class="label"><?= $l ?></div></div></div></div>
  <?php endforeach; ?>
</div>
