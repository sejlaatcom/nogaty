<?php use App\Models\ClassRoom; use App\Models\Student; ?>
<div class="page-header">
  <div><h1>الطلاب</h1><div class="sub"><?= num($result['total']) ?> طالب<?= \App\Services\Scope::all() ? '' : ' في فصولك المسندة' ?></div></div>
  <div class="d-flex gap-2 flex-wrap">
    <div class="dropdown">
      <button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download"></i> تصدير</button>
      <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="<?= e(url('/students/export', array_filter($filters) + ['format' => 'xlsx'])) ?>">Excel</a></li>
        <li><a class="dropdown-item" href="<?= e(url('/students/export', array_filter($filters) + ['format' => 'csv'])) ?>">CSV</a></li>
      </ul>
    </div>
    <?php if ($canImport): ?><a class="btn btn-soft" href="<?= e(url('/import/students')) ?>"><i class="bi bi-file-earmark-arrow-up"></i> استيراد Excel/CSV</a><?php endif; ?>
    <?php if ($canManage): ?><a class="btn btn-primary" href="<?= e(url('/students/create', ['class' => $filters['class'] ?: null])) ?>"><i class="bi bi-person-plus"></i> إضافة طالب</a><?php endif; ?>
  </div>
</div>

<?php if ($cards): ?>
  <div class="alert alert-success d-flex justify-content-between align-items-center">
    <span>جاهزة للطباعة: بطاقات دخول <?= count($cards) ?> طالب بكلمات المرور الجديدة.</span>
    <a class="btn btn-sm btn-success" target="_blank" href="<?= e(url('/students/cards')) ?>"><i class="bi bi-printer"></i> طباعة البطاقات</a>
  </div>
<?php endif; ?>

<form class="filter-bar row g-2 align-items-end" method="get">
  <div class="col-md-3"><label class="form-label">بحث</label><input class="form-control" name="q" value="<?= e($filters['q']) ?>" placeholder="الاسم أو الرقم المدرسي"></div>
  <div class="col-md-2"><label class="form-label">الصف</label><select class="form-select" name="grade" data-auto-submit><option value="">الكل</option>
    <?php foreach ($grades as $g): ?><option value="<?= (int) $g['id'] ?>"<?= selected($filters['grade'], $g['id']) ?>><?= e($g['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><label class="form-label">الفصل</label><select class="form-select" name="class" data-auto-submit><option value="">الكل</option>
    <?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($filters['class'], $c['id']) ?>><?= e(ClassRoom::label($c)) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><label class="form-label">الحالة</label><select class="form-select" name="status" data-auto-submit><option value="">الكل</option>
    <?php foreach (Student::STATUSES as $k => $v): ?><option value="<?= $k ?>"<?= selected($filters['status'], $k) ?>><?= $v ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><label class="form-label">الحساب</label><select class="form-select" name="account" data-auto-submit><option value="">الكل</option>
    <option value="active"<?= selected($filters['account'], 'active') ?>>مفعّل</option><option value="inactive"<?= selected($filters['account'], 'inactive') ?>>معطّل</option></select></div>
  <div class="col-md-1"><button class="btn btn-outline-primary w-100"><i class="bi bi-search"></i></button></div>
  <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="unassigned" value="1" id="un"<?= checked($filters['unassigned']) ?> data-auto-submit><label class="form-check-label small" for="un">طلاب بدون فصل في العام الحالي</label></div></div>
</form>

<form method="post" action="<?= e(url('/students/bulk')) ?>" class="card">
  <?= csrf_field() ?>
  <?php if ($canManage): ?>
  <div class="table-tools">
    <span class="small text-muted">للمحدد:</span>
    <select class="form-select form-select-sm" name="class_id" style="max-width:220px"><option value="">نقل إلى فصل…</option>
      <?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e(ClassRoom::label($c)) ?></option><?php endforeach; ?></select>
    <button class="btn btn-sm btn-soft" name="action" value="move" data-confirm="نقل الطلاب المحددين إلى الفصل المختار؟ تبقى نتائجهم السابقة مرتبطة بفصولهم السابقة.">نقل</button>
    <button class="btn btn-sm btn-light" name="action" value="activate">تفعيل</button>
    <button class="btn btn-sm btn-light" name="action" value="deactivate" data-confirm="تعطيل حسابات الطلاب المحددين؟">تعطيل</button>
    <button class="btn btn-sm btn-light" name="action" value="passwords" data-confirm="إنشاء كلمات مرور جديدة للطلاب المحددين؟ ستتوقف كلمات مرورهم الحالية."><i class="bi bi-key"></i> كلمات مرور وبطاقات</button>
  </div>
  <?php endif; ?>
  <div class="table-responsive">
    <table class="table table-hover" data-table>
      <thead><tr><?php if ($canManage): ?><th data-sort="false"><input class="form-check-input" type="checkbox" data-check-all="ids[]"></th><?php endif; ?>
        <th>الطالب</th><th>الرقم المدرسي</th><th>اسم المستخدم</th><th>الصف / الفصل</th><th>الحالة</th><th>آخر دخول</th><th data-sort="false"></th></tr></thead>
      <tbody>
      <?php foreach ($result['rows'] as $s): ?>
        <tr>
          <?php if ($canManage): ?><td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int) $s['id'] ?>"></td><?php endif; ?>
          <td><a href="<?= e(url('/students/' . $s['id'])) ?>" class="fw-bold"><?= e($s['full_name']) ?></a></td>
          <td><?= e($s['student_number']) ?></td>
          <td class="ltr small"><?= e($s['username']) ?></td>
          <td><?= $s['class_id'] ? e(ClassRoom::label($s)) : '<span class="badge badge-soft-warning">بدون فصل</span>' ?></td>
          <td><span class="badge <?= $s['status'] === 'active' && (int) $s['is_active'] ? 'badge-soft-success' : 'badge-soft-secondary' ?>"><?= e(Student::STATUSES[$s['status']] ?? $s['status']) ?></span></td>
          <td class="small text-muted" data-value="<?= e($s['last_login_at']) ?>"><?= e($s['last_login_at'] ? ago($s['last_login_at']) : 'لم يدخل') ?></td>
          <td class="actions">
            <a class="btn btn-sm btn-light" href="<?= e(url('/students/' . $s['id'])) ?>"><i class="bi bi-eye"></i></a>
            <?php if ($canManage): ?><a class="btn btn-sm btn-light" href="<?= e(url('/students/' . $s['id'] . '/edit')) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$result['rows']): ?><tr class="empty-row"><td colspan="8"><div class="empty-state"><i class="bi bi-people"></i>لا يوجد طلاب مطابقون. أضفهم يدوياً أو استوردهم من Excel.</div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php include APP_PATH . '/Views/partials/pagination.php'; ?>
</form>
