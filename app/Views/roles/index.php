<div class="page-head">
  <h1>🔐 الأدوار والصلاحيات</h1>
  <span class="spacer"></span>
  <button class="btn" data-modal="modal-role-new">➕ دور جديد</button>
</div>

<div class="card">
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>الدور</th><th>المعرف</th><th>الوصف</th><th>المستخدمون</th><th>الصلاحيات</th><th>إجراءات</th></tr></thead>
        <tbody>
        <?php foreach ($roles as $r): ?>
          <tr>
            <td><b><?= e($r['name']) ?></b> <?= $r['is_system'] ? '<span class="badge muted">نظام</span>' : '' ?></td>
            <td dir="ltr" class="small"><code><?= e($r['slug']) ?></code></td>
            <td class="small muted"><?= e($r['description'] ?: '-') ?></td>
            <td><?= num($r['users_count']) ?></td>
            <td><?= $r['slug'] === 'admin' ? '<span class="badge ok">كل الصلاحيات</span>' : num($r['perms_count']) ?></td>
            <td class="actions">
              <div class="btn-group">
                <?php if ($r['slug'] !== 'admin'): ?>
                  <a class="btn outline xs" href="<?= e(url('roles/edit', ['id' => $r['id']])) ?>">الصلاحيات</a>
                <?php endif; ?>
                <?php if (!$r['is_system'] && (int) $r['users_count'] === 0): ?>
                  <form method="post" action="<?= e(url('roles/delete')) ?>" style="display:inline" data-confirm="حذف الدور؟">
                    <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button class="btn danger xs">حذف</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal" id="modal-role-new">
  <div class="modal-box">
    <form method="post" action="<?= e(url('roles/store')) ?>">
      <?= Csrf::field() ?>
      <div class="modal-head"><h3>➕ دور جديد</h3>
        <button type="button" class="x" onclick="closeModal('modal-role-new')">&times;</button></div>
      <div class="modal-body">
        <div class="form-grid two">
          <div class="field"><label>اسم الدور <span class="req">*</span></label><input type="text" name="name" required></div>
          <div class="field"><label>المعرف البرمجي <span class="req">*</span></label>
            <input type="text" name="slug" required dir="ltr" pattern="[a-z0-9_]+" placeholder="supervisor">
            <span class="hint">أحرف إنجليزية صغيرة وأرقام و _ فقط</span></div>
          <div class="field full"><label>الوصف</label><input type="text" name="description" maxlength="255"></div>
        </div>
      </div>
      <div class="modal-foot">
        <button class="btn">إنشاء ثم تحديد الصلاحيات</button>
        <button type="button" class="btn outline" onclick="closeModal('modal-role-new')">إلغاء</button>
      </div>
    </form>
  </div>
</div>
