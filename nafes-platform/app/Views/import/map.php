<div class="page-header"><div><h1><?= e($importer->title()) ?></h1><div class="sub">الملف: <?= e($state['original']) ?></div></div>
  <a class="btn btn-light" href="<?= e(url('/import/' . $kind)) ?>">ملف آخر</a></div>
<?php $step = 2; include __DIR__ . '/_steps.php'; ?>

<?php if (count($state['sheets']) > 1): ?>
<form method="get" class="filter-bar d-flex gap-2 align-items-end">
  <input type="hidden" name="token" value="<?= e($state['token']) ?>">
  <div><label class="form-label">ورقة Excel</label>
    <select class="form-select" name="sheet" data-auto-submit><?php foreach ($state['sheets'] as $s): ?><option<?= selected($state['sheet'], $s) ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
</form>
<?php endif; ?>

<form method="post" action="<?= e(url('/import/' . $kind . '/map?token=' . $state['token'])) ?>">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-header"><h2>2) مطابقة الأعمدة</h2><span class="small text-muted">طابقنا الأعمدة تلقائياً حسب العناوين — راجعها وعدّل ما يلزم</span></div>
        <div class="card-body">
          <?php foreach ($importer->fields() as $key => [$label, $required, , $desc]): ?>
            <div class="row g-2 align-items-center mb-2">
              <label class="col-sm-4 form-label mb-0 <?= $required ? 'required' : '' ?>"><?= e($label) ?></label>
              <div class="col-sm-8"><select class="form-select <?= $mapping[$key] === null && $required ? 'is-invalid' : '' ?>" name="map[<?= e($key) ?>]">
                <option value="">— لا يوجد —</option>
                <?php foreach ($headers as $i => $h): ?><option value="<?= $i ?>"<?= selected($mapping[$key] ?? '', $i) ?>>العمود <?= $i + 1 ?>: <?= e(str_limit($h, 40) ?: '(بدون عنوان)') ?></option><?php endforeach; ?>
              </select></div>
            </div>
          <?php endforeach; ?>
          <div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="has_header" value="1" id="hh"<?= checked($state['header']) ?>>
            <label class="form-check-label" for="hh">الصف الأول يحتوي على عناوين الأعمدة</label></div>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <?php if ($importer->options()): ?>
      <div class="card mb-3">
        <div class="card-header"><h2>خيارات الاستيراد</h2></div>
        <div class="card-body">
          <?php foreach ($importer->options() as $key => [$label, $type, $default]): ?>
            <?php $val = $state['options'][$key] ?? $default; ?>
            <?php if ($type === 'bool'): ?>
              <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="opt_<?= e($key) ?>" value="1" id="o<?= e($key) ?>"<?= checked($val) ?>>
                <label class="form-check-label" for="o<?= e($key) ?>"><?= e($label) ?></label></div>
            <?php elseif ($type === 'class'): ?>
              <label class="form-label"><?= e($label) ?></label>
              <select class="form-select mb-2" name="opt_<?= e($key) ?>"><option value="">— بدون —</option>
                <?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($val, $c['id']) ?>><?= e(\App\Models\ClassRoom::label($c)) ?></option><?php endforeach; ?></select>
            <?php elseif (is_array($default)): ?>
              <label class="form-label"><?= e($label) ?></label>
              <select class="form-select mb-2" name="opt_<?= e($key) ?>"><?php foreach ($default as $k => $v): ?><option value="<?= e($k) ?>"<?= selected($state['options'][$key] ?? array_key_first($default), $k) ?>><?= e($v) ?></option><?php endforeach; ?></select>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <button class="btn btn-primary w-100 py-2"><i class="bi bi-search"></i> التحقق والمعاينة</button>
    </div>
  </div>
</form>

<div class="card mt-3">
  <div class="card-header"><h2>أول صفوف الملف</h2></div>
  <div class="table-responsive"><table class="table table-sm small">
    <thead><tr><?php foreach ($headers as $i => $h): ?><th>(<?= $i + 1 ?>) <?= e($h) ?></th><?php endforeach; ?></tr></thead>
    <tbody><?php foreach ($sample as $row): ?><tr><?php foreach ($row as $v): ?><td><?= e(str_limit($v, 50)) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
  </table></div>
</div>
