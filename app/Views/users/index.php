<div class="page-head">
  <h1>👥 المستخدمون</h1>
  <span class="spacer"></span>
  <a class="btn" href="<?= e(url('users/create')) ?>">➕ إضافة مستخدم</a>
</div>

<form class="filters" method="get" action="index.php">
  <input type="hidden" name="r" value="users/index">
  <div class="row">
    <div><label>بحث</label><input type="search" name="q" value="<?= e($q) ?>" placeholder="اسم أو مسمى وظيفي"></div>
    <div><label>الدور</label>
      <select name="role">
        <option value="">الكل</option>
        <?php foreach ($roles as $r): ?>
          <option value="<?= (int) $r['id'] ?>" <?= (int) $role === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flex"><button class="btn sm">بحث</button><a class="btn ghost sm" href="<?= e(url('users/index')) ?>">إلغاء</a></div>
  </div>
</form>

<div class="card">
  <div class="card-head"><h3>القائمة</h3><span class="spacer"></span><span class="chip"><?= num($page->total) ?> مستخدم</span></div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>الاسم</th><th>اسم الدخول</th><th>المسمى الوظيفي</th><th>الإدارة</th>
                   <th>الدور</th><th>آخر دخول</th><th>الحالة</th><th>إجراءات</th></tr></thead>
        <tbody>
        <?php foreach ($page->items as $u): ?>
          <tr>
            <td>
              <div class="flex">
                <span class="user-chip"><span class="avatar"><?= e(mb_substr($u['full_name'], 0, 1)) ?></span></span>
                <b><?= e($u['full_name']) ?></b>
              </div>
            </td>
            <td dir="ltr" class="small"><?= e($u['username']) ?></td>
            <td class="small"><?= e($u['job_title'] ?: '-') ?></td>
            <td class="small"><?= e($u['department_name'] ?: '-') ?></td>
            <td><span class="badge brand"><?= e($u['role_name']) ?></span></td>
            <td class="small nowrap"><?= $u['last_login_at'] ? e(timeAgo($u['last_login_at'])) : '<span class="muted">لم يدخل</span>' ?></td>
            <td><span class="badge <?= $u['is_active'] ? 'ok' : 'muted' ?>"><?= $u['is_active'] ? 'نشط' : 'موقوف' ?></span></td>
            <td class="actions">
              <div class="btn-group">
                <a class="btn outline xs" href="<?= e(url('users/edit', ['id' => $u['id']])) ?>">تعديل</a>
                <?php if ((int) $u['id'] !== Auth::id()): ?>
                <form method="post" action="<?= e(url('users/toggle')) ?>" style="display:inline">
                  <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                  <button class="btn <?= $u['is_active'] ? 'warn' : 'ok' ?> xs"><?= $u['is_active'] ? 'إيقاف' : 'تفعيل' ?></button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= $page->links() ?>
  </div>
</div>
