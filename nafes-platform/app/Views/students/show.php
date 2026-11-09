<?php use App\Models\ClassRoom; use App\Models\Student; use App\Services\Levels; ?>
<div class="page-header">
  <div><h1><?= e($student['full_name']) ?></h1><div class="sub">الرقم المدرسي: <?= e($student['student_number']) ?> — <?= $student['class_id'] ? e(ClassRoom::label($student)) : 'بدون فصل' ?></div></div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (can('reports.view')): ?><a class="btn btn-soft" href="<?= e(url('/reports/student/' . $student['id'])) ?>"><i class="bi bi-clipboard-data"></i> تقرير الطالب</a><?php endif; ?>
    <?php if ($canManage): ?>
      <a class="btn btn-light" href="<?= e(url('/students/' . $student['id'] . '/edit')) ?>"><i class="bi bi-pencil"></i> تعديل</a>
      <form method="post" action="<?= e(url('/students/' . $student['id'] . '/delete')) ?>"><?= csrf_field() ?>
        <button class="btn btn-light text-danger" data-confirm="حذف الطالب؟ إن كانت له نتائج فسيُعطّل حسابه فقط."><i class="bi bi-trash"></i></button></form>
    <?php endif; ?>
  </div>
</div>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card mb-3"><div class="card-body">
      <dl class="row mb-0 small">
        <dt class="col-5">اسم المستخدم</dt><dd class="col-7 ltr"><?= e($student['username']) ?></dd>
        <dt class="col-5">الحالة</dt><dd class="col-7"><?= e(Student::STATUSES[$student['status']] ?? '') ?> / الحساب <?= (int) $student['is_active'] ? 'مفعّل' : 'معطّل' ?></dd>
        <dt class="col-5">آخر دخول</dt><dd class="col-7"><?= e(dt($student['last_login_at'])) ?></dd>
        <?php if ($student['notes']): ?><dt class="col-5">ملاحظات</dt><dd class="col-7"><?= e($student['notes']) ?></dd><?php endif; ?>
      </dl>
    </div></div>
    <?php if ($canManage): ?>
    <form class="card mb-3" method="post" action="<?= e(url('/students/' . $student['id'] . '/transfer')) ?>">
      <?= csrf_field() ?>
      <div class="card-header"><h2>نقل إلى فصل</h2></div>
      <div class="card-body">
        <select class="form-select mb-2" name="class_id" required><option value="">اختر الفصل</option>
          <?php foreach ($classes as $c): if ((int) $c['id'] !== (int) $student['class_id']): ?><option value="<?= (int) $c['id'] ?>"><?= e(ClassRoom::label($c)) ?></option><?php endif; endforeach; ?></select>
        <button class="btn btn-soft w-100" data-confirm="نقل الطالب؟ تبقى نتائجه السابقة مرتبطة بفصله الحالي.">نقل</button>
      </div>
    </form>
    <?php endif; ?>
    <div class="card"><div class="card-header"><h2>سجل التسجيل في الفصول</h2></div>
      <ul class="list-group list-group-flush small">
        <?php foreach ($history as $h): ?>
          <li class="list-group-item d-flex justify-content-between"><span><?= e($h['year_name'] . ' — ' . $h['grade_name'] . ' / ' . $h['class_name']) ?></span>
            <span class="text-muted"><?= e(dt($h['enrolled_on'], false)) ?><?= $h['left_on'] ? ' ← ' . e(dt($h['left_on'], false)) : '' ?>
            <?= ['active' => '<span class="badge badge-soft-success">حالي</span>', 'transferred' => '<span class="badge badge-soft-secondary">نُقل</span>', 'completed' => '<span class="badge badge-soft-primary">مكتمل</span>'][$h['status']] ?></span></li>
        <?php endforeach; ?>
        <?php if (!$history): ?><li class="list-group-item text-muted">غير مسجل في أي فصل.</li><?php endif; ?>
      </ul></div>
  </div>
  <div class="col-lg-8">
    <div class="card"><div class="card-header"><h2>آخر النتائج</h2></div>
      <div class="table-responsive"><table class="table table-sm" data-table>
        <thead><tr><th>الاختبار</th><th>المادة</th><th>الفصل وقتها</th><th>الدرجة</th><th>النسبة</th><th>المستوى</th><th>التاريخ</th></tr></thead>
        <tbody>
        <?php foreach ($results as $r): ?>
          <tr><td><?= e($r['title']) ?></td><td><?= e($r['subject_name'] ?? 'شامل') ?></td><td class="small"><?= e(($r['grade_name'] ?? '') . ' / ' . ($r['class_name'] ?? '')) ?> <small class="text-muted"><?= e($r['year_name']) ?></small></td>
            <td><?= num((float) $r['score'], 1) ?>/<?= num((float) $r['max_score'], 1) ?></td><td data-value="<?= e($r['percent']) ?>"><?= pct((float) $r['percent']) ?></td>
            <td><?= Levels::badge($r['level']) ?></td><td class="small"><?= e(dt($r['created_at'], false)) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$results): ?><tr class="empty-row"><td colspan="7"><div class="empty-state"><i class="bi bi-bar-chart"></i>لا توجد نتائج بعد.</div></td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>
