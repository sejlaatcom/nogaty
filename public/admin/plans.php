<?php
declare(strict_types=1);
define('APP_AREA', 'admin');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';

$admin = Auth::requireAdmin();

/* ------------------------- الإجراءات ------------------------- */
if (is_post()) {
    csrf_verify();
    $action  = (string) input('action', '');
    $planIds = array_map('intval', (array) ($_POST['plan_ids'] ?? []));
    $planIds = array_values(array_filter($planIds));
    $weekId  = input_int('week');

    if ($action === 'bulk_approve' && $planIds) {
        foreach ($planIds as $planId) {
            Plans::approve($planId, (int) $admin['id']);
        }
        flash('success', 'تم اعتماد ' . count($planIds) . ' خطة.');
    } elseif ($action === 'bulk_reject' && $planIds) {
        $note = (string) input('note', 'يرجى مراجعة الخطة وإعادة إرسالها.');
        foreach ($planIds as $planId) {
            Plans::reject($planId, (int) $admin['id'], $note);
        }
        flash('success', 'تم إرجاع ' . count($planIds) . ' خطة للمعلمين.');
    } elseif ($action === 'approve_week' && $weekId) {
        $ids = Database::all("SELECT id FROM plans WHERE week_id = ? AND status = 'submitted'", [$weekId]);
        foreach ($ids as $row) {
            Plans::approve((int) $row['id'], (int) $admin['id']);
        }
        flash('success', 'تم اعتماد ' . count($ids) . ' خطة في هذا الأسبوع.');
    } else {
        flash('warning', 'لم يتم تحديد أي خطة.');
    }

    redirect('admin/plans.php?week=' . $weekId . '&status=' . urlencode((string) input('status', '')));
}

/* ------------------------- الفلاتر ------------------------- */
$weeks  = Plans::weeks();
$weekId = input_int('week') ?: (int) (Plans::currentWeek()['id'] ?? ($weeks[0]['id'] ?? 0));
$status = (string) input('status', '');
$classId   = input_int('class');
$teacherId = input_int('teacher');

$conditions = ['p.week_id = :week'];
$params     = ['week' => $weekId];

if ($status !== '' && isset(PLAN_STATUSES[$status])) {
    $conditions[] = 'p.status = :status';
    $params['status'] = $status;
}
if ($classId) {
    $conditions[] = 'p.classroom_id = :class';
    $params['class'] = $classId;
}
if ($teacherId) {
    $conditions[] = 'p.teacher_id = :teacher';
    $params['teacher'] = $teacherId;
}

$plans = $weekId ? Database::all(
    'SELECT p.*, s.name AS subject_name, u.name AS teacher_name, c.name AS class_name, g.name AS grade_name
       FROM plans p
       JOIN subjects s   ON s.id = p.subject_id
       JOIN users u      ON u.id = p.teacher_id
       JOIN classrooms c ON c.id = p.classroom_id
       JOIN grades g     ON g.id = c.grade_id
      WHERE ' . implode(' AND ', $conditions) . '
   ORDER BY FIELD(p.status, "submitted", "draft", "rejected", "approved"), g.sort_order, c.sort_order, s.sort_order',
    $params
) : [];

$teachers = Database::all("SELECT id, name FROM users WHERE role = 'teacher' AND is_active = 1 ORDER BY name");

page_start('اعتماد الخطط', 'admin', 'plans');
?>

<div class="filters">
  <form method="get">
    <div class="field">
      <label>الأسبوع</label>
      <select name="week" data-auto-submit>
        <?php foreach ($weeks as $item): ?>
          <option value="<?= (int) $item['id'] ?>" <?= (int) $item['id'] === $weekId ? 'selected' : '' ?>>
            <?= h($item['title'] ?: ('الأسبوع ' . $item['week_no'])) ?>
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
    <div class="field">
      <label>الفصل</label>
      <select name="class" data-auto-submit>
        <option value="0">كل الفصول</option>
        <?php foreach (Plans::classrooms() as $item): ?>
          <option value="<?= (int) $item['id'] ?>" <?= (int) $item['id'] === $classId ? 'selected' : '' ?>>
            <?= h($item['grade_name'] . ' / ' . $item['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>المعلم</label>
      <select name="teacher" data-auto-submit>
        <option value="0">كل المعلمين</option>
        <?php foreach ($teachers as $item): ?>
          <option value="<?= (int) $item['id'] ?>" <?= (int) $item['id'] === $teacherId ? 'selected' : '' ?>><?= h($item['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>&nbsp;</label><button class="btn btn-outline" type="submit">تصفية</button></div>
  </form>
</div>

<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="week" value="<?= $weekId ?>">
  <input type="hidden" name="status" value="<?= h($status) ?>">

  <div class="card">
    <div class="card-head">
      <h2>الخطط (<?= count($plans) ?>)</h2>
      <div class="flex">
        <button class="btn btn-sm btn-success" type="submit" name="action" value="approve_week"
                data-confirm="سيتم اعتماد جميع الخطط المرسلة في هذا الأسبوع. متابعة؟">اعتماد كل المرسل في الأسبوع</button>
      </div>
    </div>
    <div class="card-body tight">
      <div class="table-wrap">
        <table class="data">
          <thead>
            <tr>
              <th style="width:36px"><input type="checkbox" data-check-all="plan_ids[]"></th>
              <th>الفصل</th><th>المادة</th><th>المعلم</th><th>الحالة</th><th>الإرسال</th><th>الاعتماد</th><th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($plans as $plan): ?>
            <tr>
              <td><input type="checkbox" name="plan_ids[]" value="<?= (int) $plan['id'] ?>"></td>
              <td><?= h($plan['grade_name'] . ' / ' . $plan['class_name']) ?></td>
              <td><strong><?= h($plan['subject_name']) ?></strong></td>
              <td><?= h($plan['teacher_name']) ?></td>
              <td><span class="<?= status_class($plan['status']) ?>"><?= h(status_label($plan['status'])) ?></span></td>
              <td class="text-muted nowrap"><?= h($plan['submitted_at'] ?? '—') ?></td>
              <td class="text-muted nowrap"><?= h($plan['reviewed_at'] ?? '—') ?></td>
              <td class="actions">
                <a class="btn btn-sm btn-primary" href="<?= h(url('admin/review.php?id=' . (int) $plan['id'])) ?>">مراجعة</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$plans): ?>
            <tr><td colspan="8" class="empty">لا توجد خطط مطابقة للفلاتر المحددة.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php if ($plans): ?>
    <div class="card">
      <div class="card-body">
        <div class="inline-form">
          <div class="field" style="flex:2">
            <label>ملاحظة الإرجاع (تظهر للمعلم عند الإرجاع للتعديل)</label>
            <input type="text" name="note" placeholder="مثال: يرجى إضافة الواجبات ليومي الثلاثاء والأربعاء">
          </div>
          <button class="btn btn-success" type="submit" name="action" value="bulk_approve"
                  data-confirm="اعتماد الخطط المحددة؟">اعتماد المحدد</button>
          <button class="btn btn-danger" type="submit" name="action" value="bulk_reject"
                  data-confirm="إرجاع الخطط المحددة للمعلمين؟">إرجاع المحدد للتعديل</button>
        </div>
      </div>
    </div>
  <?php endif; ?>
</form>

<?php page_end('admin'); ?>
