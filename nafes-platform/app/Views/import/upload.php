<div class="page-header"><div><h1><?= e($importer->title()) ?></h1><div class="sub">Excel (.xlsx) أو CSV بترميز UTF-8</div></div></div>
<?php $step = 1; include __DIR__ . '/_steps.php'; ?>
<div class="row g-3">
  <div class="col-lg-7">
    <form class="card" method="post" enctype="multipart/form-data" action="<?= e(url('/import/' . $kind)) ?>" data-once>
      <?= csrf_field() ?>
      <div class="card-header"><h2>1) اختر الملف</h2>
        <div class="d-flex gap-2">
          <a class="btn btn-sm btn-soft" href="<?= e(url('/import/' . $kind . '/template')) ?>"><i class="bi bi-file-earmark-excel"></i> قالب Excel</a>
          <a class="btn btn-sm btn-light" href="<?= e(url('/import/' . $kind . '/template', ['format' => 'csv'])) ?>">قالب CSV</a>
        </div></div>
      <div class="card-body">
        <input type="file" class="form-control form-control-lg mb-3" name="file" accept=".xlsx,.csv,text/csv" required>
        <ul class="small text-muted mb-3">
          <?php foreach ($importer->instructions() as $line): ?><li><?= e($line) ?></li><?php endforeach; ?>
          <li>الحد الأقصى 3000 سجل و10 ميجابايت للملف الواحد.</li>
        </ul>
        <button class="btn btn-primary"><i class="bi bi-upload"></i> رفع ومتابعة</button>
      </div>
    </form>
  </div>
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header"><h2>الحقول</h2></div>
      <ul class="list-group list-group-flush small">
        <?php foreach ($importer->fields() as [$label, $required, , $desc]): ?>
          <li class="list-group-item"><strong class="<?= $required ? 'required' : '' ?>"><?= e($label) ?></strong><?php if ($desc): ?> — <span class="text-muted"><?= e($desc) ?></span><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>
<?php if ($batches): ?>
<div class="card mt-3">
  <div class="card-header"><h2>عمليات الاستيراد السابقة</h2></div>
  <div class="table-responsive"><table class="table table-sm">
    <thead><tr><th>التاريخ</th><th>الملف</th><th>بواسطة</th><th>الإجمالي</th><th>مضاف</th><th>محدّث</th><th>مرفوض</th><th></th></tr></thead>
    <tbody><?php foreach ($batches as $b): ?>
      <tr><td><?= e(dt($b['created_at'])) ?></td><td><?= e($b['filename']) ?></td><td><?= e($b['user_name'] ?? '—') ?></td><td><?= (int) $b['total'] ?></td>
        <td class="text-success"><?= (int) $b['created'] ?></td><td><?= (int) $b['updated'] ?></td><td class="text-danger"><?= (int) $b['rejected'] ?></td>
        <td><a class="btn btn-sm btn-light" href="<?= e(url('/import/' . $kind . '/report/' . $b['id'])) ?>">التقرير</a></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</div>
<?php endif; ?>
