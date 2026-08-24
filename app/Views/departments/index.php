<div class="page-head">
  <h1>🏫 الهيكل التنظيمي للمدرسة</h1>
  <span class="spacer"></span>
  <button class="btn" data-modal="modal-dept-new">➕ إضافة إدارة</button>
</div>

<div class="grid" style="grid-template-columns:1fr 1fr">
  <div class="card">
    <div class="card-head"><h3>الشجرة التنظيمية</h3></div>
    <div class="card-body">
      <?php
      $render = static function (array $nodes) use (&$render): void { ?>
        <ul class="tree">
        <?php foreach ($nodes as $n): $has = !empty($n['children']); ?>
          <li data-id="<?= (int) $n['id'] ?>">
            <span class="node">
              <span class="toggle"><?= $has ? '▼' : '·' ?></span>
              <span><?= $has ? '🏛️' : '🔹' ?></span>
              <span class="n"><?= e($n['name']) ?><?= $n['code'] ? ' <code class="xsmall">' . e($n['code']) . '</code>' : '' ?></span>
              <?php if ((int) $n['users_count']): ?><span class="c"><?= (int) $n['users_count'] ?> 👤</span><?php endif; ?>
            </span>
            <?php if ($has) { $render($n['children']); } ?>
          </li>
        <?php endforeach; ?>
        </ul>
      <?php };
      if (!$tree) { echo '<div class="empty">لم تُضف إدارات بعد</div>'; } else { $render($tree); } ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3>قائمة الإدارات</h3></div>
    <div class="card-body tight">
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>الإدارة</th><th>الرمز</th><th>المدير</th><th>الموظفون</th><th>الحالة</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($flat as $d): ?>
            <tr>
              <td><?= e($d['_label']) ?></td>
              <td class="small" dir="ltr"><?= e($d['code'] ?: '-') ?></td>
              <td class="small"><?= e($d['manager_name'] ?: '-') ?></td>
              <td class="center"><?= (int) $d['users_count'] ?></td>
              <td><span class="badge <?= $d['is_active'] ? 'ok' : 'muted' ?>"><?= $d['is_active'] ? 'نشطة' : 'موقوفة' ?></span></td>
              <td class="actions">
                <div class="btn-group">
                  <button class="btn outline xs" data-modal="modal-dept-edit"
                    data-set-id="<?= (int) $d['id'] ?>"
                    data-set-name="<?= e($d['name']) ?>"
                    data-set-code="<?= e($d['code']) ?>"
                    data-set-parent_id="<?= (int) $d['parent_id'] ?>"
                    data-set-manager_id="<?= (int) $d['manager_id'] ?>"
                    data-set-description="<?= e($d['description']) ?>"
                    data-set-sort_order="<?= (int) $d['sort_order'] ?>"
                    data-set-is_active="<?= (int) $d['is_active'] ?>">تعديل</button>
                  <form method="post" action="<?= e(url('departments/delete')) ?>" style="display:inline" data-confirm="حذف الإدارة؟">
                    <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                    <button class="btn danger xs">حذف</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php $deptFields = static function (array $flat, array $users): void { ?>
  <div class="form-grid two">
    <div class="field"><label>اسم الإدارة <span class="req">*</span></label><input type="text" name="name" required maxlength="150"></div>
    <div class="field"><label>الرمز</label><input type="text" name="code" maxlength="40" dir="ltr" placeholder="EDU"></div>
    <div class="field"><label>تتبع لـ</label>
      <select name="parent_id">
        <option value="">— إدارة رئيسية —</option>
        <?php foreach ($flat as $d): ?><option value="<?= (int) $d['id'] ?>"><?= e($d['_label']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>مدير الإدارة</label>
      <select name="manager_id">
        <option value="">— غير محدد —</option>
        <?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>"><?= e($u['full_name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>ترتيب العرض</label><input type="number" name="sort_order" value="0"></div>
    <div class="field full"><label>الوصف</label><textarea name="description" rows="2" maxlength="500"></textarea></div>
  </div>
<?php }; ?>

<div class="modal" id="modal-dept-new">
  <div class="modal-box">
    <form method="post" action="<?= e(url('departments/store')) ?>">
      <?= Csrf::field() ?>
      <div class="modal-head"><h3>➕ إدارة جديدة</h3>
        <button type="button" class="x" onclick="closeModal('modal-dept-new')">&times;</button></div>
      <div class="modal-body"><?php $deptFields($flat, $users); ?></div>
      <div class="modal-foot"><button class="btn">حفظ</button>
        <button type="button" class="btn outline" onclick="closeModal('modal-dept-new')">إلغاء</button></div>
    </form>
  </div>
</div>

<div class="modal" id="modal-dept-edit">
  <div class="modal-box">
    <form method="post" action="<?= e(url('departments/update')) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="id" value="">
      <div class="modal-head"><h3>✏️ تعديل الإدارة</h3>
        <button type="button" class="x" onclick="closeModal('modal-dept-edit')">&times;</button></div>
      <div class="modal-body">
        <?php $deptFields($flat, $users); ?>
        <label class="check mt-sm"><input type="checkbox" name="is_active" value="1" checked> الإدارة نشطة</label>
      </div>
      <div class="modal-foot"><button class="btn">حفظ التعديلات</button>
        <button type="button" class="btn outline" onclick="closeModal('modal-dept-edit')">إلغاء</button></div>
    </form>
  </div>
</div>
