<?php use App\Services\Levels; use App\Helpers\Chart; ?>
<div class="page-header"><div><h1>تقدمي في المهارات</h1><div class="sub">القراءة والرياضيات والعلوم — حسب نتائج الاختبارات والتدريب</div></div></div>
<div class="card mb-3"><div class="card-header"><h2>تطور نتائجي</h2></div><div class="card-body">
  <?php if (count($trend) >= 2): ?>
    <?php $series = []; foreach ($trend as $t) { $series[$t['subject_name'] ?? 'شامل'][] = ['x' => dt($t['created_at'], false), 'y' => (float) $t['percent']]; }
      $labels = array_values(array_unique(array_map(static fn ($t) => dt($t['created_at'], false), $trend)));
      $ds = []; foreach ($series as $name => $pts) { $map = array_column($pts, 'y', 'x'); $ds[] = ['label' => $name, 'data' => array_map(static fn ($l) => $map[$l] ?? null, $labels), 'spanGaps' => true]; } ?>
    <?= Chart::render('line', $labels, $ds, 260, true) ?>
  <?php else: ?><?= Chart::empty('أدِّ اختبارين على الأقل لرؤية تطور نتائجك.') ?><?php endif; ?>
</div></div>
<?php foreach ($tree as $s): ?>
<div class="card mb-3"><div class="card-header"><h2><?= e($s['name']) ?></h2></div><div class="card-body">
  <?php foreach ($s['domains'] as $d): ?>
    <div class="d-flex justify-content-between align-items-center mt-2"><h3 class="h6 mb-1"><?= e($d['name']) ?></h3><?= $d['percent'] !== null ? Levels::badge($d['percent']) . ' <b>' . pct($d['percent']) . '</b>' : '<small class="text-muted">لم تُقس</small>' ?></div>
    <?php foreach ($d['skills'] as $k): ?>
      <div class="skill-row"><span class="small"><?= e(($k['code'] ? $k['code'] . ' — ' : '') . $k['name']) ?></span>
        <?= $k['percent'] !== null ? Levels::bar($k['percent']) : '<div class="progress"></div>' ?><b class="small"><?= pct($k['percent']) ?></b></div>
    <?php endforeach; ?>
  <?php endforeach; ?>
</div></div>
<?php endforeach; ?>
<p class="disclaimer"><?= e(setting('report_disclaimer')) ?></p>
