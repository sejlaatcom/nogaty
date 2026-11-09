<?php
declare(strict_types=1);
define('APP_AREA', 'nafes');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';
require APP_PATH . '/views/nafes.php';

$user = Auth::requireStaff();

$id       = input_int('id');
$question = $id ? QuestionBank::find($id) : null;
if ($id && !$question) {
    http_response_code(404);
    exit('السؤال غير موجود.');
}
$canEdit = !$question || QuestionBank::canEdit($user, $question);
$errors  = [];

if (is_post()) {
    csrf_verify();
    if (!$canEdit) {
        flash('error', 'لا يمكنك تعديل سؤال أضافه معلم آخر، يمكنك نسخه وتعديل النسخة.');
        redirect('nafes/question.php?id=' . $id);
    }

    $type    = input('type') === 'tf' ? 'tf' : 'mcq';
    $correct = input_int('correct', -1);
    $options = [];
    if ($type === 'tf') {
        $options[] = ['body' => 'صح', 'is_correct' => input('tf_correct') === '1'];
    } else {
        foreach ((array) ($_POST['options'] ?? []) as $index => $body) {
            $options[] = ['body' => is_string($body) ? $body : '', 'is_correct' => (int) $index === $correct];
        }
    }

    $data = [
        'subject_id'  => input_int('subject_id'),
        'grade_id'    => input_int('grade_id'),
        'skill_id'    => input_int('skill_id'),
        'passage_id'  => input_int('passage_id'),
        'type'        => $type,
        'stem'        => (string) ($_POST['stem'] ?? ''),
        'difficulty'  => input_int('difficulty', 2),
        'explanation' => (string) ($_POST['explanation'] ?? ''),
        'is_active'   => isset($_POST['is_active']),
    ];

    [$image, $imageError] = QuestionBank::uploadImage($_FILES['image'] ?? []);
    if ($imageError !== '') {
        $errors[] = $imageError;
    }
    $oldImage = $question['image'] ?? null;
    if ($image !== null) {
        $data['image'] = $image;
    } elseif (isset($_POST['remove_image'])) {
        $data['image'] = null;
    }

    if (!$errors) {
        [$savedId, $errors] = QuestionBank::save($id ?: null, $data, $options, (int) $user['id']);
    }

    if ($errors) {
        QuestionBank::removeImage($image);
        // إعادة عرض النموذج بالقيم المدخلة
        $question = array_merge($question ?? [], $data, [
            'options' => array_map(static fn ($o) => ['body' => $o['body'], 'is_correct' => $o['is_correct'] ? 1 : 0], $options),
            'image'   => $oldImage,
        ]);
    } else {
        if (array_key_exists('image', $data) && $oldImage && $oldImage !== $data['image']) {
            QuestionBank::removeImage($oldImage);
        }
        Activity::log($id ? 'question_update' : 'question_create', 'nafes_questions', $savedId);
        flash('success', $id ? 'تم حفظ السؤال.' : 'تمت إضافة السؤال إلى البنك.');
        if (input('next') === 'new') {
            redirect('nafes/question.php?subject=' . $data['subject_id'] . '&grade=' . $data['grade_id']
                . '&skill=' . $data['skill_id'] . '&passage=' . $data['passage_id'] . '&difficulty=' . $data['difficulty']);
        }
        redirect('nafes/question.php?id=' . $savedId);
    }
}

// نسخ سؤال (مثلاً سؤال لمعلم آخر) لتعديله كسؤال جديد
$copyId = input_int('copy');
if (!$question && $copyId && ($source = QuestionBank::find($copyId))) {
    $question = $source;
    unset($question['id'], $question['image']);
}

