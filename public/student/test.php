<?php
declare(strict_types=1);
define('APP_AREA', 'student');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';
require APP_PATH . '/views/nafes.php';

$user = Auth::requireStudent();

// بدء اختبار جديد أو متابعة المحاولة الجارية
if (is_post() && input('action') === 'start') {
    csrf_verify();
    [$attemptId, $error] = Exams::start(input_int('test'), $user);
    if (!$attemptId) {
        flash('error', $error);
        redirect('student/tests.php');
    }
    redirect('student/test.php?attempt=' . $attemptId);
}

$attempt = Exams::attempt(input_int('attempt'));
if (!$attempt || (int) $attempt['student_id'] !== (int) $user['id']) {
    http_response_code(404);
    exit('المحاولة غير موجودة.');
}
if (Exams::finalizeIfExpired($attempt)) {
    flash('warning', 'انتهى الوقت المحدد وتم تسليم إجاباتك تلقائياً.');
}
if ($attempt['status'] !== 'in_progress' || Exams::isExpired($attempt)) {
    redirect('student/result.php?attempt=' . (int) $attempt['id']);
}

// التسليم (ويحفظ كل الإجابات المرسلة — يعمل أيضاً بدون جافاسكربت)
if (is_post() && input('action') === 'submit') {
    csrf_verify();
    foreach ((array) ($_POST['answer'] ?? []) as $questionId => $optionId) {
        Exams::saveAnswer($attempt, (int) $questionId, (int) $optionId);
    }
    Exams::submit((int) $attempt['id']);
    Activity::log('attempt_submit', 'nafes_attempts', (int) $attempt['id']);
    redirect('student/result.php?attempt=' . (int) $attempt['id']);
}

$questions   = Exams::attemptQuestions((int) $attempt['id']);
$secondsLeft = Exams::secondsLeft($attempt);
$answered    = count(array_filter($questions, static fn ($q) => $q['option_id'] !== null));
$title       = Exams::attemptTitle($attempt);

page_start($title, 'student', $attempt['mode'] === 'practice' ? 'practice' : 'tests');
?>
<form method="post" id="exam" class="exam" data-attempt="<?= (int) $attempt['id'] ?>"
      data-answer-url="<?= h(url('student/answer.php')) ?>" data-token="<?= h(csrf_token()) ?>"
      <?= $secondsLeft !== null ? 'data-seconds="' . $secondsLeft . '"' : '' ?>>
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="submit">

  <div class="exam-bar">
    <div>
      <strong><?= h($title) ?></strong>
      <small class="exam-progress">أجبت عن <b id="answered-count"><?= $answered ?></b> من <?= count($questions) ?></small>
    </div>
    <?php if ($secondsLeft !== null): ?>
      <div class="exam-timer" id="exam-timer" title="الوقت المتبقي">⏱ <span>--:--</span></div>
    <?php endif; ?>
    <span id="save-status" class="text-muted"></span>
  </div>

  <?php if ($attempt['instructions'] && $attempt['mode'] === 'test'): ?>
    <div class="alert alert-info"><?= nl2br_e($attempt['instructions']) ?></div>
  <?php endif; ?>

  <div class="exam-nav" id="exam-nav">
    <?php foreach ($questions as $i => $q): ?>
      <button type="button" data-goto="<?= $i ?>" class="<?= $q['option_id'] !== null ? 'answered' : '' ?> <?= (int) $q['flagged'] === 1 ? 'flagged' : '' ?>"><?= $i + 1 ?></button>
    <?php endforeach; ?>
  </div>

  <?php foreach ($questions as $i => $q): ?>
    <section class="tq card" data-index="<?= $i ?>" data-question="<?= (int) $q['question_id'] ?>">
      <div class="card-head">
        <h3>السؤال <?= $i + 1 ?> من <?= count($questions) ?></h3>
        <label class="flag-toggle"><input type="checkbox" data-flag <?= (int) $q['flagged'] === 1 ? 'checked' : '' ?>> ⚑ للمراجعة</label>
      </div>
      <div class="card-body">
        <?php question_body($q, $q['passage']); ?>
        <div class="q-options choose">
          <?php foreach ($q['options'] as $j => $option): ?>
            <label class="q-choice">
              <input type="radio" name="answer[<?= (int) $q['question_id'] ?>]" value="<?= (int) $option['id'] ?>" <?= (int) $q['option_id'] === (int) $option['id'] ? 'checked' : '' ?>>
              <span class="opt-letter"><?= option_letter($j) ?></span>
              <span><?= h($option['body']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="exam-steps">
        <button type="button" class="btn btn-outline" data-step="-1" <?= $i === 0 ? 'disabled' : '' ?>>→ السابق</button>
        <?php if ($i < count($questions) - 1): ?>
          <button type="button" class="btn btn-primary" data-step="1">التالي ←</button>
        <?php else: ?>
          <button type="button" class="btn btn-success" data-finish>إنهاء ومراجعة</button>
        <?php endif; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <div class="card exam-submit">
    <div class="card-body text-center">
      <p id="submit-summary" class="mb16">تأكد من إجاباتك قبل التسليم، لن تتمكن من التعديل بعده.</p>
      <button type="submit" class="btn btn-success" id="submit-btn">تسليم <?= $attempt['mode'] === 'practice' ? 'التدريب' : 'الاختبار' ?></button>
    </div>
  </div>
</form>
<?php page_end('student'); ?>
