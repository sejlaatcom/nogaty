<?php
declare(strict_types=1);
define('APP_AREA', 'nafes');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';
require APP_PATH . '/views/nafes.php';

$user = Auth::requireStaff();

$filters = [
    'subject' => input_int('subject'),
    'grade'   => input_int('grade'),
    'status'  => (string) input('status', ''),
];
$tests    = Exams::listFor($user, $filters);
$subjects = Nafes::subjects();
$grades   = Nafes::grades();

page_start('اختبارات نافس', nafes_area(), 'nafes_tests');
?>

<div class="toolbar">
  <a class="btn btn-primary" href="<?= h(url('nafes/test.php')) ?>">+ اختبار جديد</a>
  <span class="spacer"></span>
</div>

<div class="filters">
  <form method="get">
    <div class="field"><label>المادة</label><select name="subject" data-auto-submit><?= options_html($subjects, $filters['subject'], 'كل المواد') ?></select></div>
    <div class="field"><label>الصف</label><select name="grade" data-auto-submit><?= options_html($grades, $filters['grade'], 'كل الصفوف') ?></select></div>
    <div class="field"><label>الحالة</label>
      <select name="status" data-auto-submit>
        <option value="">الكل</option>
        <?php foreach (Exams::STATUSES as $key => $label): ?>
          <option value="<?= $key ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>
</div>

<div class="card">
  <div class="card-head"><h2>الاختبارات (<?= count($tests) ?>)</h2></div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>الاختبار</th><th>المادة / الصف</th><th>النوع</th><th>الفصول</th><th>الأسئلة</th><th>الموعد</th><th>أنجزه</th><th>المتوسط</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($tests as $test): ?>
          <tr>
            <td><strong><?= h($test['title']) ?></strong><br><small><?= h($test['author_name'] ?? '') ?></small></td>
            <td class="nowrap"><?= h($test['subject_name']) ?><br><small><?= h($test['grade_name']) ?></small></td>
            <td><small><?= h(Exams::KINDS[$test['kind']] ?? '') ?></small></td>
            <td><small><?= h($test['classes_label'] ?: '—') ?></small></td>
            <td><?= (int) $test['questions_count'] ?></td>
            <td class="nowrap"><small>
              <?= $test['starts_at'] ? 'من ' . h(substr($test['starts_at'], 0, 16)) . '<br>' : '' ?>
              <?= $test['ends_at'] ? 'إلى ' . h(substr($test['ends_at'], 0, 16)) : ($test['starts_at'] ? '' : 'مفتوح') ?>
            </small></td>
            <td><?= (int) $test['students_done'] ?></td>
            <td><?= $test['avg_percent'] !== null ? Nafes::pct((float) $test['avg_percent']) . ' ' . Nafes::levelBadge((float) $test['avg_percent']) : '—' ?></td>
            <td><span class="badge <?= match ($test['status']) { 'published' => 'badge-green', 'closed' => 'badge-gray', default => 'badge-amber' } ?>"><?= h(Exams::STATUSES[$test['status']]) ?></span></td>
            <td class="actions">
              <?php if (Exams::canManage($user, $test)): ?>
                <a class="btn btn-sm btn-outline" href="<?= h(url('nafes/test.php?id=' . (int) $test['id'])) ?>">إدارة</a>
              <?php endif; ?>
              <a class="btn btn-sm btn-outline" href="<?= h(url('nafes/test_results.php?id=' . (int) $test['id'])) ?>">النتائج</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$tests): ?>
          <tr><td colspan="10" class="empty">لا توجد اختبارات بعد. أنشئ اختباراً واختر أسئلته من البنك أو ولّدها تلقائياً حسب المهارات.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php page_end(nafes_area()); ?>
