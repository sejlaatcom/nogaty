<?php
use App\Services\Levels; use App\Services\TestEngine as TE;
$stateBadge = ['in_progress' => ['جارٍ — أكمل الاختبار', 'warning'], 'available' => ['متاح الآن', 'success'], 'upcoming' => ['قادم', 'primary'], 'done' => ['مكتمل', 'secondary'], 'missed' => ['فات موعده', 'danger']];
[$sl, $sc] = $stateBadge[$t['state']];
$last = $t['attempts'] ? end($t['attempts']) : null;
?>
<div class="card h-100"><div class="card-body d-flex flex-column">
  <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
    <span class="badge badge-soft-primary"><?= e(TE::TYPES[$t['type']] ?? '') ?></span>
    <span class="badge badge-soft-<?= $sc ?>"><?= $sl ?></span>
  </div>
  <h3 class="h6"><?= e($t['title']) ?></h3>
  <div class="small text-muted mb-2">
    <i class="bi bi-book"></i> <?= e($t['subject_name'] ?? 'شامل') ?> · <i class="bi bi-list-ol"></i> <?= (int) $t['questions_count'] ?> سؤالاً
    · <i class="bi bi-stopwatch"></i> <?= (int) $t['duration_minutes'] ? (int) $t['duration_minutes'] . ' دقيقة' : 'بدون وقت' ?>
  </div>
  <?php if ($t['available_until'] && in_array($t['state'], ['available', 'in_progress', 'upcoming'], true)): ?>
    <div class="small text-danger mb-2"><i class="bi bi-alarm"></i> يُغلق <?= e(dt($t['available_until'])) ?></div><?php endif; ?>
  <?php if ($t['state'] === 'upcoming'): ?><div class="small mb-2"><i class="bi bi-calendar-event"></i> يُتاح <?= e(dt($t['available_from'])) ?></div><?php endif; ?>
  <div class="mt-auto d-flex gap-2 align-items-center flex-wrap">
    <?php if (in_array($t['state'], ['available', 'in_progress'], true)): ?>
      <a class="btn btn-<?= $t['state'] === 'in_progress' ? 'warning' : 'primary' ?>" href="<?= e(url('/student/tests/' . $t['id'])) ?>">
        <?= $t['state'] === 'in_progress' ? 'متابعة' : ($t['attempts'] ? 'محاولة جديدة' : 'ابدأ') ?></a>
    <?php endif; ?>
    <?php if ($last && $last['status'] !== 'in_progress'): ?>
      <a class="btn btn-light" href="<?= e(url('/student/results/' . $last['id'])) ?>">النتيجة <?= $last['percent'] !== null && TE::resultVisible($last + ['result_visibility' => $t['result_visibility'], 'test_status' => $t['status'], 'available_until' => $t['available_until'], 'results_released' => $t['results_released']]) ? pct((float) $last['percent']) : '' ?></a>
    <?php endif; ?>
  </div>
</div></div>