$q = $question ?? [
    'subject_id' => input_int('subject'), 'grade_id' => input_int('grade'), 'skill_id' => input_int('skill'),
    'passage_id' => input_int('passage'), 'type' => 'mcq', 'stem' => '', 'difficulty' => input_int('difficulty', 2) ?: 2,
    'explanation' => '', 'is_active' => 1, 'options' => [], 'image' => null,
];
$options = $q['options'] ?: [];
if ($q['type'] === 'mcq') {
    while (count($options) < 4) {
        $options[] = ['body' => '', 'is_correct' => 0];
    }
}
$tfTrue = $q['type'] === 'tf' && !empty($options[0]['is_correct']) && ($options[0]['body'] ?? '') === 'صح';

$subjects = Nafes::subjects();
$grades   = Nafes::grades();
$skills   = QuestionBank::allSkills();
$passages = QuestionBank::passages();

page_start($id ? 'تعديل سؤال #' . $id : 'سؤال جديد', nafes_area(), 'nafes_bank');
?>

<?php foreach ($errors as $error): ?>
  <div class="alert alert-error"><?= h($error) ?></div>
<?php endforeach; ?>

<?php if (!$canEdit): ?>
  <div class="alert alert-info">هذا السؤال أضافه معلم آخر — يمكنك عرضه أو
    <a href="<?= h(url('nafes/question.php?copy=' . $id)) ?>">إنشاء نسخة منه</a> لتعديلها.</div>
<?php endif; ?>

<div class="toolbar">
  <a class="btn btn-outline" href="<?= h(url('nafes/questions.php')) ?>">← بنك الأسئلة</a>
  <?php if ($id): ?>
    <a class="btn btn-outline" href="<?= h(url('nafes/question.php?copy=' . $id)) ?>">نسخ السؤال</a>
    <a class="btn btn-outline" href="<?= h(url('nafes/question.php?subject=' . (int) $q['subject_id'] . '&grade=' . (int) $q['grade_id'] . '&skill=' . (int) $q['skill_id'])) ?>">+ سؤال جديد بنفس المهارة</a>
  <?php endif; ?>
</div>

