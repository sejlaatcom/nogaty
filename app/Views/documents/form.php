<?php
$isEdit = !empty($document);
$v = static function (string $key, $default = '') use ($document) {
    $old = old($key, null);
    if ($old !== null && $old !== '') { return $old; }
    return $document[$key] ?? $default;
};
?>
<div class="page-head">
  <h1><?= e($title) ?></h1>
  <span class="spacer"></span>
  <a class="btn ghost" href="<?= e(url('documents/index')) ?>">↩︎ رجوع</a>
</div>

<form method="post" enctype="multipart/form-data" data-once
      action="<?= e($isEdit ? url('documents/update') : url('documents/store')) ?>">
  <?= Csrf::field() ?>
  <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $document['id'] ?>"><?php endif; ?>

  <div class="card">
    <div class="card-head"><h3>📄 بيانات الوثيقة</h3></div>
    <div class="card-body">
      <div class="form-grid">
        <div class="field full">
          <label>عنوان الوثيقة <span class="req">*</span></label>
          <input type="text" name="title" required maxlength="500" value="<?= e($v('title')) ?>"
                 placeholder="مثال : لائحة السلوك والمواظبة للعام 1447 هـ">
        </div>
        <div class="field">
          <label>مجلد الأرشفة <span class="req">*</span></label>
          <select name="folder_id" required>
            <option value="">— اختر المجلد —</option>
            <?php foreach ($lists['folders'] as $f): ?>
              <option value="<?= (int) $f['id'] ?>"
                <?= (int) $v('folder_id', $folderId) === (int) $f['id'] ? 'selected' : '' ?>><?= e($f['_label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>رقم الوثيقة</label>
          <input type="text" name="doc_no" maxlength="80" value="<?= e($v('doc_no')) ?>" dir="ltr">
        </div>
        <div class="field">
          <label>تاريخ الوثيقة</label>
          <input type="date" name="doc_date" value="<?= e($v('doc_date')) ?>">
        </div>
        <div class="field">
          <label>نوع الوثيقة</label>
          <input type="text" name="doc_type" maxlength="80" list="doc-types" value="<?= e($v('doc_type')) ?>"
                 placeholder="تعميم / لائحة / محضر / شهادة / كشف">
          <datalist id="doc-types">
            <?php foreach (['تعميم','لائحة','محضر اجتماع','قرار','شهادة','كشف درجات','خطاب','تقرير','نموذج','صورة','سجل'] as $t): ?>
              <option value="<?= e($t) ?>">
            <?php endforeach; ?>
          </datalist>
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
          <label>العام الدراسي</label>
          <select name="academic_year_id">
            <option value="">— غير محدد —</option>
            <?php foreach ($lists['years'] as $y):
              $sel = $isEdit ? ((int) $v('academic_year_id') === (int) $y['id']) : (bool) $y['is_current']; ?>
              <option value="<?= (int) $y['id'] ?>" <?= $sel ? 'selected' : '' ?>><?= e($y['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field full">
          <label>الوصف</label>
          <textarea name="description" rows="3"><?= e($v('description')) ?></textarea>
        </div>
        <div class="field full">
          <label>كلمات مفتاحية</label>
          <input type="text" name="keywords" maxlength="500" value="<?= e($v('keywords')) ?>"
                 placeholder="افصل بفاصلة لتسهيل البحث">
        </div>
        <div class="field full">
          <label>الوسوم</label>
          <input type="text" name="tags" value="<?= e(old('tags', $tags ?? '')) ?>" placeholder="وسوم مفصولة بفاصلة">
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <?php if ($isEdit && !empty($attachments)): ?>
        <h4>المرفقات الحالية</h4>
        <?php View::partial('attachment_list', [
          'attachments' => $attachments, 'ownerType' => 'document',
          'ownerId' => (int) $document['id'], 'readonly' => true,
        ]); ?>
        <hr>
      <?php endif; ?>
      <?php View::partial('attachment_fields'); ?>
    </div>
    <div class="card-foot">
      <div class="form-actions">
        <button type="submit" class="btn">💾 <?= $isEdit ? 'حفظ التعديلات' : 'حفظ الوثيقة' ?></button>
        <a class="btn outline" href="<?= e(url('documents/index')) ?>">إلغاء</a>
      </div>
    </div>
  </div>
</form>
