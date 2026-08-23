<?php
declare(strict_types=1);
define('APP_AREA', 'teacher');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';

$user = Auth::requireTeacher();

$status = (string) input('status', '');
$weekId = input_int('week');

$conditions = ['p.teacher_id = :teacher'];
$params     = ['teacher' => $user['id']];

if ($status !== '' && isset(PLAN_STATUSES[$status])) {
    $conditions[] = 'p.status = :status';
    $params['status'] = $status;
}
if ($weekId) {
    $conditions[] = 'p.week_id = :week';
    $params['week'] = $weekId;
}

$plans = Database::all(
    'SELECT p.*, s.name AS subject_name, c.name AS class_name, g.name AS grade_name,
            w.week_no, w.title AS week_title, w.hijri_start, w.start_date
       FROM plans p
       JOIN subjects s   ON s.id = p.subject_id
       JOIN classrooms c ON c.id = p.classroom_id
       JOIN grades g     ON g.id = c.grade_id
       JOIN weeks w      ON w.id = p.week_id
      WHERE ' . implode(' AND ', $conditions) . '
   ORDER BY w.start_date DESC, g.sort_order, s.sort_order
      LIMIT 300',
    $params
);

page_start('جميع خططي', 'teacher', 'plans');
?>
<div class="filters">
  <form method="get">
    <div class="field">
      <label>الأسبوع</label>
      <select name="week" data-auto-submit>
        <option value="0">كل الأسابيع</option>
        <?php foreach (Plans::weeks() as $week): ?>
          <option value="<?= (int) $week['id'] ?>" <?= (int) $week['id'] === $weekId ? 'selected' : '' ?>>
            <?= h($week['title'] ?: ('الأسبوع ' . $week['week_no'])) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>الحالة</label>
      <select name="status" data-auto-submit>
        <option value="">كل الحالات</option>
        <?php foreach (PLAN_STATUSES as $key => $label): ?>
          <option value="<?= h($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>&nbsp;</label><button class="btn btn-outline" type="submit">تصفية</button></div>
  </form>
</div>

<div class="card">
  <div class="card-head"><h2>خططي (<?= count($plans) ?>)</h2></div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr><th>الأسبوع</th><th>التاريخ</th><th>المادة</th><th>الفصل</th><th>الحالة</th><th>ملاحظة الإدارة</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($plans as $plan): ?>
          <tr>
            <td><?= h($plan['week_title'] ?: ('الأسبوع ' . $plan['week_no'])) ?></td>
            <td class="text-muted nowrap"><?= h($plan['hijri_start'] ?: $plan['start_date']) ?></td>
            <td><strong><?= h($plan['subject_name']) ?></strong></td>
            <td><?= h($plan['grade_name'] . ' / ' . $plan['class_name']) ?></td>
            <td><span class="<?= status_class($plan['status']) ?>"><?= h(status_label($plan['status'])) ?></span></td>
            <td class="text-muted"><?= h(str_short($plan['review_note'], 40)) ?></td>
            <td class="actions">
              <a class="btn btn-sm btn-outline"
                 href="<?= h(url('teacher/plan.php?week=' . (int) $plan['week_id'] . '&class=' . (int) $plan['classroom_id'] . '&subject=' . (int) $plan['subject_id'])) ?>">فتح</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$plans): ?>
          <tr><td colspan="7" class="empty">لا توجد خطط مطابقة.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php page_end('teacher'); ?>
