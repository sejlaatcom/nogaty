<div class="page-header"><div><h1>النصوص القرائية</h1><div class="sub">نصوص أدبية ومعلوماتية تتبعها مجموعة أسئلة</div></div>
  <?php if ($canEdit): ?><a class="btn btn-primary" href="<?= e(url('/passages/create')) ?>"><i class="bi bi-plus-lg"></i> نص جديد</a><?php endif; ?></div>
<form class="filter-bar row g-2" method="get">
  <div class="col-md-3"><select class="form-select" name="subject" data-auto-submit><option value="">كل المواد</option><?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected($filters['subject'], $s['id']) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><select class="form-select" name="grade" data-auto-submit><option value="">كل الصفوف</option><?php foreach ($grades as $g): ?><option value="<?= (int) $g['id'] ?>"<?= selected($filters['grade'], $g['id']) ?>><?= e($g['name']) ?></option><?php endforeach; ?></select></div>
</form>
<div class="row g-3">
  <?php foreach ($passages as $p): ?>
    <div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-body d-flex flex-column">
      <h3 class="h6"><?= e($p['title']) ?></h3>
      <div class="small text-muted mb-2"><?= e($p['subject_name'] . ' — ' . $p['grade_name']) ?> · <?= (int) $p['q_count'] ?> سؤال</div>
      <p class="small flex-grow-1"><?= e(str_limit($p['body'], 170)) ?></p>
      <div class="d-flex gap-2">
        <a class="btn btn-sm btn-soft" href="<?= e(url('/passages/' . $p['id'] . '/edit')) ?>">فتح</a>
        <a class="btn btn-sm btn-light" href="<?= e(url('/questions/create', ['passage' => $p['id'], 'subject' => $p['subject_id'], 'grade' => $p['grade_id']])) ?>">+ سؤال على النص</a>
      </div>
    </div></div></div>
  <?php endforeach; ?>
  <?php if (!$passages): ?><div class="col-12"><div class="card"><div class="empty-state"><i class="bi bi-book"></i>لا توجد نصوص بعد.</div></div></div><?php endif; ?>
</div>
