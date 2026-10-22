<?php
declare(strict_types=1);
define('APP_AREA', 'student');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';
require APP_PATH . '/views/nafes.php';

$user  = Auth::requireStudent();
$tests = Exams::forStudent($user);

$groups = ['in_progress' => [], 'available' => [], 'upcoming' => [], 'done' => []];
foreach ($tests as $test) {
    $groups[$test['state'] === 'ended' ? 'done' : $test['state']][] = $test;
}
$titles = [
    'in_progress' => 'اختبارات بدأتها ولم تسلّمها',
    'available'   => 'اختبارات متاحة الآن',
    'upcoming'    => 'اختبارات قادمة',
    'done'        => 'اختبارات منتهية',
];

page_start('اختباراتي', 'student', 'tests');
?>

<?php if (!$tests): ?>
  <div class="card"><div class="card-body empty">لا توجد اختبارات لفصلك حالياً. يمكنك التدرب ذاتياً من صفحة
    <a href="<?= h(url('student/practice.php')) ?>">التدريب الذاتي</a>.</div></div>
<?php endif; ?>

<?php foreach ($groups as $state => $items): ?>
  <?php if (!$items) { continue; } ?>
  <h2 class="mt16"><?= h($titles[$state]) ?></h2>
  <div class="test-cards mb16">
    <?php foreach ($items as $test): ?>
      <?php $best = null; foreach ($test['attempts'] as $a) { if ($a['status'] === 'submitted') { $best = max($best ?? 0, (float) $a['percent']); } } ?>
      <div class="test-card">
        <h3><?= h($test['title']) ?></h3>
        <div class="meta">
          <span class="badge badge-teal"><?= h($test['subject_name']) ?></span>
          <span><?= h(Exams::KINDS[$test['kind']] ?? '') ?></span>
          <span>· <?= (int) $test['questions_count'] ?> سؤالاً</span>
          <span>· <?= (int) $test['duration_min'] > 0 ? (int) $test['duration_min'] . ' دقيقة' : 'بدون وقت محدد' ?></span>
        </div>
        <?php if ($test['ends_at'] && $state !== 'done'): ?><small>يُغلق في: <?= h(substr($test['ends_at'], 0, 16)) ?></small><?php endif; ?>
        <?php if ($state === 'upcoming'): ?><small>يبدأ في: <?= h(substr((string) $test['starts_at'], 0, 16)) ?></small><?php endif; ?>
        <div class="actions">
          <?php if (in_array($state, ['available', 'in_progress'], true)): ?>
            <form method="post" action="<?= h(url('student/test.php')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="start">
              <input type="hidden" name="test" value="<?= (int) $test['id'] ?>">
              <button class="btn btn-primary" <?= $state === 'available' ? 'data-confirm="بدء الاختبار الآن؟' . ((int) $test['duration_min'] > 0 ? ' سيبدأ احتساب الوقت (' . (int) $test['duration_min'] . ' دقيقة).' : '') . '"' : '' ?>>
                <?= $state === 'in_progress' ? 'متابعة الاختبار' : ($test['attempts'] ? 'محاولة جديدة' : 'ابدأ الاختبار') ?>
              </button>
            </form>
          <?php endif; ?>
          <?php if ($best !== null): ?>
            <span>أفضل نتيجة: <b><?= Nafes::pct($best) ?></b> <?= Nafes::levelBadge($best) ?></span>
          <?php elseif ($state === 'done' || $state === 'ended'): ?>
            <span class="badge badge-gray">لم تختبره</span>
          <?php endif; ?>
        </div>
        <?php foreach ($test['attempts'] as $a): ?>
          <?php if ($a['status'] === 'submitted'): ?>
            <a class="text-muted" href="<?= h(url('student/result.php?attempt=' . (int) $a['id'])) ?>">↩ نتيجة محاولة <?= h(substr((string) $a['submitted_at'], 0, 16)) ?>: <?= (int) $a['score'] ?>/<?= (int) $a['total'] ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>

<?php page_end('student'); ?>
