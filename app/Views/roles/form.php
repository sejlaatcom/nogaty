<div class="page-head">
  <h1>🔐 <?= e($title) ?></h1>
  <span class="spacer"></span>
  <a class="btn ghost" href="<?= e(url('roles/index')) ?>">↩︎ رجوع</a>
</div>

<form method="post" action="<?= e(url('roles/update')) ?>" data-once>
  <?= Csrf::field() ?>
  <input type="hidden" name="id" value="<?= (int) $role['id'] ?>">

  <div class="card">
    <div class="card-head"><h3>بيانات الدور</h3></div>
    <div class="card-body">
      <div class="form-grid two">
        <div class="field"><label>اسم الدور</label><input type="text" name="name" value="<?= e($role['name']) ?>" required></div>
        <div class="field"><label>الوصف</label><input type="text" name="description" value="<?= e($role['description']) ?>" maxlength="255"></div>
      </div>
    </div>
  </div>

  <div class="card" id="perms-scope">
    <div class="card-head">
      <h3>الصلاحيات الممنوحة</h3>
      <span class="spacer"></span>
      <label class="check"><input type="checkbox" data-check-all="#perms-scope"> تحديد الكل</label>
    </div>
    <div class="card-body">
      <div class="grid grid-3">
        <?php foreach ($grouped as $group => $perms): ?>
          <fieldset>
            <legend><?= e($group) ?></legend>
            <?php foreach ($perms as $p): ?>
              <label class="check mb-sm">
                <input type="checkbox" name="permissions[]" value="<?= (int) $p['id'] ?>"
                       <?= in_array((int) $p['id'], $assigned, true) ? 'checked' : '' ?>>
                <span><?= e($p['name']) ?><br><code class="xsmall"><?= e($p['code']) ?></code></span>
              </label>
            <?php endforeach; ?>
          </fieldset>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card-foot">
      <div class="form-actions">
        <button class="btn">💾 حفظ الصلاحيات</button>
        <a class="btn outline" href="<?= e(url('roles/index')) ?>">إلغاء</a>
      </div>
    </div>
  </div>
</form>
