<?php use App\Services\Levels; use App\Helpers\Chart; ?>
<div class="card mb-3" style="background:linear-gradient(135deg,var(--brand-dark),var(--brand) 60%,var(--accent));color:#fff;border:none">
  <div class="card-body p-4 d-flex flex-wrap justify-content-between gap-3 align-items-center">
    <div><h1 class="h4 text-white mb-1">أهلاً <?= e(explode(' ', $student['full_name'])[0]) ?> 👋</h1>
      <div class="opacity-75"><?= e(($student['grade_name'] ?? '') . ' / ' . ($student['class_name'] ?? '')) ?> — <?= e(setting('school_name')) ?></div></div>
    <?php if ($extra): ?>
      <div class="d-flex gap-3 text-center">
        <div><div class="fs-3 fw-bold"><?= num($extra['points']) ?></div><small class="opacity-75">نقطة</small></div>
        <div><div class="fs-3 fw-bold"><?= (int) $extra['badges'] ?></div><small class="opacity-75">شارة</small></div>
        <div><div class="fs-3 fw-bold"><?= (int) $extra['week_done'] ?>/<?= (int) $extra['week_goal'] ?></div><small class="opacity-75">هدف الأسبوع</small></div>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card h-100"><div class="card-header"><h2><i class="bi bi-journal-check text-primary"></i> بانتظارك</h2><a class="small" href="<?= e(url('/student/tests')) ?>">كل الاختبارات</a></div>
      <div class="list-group list-group-flush">
        <?php foreach (array_slice($open, 0, 5) as $t): ?>
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2" href="<?= e(url('/student/tests/' . $t['id'])) ?>">
            <span><strong><?= e($t['title']) ?></strong><br><small class="text-muted"><?= e($t['subject_name'] ?? 'شامل') ?><?= $t['available_until'] ? ' · يُغلق ' . e(dt($t['available_until'])) : '' ?></small></span>
            <span class="btn btn-sm btn-<?= $t['state'] === 'in_progress' ? 'warning' : 'primary' ?>"><?= $t['state'] === 'in_progress' ? 'متابعة' : 'ابدأ' ?></span></a>
        <?php endforeach; ?>
        <?php if (!$open): ?><div class="empty-state py-4"><i class="bi bi-emoji-smile"></i>لا توجد اختبارات بانتظارك الآن.<br><a href="<?= e(url('/student/practice')) ?>">تدرّب ذاتياً</a></div><?php endif; ?>
      </div></div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100"><div class="card-header"><h2><i class="bi bi-graph-up text-primary"></i> مستواي في المواد</h2></div><div class="card-body">
      <?php foreach ($subjects as $s): ?>
        <div class="skill-row"><span><i class="bi bi-<?= e($s['icon'] ?: 'book') ?>" style="color:<?= e($s['color'] ?: '#0b5394') ?>"></i> <?= e($s['name']) ?></span>
          <?= Levels::bar($s['test_avg'] !== null ? (float) $s['test_avg'] : null) ?><b><?= pct($s['test_avg'] !== null ? (float) $s['test_avg'] : null) ?></b></div>
      <?php endforeach; ?>
      <p class="small text-muted mt-2 mb-0">متوسط نتائجك في الاختبارات التدريبية.</p>
    </div></div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card h-100"><div class="card-header"><h2><i class="bi bi-clock-history text-primary"></i> آخر النتائج</h2><a class="small" href="<?= e(url('/student/results')) ?>">الكل</a></div>
      <div class="list-group list-group-flush">
        <?php foreach ($results as $r): ?>
          <a class="list-group-item list-group-item-action d-flex justify-content-between" href="<?= e(url('/student/results/' . $r['attempt_id'])) ?>">
            <span><?= e($r['title']) ?><br><small class="text-muted"><?= e($r['subject_name'] ?? '') ?> · <?= e(dt($r['created_at'], false)) ?></small></span>
            <span class="text-end"><b><?= pct((float) $r['percent']) ?></b><br><?= Levels::badge($r['level']) ?></span></a>
        <?php endforeach; ?>
        <?php if (!$results): ?><div class="empty-state py-4">لا توجد نتائج بعد.</div><?php endif; ?>
      </div></div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100"><div class="card-header"><h2><i class="bi bi-bullseye text-danger"></i> مهارات أحتاج لتحسينها</h2><a class="small" href="<?= e(url('/student/progress')) ?>">تقدمي</a></div><div class="card-body">
      <?php foreach ($weak as $w): ?>
        <div class="d-flex justify-content-between align-items-center border-bottom py-2 gap-2">
          <span class="small"><?= e($w['skill_name']) ?><br><span class="text-muted"><?= e($w['subject_name']) ?> · <?= pct((float) $w['percent']) ?></span></span>
          <form method="post" action="<?= e(url('/student/practice/start')) ?>"><?= csrf_field() ?><input type="hidden" name="subject" value="<?= (int) $w['subject_id'] ?>"><input type="hidden" name="skill" value="<?= (int) $w['skill_id'] ?>">
            <button class="btn btn-sm btn-soft">تدرّب</button></form>
        </div>
      <?php endforeach; ?>
      <?php if (!$weak): ?><p class="text-muted mb-0">ستظهر هنا المهارات التي تحتاج تدريباً بعد أدائك للاختبارات.</p><?php endif; ?>
    </div></div>
  </div>
</div>
