<?php
use App\Services\QuestionBank as QB;
$isEdit = (int) $q['id'] > 0;
$options = $q['options'] ?: [];
if ($q['type'] === 'short_answer') {
    $accepted = implode("\n", array_column($options, 'body'));
    $options = [];
}
if ($q['type'] === 'true_false') {
    $tfTrue = !$options || !empty($options[0]['is_correct']) && ($options[0]['body'] ?? 'صح') === 'صح';
    $options = [];
}
while (count($options) < 4) {
    $options[] = ['body' => '', 'match_text' => '', 'is_correct' => 0, 'feedback' => ''];
}
?>
<div class="page-header">
  <div><h1><?= e($title) ?></h1><?php if ($isEdit): ?><div class="sub">النسخة الحالية <?= (int) $q['version'] ?> — يُحفظ سجل بالنسخة السابقة عند كل تعديل</div><?php endif; ?></div>
  <a class="btn btn-light" href="<?= e(url($isEdit ? '/questions/' . $q['id'] : '/questions')) ?>">رجوع</a>
</div>

<form method="post" enctype="multipart/form-data" action="<?= e(url($isEdit ? '/questions/' . $q['id'] : '/questions')) ?>" id="qform" data-once
      data-skill="<?= (int) $q['skill_id'] ?>" data-indicator="<?= (int) $q['indicator_id'] ?>" data-passage="<?= (int) $q['passage_id'] ?>">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-xl-8">
      <div class="card mb-3">
        <div class="card-header"><h2>التصنيف</h2></div>
        <div class="card-body row g-3">
          <div class="col-md-4"><label class="form-label required">الصف</label><select class="form-select" name="grade_id" id="f-grade" required>
            <?php foreach ($grades as $g): ?><option value="<?= (int) $g['id'] ?>"<?= selected($q['grade_id'], $g['id']) ?>><?= e($g['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-4"><label class="form-label required">المادة</label><select class="form-select" name="subject_id" id="f-subject" required>
            <?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected($q['subject_id'], $s['id']) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-4"><label class="form-label">النص القرائي</label><select class="form-select" name="passage_id" id="f-passage"><option value="">— بدون —</option></select></div>
          <div class="col-md-7"><label class="form-label">المجال ← المهارة</label><select class="form-select" name="skill_id" id="f-skill"><option value="">— بدون مهارة —</option></select></div>
          <div class="col-md-5"><label class="form-label">مؤشر الأداء</label><select class="form-select" name="indicator_id" id="f-indicator"><option value="">— بدون —</option></select></div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><h2>السؤال</h2>
          <select class="form-select form-select-sm w-auto" name="type" id="f-type">
            <?php foreach (QB::TYPES as $k => $v): ?><option value="<?= $k ?>"<?= selected($q['type'], $k) ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
        <div class="card-body">
          <div class="btn-toolbar gap-1 mb-2" data-insert-target="#f-stem">
            <?php foreach (['**غامق**' => '<b>B</b>', '\\frac{أ}{ب}' => 'أ/ب', '\\sqrt{س}' => '√', 'س^{2}' => 'x²', 'س_{1}' => 'x₁', '×' => '×', '÷' => '÷', '±' => '±', '≤' => '≤', '≥' => '≥', '≠' => '≠', 'π' => 'π', '°' => '°', '√' => '√'] as $ins => $label): ?>
              <button type="button" class="btn btn-sm btn-light" data-insert="<?= e($ins) ?>"><?= $label ?></button>
            <?php endforeach; ?>
            <button type="button" class="btn btn-sm btn-light" data-insert="<?= e("\n| العنوان 1 | العنوان 2 |\n| --- | --- |\n| قيمة | قيمة |\n") ?>"><i class="bi bi-table"></i> جدول</button>
          </div>
          <textarea class="form-control mb-1" name="stem" id="f-stem" rows="4" required><?= e($q['stem']) ?></textarea>
          <div class="form-text mb-3">يدعم الكسور والجذور والأسس والجداول والنص الغامق — اضغط «معاينة» لرؤية الشكل النهائي.</div>

          <!-- صح أو خطأ -->
          <div data-for="true_false" class="mb-3">
            <label class="form-label">الإجابة الصحيحة</label>
            <div class="d-flex gap-3">
              <div class="form-check"><input class="form-check-input" type="radio" name="tf" value="1" id="tf1"<?= checked($tfTrue ?? true) ?>><label class="form-check-label" for="tf1">العبارة صحيحة</label></div>
              <div class="form-check"><input class="form-check-input" type="radio" name="tf" value="0" id="tf0"<?= checked(!($tfTrue ?? true)) ?>><label class="form-check-label" for="tf0">العبارة خاطئة</label></div>
            </div>
          </div>

          <!-- إجابة قصيرة -->
          <div data-for="short_answer" class="mb-3">
            <label class="form-label">الإجابات المقبولة (سطر لكل إجابة)</label>
            <textarea class="form-control" name="accepted" rows="3" placeholder="مثال: 24"><?= e($accepted ?? '') ?></textarea>
            <div class="form-text">تُقارن بعد توحيد الهمزات والتشكيل والأرقام العربية.</div>
            <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="manual_grading" value="1" id="mg"<?= checked($q['manual_grading']) ?>><label class="form-check-label" for="mg">تصحيح يدوي دائماً (للإجابات المفتوحة)</label></div>
          </div>

          <!-- الخيارات -->
          <div data-for="single multiple matching ordering">
            <label class="form-label" data-label-for="single">الخيارات — حدد الإجابة الصحيحة</label>
            <label class="form-label" data-label-for="multiple">الخيارات — حدد كل الإجابات الصحيحة</label>
            <label class="form-label" data-label-for="matching">أزواج التوصيل — العنصر ومقابله الصحيح (تُخلط المقابلات للطالب)</label>
            <label class="form-label" data-label-for="ordering">العناصر بترتيبها الصحيح من الأول إلى الأخير (تُخلط للطالب)</label>
            <div id="options" data-type="<?= e($q['type']) ?>">
              <?php foreach ($options as $i => $o): ?>
                <div class="opt-row border rounded-3 p-2 mb-2">
                  <div class="d-flex gap-2 align-items-center">
                    <input class="form-check-input c-single" type="radio" name="correct" value="<?= $i ?>"<?= checked(!empty($o['is_correct']) && $q['type'] === 'single') ?> title="الإجابة الصحيحة">
                    <input class="form-check-input c-multi" type="checkbox" name="correct_multi[]" value="<?= $i ?>"<?= checked(!empty($o['is_correct']) && $q['type'] === 'multiple') ?> title="إجابة صحيحة">
                    <span class="opt-letter num"><?= $i + 1 ?></span>
                    <input class="form-control o-body" name="opt[<?= $i ?>][body]" value="<?= e($o['body']) ?>" placeholder="نص الخيار">
                    <input class="form-control o-match" name="opt[<?= $i ?>][match]" value="<?= e($o['match_text'] ?? '') ?>" placeholder="المقابل الصحيح">
                    <button type="button" class="btn btn-sm btn-light" data-opt-move="-1" title="أعلى"><i class="bi bi-arrow-up"></i></button>
                    <button type="button" class="btn btn-sm btn-light" data-opt-move="1" title="أسفل"><i class="bi bi-arrow-down"></i></button>
                    <button type="button" class="btn btn-sm btn-light text-danger" data-opt-remove title="حذف"><i class="bi bi-x-lg"></i></button>
                  </div>
                  <input class="form-control form-control-sm mt-1 o-feedback" name="opt[<?= $i ?>][feedback]" value="<?= e($o['feedback'] ?? '') ?>" placeholder="تفسير الخطأ الشائع عند اختيار هذا البديل (اختياري)">
                </div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm btn-soft" id="add-opt"><i class="bi bi-plus-lg"></i> إضافة</button>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><h2>التفسير والمرفقات</h2></div>
        <div class="card-body row g-3">
          <div class="col-12"><label class="form-label">تفسير الإجابة</label><textarea class="form-control" name="explanation" rows="2"><?= e($q['explanation']) ?></textarea></div>
          <div class="col-12"><label class="form-label">الأخطاء الشائعة (للمعلم وللطالب عند المراجعة)</label><textarea class="form-control" name="common_mistakes" rows="2"><?= e($q['common_mistakes']) ?></textarea></div>
          <div class="col-md-8"><label class="form-label">صور / رسوم بيانية / جداول مصورة</label><input type="file" class="form-control" name="media[]" accept="image/png,image/jpeg,image/gif,image/webp" multiple></div>
          <div class="col-md-4"><label class="form-label">وصف الصورة (لإمكانية الوصول)</label><input class="form-control" name="media_alt"></div>
          <?php if ($q['media']): ?><div class="col-12 d-flex gap-2 flex-wrap"><?php foreach ($q['media'] as $m): ?>
            <label class="border rounded p-1 text-center small"><img src="<?= e(url('/media/' . $m['id'])) ?>" style="max-height:100px" alt=""><br><input type="checkbox" name="remove_media[]" value="<?= (int) $m['id'] ?>"> حذف</label>
          <?php endforeach; ?></div><?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-xl-4">
      <div class="card mb-3" style="position:sticky;top:80px">
        <div class="card-header"><h2>الخصائص</h2></div>
        <div class="card-body">
          <div class="row g-2">
            <div class="col-6"><label class="form-label">الصعوبة</label><select class="form-select" name="difficulty"><?php foreach (QB::DIFFICULTIES as $k => $v): ?><option value="<?= $k ?>"<?= selected($q['difficulty'], $k) ?>><?= $v ?></option><?php endforeach; ?></select></div>
            <div class="col-6"><label class="form-label">المستوى المعرفي</label><select class="form-select" name="cognitive_level"><?php foreach (QB::COGNITIVE as $k => $v): ?><option value="<?= $k ?>"<?= selected($q['cognitive_level'], $k) ?>><?= $v ?></option><?php endforeach; ?></select></div>
            <div class="col-6"><label class="form-label">الدرجة</label><input type="number" step="0.25" min="0.25" max="100" class="form-control" name="points" value="<?= e((string) (float) $q['points']) ?>"></div>
            <div class="col-6"><label class="form-label">الحالة</label><select class="form-select" name="status">
              <option value="draft"<?= selected($q['status'], 'draft') ?>>مسودة</option>
              <option value="review"<?= selected($q['status'], 'review') ?>>إرسال للمراجعة</option>
              <option value="published"<?= selected($q['status'], 'published') ?>><?= $canPublish ? 'منشور' : 'منشور (بعد المراجعة)' ?></option>
            </select></div>
            <div class="col-12"><label class="form-label">مصدر السؤال</label><input class="form-control" name="source" value="<?= e($q['source']) ?>" placeholder="من إعداد المعلم / كتاب ... "></div>
            <div class="col-12"><label class="form-label">حقوق الاستخدام</label><input class="form-control" name="usage_rights" value="<?= e($q['usage_rights']) ?>" placeholder="داخلي للمدرسة / مرخص ..."></div>
            <?php if ($isEdit): ?><div class="col-12"><label class="form-label">ملاحظة التعديل</label><input class="form-control" name="change_note" placeholder="سبب التعديل (يظهر في سجل النسخ)"></div><?php endif; ?>
            <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="allow_duplicate" value="1" id="ad"><label class="form-check-label small" for="ad">السماح بالحفظ رغم وجود سؤال مطابق</label></div></div>
          </div>
          <hr>
          <button type="button" class="btn btn-soft w-100 mb-2" id="preview-btn"><i class="bi bi-eye"></i> معاينة قبل الحفظ</button>
          <button class="btn btn-primary w-100 mb-2" name="next" value="show"><i class="bi bi-check2"></i> حفظ</button>
          <button class="btn btn-outline-primary w-100" name="next" value="new">حفظ وإضافة سؤال آخر</button>
        </div>
      </div>
    </div>
  </div>
</form>

<div class="modal fade" id="previewModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">معاينة السؤال</h5><button type="button" class="btn-close ms-0 me-auto" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="row g-3">
    <div class="col-lg-6"><h6 class="text-muted">كما يراه الطالب</h6><div class="border rounded-3 p-3" id="pv-student"></div></div>
    <div class="col-lg-6"><h6 class="text-muted">للمعلم (مع الإجابة)</h6><div class="border rounded-3 p-3" id="pv-teacher"></div></div>
  </div></div>
</div></div></div>
<?php \App\Core\View::push('scripts', '<script src="' . asset('js/question-form.js') . '"></script>'); ?>
