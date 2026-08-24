<div class="page-head">
  <h1>🏛️ الجهات المراسلة</h1>
  <span class="spacer"></span>
  <button class="btn" data-modal="modal-corr-new">➕ إضافة جهة</button>
</div>

<form class="filters" method="get" action="index.php">
  <input type="hidden" name="r" value="correspondents/index">
  <div class="row">
    <div><label>بحث</label><input type="search" name="q" value="<?= e($q) ?>" placeholder="اسم الجهة"></div>
    <div><label>النوع</label>
      <select name="type">
        <option value="">الكل</option>
        <?php foreach (['government','education','company','school','individual','internal','other'] as $t): ?>
          <option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= e(labelCorrespondentType($t)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flex"><button class="btn sm">بحث</button><a class="btn ghost sm" href="<?= e(url('correspondents/index')) ?>">إلغاء</a></div>
  </div>
</form>

<div class="card">
  <div class="card-head"><h3>القائمة</h3><span class="spacer"></span><span class="chip"><?= num($page->total) ?> جهة</span></div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>الجهة</th><th>النوع</th><th>جهة الاتصال</th><th>الهاتف</th>
                   <th>المعاملات</th><th>الحالة</th><th>إجراءات</th></tr></thead>
        <tbody>
        <?php foreach ($page->items as $c): ?>
          <tr>
            <td><b><?= e($c['name']) ?></b><?php if ($c['address']): ?><br><small class="muted"><?= e(excerpt($c['address'], 40)) ?></small><?php endif; ?></td>
            <td><span class="badge info"><?= e(labelCorrespondentType($c['type'])) ?></span></td>
            <td class="small"><?= e($c['contact_person'] ?: '-') ?></td>
            <td class="small" dir="ltr"><?= e($c['phone'] ?: '-') ?></td>
            <td class="center">
              <a href="<?= e(url('transactions/index', ['corr' => $c['id']])) ?>"><?= num($c['trx_count']) ?></a>
            </td>
            <td><span class="badge <?= $c['is_active'] ? 'ok' : 'muted' ?>"><?= $c['is_active'] ? 'نشطة' : 'مخفية' ?></span></td>
            <td class="actions">
              <div class="btn-group">
                <button class="btn outline xs" data-modal="modal-corr-edit"
                  data-set-id="<?= (int) $c['id'] ?>"
                  data-set-name="<?= e($c['name']) ?>"
                  data-set-type="<?= e($c['type']) ?>"
                  data-set-contact_person="<?= e($c['contact_person']) ?>"
                  data-set-phone="<?= e($c['phone']) ?>"
                  data-set-email="<?= e($c['email']) ?>"
                  data-set-address="<?= e($c['address']) ?>"
                  data-set-notes="<?= e($c['notes']) ?>"
                  data-set-is_active="<?= (int) $c['is_active'] ?>">تعديل</button>
                <form method="post" action="<?= e(url('correspondents/delete')) ?>" style="display:inline" data-confirm="حذف / إخفاء الجهة؟">
                  <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                  <button class="btn danger xs">حذف</button>
                </form>
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

<?php $corrFields = static function (): void { ?>
  <div class="form-grid two">
    <div class="field"><label>اسم الجهة <span class="req">*</span></label><input type="text" name="name" required maxlength="200"></div>
    <div class="field"><label>النوع</label>
      <select name="type">
        <?php foreach (['government','education','company','school','individual','internal','other'] as $t): ?>
          <option value="<?= $t ?>"><?= e(labelCorrespondentType($t)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>جهة الاتصال</label><input type="text" name="contact_person" maxlength="150"></div>
    <div class="field"><label>الهاتف</label><input type="tel" name="phone" dir="ltr" maxlength="40"></div>
    <div class="field"><label>البريد الإلكتروني</label><input type="email" name="email" dir="ltr"></div>
    <div class="field"><label>العنوان</label><input type="text" name="address" maxlength="300"></div>
    <div class="field full"><label>ملاحظات</label><textarea name="notes" rows="2" maxlength="500"></textarea></div>
  </div>
<?php }; ?>

<div class="modal" id="modal-corr-new">
  <div class="modal-box">
    <form method="post" action="<?= e(url('correspondents/store')) ?>">
      <?= Csrf::field() ?>
      <div class="modal-head"><h3>➕ جهة جديدة</h3>
        <button type="button" class="x" onclick="closeModal('modal-corr-new')">&times;</button></div>
      <div class="modal-body"><?php $corrFields(); ?></div>
      <div class="modal-foot"><button class="btn">حفظ</button>
        <button type="button" class="btn outline" onclick="closeModal('modal-corr-new')">إلغاء</button></div>
    </form>
  </div>
</div>

<div class="modal" id="modal-corr-edit">
  <div class="modal-box">
    <form method="post" action="<?= e(url('correspondents/update')) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="id" value="">
      <div class="modal-head"><h3>✏️ تعديل الجهة</h3>
        <button type="button" class="x" onclick="closeModal('modal-corr-edit')">&times;</button></div>
      <div class="modal-body">
        <?php $corrFields(); ?>
        <label class="check mt-sm"><input type="checkbox" name="is_active" value="1" checked> الجهة نشطة</label>
      </div>
      <div class="modal-foot"><button class="btn">حفظ</button>
        <button type="button" class="btn outline" onclick="closeModal('modal-corr-edit')">إلغاء</button></div>
    </form>
  </div>
</div>