<div class="q-editor-grid">
<form method="post" enctype="multipart/form-data" class="card" id="question-form">
  <?= csrf_field() ?>
  <div class="card-body">
    <fieldset <?= $canEdit ? '' : 'disabled' ?> class="plain">
    <div class="grid grid-2">
      <div class="field"><label>المادة *</label><select name="subject_id" id="q-subject" required><?= options_html($subjects, (int) $q['subject_id']) ?></select></div>
      <div class="field"><label>الصف *</label><select name="grade_id" id="q-grade" required><?= options_html($grades, (int) $q['grade_id']) ?></select></div>
      <div class="field"><label>المهارة / ناتج التعلم</label><select name="skill_id" id="q-skill"><?= skill_options_html($skills, (int) $q['skill_id']) ?></select>
        <small class="help"><a href="<?= h(url('nafes/skills.php')) ?>" target="_blank">إدارة المهارات</a></small></div>
      <div class="field"><label>نص القراءة (اختياري)</label>
        <select name="passage_id" id="q-passage">
          <option value="0">— بدون نص —</option>
          <?php foreach ($passages as $passage): ?>
            <option value="<?= (int) $passage['id'] ?>" data-subject="<?= (int) $passage['subject_id'] ?>" data-grade="<?= (int) $passage['grade_id'] ?>"
              <?= (int) $q['passage_id'] === (int) $passage['id'] ? 'selected' : '' ?>><?= h($passage['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="field">
      <label>نوع السؤال</label>
      <div class="flex">
        <?php foreach (QuestionBank::TYPES as $key => $label): ?>
          <label class="flex"><input type="radio" name="type" value="<?= $key ?>" style="width:auto" <?= $q['type'] === $key ? 'checked' : '' ?> data-qtype> <?= h($label) ?></label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="field"><label>نص السؤال *</label><textarea name="stem" rows="4" data-grow required><?= h($q['stem']) ?></textarea></div>

    <div class="field">
      <label>صورة / شكل توضيحي (اختياري)</label>
      <?php if (!empty($q['image'])): ?>
        <div class="flex mb16"><img class="q-image small" src="<?= h(url('uploads/' . $q['image'])) ?>" alt="">
          <label class="flex"><input type="checkbox" name="remove_image" style="width:auto"> حذف الصورة</label></div>
      <?php endif; ?>
      <input type="file" name="image" accept="image/*">
    </div>

    <div class="field" data-type-block="mcq">
      <label>الخيارات (حدد الإجابة الصحيحة)</label>
      <div id="options-list">
        <?php foreach ($q['type'] === 'mcq' ? $options : array_fill(0, 4, ['body' => '', 'is_correct' => 0]) as $i => $option): ?>
          <div class="option-row">
            <label class="opt-correct" title="الإجابة الصحيحة"><input type="radio" name="correct" value="<?= $i ?>" <?= !empty($option['is_correct']) ? 'checked' : '' ?>></label>
            <span class="opt-letter"><?= option_letter($i) ?></span>
            <input type="text" name="options[<?= $i ?>]" value="<?= h($option['body']) ?>" placeholder="نص الخيار">
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-sm btn-outline" id="add-option">+ خيار</button>
    </div>

    <div class="field" data-type-block="tf">
      <label>الإجابة الصحيحة</label>
      <div class="flex">
        <label class="flex"><input type="radio" name="tf_correct" value="1" style="width:auto" <?= $tfTrue || $q['type'] !== 'tf' ? 'checked' : '' ?>> صح</label>
        <label class="flex"><input type="radio" name="tf_correct" value="0" style="width:auto" <?= $q['type'] === 'tf' && !$tfTrue ? 'checked' : '' ?>> خطأ</label>
      </div>
    </div>

    <div class="grid grid-2">
      <div class="field">
        <label>مستوى الصعوبة</label>
        <select name="difficulty">
          <?php foreach (QuestionBank::DIFFICULTIES as $value => $label): ?>
            <option value="<?= $value ?>" <?= (int) $q['difficulty'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>الحالة</label>
        <label class="flex"><input type="checkbox" name="is_active" style="width:auto" <?= (int) $q['is_active'] === 1 ? 'checked' : '' ?>> متاح للاستخدام في الاختبارات والتدريب</label>
      </div>
    </div>

    <div class="field"><label>التفسير / التغذية الراجعة (يظهر للطالب عند المراجعة)</label>
      <textarea name="explanation" rows="2" data-grow><?= h((string) $q['explanation']) ?></textarea></div>

    <div class="toolbar mb0">
      <button class="btn btn-primary" type="submit" name="next" value="stay">حفظ</button>
      <button class="btn btn-outline" type="submit" name="next" value="new">حفظ وإضافة سؤال آخر</button>
    </div>
    </fieldset>
  </div>
</form>

<?php if ($id && $question): ?>
<div class="card">
  <div class="card-head"><h2>معاينة كما يراها الطالب</h2><?= difficulty_badge((int) $question['difficulty']) ?></div>
  <div class="card-body">
    <?php question_body($question, $question['passage'] ?? null); ?>
    <ol class="q-options preview">
      <?php foreach (QuestionBank::options($id) as $i => $option): ?>
        <li class="<?= (int) $option['is_correct'] === 1 ? 'correct' : '' ?>"><span class="opt-letter"><?= option_letter($i) ?></span><?= h($option['body']) ?></li>
      <?php endforeach; ?>
    </ol>
    <?php if ($question['explanation']): ?><div class="q-explain"><b>التفسير:</b> <?= nl2br_e($question['explanation']) ?></div><?php endif; ?>
    <p class="help mt16 mb0">المهارة: <?= h($question['skill_name'] ? QuestionBank::skillLabel(['code' => $question['skill_code'], 'name' => $question['skill_name']]) : 'غير محددة') ?></p>
  </div>
</div>
<?php endif; ?>
</div>

<?php page_end(nafes_area()); ?>
