<?php
$isEdit = !empty($transaction);
$v = static function (string $key, $default = '') use ($transaction) {
    $old = old($key, null);
    if ($old !== null && $old !== '') { return $old; }
    return $transaction[$key] ?? $default;
};
?>
<div class="page-head">
  <h1><?= e($title) ?></h1>
  <span class="spacer"></span>
  <a class="btn ghost" href="<?= e(url('transactions/index', ['direction' => $direction])) ?>">↩︎ رجوع</a>
</div>

<form method="post" enctype="multipart/form-data" data-once
      action="<?= e($isEdit ? url('transactions/update') : url('transactions/store')) ?>">
  <?= Csrf::field() ?>
  <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $transaction['id'] ?>"><?php endif; ?>
  <input type="hidden" name="direction" value="<?= e($direction) ?>">

  <!-- ========================= البيانات الأساسية ========================= -->
  <div class="card">
    <div class="card-head">
      <h3>📋 البيانات الأساسية</h3>
      <span class="spacer"></span>
      <span class="badge brand">معاملة <?= e(labelDirection($direction)) ?>ة</span>
    </div>
    <div class="card-body">
      <div class="form-grid">
        <div class="field">
          <label>رقم المعاملة الداخلي</label>
          <input type="text" value="<?= e($nextNo) ?>" readonly dir="ltr">
          <span class="hint"><?= $isEdit ? 'لا يمكن تعديل رقم القيد' : 'يُولَّد آلياً عند الحفظ' ?></span>
        </div>
        <div class="field">
          <label>رقم المعاملة لدى الجهة</label>
          <input type="text" name="reference_no" value="<?= e($v('reference_no')) ?>" placeholder="الرقم الوارد في الخطاب">
        </div>
        <div class="field">
          <label>تاريخ المعاملة <span class="req">*</span></label>
          <input type="date" name="transaction_date" required
                 value="<?= e($v('transaction_date', date('Y-m-d'))) ?>">
          <span class="hint">يُحوَّل للهجري تلقائياً</span>
        </div>

        <div class="field full">
          <label>موضوع المعاملة <span class="req">*</span></label>
          <input type="text" name="subject" required maxlength="500" value="<?= e($v('subject')) ?>"
                 placeholder="مثال : تعميم بشأن جدول الاختبارات النهائية للفصل الأول">
        </div>

        <div class="field">
          <label>الجهة المراسلة</label>
          <select name="correspondent_id">
            <option value="">— اختر الجهة —</option>
            <?php foreach ($lists['correspondents'] as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= (int) $v('correspondent_id') === (int) $c['id'] ? 'selected' : '' ?>>
                <?= e($c['name']) ?> (<?= e(labelCorrespondentType($c['type'])) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>من إدارة</label>
          <select name="from_department_id">
            <option value="">— غير محدد —</option>
            <?php foreach ($lists['departments'] as $d): ?>
              <option value="<?= (int) $d['id'] ?>" <?= (int) $v('from_department_id') === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['_label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>إلى إدارة</label>
          <select name="to_department_id">
            <option value="">— غير محدد —</option>
            <?php foreach ($lists['departments'] as $d): ?>
              <option value="<?= (int) $d['id'] ?>" <?= (int) $v('to_department_id') === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['_label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field full">
          <label>ملخص / نص المعاملة</label>
          <textarea name="body" rows="4" placeholder="اكتب هنا ملخصاً أو نص المعاملة لتسهيل البحث لاحقاً"><?= e($v('body')) ?></textarea>
        </div>
      </div>
    </div>
  </div>

  <!-- ======================= التصنيف وموقع الأرشفة ======================= -->
  <div class="card">
    <div class="card-head"><h3>🗂️ التصنيف وموقع الأرشفة</h3></div>
    <div class="card-body">
      <div class="form-grid">
        <div class="field">
          <label>مجلد الأرشفة</label>
          <select name="folder_id">
            <option value="">— بدون مجلد —</option>
            <?php foreach ($lists['folders'] as $f): ?>
              <option value="<?= (int) $f['id'] ?>" <?= (int) $v('folder_id') === (int) $f['id'] ? 'selected' : '' ?>><?= e($f['_label']) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="hint">حدد موقع المعاملة في شجرة الأرشفة</span>
        </div>
        <div class="field">
          <label>التصنيف</label>
          <select name="category_id">
            <option value="">— بدون تصنيف —</option>
            <?php foreach ($lists['categories'] as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= (int) $v('category_id') === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>العام الدراسي</label>
          <select name="academic_year_id">
            <option value="">— غير محدد —</option>
            <?php foreach ($lists['years'] as $y):
              $sel = $isEdit ? ((int) $v('academic_year_id') === (int) $y['id']) : (bool) $y['is_current']; ?>
              <option value="<?= (int) $y['id'] ?>" <?= $sel ? 'selected' : '' ?>><?= e($y['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label>درجة الأهمية</label>
          <select name="priority">
            <?php foreach (['normal','urgent','very_urgent'] as $p): ?>
              <option value="<?= $p ?>" <?= $v('priority', 'normal') === $p ? 'selected' : '' ?>><?= e(labelPriority($p)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>درجة السرية</label>
          <select name="confidentiality">
            <?php foreach (['normal','confidential','top_secret'] as $c): ?>
              <option value="<?= $c ?>" <?= $v('confidentiality', 'normal') === $c ? 'selected' : '' ?>><?= e(labelConfidentiality($c)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>حالة المعاملة</label>
          <select name="status">
            <?php foreach (['draft','registered','in_progress','completed','archived','closed'] as $s): ?>
              <option value="<?= $s ?>" <?= $v('status', 'registered') === $s ? 'selected' : '' ?>><?= e(labelStatus($s)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label>تاريخ الاستلام</label>
          <input type="date" name="received_date" value="<?= e($v('received_date')) ?>">
        </div>
        <div class="field">
          <label>تاريخ الاستحقاق / الإنجاز</label>
          <input type="date" name="due_date" value="<?= e($v('due_date')) ?>">
          <span class="hint">تظهر المعاملة كمتأخرة بعد هذا التاريخ</span>
        </div>
        <div class="field">
          <label>ربط بمعاملة سابقة</label>
          <input type="number" name="linked_transaction_id" min="1" dir="ltr"
                 value="<?= e($v('linked_transaction_id')) ?>" placeholder="معرّف المعاملة المرتبطة">
          <span class="hint">لربط الصادر بالوارد الخاص به</span>
        </div>

        <div class="field full">
          <label>كلمات مفتاحية للبحث</label>
          <input type="text" name="keywords" value="<?= e($v('keywords')) ?>"
                 placeholder="افصل بين الكلمات بفاصلة : اختبارات، الفصل الأول، جدول">
        </div>
        <div class="field full">
          <label>الوسوم</label>
          <input type="text" name="tags" value="<?= e(old('tags', $tags ?? '')) ?>"
                 placeholder="وسوم مفصولة بفاصلة : عاجل، متابعة، إدارة التعليم">
        </div>
        <div class="field full">
          <label>ملاحظات</label>
          <textarea name="notes" rows="2"><?= e($v('notes')) ?></textarea>
        </div>
      </div>
    </div>
  </div>

  <!-- ============================== المرفقات ============================== -->
  <div class="card">
    <div class="card-body">
      <?php if ($isEdit && !empty($attachments)): ?>
        <h4>المرفقات الحالية</h4>
        <?php View::partial('attachment_list', [
          'attachments' => $attachments,
          'ownerType'   => 'transaction',
          'ownerId'     => (int) $transaction['id'],
          'readonly'    => true,
        ]); ?>
        <hr>
      <?php endif; ?>
      <?php View::partial('attachment_fields'); ?>
    </div>
    <div class="card-foot">
      <div class="form-actions">
        <button type="submit" class="btn">💾 <?= $isEdit ? 'حفظ التعديلات' : 'حفظ وقيد المعاملة' ?></button>
        <a class="btn outline" href="<?= e($isEdit ? url('transactions/show', ['id' => $transaction['id']]) : url('transactions/index', ['direction' => $direction])) ?>">إلغاء</a>
      </div>
    </div>
  </div>
</form>
