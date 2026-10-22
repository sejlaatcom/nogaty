<?php
declare(strict_types=1);
define('APP_AREA', 'nafes');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';
require APP_PATH . '/views/nafes.php';

$user = Auth::requireStaff();
$test = Exams::find(input_int('id'));
if (!$test || !Exams::canView($user, $test)) {
    http_response_code(404);
    exit('الاختبار غير موجود.');
}
$withKey   = input('key') === '1';
$questions = Exams::questions((int) $test['id']);
$options   = QuestionBank::optionsFor(array_column($questions, 'id'));

page_start($test['title'], 'print');
?>
<div class="no-print toolbar paper-toolbar">
  <button class="btn btn-primary" data-print>طباعة / حفظ PDF</button>
  <a class="btn btn-outline" href="?id=<?= (int) $test['id'] ?>&key=<?= $withKey ? '0' : '1' ?>"><?= $withKey ? 'إخفاء الإجابات' : 'إظهار مفتاح الإجابات' ?></a>
</div>

<div class="paper">
  <div class="paper-head">
    <div><?= h(Settings::get('ministry_name')) ?><br><?= h(Settings::get('education_dept')) ?><br><?= h(Settings::get('school_name')) ?></div>
    <div class="text-center">
      <h2><?= h($test['title']) ?></h2>
      <div><?= h($test['subject_name'] . ' — ' . $test['grade_name']) ?> · <?= count($questions) ?> سؤالاً<?= (int) $test['duration_min'] > 0 ? ' · الزمن ' . (int) $test['duration_min'] . ' دقيقة' : '' ?></div>
    </div>
    <div>اسم الطالب: ..............................<br>الفصل: ............<br>الدرجة: ........ / <?= count($questions) ?></div>
  </div>
  <?php if ($test['instructions']): ?><p class="paper-instr"><?= nl2br_e($test['instructions']) ?></p><?php endif; ?>

  <?php $lastPassage = null; ?>
  <?php foreach ($questions as $i => $question): ?>
    <?php if ($question['passage_id'] && $question['passage_id'] !== $lastPassage): ?>
      <?php $passage = QuestionBank::passage((int) $question['passage_id']); ?>
      <div class="paper-passage"><b><?= h($passage['title']) ?></b><br><?= nl2br_e($passage['body']) ?></div>
    <?php endif; ?>
    <?php $lastPassage = $question['passage_id']; ?>
    <div class="paper-q">
      <div class="q-stem"><b><?= $i + 1 ?>)</b> <?= nl2br_e($question['stem']) ?></div>
      <?php if ($question['image']): ?><img class="q-image" src="<?= h(url('uploads/' . $question['image'])) ?>" alt=""><?php endif; ?>
      <div class="paper-options">
        <?php foreach ($options[(int) $question['id']] ?? [] as $j => $option): ?>
          <span class="<?= $withKey && (int) $option['is_correct'] === 1 ? 'key' : '' ?>"><b><?= option_letter($j) ?>)</b> <?= h($option['body']) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<script src="<?= asset('js/app.js') ?>?v=2"></script>
<?php page_end('print'); ?>
