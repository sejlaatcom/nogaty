<?php
declare(strict_types=1);
define('APP_AREA', 'student');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';
require APP_PATH . '/views/nafes.php';

$user = Auth::requireStudent();
$mode = input('mode') === 'practice' ? 'practice' : 'test';

$attempts = Database::all(
    "SELECT a.*, t.title AS test_title, s.name AS subject_name, sk.name AS skill_name
       FROM nafes_attempts a
       JOIN subjects s      ON s.id = a.subject_id
  LEFT JOIN nafes_tests t   ON t.id = a.test_id
  LEFT JOIN nafes_skills sk ON sk.id = a.skill_id
      WHERE a.student_id = ? AND a.status = 'submitted' AND a.mode = ?
   ORDER BY a.submitted_at DESC",
    [$user['id'], $mode]
);

$bySubject = Database::all(
    "SELECT s.name, COUNT(*) AS n, AVG(a.percent) AS avg_percent, MAX(a.percent) AS best
       FROM nafes_attempts a JOIN subjects s ON s.id = a.subject_id
      WHERE a.student_id = ? AND a.status = 'submitted' AND a.mode = 'test'
   GROUP BY a.subject_id, s.name ORDER BY s.sort_order",
    [$user['id']]
);

page_start('نتائجي', 'student', 'results');
?>
<?php if ($bySubject): ?>
<div class="stats">
  <?php foreach ($bySubject as $row): ?>
    <div class="stat">
      <div class="num"><?= Nafes::pct((float) $row['avg_percent']) ?></div>
      <div class="lbl"><?= h($row['name']) ?> — متوسط <?= (int) $row['n'] ?> اختبار <?= Nafes::levelBadge((float) $row['avg_percent']) ?></div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="toolbar">
  <a class="btn <?= $mode === 'test' ? 'btn-primary' : 'btn-outline' ?>" href="?mode=test">الاختبارات</a>
  <a class="btn <?= $mode === 'practice' ? 'btn-primary' : 'btn-outline' ?>" href="?mode=practice">التدريب الذاتي</a>
</div>

<div class="card">
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th><?= $mode === 'test' ? 'الاختبار' : 'التدريب' ?></th><th>المادة</th><th>التاريخ</th><th>الدرجة</th><th>النسبة</th><th>المستوى</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($attempts as $a): ?>
          <tr>
            <td><strong><?= h($a['mode'] === 'test' ? $a['test_title'] : ($a['skill_name'] ?: 'تدريب شامل')) ?></strong></td>
            <td><?= h($a['subject_name']) ?></td>
            <td class="text-muted nowrap"><?= h(substr((string) $a['submitted_at'], 0, 16)) ?></td>
            <td><?= (int) $a['score'] ?> / <?= (int) $a['total'] ?></td>
            <td><?= Nafes::pct((float) $a['percent']) ?></td>
            <td><?= Nafes::levelBadge((float) $a['percent']) ?></td>
            <td><a class="btn btn-sm btn-outline" href="<?= h(url('student/result.php?attempt=' . (int) $a['id'])) ?>">التفاصيل</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$attempts): ?>
          <tr><td colspan="7" class="empty">لا توجد نتائج بعد.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php page_end('student'); ?>
