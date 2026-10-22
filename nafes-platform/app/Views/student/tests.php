<?php
$groups = ['in_progress' => 'اختبارات بدأتها ولم تسلّمها', 'available' => 'متاحة الآن', 'upcoming' => 'قادمة', 'done' => 'مكتملة', 'missed' => 'فات موعدها'];
$by = [];
foreach ($tests as $t) { $by[$t['state']][] = $t; }
?>
<div class="page-header"><div><h1>اختباراتي</h1><div class="sub">الاختبارات المسندة إليك من معلميك</div></div>
  <a class="btn btn-soft" href="<?= e(url('/student/practice')) ?>"><i class="bi bi-lightning-charge"></i> تدريب ذاتي</a></div>
<?php if (!$tests): ?><div class="card"><div class="empty-state"><i class="bi bi-journal-check"></i>لا توجد اختبارات مسندة إليك حالياً. يمكنك التدرب ذاتياً على المهارات.</div></div><?php endif; ?>
<?php foreach ($groups as $state => $label): if (empty($by[$state])) { continue; } ?>
  <h2 class="h6 text-muted mt-4 mb-2"><?= $label ?> (<?= count($by[$state]) ?>)</h2>
  <div class="row g-3"><?php foreach ($by[$state] as $t): ?><div class="col-md-6 col-xl-4"><?php include __DIR__ . '/_test_card.php'; ?></div><?php endforeach; ?></div>
<?php endforeach; ?>
