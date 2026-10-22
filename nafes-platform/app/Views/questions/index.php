<?php use App\Services\QuestionBank as QB; ?>
<div class="page-header">
  <div><h1>بنك الأسئلة</h1><div class="sub">
    <?php foreach (QB::STATUSES as $k => [$l, $c]): ?><span class="badge badge-soft-<?= $c ?> me-1"><?= $l ?>: <?= (int) ($counts[$k] ?? 0) ?></span><?php endforeach; ?></div></div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (can('questions.export')): ?>
      <a class="btn btn-light" href="<?= e(url('/questions/export', array_filter($filters, 'is_scalar'))) ?>"><i class="bi bi-download"></i> تصدير Excel</a>
    <?php endif; ?>
    <?php if (can('questions.import')): ?><a class="btn btn-soft" href="<?= e(url('/import/questions')) ?>"><i class="bi bi-file-earmark-arrow-up"></i> استيراد</a><?php endif; ?>
    <?php if (can('questions.create')): ?>
      <div class="dropdown">
        <button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-plus-lg"></i> سؤال جديد</button>
        <ul class="dropdown-menu"><?php foreach (QB::TYPES as $k => $v): ?>
          <li><a class="dropdown-item" href="<?= e(url('/questions/create', ['type' => $k, 'subject' => $filters['subject'] ?: null, 'grade' => $filters['grade'] ?: null, 'skill' => $filters['skill'] ?: null])) ?>"><?= e($v) ?></a></li>
        <?php endforeach; ?></ul>
      </div>
    <?php endif; ?>
  </div>
</div>

<form class="filter-bar row g-2 align-items-end" method="get">
  <div class="col-md-2"><label class="form-label">الصف</label><select class="form-select" name="grade" data-auto-submit><option value="">الكل</option><?php foreach ($grades as $g): ?><option value="<?= (int) $g['id'] ?>"<?= selected($filters['grade'], $g['id']) ?>><?= e($g['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><label class="form-label">المادة</label><select class="form-select" name="subject" data-auto-submit><option value="">الكل</option><?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected($filters['subject'], $s['id']) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><label class="form-label">المجال</label><select class="form-select" name="domain" data-auto-submit <?= $domains ? '' : 'disabled' ?>><option value="">الكل</option><?php foreach ($domains as $d): ?><option value="<?= (int) $d['id'] ?>"<?= selected($filters['domain'], $d['id']) ?>><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><label class="form-label">المهارة</label><select class="form-select" name="skill" data-auto-submit <?= $skills ? '' : 'disabled' ?>><option value="">الكل</option><?php foreach ($skills as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected($filters['skill'], $s['id']) ?>><?= e(($s['code'] ? $s['code'] . ' — ' : '') . str_limit($s['name'], 50)) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><label class="form-label">بحث في النص أو رقم السؤال</label><input class="form-control" name="q" value="<?= e($filters['q']) ?>"></div>
  <div class="col-md-2"><label class="form-label">النوع</label><select class="form-select" name="type" data-auto-submit><option value="">الكل</option><?php foreach (QB::TYPES as $k => $v): ?><option value="<?= $k ?>"<?= selected($filters['type'], $k) ?>><?= $v ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><label class="form-label">الصعوبة</label><select class="form-select" name="difficulty" data-auto-submit><option value="">الكل</option><?php foreach (QB::DIFFICULTIES as $k => $v): ?><option value="<?= $k ?>"<?= selected($filters['difficulty'], $k) ?>><?= $v ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><label class="form-label">المستوى المعرفي</label><select class="form-select" name="cognitive" data-auto-submit><option value="">الكل</option><?php foreach (QB::COGNITIVE as $k => $v): ?><option value="<?= $k ?>"<?= selected($filters['cognitive'], $k) ?>><?= $v ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><label class="form-label">الحالة</label><select class="form-select" name="status" data-auto-submit><option value="">غير المؤرشفة</option><?php foreach (QB::STATUSES as $k => [$v]): ?><option value="<?= $k ?>"<?= selected($filters['status'], $k) ?>><?= $v ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="mine" value="1" id="mine"<?= checked($filters['mine']) ?> data-auto-submit><label class="form-check-label" for="mine">أسئلتي فقط</label></div></div>
  <div class="col-md-2"><button class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> بحث</button></div>
</form>

<form method="post" action="<?= e(url('/questions/status')) ?>" class="card">
  <?= csrf_field() ?>
  <?php if (can('questions.review', 'questions.edit_any')): ?>
  <div class="table-tools">
    <span class="small text-muted">للمحدد:</span>
    <button class="btn btn-sm btn-success" name="status" value="published" data-confirm="نشر الأسئلة المحددة؟ تصبح متاحة للاختبارات."><i class="bi bi-check2-circle"></i> نشر</button>
    <button class="btn btn-sm btn-light" name="status" value="review">إعادة للمراجعة</button>
    <button class="btn btn-sm btn-light" name="status" value="draft">مسودة</button>
    <button class="btn btn-sm btn-light" name="status" value="archived" data-confirm="أرشفة الأسئلة المحددة؟">أرشفة</button>
  </div>
  <?php endif; ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead><tr><th><input class="form-check-input" type="checkbox" data-check-all="ids[]"></th><th>#</th><th style="min-width:280px">السؤال</th><th>المادة / الصف</th><th>المهارة</th><th>النوع</th><th>الصعوبة</th><th>الاستخدام</th><th>الحالة</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($result['rows'] as $q): [$sl, $sc] = QB::STATUSES[$q['status']]; ?>
        <tr>
          <td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int) $q['id'] ?>"></td>
          <td class="text-muted"><?= (int) $q['id'] ?></td>
          <td><a href="<?= e(url('/questions/' . $q['id'])) ?>" class="text-body"><?= e(str_limit($q['stem'], 110)) ?></a>
            <?php if ($q['passage_title']): ?><br><small class="text-muted"><i class="bi bi-book"></i> <?= e($q['passage_title']) ?></small><?php endif; ?>
            <br><small class="text-muted"><?= e($q['author_name'] ?? '') ?> · نسخة <?= (int) $q['version'] ?></small></td>
          <td class="small"><?= e($q['subject_name']) ?><br><span class="text-muted"><?= e($q['grade_name']) ?></span></td>
          <td class="small" style="max-width:220px"><?= e($q['skill_name'] ? ($q['skill_code'] ? $q['skill_code'] . ' ' : '') . str_limit($q['skill_name'], 45) : '—') ?></td>
          <td class="small"><?= e(QB::TYPES[$q['type']]) ?></td>
          <td><span class="badge badge-soft-<?= [1 => 'success', 2 => 'warning', 3 => 'danger'][(int) $q['difficulty']] ?>"><?= QB::DIFFICULTIES[(int) $q['difficulty']] ?></span>
            <br><small class="text-muted"><?= QB::COGNITIVE[$q['cognitive_level']] ?></small></td>
          <td><?= (int) $q['used_count'] ?> اختبار</td>
          <td><span class="badge badge-soft-<?= $sc ?>"><?= $sl ?></span></td>
          <td class="actions"><a class="btn btn-sm btn-light" href="<?= e(url('/questions/' . $q['id'])) ?>"><i class="bi bi-eye"></i></a>
            <?php if (\App\Services\QuestionBank::canEdit($q)): ?><a class="btn btn-sm btn-light" href="<?= e(url('/questions/' . $q['id'] . '/edit')) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$result['rows']): ?><tr><td colspan="10"><div class="empty-state"><i class="bi bi-collection"></i>لا توجد أسئلة مطابقة.</div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php include APP_PATH . '/Views/partials/pagination.php'; ?>
</form>
