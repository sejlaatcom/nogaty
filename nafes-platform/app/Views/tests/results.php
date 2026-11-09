<?php use App\Services\Levels; use App\Services\TestEngine as TE; ?>
<div class="page-header">
  <div><h1>نتائج: <?= e($test['title']) ?></h1><div class="sub"><?= e(TE::TYPES[$test['type']]) ?> · <?= e($test['subject_name'] ?? 'شامل') ?> · <?= e($test['grade_name']) ?> — تُحتسب أعلى محاولة لكل طالب</div></div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-light" href="<?= e(url('/tests/' . $test['id'])) ?>">الاختبار</a>
    <?php if (can('reports.view')): ?><a class="btn btn-soft" href="<?= e(url('/reports/test', ['test' => $test['id']])) ?>"><i class="bi bi-graph-up"></i> التحليل التفصيلي</a><?php endif; ?>
    <?php if ($canManage && $test['result_visibility'] === 'manual' && !(int) $test['results_released']): ?>
      <form method="post" action="<?= e(url('/tests/' . $test['id'] . '/release')) ?>"><?= csrf_field() ?><button class="btn btn-success" data-confirm="إعلان النتائج للطلاب وإشعارهم؟"><i class="bi bi-megaphone"></i> إعلان النتائج</button></form>
    <?php elseif ($test['result_visibility'] === 'manual'): ?><span class="badge badge-soft-success align-self-center">النتائج معلنة</span><?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-people"></i></div><div><div class="stat-value"><?= count($rows) ?></div><div class="stat-label">طالب مستهدف</div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-check2-all"></i></div><div><div class="stat-value"><?= $done ?></div><div class="stat-label">أنجزوا (<?= pct($rows ? $done / count($rows) * 100 : null) ?>)</div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-speedometer2"></i></div><div><div class="stat-value"><?= pct($avg) ?></div><div class="stat-label">متوسط الدرجات</div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-hourglass-split"></i></div><div><div class="stat-value"><?= count($rows) - $done ?></div><div class="stat-label">لم يُنجزوا بعد</div></div></div></div>
</div>

<div class="card">
  <div class="table-tools" data-table-tools="res">
    <input class="form-control form-control-sm" style="max-width:16rem" data-table-search placeholder="بحث بالاسم أو الرقم">
    <select class="form-select form-select-sm" style="max-width:12rem" data-table-filter="6"><option value="">كل الحالات</option><option>لم يبدأ</option><option>جارية</option><option>بانتظار التصحيح</option><option>مكتمل</option></select>
    <span class="small text-muted">المعروض: <b data-table-count><?= count($rows) ?></b></span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle" data-table id="res">
      <thead><tr><th>الطالب</th><th>الرقم</th><th>الفصل</th><th>المحاولات</th><th>أعلى نسبة</th><th>المستوى</th><th>الحالة</th><th data-sort="false"></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r):
          $state = match ($r['last_status']) { null => 'لم يبدأ', 'in_progress' => 'جارية', 'needs_grading' => 'بانتظار التصحيح', default => 'مكتمل' }; ?>
        <tr>
          <td><?= e($r['full_name']) ?></td>
          <td class="ltr small"><?= e($r['student_number']) ?></td>
          <td class="small"><?= e($r['class_name'] ?? '—') ?></td>
          <td><?= (int) $r['attempts'] ?><?= (int) $test['max_attempts'] ? ' / ' . (int) $test['max_attempts'] : '' ?></td>
          <td data-value="<?= $r['best_percent'] !== null ? (float) $r['best_percent'] : -1 ?>"><?= $r['best_percent'] !== null ? '<div class="d-flex align-items-center gap-2" style="min-width:140px">' . Levels::bar((float) $r['best_percent']) . '<b>' . pct((float) $r['best_percent']) . '</b></div>' : '—' ?></td>
          <td><?= $r['best_percent'] !== null ? Levels::badge((float) $r['best_percent']) : '' ?></td>
          <td data-value="<?= $state ?>"><span class="badge badge-soft-<?= ['لم يبدأ' => 'secondary', 'جارية' => 'info', 'بانتظار التصحيح' => 'warning', 'مكتمل' => 'success'][$state] ?>"><?= $state ?></span></td>
          <td class="actions">
            <?php if ($r['best_attempt']): ?><a class="btn btn-sm btn-light" href="<?= e(url('/attempts/' . $r['best_attempt'])) ?>" title="تقرير المحاولة"><i class="bi bi-file-earmark-text"></i></a><?php endif; ?>
            <?php if ((int) $r['attempts'] && can('results.grade')): ?>
              <form method="post" action="<?= e(url('/tests/' . $test['id'] . '/reset-attempt')) ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="student_id" value="<?= (int) $r['student_id'] ?>">
                <button class="btn btn-sm btn-light text-danger" title="السماح بإعادة الاختبار" data-confirm="حذف محاولات <?= e($r['full_name']) ?> في هذا الاختبار للسماح له بإعادته؟ لا يمكن التراجع."><i class="bi bi-arrow-counterclockwise"></i></button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr class="empty-row"><td colspan="8"><div class="empty-state"><i class="bi bi-people"></i>لم يُسند الاختبار لأي طالب بعد.</div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<p class="small text-muted mt-2"><?= e(\App\Services\Settings::get('report_disclaimer')) ?></p>
