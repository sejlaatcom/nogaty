<div class="page-head"><h1>🏷️ التصنيفات والأعوام الدراسية</h1></div>

<div class="grid grid-2">
  <!-- ============================== التصنيفات ============================== -->
  <div class="card">
    <div class="card-head">
      <h3>🏷️ تصنيفات المعاملات</h3><span class="spacer"></span>
      <button class="btn sm" data-modal="modal-cat" data-set-id="" data-set-name="" data-set-color="#0f766e">➕ إضافة</button>
    </div>
    <div class="card-body tight">
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>التصنيف</th><th>اللون</th><th>المعاملات</th><th>الحالة</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($categories as $c): ?>
            <tr>
              <td><b><?= e($c['name']) ?></b></td>
              <td><span class="badge" style="background:<?= e($c['color']) ?>22;color:<?= e($c['color']) ?>"><?= e($c['color']) ?></span></td>
              <td class="center"><?= num($c['trx_count']) ?></td>
              <td><span class="badge <?= $c['is_active'] ? 'ok' : 'muted' ?>"><?= $c['is_active'] ? 'نشط' : 'موقوف' ?></span></td>
              <td class="actions">
                <div class="btn-group">
                  <button class="btn outline xs" data-modal="modal-cat"
                    data-set-id="<?= (int) $c['id'] ?>" data-set-name="<?= e($c['name']) ?>"
                    data-set-color="<?= e($c['color']) ?>" data-set-is_active="<?= (int) $c['is_active'] ?>">تعديل</button>
                  <form method="post" action="<?= e(url('correspondents/deleteCategory')) ?>" style="display:inline"
                        data-confirm="حذف التصنيف؟ ستفقد المعاملات ارتباطها به.">
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
    </div>
  </div>

  <!-- ============================ الأعوام الدراسية ============================ -->
  <div class="card">
    <div class="card-head">
      <h3>📅 الأعوام الدراسية</h3><span class="spacer"></span>
      <button class="btn sm" data-modal="modal-year" data-set-id="" data-set-name="">➕ إضافة</button>
    </div>
    <div class="card-body tight">
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>العام</th><th>من</th><th>إلى</th><th>الحالي</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($years as $y): ?>
            <tr>
              <td><b><?= e($y['name']) ?></b></td>
              <td class="small"><?= e(gdate($y['start_date'])) ?></td>
              <td class="small"><?= e(gdate($y['end_date'])) ?></td>
              <td><?= $y['is_current'] ? '<span class="badge ok">العام الحالي</span>' : '<span class="muted">-</span>' ?></td>
              <td class="actions">
                <button class="btn outline xs" data-modal="modal-year"
                  data-set-id="<?= (int) $y['id'] ?>" data-set-name="<?= e($y['name']) ?>"
                  data-set-start_date="<?= e($y['start_date']) ?>" data-set-end_date="<?= e($y['end_date']) ?>"
                  data-set-is_current="<?= (int) $y['is_current'] ?>">تعديل</button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal" id="modal-cat">
  <div class="modal-box">
    <form method="post" action="<?= e(url('correspondents/saveCategory')) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="id" value="">
      <div class="modal-head"><h3>🏷️ التصنيف</h3>
        <button type="button" class="x" onclick="closeModal('modal-cat')">&times;</button></div>
      <div class="modal-body">
        <div class="form-grid two">
          <div class="field"><label>اسم التصنيف <span class="req">*</span></label><input type="text" name="name" required maxlength="150"></div>
          <div class="field"><label>اللون</label><input type="color" name="color" value="#0f766e"></div>
        </div>
        <label class="check mt-sm"><input type="checkbox" name="is_active" value="1" checked> التصنيف نشط</label>
      </div>
      <div class="modal-foot"><button class="btn">حفظ</button>
        <button type="button" class="btn outline" onclick="closeModal('modal-cat')">إلغاء</button></div>
    </form>
  </div>
</div>

<div class="modal" id="modal-year">
  <div class="modal-box">
    <form method="post" action="<?= e(url('correspondents/saveYear')) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="id" value="">
      <div class="modal-head"><h3>📅 العام الدراسي</h3>
        <button type="button" class="x" onclick="closeModal('modal-year')">&times;</button></div>
      <div class="modal-body">
        <div class="form-grid two">
          <div class="field full"><label>اسم العام <span class="req">*</span></label>
            <input type="text" name="name" required maxlength="60" placeholder="1447 هـ / 2025-2026"></div>
          <div class="field"><label>تاريخ البداية</label><input type="date" name="start_date"></div>
          <div class="field"><label>تاريخ النهاية</label><input type="date" name="end_date"></div>
        </div>
        <label class="check mt-sm"><input type="checkbox" name="is_current" value="1"> هذا هو العام الدراسي الحالي</label>
      </div>
      <div class="modal-foot"><button class="btn">حفظ</button>
        <button type="button" class="btn outline" onclick="closeModal('modal-year')">إلغاء</button></div>
    </form>
  </div>
</div>
