<?php
$labels = ['create' => ['جديد', 'success'], 'update' => ['تحديث', 'primary'], 'reject' => ['مرفوض', 'danger']];
$fields = $importer->fields();
$cols = array_slice(array_keys($fields), 0, 6);
?>
<div class="page-header"><div><h1><?= e($importer->title()) ?></h1><div class="sub">الملف: <?= e($state['original']) ?><?= $state['sheet'] !== 'CSV' ? ' — الورقة: ' . e($state['sheet']) : '' ?></div></div>
  <a class="btn btn-light" href="<?= e(url('/import/' . $kind . '/map?token=' . $state['token'])) ?>"><i class="bi bi-arrow-right"></i> تعديل المطابقة</a></div>
<?php $step = 3; include __DIR__ . '/_steps.php'; ?>

<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="card stat-card success"><span class="icon"><i class="bi bi-plus-circle"></i></span><div><div class="value"><?= (int) ($summary['create'] ?? 0) ?></div><div class="label">سجل جديد</div></div></div></div>
  <div class="col-md-3"><div class="card stat-card"><span class="icon"><i class="bi bi-arrow-repeat"></i></span><div><div class="value"><?= (int) ($summary['update'] ?? 0) ?></div><div class="label">سيُحدّث</div></div></div></div>
  <div class="col-md-3"><div class="card stat-card danger"><span class="icon"><i class="bi bi-x-octagon"></i></span><div><div class="value"><?= (int) ($summary['reject'] ?? 0) ?></div><div class="label">مرفوض (لن يُستورد)</div></div></div></div>
  <div class="col-md-3 d-flex align-items-center">
    <form method="post" action="<?= e(url('/import/' . $kind . '/run?token=' . $state['token'])) ?>" class="w-100" data-once>
      <?= csrf_field() ?>
      <button class="btn btn-success w-100 py-3" <?= ($summary['create'] ?? 0) + ($summary['update'] ?? 0) === 0 ? 'disabled' : '' ?>
        data-confirm="استيراد <?= (int) (($summary['create'] ?? 0) + ($summary['update'] ?? 0)) ?> سجلاً صحيحاً؟ السجلات المرفوضة لن تُحفظ.">
        <i class="bi bi-check2-circle"></i> استيراد السجلات الصحيحة</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2>4) نتيجة التحقق</h2>
    <div class="btn-group btn-group-sm">
      <?php foreach (['all' => 'الكل', 'create' => 'جديد', 'update' => 'تحديث', 'reject' => 'مرفوض'] as $k => $v): ?>
        <a class="btn <?= $filter === $k ? 'btn-primary' : 'btn-light' ?>" href="<?= e(url('/import/' . $kind . '/preview', ['token' => $state['token'], 'show' => $k])) ?>"><?= $v ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="table-responsive"><table class="table table-sm align-middle">
    <thead><tr><th>السطر</th><th>الحالة</th><?php foreach ($cols as $c): ?><th><?= e($fields[$c][0]) ?></th><?php endforeach; ?><th>الملاحظات</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): [$l, $color] = $labels[$r['action']]; ?>
      <tr class="<?= $r['action'] === 'reject' ? 'table-danger' : '' ?>">
        <td><?= (int) $r['line'] ?></td>
        <td><span class="badge badge-soft-<?= $color ?>"><?= $l ?></span></td>
        <?php foreach ($cols as $c): ?><td class="small"><?= e(str_limit($r['record'][$c] ?? '', 40)) ?></td><?php endforeach; ?>
        <td class="small"><?php foreach ($r['errors'] as $er): ?><div class="text-danger">• <?= e($er) ?></div><?php endforeach; ?>
          <?php foreach ($r['warnings'] as $w): ?><div class="text-warning">• <?= e($w) ?></div><?php endforeach; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="9"><div class="empty-state">لا توجد سجلات في هذا التصنيف.</div></td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <?php if ($shownTotal > count($rows)): ?><div class="card-body small text-muted">يُعرض أول <?= count($rows) ?> سجل من <?= $shownTotal ?>.</div><?php endif; ?>
</div>
