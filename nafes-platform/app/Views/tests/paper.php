<?php use App\Services\QuestionRenderer as R; ?>
<div class="mb-3 small">
  <div class="d-flex flex-wrap gap-4"><span>اسم الطالب: ..............................................</span><span>الفصل: ..............</span><span>الدرجة: ........ / <?= num((float) $test['total_points'], 1) ?></span></div>
  <?php if ($test['duration_minutes']): ?><div class="mt-1">الزمن: <?= (int) $test['duration_minutes'] ?> دقيقة · عدد الأسئلة: <?= count($questions) ?></div><?php endif; ?>
  <?php if ($test['description']): ?><div class="mt-2 border rounded p-2"><?= nl2br(e($test['description'])) ?></div><?php endif; ?>
</div>
<?php $lastPassage = null; foreach ($questions as $i => $q): $snap = $q['snap']; ?>
  <div class="paper-q mb-3" style="break-inside:avoid">
    <?php if (!empty($snap['passage']) && $lastPassage !== ($snap['passage']['id'] ?? null)): $lastPassage = $snap['passage']['id'] ?? null; ?><?= R::passage($snap['passage']) ?><?php endif; ?>
    <div class="d-flex gap-2"><b><?= $i + 1 ?>.</b><div class="flex-grow-1"><?= R::stem($snap) ?><?= R::body($snap, $key ? 'preview' : 'paper') ?></div><small class="text-muted text-nowrap">(<?= num((float) $q['points'], 2) ?>)</small></div>
  </div>
<?php endforeach; ?>
<p class="small text-muted mt-4"><?= e(\App\Services\Settings::get('report_disclaimer')) ?></p>
