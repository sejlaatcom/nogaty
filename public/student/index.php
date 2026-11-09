<?php
declare(strict_types=1);
define('APP_AREA', 'student');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';
require APP_PATH . '/views/nafes.php';

$user      = Auth::requireStudent();
$classroom = $user['classroom_id'] ? Plans::classroom((int) $user['classroom_id']) : null;
$tests     = Exams::forStudent($user);
$open      = array_filter($tests, static fn ($t) => in_array($t['state'], ['available', 'in_progress'], true));
$summary   = Database::first(
    "SELECT COUNT(*) AS n, AVG(percent) AS avg_percent,
            SUM(mode = 'practice') AS practice_count, SUM(CASE WHEN mode = 'practice' THEN total ELSE 0 END) AS practice_questions
       FROM nafes_attempts WHERE student_id = ? AND status = 'submitted'",
    [$user['id']]
);
$last = Database::first(
    "SELECT a.*, t.title AS test_title, s.name AS subject_name, NULL AS skill_name
       FROM nafes_attempts a JOIN subjects s ON s.id = a.subject_id LEFT JOIN nafes_tests t ON t.id = a.test_id
      WHERE a.student_id = ? AND a.status = 'submitted' AND a.mode = 'test' ORDER BY a.submitted_at DESC LIMIT 1",
    [$user['id']]
);

// أضعف المهارات للتوصية بالتدريب
$weakSkills = [];
foreach (Exams::studentSkillStats((int) $user['id']) as $skillId => $stat) {
    if ($stat['percent'] < (float) Settings::get('nafes_level_proficient')) {
        $skill = QuestionBank::skill($skillId);
        if ($skill) {
            $weakSkills[] = $skill + $stat;
        }
    }
}
usort($weakSkills, static fn ($a, $b) => $a['percent'] <=> $b['percent']);
$weakSkills = array_slice($weakSkills, 0, 5);

page_start('الرئيسية', 'student', 'index');
?>
<div class="hero">
  <h1>أهلاً <?= h($user['name']) ?> 👋</h1>
  <p><?= h(Settings::get('nafes_title')) ?></p>
  <div class="hero-meta">
    <span><?= h($classroom ? Plans::classLabel($classroom) : 'لم يُحدَّد الفصل') ?></span>
    <span><?= h(Settings::get('school_name')) ?></span>
  </div>
</div>

<?php if (!$classroom): ?>
  <div class="alert alert-warning">لم يتم تحديد فصلك الدراسي بعد، يرجى مراجعة إدارة المدرسة.</div>
<?php endif; ?>

<div class="stats">
  <div class="stat teal"><div class="num"><?= count($open) ?></div><div class="lbl">اختبارات متاحة لك الآن</div></div>
  <div class="stat"><div class="num"><?= $summary['avg_percent'] !== null ? Nafes::pct((float) $summary['avg_percent']) : '—' ?></div><div class="lbl">متوسط نتائجك</div></div>
  <div class="stat green"><div class="num"><?= (int) $summary['practice_count'] ?></div><div class="lbl">تدريب ذاتي (<?= (int) $summary['practice_questions'] ?> سؤالاً)</div></div>
</div>

<?php if ($open): ?>
<div class="card">
  <div class="card-head"><h2>بانتظارك</h2><a class="btn btn-sm btn-outline" href="<?= h(url('student/tests.php')) ?>">كل الاختبارات</a></div>
  <div class="card-body">
    <?php foreach ($open as $test): ?>
      <div class="practice-row">
        <div><strong><?= h($test['title']) ?></strong> <span class="badge badge-teal"><?= h($test['subject_name']) ?></span>
          <?php if ($test['ends_at']): ?><br><small>يُغلق في <?= h(substr($test['ends_at'], 0, 16)) ?></small><?php endif; ?></div>
        <a class="btn btn-sm btn-primary" href="<?= h(url('student/tests.php')) ?>"><?= $test['state'] === 'in_progress' ? 'متابعة' : 'ابدأ' ?></a>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="grid grid-2">
  <div class="card">
    <div class="card-head"><h2>آخر نتيجة</h2></div>
    <div class="card-body text-center">
      <?php if ($last): ?>
        <?= score_ring((float) $last['percent'], (int) $last['score'] . ' من ' . (int) $last['total']) ?>
        <p class="mb0"><b><?= h($last['test_title']) ?></b> — <?= h($last['subject_name']) ?> <?= Nafes::levelBadge((float) $last['percent']) ?></p>
        <a href="<?= h(url('student/result.php?attempt=' . (int) $last['id'])) ?>">عرض التفاصيل</a>
      <?php else: ?>
        <p class="empty">لم تختبر بعد.</p>
      <?php endif; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><h2>مهارات أنصحك بالتدرب عليها</h2></div>
    <div class="card-body">
      <?php foreach ($weakSkills as $skill): ?>
        <div class="practice-row">
          <div class="practice-skill"><?= h($skill['name']) ?><br><small><?= Nafes::pct($skill['percent']) ?> <?= Nafes::levelBadge($skill['percent']) ?></small></div>
          <form method="post" action="<?= h(url('student/practice.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="subject" value="<?= (int) $skill['subject_id'] ?>">
            <input type="hidden" name="skill" value="<?= (int) $skill['id'] ?>">
            <button class="btn btn-sm btn-outline">تدرّب</button>
          </form>
        </div>
      <?php endforeach; ?>
      <?php if (!$weakSkills): ?>
        <p class="text-muted mb0">ستظهر هنا المهارات التي تحتاج إلى تدريب بعد أن تؤدي اختباراً أو تدريباً.
          <a href="<?= h(url('student/practice.php')) ?>">ابدأ التدريب الذاتي</a>.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2>ما هي اختبارات نافس؟</h2></div>
  <div class="card-body">
    <p>اختبارات «نافس» الوطنية تقيس مدى تمكنك من المهارات الأساسية في <b>القراءة والرياضيات والعلوم</b>.
       في هذه المنصة تجد اختبارات تدريبية يعدّها معلموك، وتدريباً ذاتياً على كل مهارة، ونتائج توضّح مستواك ونقاط القوة والتحسين.</p>
  </div>
</div>
<?php page_end('student'); ?>
