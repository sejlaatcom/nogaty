<?php
declare(strict_types=1);
define('APP_AREA', 'teacher');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';

$user = Auth::requireTeacher();

$weeks = Plans::weeks();
$weekId = input_int('week');
if (!$weekId) {
    $weekId = (int) (Plans::currentWeek()['id'] ?? ($weeks[0]['id'] ?? 0));
}
$week = $weekId ? Plans::week($weekId) : null;

$assignments = Plans::teacherAssignments((int) $user['id']);
$rows = [];
$counts = ['total' => 0, 'draft' => 0, 'submitted' => 0, 'approved' => 0, 'rejected' => 0, 'missing' => 0];

foreach ($assignments as $assignment) {
    $plan = $week ? Plans::find($weekId, (int) $assignment['classroom_id'], (int) $assignment['subject_id']) : null;
    $status = $plan['status'] ?? null;
    $counts['total']++;
    if ($status === null) {
        $counts['missing']++;
    } else {
        $counts[$status] = ($counts[$status] ?? 0) + 1;
    }
    $rows[] = $assignment + ['plan' => $plan, 'status' => $status];
}

page_start('خطط الأسبوع', 'teacher', 'index');
?>

<?php if (!$week): ?>
  <div class="alert alert-warning">لم يتم إعداد الأسابيع الدراسية بعد. يرجى مراجعة إدارة المدرسة.</div>
<?php else: ?>

<div class="filters">
  <form method="get">
    <div class="field">
      <label>الأسبوع الدراسي</label>
      <select name="week" data-auto-submit>
        <?php foreach ($weeks as $item): ?>
          <option value="<?= (int) $item['id'] ?>" <?= (int) $item['id'] === $weekId ? 'selected' : '' ?>>
            <?= h($item['title'] ?: ('الأسبوع ' . $item['week_no'])) ?>
            — <?= h($item['hijri_start'] ?: $item['start_date']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>&nbsp;</label>
      <span class="pill-week">
        من <?= h($week['hijri_start'] ?: $week['start_date']) ?> إلى <?= h($week['hijri_end'] ?: $week['end_date']) ?>
      </span>
    </div>
    <?php if ($week['status'] === 'closed'): ?>
      <div class="field"><label>&nbsp;</label><span class="badge badge-red">الإدخال مغلق لهذا الأسبوع</span></div>
    <?php endif; ?>
  </form>
</div>

<div class="stats">
  <div class="stat teal"><div class="num"><?= $counts['total'] ?></div><div class="lbl">المواد المسندة لي</div></div>
  <div class="stat green"><div class="num"><?= $counts['approved'] ?></div><div class="lbl">خطط معتمدة</div></div>
  <div class="stat amber"><div class="num"><?= $counts['submitted'] ?></div><div class="lbl">بانتظار الاعتماد</div></div>
  <div class="stat red"><div class="num"><?= $counts['rejected'] ?></div><div class="lbl">معادة للتعديل</div></div>
  <div class="stat"><div class="num"><?= $counts['missing'] + $counts['draft'] ?></div><div class="lbl">لم تُرسل بعد</div></div>
</div>

<div class="card">
  <div class="card-head">
    <h2>المواد المسندة لي في <?= h($week['title'] ?: ('الأسبوع ' . $week['week_no'])) ?></h2>
    <span class="text-muted"><?= h($week['term_name'] ?? '') ?> — <?= h($week['year_name'] ?? '') ?></span>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>المادة</th><th>الصف / الفصل</th><th>الحالة</th><th>ملاحظة الإدارة</th><th>آخر تحديث</th><th>الإجراء</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
          <?php $planUrl = url('teacher/plan.php?week=' . $weekId . '&class=' . (int) $row['classroom_id'] . '&subject=' . (int) $row['subject_id']); ?>
          <tr>
            <td><strong><?= h($row['subject_name']) ?></strong></td>
            <td><?= h($row['grade_name'] . ' / ' . $row['class_name']) ?></td>
            <td>
              <?php if ($row['status'] === null): ?>
                <span class="badge badge-gray">لم تُعبأ</span>
              <?php else: ?>
                <span class="<?= status_class($row['status']) ?>"><?= h(status_label($row['status'])) ?></span>
              <?php endif; ?>
            </td>
            <td class="text-muted"><?= h(str_short($row['plan']['review_note'] ?? '', 45)) ?></td>
            <td class="text-muted nowrap"><?= h($row['plan']['updated_at'] ?? '—') ?></td>
            <td class="actions">
              <a class="btn btn-sm <?= in_array($row['status'], ['approved'], true) ? 'btn-outline' : 'btn-primary' ?>" href="<?= h($planUrl) ?>">
                <?= $row['status'] === null ? 'إدخال الخطة' : ($row['status'] === 'approved' ? 'عرض' : 'تعديل') ?>
              </a>
              <a class="btn btn-sm btn-outline" target="_blank"
                 href="<?= h(url('sheet.php?week=' . $weekId . '&class=' . (int) $row['classroom_id'] . '&preview=1')) ?>">ورقة الفصل</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="6" class="empty">لا توجد مواد مسندة لك حتى الآن. يرجى مراجعة إدارة المدرسة لإسناد المواد.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php page_end('teacher'); ?>
