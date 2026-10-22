<?php
declare(strict_types=1);
define('APP_AREA', 'nafes');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';
require APP_PATH . '/views/nafes.php';

$user    = Auth::requireStaff();
$attempt = Exams::attempt(input_int('id'));
$student = $attempt ? Nafes::student((int) $attempt['student_id']) : null;
if (!$attempt || !$student || !Nafes::canAccessClassroom($user, (int) $student['classroom_id'])) {
    http_response_code(404);
    exit('المحاولة غير موجودة أو لا تملك صلاحية عرضها.');
}
Exams::finalizeIfExpired($attempt);
$attempt = Exams::attempt((int) $attempt['id']);

page_start($student['name'] . ' — ' . Exams::attemptTitle($attempt), nafes_area(), $attempt['test_id'] ? 'nafes_tests' : 'nafes_reports');
?>
<div class="toolbar no-print">
  <?php if ($attempt['test_id']): ?>
    <a class="btn btn-outline" href="<?= h(url('nafes/test_results.php?id=' . (int) $attempt['test_id'])) ?>">← نتائج الاختبار</a>
  <?php endif; ?>
  <a class="btn btn-outline" href="<?= h(url('nafes/student_report.php?id=' . (int) $student['id'])) ?>">تقرير الطالب</a>
  <button class="btn btn-outline" data-print>طباعة</button>
</div>
<p><b><?= h($student['name']) ?></b> — <?= h(Nafes::classOf($student)) ?> · بدأ <?= h((string) $attempt['started_at']) ?></p>

<?php if ($attempt['status'] !== 'submitted'): ?>
  <div class="alert alert-warning">الطالب لم يسلّم هذه المحاولة بعد.</div>
<?php else: ?>
  <?php render_attempt_result($attempt, true); ?>
<?php endif; ?>
<?php page_end(nafes_area()); ?>
