<?php use App\Services\TestEngine as TE; ?>
<div class="page-header">
  <div><h1>الاختبارات</h1><div class="sub">الاختبارات التشخيصية والتدريبية والمحاكية والواجبات — النتائج لا تمثل نتيجة نافس الرسمية</div></div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (can('results.grade')): ?><a class="btn btn-light" href="<?= e(url('/grading')) ?>"><i class="bi bi-pencil-square"></i> التصحيح اليدوي</a><?php endif; ?>
    <?php if (can('tests.create')): ?>
      <div class="dropdown">
        <button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-plus-lg"></i> اختبار جديد</button>
        <ul class="dropdown-menu"><?php foreach (TE::TYPES as $k => $v): if ($k === 'practice') continue; ?>
          <li><a class="dropdown-item" href="<?= e(url('/tests/create', ['type' => $k])) ?>"><?= e($v) ?></a></li>
        <?php endforeach; ?></ul>
      </div>
    <?php endif; ?>
  </div>
</div>

<form class="filter-bar row g-2 align-items-end" method="get">
  <div class="col-md-3"><label class="form-label">بحث بالاسم</label><input class="form-control" name="q" value="<?= e($filters['q']) ?>"></div>
  <div class="col-md-2"><label class="form-label">النوع</label><select class="form-select" name="type" data-auto-submit><option value="">الكل</option>
    <?php foreach (TE::TYPES as $k => $v): if ($k === 'practice') continue; ?><option value="<?= $k ?>"<?= selected($filters['type'], $k) ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><label class="form-label">المادة</label><select class="form-select" name="subject" data-auto-submit><option value="">الكل</option>
    <?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected($filters['subject'], $s['id']) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><label class="form-label">الحالة</label><select class="form-select" name="status" data-auto-submit><option value="">الكل</option>
    <?php foreach (TE::STATUSES as $k => [$v]): ?><option value="<?= $k ?>"<?= selected($filters['status'], $k) ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="mine" value="1" id="mine"<?= checked($filters['mine']) ?> data-auto-submit><label class="form-check-label" for="mine">اختباراتي فقط</label></div></div>
  <div class="col-md-1"><button class="btn btn-outline-primary w-100"><i class="bi bi-search"></i></button></div>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle" data-table>
      <thead><tr><th data-sort>الاختبار</th><th data-sort>النوع</th><th data-sort>المادة</th><th>المستهدفون</th><th data-sort>الأسئلة</th><th>الإتاحة</th><th data-sort>أنجزه</th><th data-sort>المتوسط</th><th data-sort>الحالة</th><th data-sort="false"></th></tr></thead>
      <tbody>
      <?php foreach ($tests as $t): [$sl, $sc] = TE::STATUSES[$t['status']]; ?>
        <tr>
          <td><a class="fw-semibold" href="<?= e(url('/tests/' . $t['id'])) ?>"><?= e($t['title']) ?></a><br><small class="text-muted"><?= e($t['author'] ?? '—') ?> · <?= e($t['grade_name']) ?></small></td>
          <td class="small"><?= e(TE::TYPES[$t['type']]) ?></td>
          <td class="small"><?= e($t['subject_name'] ?? 'شامل') ?></td>
          <td class="small" style="max-width:200px"><?= e($t['classes'] ? str_limit($t['classes'], 60) : '') ?><?= (int) $t['individual'] ? '<br><span class="text-muted">' . (int) $t['individual'] . ' طالب فردياً</span>' : '' ?><?= !$t['classes'] && !(int) $t['individual'] ? '<span class="text-danger">غير مسند</span>' : '' ?></td>
          <td data-value="<?= (int) $t['q_count'] ?>"><?= (int) $t['q_count'] ?> · <span class="text-muted small"><?= num((float) $t['total_points'], 1) ?> درجة</span></td>
          <td class="small"><?= $t['available_from'] ? 'من ' . e(dt($t['available_from'])) . '<br>' : '' ?><?= $t['available_until'] ? 'إلى ' . e(dt($t['available_until'])) : 'مفتوح' ?></td>
          <td data-value="<?= (int) $t['done_count'] ?>"><?= (int) $t['done_count'] ?></td>
          <td data-value="<?= $t['avg_percent'] !== null ? round((float) $t['avg_percent'], 1) : -1 ?>"><?= $t['avg_percent'] !== null ? \App\Services\Levels::badge((float) $t['avg_percent']) . ' ' . pct((float) $t['avg_percent']) : '—' ?></td>
          <td><span class="badge badge-soft-<?= $sc ?>"><?= $sl ?></span></td>
          <td class="actions"><a class="btn btn-sm btn-light" href="<?= e(url('/tests/' . $t['id'])) ?>" title="فتح"><i class="bi bi-gear"></i></a>
            <a class="btn btn-sm btn-light" href="<?= e(url('/tests/' . $t['id'] . '/results')) ?>" title="النتائج"><i class="bi bi-bar-chart"></i></a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$tests): ?><tr class="empty-row"><td colspan="10"><div class="empty-state"><i class="bi bi-ui-checks"></i>لا توجد اختبارات بعد<?= can('tests.create') ? ' — أنشئ أول اختبار من الزر «اختبار جديد».' : '.' ?></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
