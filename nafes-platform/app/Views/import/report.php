<?php if ($print): ?>
  <h2 class="h5 mb-3">بطاقات الدخول — <?= e($importer->title()) ?></h2>
  <div class="row g-2">
    <?php foreach ($credentials as $c): ?>
      <div class="col-4" style="break-inside:avoid"><div class="border rounded-3 p-2 small" style="border-style:dashed!important">
        <strong class="d-block"><?= e($c['name']) ?></strong>
        <?php if ($c['class']): ?><span class="text-muted"><?= e($c['class']) ?></span><br><?php endif; ?>
        اسم المستخدم: <b class="ltr"><?= e($c['username']) ?></b><br>كلمة المرور: <b class="ltr"><?= e($c['password']) ?></b><br>
        <span class="text-muted">رابط الدخول: <span class="ltr"><?= e(config('app.url') ?: url('/login')) ?></span></span>
      </div></div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
<div class="page-header"><div><h1><?= e($importer->title()) ?></h1><div class="sub">تقرير الاستيراد #<?= (int) $batch['id'] ?> — <?= e($batch['filename']) ?></div></div>
  <a class="btn btn-light" href="<?= e(url('/import/' . $kind)) ?>">استيراد ملف آخر</a></div>
<?php $step = 4; include __DIR__ . '/_steps.php'; ?>
<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="card stat-card"><span class="icon"><i class="bi bi-file-earmark-text"></i></span><div><div class="value"><?= (int) $batch['total'] ?></div><div class="label">إجمالي السجلات</div></div></div></div>
  <div class="col-md-3"><div class="card stat-card success"><span class="icon"><i class="bi bi-plus-circle"></i></span><div><div class="value"><?= (int) $batch['created'] ?></div><div class="label">أضيفت</div></div></div></div>
  <div class="col-md-3"><div class="card stat-card accent"><span class="icon"><i class="bi bi-arrow-repeat"></i></span><div><div class="value"><?= (int) $batch['updated'] ?></div><div class="label">حُدّثت</div></div></div></div>
  <div class="col-md-3"><div class="card stat-card danger"><span class="icon"><i class="bi bi-x-octagon"></i></span><div><div class="value"><?= (int) $batch['rejected'] ?></div><div class="label">رُفضت</div></div></div></div>
</div>
<?php if (isset($batch['duplicates']) && (int) $batch['duplicates'] > 0): ?><div class="alert alert-info">منها <?= (int) $batch['duplicates'] ?> سجل مكرر لم يُستورد.</div><?php endif; ?>

<?php if ($credentials): ?>
<div class="card mb-3">
  <div class="card-header"><h2><i class="bi bi-key"></i> بيانات الدخول الأولية (<?= count($credentials) ?>)</h2>
    <div class="d-flex gap-2">
      <a class="btn btn-sm btn-primary" target="_blank" href="<?= e(url('/import/' . $kind . '/report/' . $batch['id'], ['cards' => 1])) ?>"><i class="bi bi-printer"></i> طباعة البطاقات</a>
      <a class="btn btn-sm btn-soft" href="<?= e(url('/import/' . $kind . '/report/' . $batch['id'], ['download' => 'credentials'])) ?>"><i class="bi bi-download"></i> CSV</a>
    </div></div>
  <div class="card-body"><div class="alert alert-warning mb-0">تظهر كلمات المرور المولّدة في هذه الجلسة فقط ولا تُحفظ في قاعدة البيانات — اطبعها أو نزّلها الآن. سيُطلب من كل مستخدم تغييرها عند أول دخول.</div></div>
</div>
<?php endif; ?>

<?php $rejected = $batch['report']['rejected_rows'] ?? []; ?>
<?php if ($rejected): ?>
<div class="card">
  <div class="card-header"><h2>السجلات المرفوضة وأسبابها</h2>
    <a class="btn btn-sm btn-soft" href="<?= e(url('/import/' . $kind . '/report/' . $batch['id'], ['download' => 'rejected'])) ?>"><i class="bi bi-download"></i> تنزيل لتصحيحها</a></div>
  <div class="table-responsive"><table class="table table-sm">
    <thead><tr><th>السطر</th><th>البيانات</th><th>الأسباب</th></tr></thead>
    <tbody><?php foreach (array_slice($rejected, 0, 300) as $r): ?>
      <tr><td><?= (int) $r['line'] ?></td><td class="small"><?= e(str_limit(implode(' | ', array_filter(array_diff_key($r['record'], ['_line' => 1]), static fn ($v) => $v !== '')), 90)) ?></td>
        <td class="small text-danger"><?= e(implode('؛ ', $r['errors'])) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</div>
<?php endif; ?>
<?php endif; ?>
