<?php use App\Services\Levels; ?>
<div class="page-header"><div><h1>التدريب الذاتي</h1><div class="sub">جلسات قصيرة (<?= (int) $size ?> أسئلة) بدون وقت، مع التصحيح والتفسير فوراً — تظهر لك أولاً الأسئلة التي لم تتقنها</div></div></div>
<?php foreach ($subjects as $s): ?>
<div class="card mb-3">
  <div class="card-header"><h2><i class="bi bi-<?= e($s['icon'] ?: 'book') ?>" style="color:<?= e($s['color'] ?: '#0b5394') ?>"></i> <?= e($s['name']) ?> <small class="text-muted">(<?= (int) $s['available'] ?> سؤال)</small></h2>
    <form method="post" action="<?= e(url('/student/practice/start')) ?>" data-once><?= csrf_field() ?><input type="hidden" name="subject" value="<?= (int) $s['id'] ?>">
      <button class="btn btn-primary"><i class="bi bi-lightning-charge"></i> تدريب شامل على المادة</button></form></div>
  <div class="card-body">
    <?php foreach ($s['domains'] as $d): $skills = array_filter($d['skills'], static fn ($k) => (int) $k['available'] > 0); if (!$skills) { continue; } ?>
      <h3 class="h6 text-muted mt-2"><?= e($d['name']) ?></h3>
      <?php foreach ($skills as $k): $m = $mastery[(int) $k['id']] ?? null; ?>
        <div class="d-flex align-items-center gap-3 border-bottom py-2 flex-wrap">
          <div class="flex-grow-1" style="min-width:220px"><span class="small"><?= e($k['name']) ?></span>
            <?php if ($m): ?><div class="d-flex align-items-center gap-2 mt-1" style="max-width:340px"><div class="flex-grow-1"><?= Levels::bar($m['percent']) ?></div><small><?= pct($m['percent']) ?></small><?= Levels::badge($m['percent']) ?></div>
            <?php else: ?><div class="small text-muted">لم تتدرب عليها بعد</div><?php endif; ?></div>
          <form method="post" action="<?= e(url('/student/practice/start')) ?>" data-once><?= csrf_field() ?><input type="hidden" name="subject" value="<?= (int) $s['id'] ?>"><input type="hidden" name="skill" value="<?= (int) $k['id'] ?>">
            <button class="btn btn-sm btn-soft">تدرّب (<?= (int) $k['available'] ?>)</button></form>
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>
<?php if (!$subjects): ?><div class="card"><div class="empty-state"><i class="bi bi-lightning"></i>لا توجد أسئلة تدريبية منشورة لصفك بعد.</div></div><?php endif; ?>
