<?php
declare(strict_types=1);
define('APP_AREA', 'student');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';
require APP_PATH . '/views/nafes.php';

$user = Auth::requireStudent();

if (is_post()) {
    csrf_verify();
    [$attemptId, $error] = Exams::startPractice($user, input_int('subject'), input_int('skill'));
    if (!$attemptId) {
        flash('error', $error);
        redirect('student/practice.php');
    }
    redirect('student/test.php?attempt=' . $attemptId);
}

$classroom = $user['classroom_id'] ? Plans::classroom((int) $user['classroom_id']) : null;
$subjects  = Nafes::subjects();
$stats     = Exams::studentSkillStats((int) $user['id']);

// عدد الأسئلة المتاحة لكل مهارة في صف الطالب
$available = [];
if ($classroom) {
    foreach (Database::all(
        'SELECT subject_id, skill_id, COUNT(*) AS n FROM nafes_questions WHERE grade_id = ? AND is_active = 1 GROUP BY subject_id, skill_id',
        [$classroom['grade_id']]
    ) as $row) {
        $available[(int) $row['subject_id']][(int) $row['skill_id']] = (int) $row['n'];
    }
}

page_start('التدريب الذاتي', 'student', 'practice');
?>
<div class="alert alert-info">
  اختر مادة أو مهارة وتدرّب عليها بأسئلة قصيرة (<?= (int) Settings::get('nafes_practice_size') ?> أسئلة) بدون وقت محدد،
  وستظهر لك الإجابات الصحيحة والتفسير بعد كل تدريب. الأسئلة التي لم تتقنها تظهر لك أولاً.
</div>

<?php if (!$classroom): ?>
  <div class="alert alert-warning">لم يتم تحديد فصلك الدراسي بعد، يرجى مراجعة إدارة المدرسة.</div>
<?php else: ?>
  <?php foreach ($subjects as $subject): ?>
    <?php
    $subjectId = (int) $subject['id'];
    $count     = array_sum($available[$subjectId] ?? []);
    if ($count === 0) { continue; }
    $domains = QuestionBank::domainsWithSkills($subjectId, (int) $classroom['grade_id']);
    ?>
    <div class="card">
      <div class="card-head">
        <h2><?= h($subject['name']) ?></h2>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="subject" value="<?= $subjectId ?>">
          <button class="btn btn-primary">تدريب شامل على المادة</button>
        </form>
      </div>
      <div class="card-body">
        <?php foreach ($domains as $domain): ?>
          <?php $skills = array_filter($domain['skills'], static fn ($s) => !empty($available[$subjectId][(int) $s['id']])); ?>
          <?php if (!$skills) { continue; } ?>
          <h4 class="mt16"><?= h($domain['name']) ?></h4>
          <?php foreach ($skills as $skill): ?>
            <?php $stat = $stats[(int) $skill['id']] ?? null; ?>
            <div class="practice-row">
              <div class="practice-skill">
                <?= h($skill['name']) ?>
                <?php if ($stat): ?>
                  <div class="progress"><span class="lv-<?= Nafes::levelKey($stat['percent']) ?>" style="width:<?= max(2, (int) $stat['percent']) ?>%"></span></div>
                  <small>إتقانك: <?= Nafes::pct($stat['percent']) ?> (<?= $stat['correct'] ?> من <?= $stat['total'] ?>) <?= Nafes::levelBadge($stat['percent']) ?></small>
                <?php else: ?>
                  <br><small class="text-muted">لم تتدرب عليها بعد</small>
                <?php endif; ?>
              </div>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="subject" value="<?= $subjectId ?>">
                <input type="hidden" name="skill" value="<?= (int) $skill['id'] ?>">
                <button class="btn btn-sm btn-outline">تدرّب</button>
              </form>
            </div>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$available): ?>
    <div class="card"><div class="card-body empty">لا توجد أسئلة تدريبية لصفك حتى الآن.</div></div>
  <?php endif; ?>
<?php endif; ?>

<?php page_end('student'); ?>
