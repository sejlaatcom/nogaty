<div class="page-header">
  <div><h1>المستخدمون والصلاحيات</h1><div class="sub">حسابات الإدارة والمعلمين والوكلاء — حسابات الطلاب تُدار من وحدة الطلاب</div></div>
  <div class="d-flex gap-2">
    <a class="btn btn-soft" href="<?= e(url('/users/roles')) ?>"><i class="bi bi-ui-checks-grid"></i> الأدوار والصلاحيات</a>
    <a class="btn btn-primary" href="<?= e(url('/users/create')) ?>"><i class="bi bi-person-plus"></i> إضافة مستخدم</a>
  </div>
</div>

<form class="filter-bar row g-2 align-items-end" method="get">
  <div class="col-md-3"><label class="form-label">الدور</label>
    <select class="form-select" name="role" data-auto-submit><option value="">كل الأدوار (عدا الطلاب)</option>
      <?php foreach ($roles as $r): ?><option value="<?= e($r['slug']) ?>"<?= selected($filters['role'], $r['slug']) ?>><?= e($r['name']) ?></option><?php endforeach; ?>
    </select></div>
  <div class="col-md-2"><label class="form-label">الحالة</label>
    <select class="form-select" name="status" data-auto-submit><option value="">الكل</option>
      <option value="active"<?= selected($filters['status'], 'active') ?>>نشط</option><option value="inactive"<?= selected($filters['status'], 'inactive') ?>>معطّل</option></select></div>
  <div class="col-md-4"><label class="form-label">بحث</label><input class="form-control" name="q" value="<?= e($filters['q']) ?>" placeholder="الاسم أو اسم المستخدم أو البريد"></div>
  <div class="col-md-auto"><button class="btn btn-outline-primary"><i class="bi bi-search"></i> بحث</button></div>
</form>

<div class="card">
  <div class="table-tools" data-table-tools="users-table">
    <input class="form-control form-control-sm search" data-table-search placeholder="تصفية فورية…">
    <span class="text-muted small">العدد: <b data-table-count><?= count($users) ?></b></span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover" id="users-table" data-table>
      <thead><tr><th>الاسم</th><th>اسم المستخدم</th><th>الأدوار</th><th>آخر دخول</th><th>الحالة</th><th data-sort="false"></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><strong><?= e($u['full_name']) ?></strong><?php if ($u['email']): ?><br><small class="text-muted ltr"><?= e($u['email']) ?></small><?php endif; ?></td>
          <td class="ltr"><?= e($u['username']) ?></td>
          <td><small><?= e($u['role_names'] ?: '—') ?></small></td>
          <td class="small text-muted" data-value="<?= e($u['last_login_at']) ?>"><?= e($u['last_login_at'] ? ago($u['last_login_at']) : 'لم يدخل بعد') ?></td>
          <td><?= (int) $u['is_active'] ? '<span class="badge badge-soft-success">نشط</span>' : '<span class="badge badge-soft-secondary">معطّل</span>' ?></td>
          <td class="actions">
            <a class="btn btn-sm btn-light" href="<?= e(url('/users/' . $u['id'] . '/edit')) ?>"><i class="bi bi-pencil"></i> تعديل</a>
            <form method="post" action="<?= e(url('/users/' . $u['id'] . '/reset-password')) ?>" class="d-inline"><?= csrf_field() ?>
              <button class="btn btn-sm btn-light" data-confirm="إنشاء كلمة مرور مؤقتة جديدة لهذا المستخدم؟"><i class="bi bi-key"></i></button></form>
            <form method="post" action="<?= e(url('/users/' . $u['id'] . '/toggle')) ?>" class="d-inline"><?= csrf_field() ?>
              <button class="btn btn-sm btn-light" data-confirm="<?= (int) $u['is_active'] ? 'تعطيل هذا الحساب؟' : 'تفعيل هذا الحساب؟' ?>"><i class="bi bi-<?= (int) $u['is_active'] ? 'pause-circle' : 'play-circle' ?>"></i></button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$users): ?><tr class="empty-row"><td colspan="6"><div class="empty-state"><i class="bi bi-people"></i>لا يوجد مستخدمون مطابقون.</div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
