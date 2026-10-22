<div class="page-header">
  <div><h1>المعلمون والإسناد</h1><div class="sub"><?= count($teachers) ?> من منسوبي المدرسة — العام <?= e(\App\Models\Year::current()['name'] ?? '') ?></div></div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-light" href="<?= e(url('/teachers/export')) ?>"><i class="bi bi-download"></i> تصدير</a>
    <?php if ($canManage): ?>
      <form method="post" action="<?= e(url('/teachers/copy-assignments')) ?>"><?= csrf_field() ?><button class="btn btn-light" data-confirm="نسخ إسناد المعلمين من العام السابق إلى فصول العام الحالي المماثلة؟"><i class="bi bi-copy"></i> نسخ إسناد العام السابق</button></form>
      <a class="btn btn-soft" href="<?= e(url('/import/teachers')) ?>"><i class="bi bi-file-earmark-arrow-up"></i> استيراد</a>
      <a class="btn btn-primary" href="<?= e(url('/teachers/create')) ?>"><i class="bi bi-person-plus"></i> إضافة معلم</a>
    <?php endif; ?>
  </div>
</div>
<div class="card">
  <div class="table-tools" data-table-tools="teachers-table">
    <input class="form-control form-control-sm search" data-table-search placeholder="بحث بالاسم أو المادة…">
    <select class="form-select form-select-sm" style="max-width:160px" data-table-filter="5"><option value="">كل الحالات</option><option>نشط</option><option>معطّل</option></select>
    <span class="text-muted small">العدد: <b data-table-count><?= count($teachers) ?></b></span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover" id="teachers-table" data-table>
      <thead><tr><th>الاسم</th><th>المعرف</th><th>الدور</th><th>المواد</th><th>الفصول</th><th>الحالة</th><th data-sort="false"></th></tr></thead>
      <tbody>
      <?php foreach ($teachers as $t): ?>
        <tr>
          <td><a class="fw-bold" href="<?= e(url('/teachers/' . $t['id'])) ?>"><?= e($t['full_name']) ?></a><br><small class="text-muted ltr"><?= e($t['username']) ?></small></td>
          <td><?= e($t['employee_number']) ?></td>
          <td class="small"><?= e($t['role_names'] ?: '—') ?></td>
          <td class="small"><?= e($t['subjects'] ?: '—') ?></td>
          <td><?= (int) $t['classes_count'] ?></td>
          <td><?= (int) $t['is_active'] ? '<span class="badge badge-soft-success">نشط</span>' : '<span class="badge badge-soft-secondary">معطّل</span>' ?></td>
          <td class="actions">
            <a class="btn btn-sm btn-light" href="<?= e(url('/teachers/' . $t['id'])) ?>" title="الإسناد"><i class="bi bi-diagram-3"></i></a>
            <?php if ($canManage): ?>
              <a class="btn btn-sm btn-light" href="<?= e(url('/teachers/' . $t['id'] . '/edit')) ?>"><i class="bi bi-pencil"></i></a>
              <form class="d-inline" method="post" action="<?= e(url('/teachers/' . $t['id'] . '/reset-password')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-light" data-confirm="إنشاء كلمة مرور مؤقتة جديدة؟"><i class="bi bi-key"></i></button></form>
              <form class="d-inline" method="post" action="<?= e(url('/teachers/' . $t['id'] . '/toggle')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-light" data-confirm="تغيير حالة الحساب؟"><i class="bi bi-<?= (int) $t['is_active'] ? 'pause' : 'play' ?>-circle"></i></button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$teachers): ?><tr class="empty-row"><td colspan="7"><div class="empty-state"><i class="bi bi-person-workspace"></i>لا يوجد معلمون بعد.</div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
