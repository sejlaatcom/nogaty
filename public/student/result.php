<?php
declare(strict_types=1);
define('APP_AREA', 'student');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';
require APP_PATH . '/views/nafes.php';

$user    = Auth::requireStudent();
$attempt = Exams::attempt(input_int('attempt'));
if (!$attempt || (int) $attempt['student_id'] !== (int) $user['id']) {
    http_response_code(404);
    exit('النتيجة غير موجودة.');
}
Exams::finalizeIfExpired($attempt);
$attempt = Exams::attempt((int) $attempt['id']);
if ($attempt['status'] !== 'submitted') {
    redirect('student/test.php?attempt=' . (int) $attempt['id']);
}

$percent = (float) $attempt['percent'];
$message = match (Nafes::levelKey($percent)) {
    'advanced'   => 'أداء متميز! أنت في المستوى المتقدم، استمر على هذا التفوق 🌟',
    'proficient' => 'أحسنت! أداؤك متمكن، راجع المهارات الأقل لتصل إلى المستوى المتقدم.',
    'basic'      => 'بداية جيدة، تدرّب على المهارات الضعيفة لترفع مستواك.',
    default      => 'تحتاج إلى مزيد من التدريب — ابدأ بالمهارات الموضحة أدناه وستلاحظ الفرق.',
};

page_start('النتيجة: ' . Exams::attemptTitle($attempt), 'student', $attempt['mode'] === 'practice' ? 'practice' : 'results');
?>
<div class="alert alert-info"><?= h($message) ?></div>

<div class="toolbar">
  <a class="btn btn-outline" href="<?= h(url('student/results.php')) ?>">← نتائجي</a>
  <?php if ($attempt['mode'] === 'practice'): ?>
    <form method="post" action="<?= h(url('student/practice.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="subject" value="<?= (int) $attempt['subject_id'] ?>">
      <input type="hidden" name="skill" value="<?= (int) $attempt['skill_id'] ?>">
      <button class="btn btn-primary">تدريب جديد على نفس المهارة</button>
    </form>
  <?php endif; ?>
</div>

<?php render_attempt_result($attempt, Exams::canReview($attempt)); ?>
<?php if (!Exams::canReview($attempt)): ?>
  <div class="alert alert-warning">مراجعة الإجابات الصحيحة غير متاحة لهذا الاختبار.</div>
<?php endif; ?>

<?php page_end('student'); ?>
