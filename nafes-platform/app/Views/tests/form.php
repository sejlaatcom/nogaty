<?php
use App\Services\TestEngine as TE;
$isEdit = (int) $test['id'] > 0;
$dtl = static fn (?string $v) => $v ? date('Y-m-d\TH:i', strtotime($v)) : '';
?>
<div class="page-header">
  <div><h1><?= e($title) ?></h1><div class="sub">الإعدادات العامة — الأسئلة والإسناد في الخطوة التالية</div></div>
  <a class="btn btn-light" href="<?= e(url($isEdit ? '/tests/' . $test['id'] : '/tests')) ?>">رجوع</a>
</div>

<form method="post" action="<?= e(url($isEdit ? '/tests/' . $test['id'] : '/tests')) ?>" data-once>
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-xl-8">
      <div class="card mb-3"><div class="card-header"><h2>بيانات الاختبار</h2></div>
        <div class="card-body row g-3">
          <div class="col-md-8"><label class="form-label required">اسم الاختبار</label><input class="form-control" name="title" maxlength="200" required value="<?= e(old('title', $test['title'])) ?>"></div>
          <div class="col-md-4"><label class="form-label required">النوع</label><select class="form-select" name="type" id="f-type">
            <?php foreach (TE::TYPES as $k => $v): if ($k === 'practice') continue; ?><option value="<?= $k ?>"<?= selected(old('type', $test['type']), $k) ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
          <div class="col-12"><label class="form-label">الوصف والتعليمات للطالب</label><textarea class="form-control" name="description" rows="3" placeholder="مثال: اقرأ كل سؤال بعناية، ويمكنك الرجوع للأسئلة قبل التسليم."><?= e(old('description', $test['description'] ?? '')) ?></textarea></div>
          <div class="col-md-6"><label class="form-label required">الصف</label><select class="form-select" name="grade_id" required <?= $isEdit && TE::hasAttempts((int) $test['id']) ? 'disabled' : '' ?>>
            <?php foreach ($grades as $g): ?><option value="<?= (int) $g['id'] ?>"<?= selected(old('grade_id', $test['grade_id']), $g['id']) ?>><?= e($g['name']) ?></option><?php endforeach; ?></select>
            <?php if ($isEdit && TE::hasAttempts((int) $test['id'])): ?><input type="hidden" name="grade_id" value="<?= (int) $test['grade_id'] ?>"><?php endif; ?></div>
          <div class="col-md-6"><label class="form-label">المادة</label><select class="form-select" name="subject_id">
            <option value="">— شامل لعدة مواد (المحاكي فقط) —</option>
            <?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected(old('subject_id', $test['subject_id']), $s['id']) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
        </div></div>

      <div class="card mb-3"><div class="card-header"><h2>الوقت والمحاولات</h2></div>
        <div class="card-body row g-3">
          <div class="col-md-4"><label class="form-label">المدة بالدقائق</label><input type="number" min="0" max="600" class="form-control" name="duration_minutes" value="<?= (int) old('duration_minutes', $test['duration_minutes']) ?>"><div class="form-text">0 = بدون مؤقت</div></div>
          <div class="col-md-4"><label class="form-label">متاح من</label><input type="datetime-local" class="form-control" name="available_from" value="<?= e(old('available_from', $dtl($test['available_from']))) ?>"></div>
          <div class="col-md-4"><label class="form-label">يغلق في</label><input type="datetime-local" class="form-control" name="available_until" value="<?= e(old('available_until', $dtl($test['available_until']))) ?>"></div>
          <div class="col-md-4"><label class="form-label">عدد المحاولات المسموحة</label><input type="number" min="0" max="20" class="form-control" name="max_attempts" value="<?= (int) old('max_attempts', $test['max_attempts']) ?>"><div class="form-text">0 = غير محدود؛ تُحتسب أعلى نتيجة.</div></div>
          <div class="col-md-8 d-flex flex-column justify-content-center gap-1">
            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="shuffle_questions" value="1" id="sq"<?= checked(old('shuffle_questions', $test['shuffle_questions'])) ?>><label class="form-check-label" for="sq">خلط ترتيب الأسئلة لكل طالب (أسئلة النص القرائي تبقى متتالية)</label></div>
            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="shuffle_options" value="1" id="so"<?= checked(old('shuffle_options', $test['shuffle_options'])) ?>><label class="form-check-label" for="so">خلط ترتيب الخيارات</label></div>
          </div>
        </div></div>
    </div>

    <div class="col-xl-4">
      <div class="card mb-3"><div class="card-header"><h2>النتيجة والمراجعة</h2></div>
        <div class="card-body">
          <label class="form-label">إظهار النتيجة للطالب</label>
          <?php foreach (TE::VISIBILITY as $k => $v): ?>
            <div class="form-check"><input class="form-check-input" type="radio" name="result_visibility" value="<?= $k ?>" id="rv-<?= $k ?>"<?= checked(old('result_visibility', $test['result_visibility']) === $k) ?>><label class="form-check-label" for="rv-<?= $k ?>"><?= e($v) ?></label></div>
          <?php endforeach; ?>
          <hr>
          <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="allow_review" value="1" id="ar"<?= checked(old('allow_review', $test['allow_review'])) ?>><label class="form-check-label" for="ar">السماح بمراجعة الإجابات والإجابة الصحيحة بعد ظهور النتيجة</label></div>
          <div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" name="show_explanations" value="1" id="se"<?= checked(old('show_explanations', $test['show_explanations'])) ?>><label class="form-check-label" for="se">إظهار تفسير الإجابة</label></div>
          <div class="alert alert-info small mt-3 mb-0"><i class="bi bi-shield-lock"></i> لا تُرسل الإجابات الصحيحة إلى جهاز الطالب أثناء الاختبار مطلقاً، وتظهر في المراجعة فقط بعد التسليم وحسب هذه الإعدادات.</div>
        </div></div>
      <button class="btn btn-primary w-100 btn-lg"><i class="bi bi-check2"></i> <?= $isEdit ? 'حفظ الإعدادات' : 'إنشاء ومتابعة لإضافة الأسئلة' ?></button>
    </div>
  </div>
</form>
