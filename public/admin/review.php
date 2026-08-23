<?php
declare(strict_types=1);
define('APP_AREA', 'admin');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';

$admin  = Auth::requireAdmin();
$planId = input_int('id');
$plan   = $planId ? Plans::withDetails($planId) : null;

if (!$plan) {
    flash('error', 'الخطة غير موجودة.');
    redirect('admin/plans.php');
}

if (is_post()) {
    csrf_verify();
    $action = (string) input('action', '');
    $note   = (string) input('note', '');

    if ($action === 'approve') {
        Plans::approve($planId, (int) $admin['id'], $note !== '' ? $note : null);
        flash('success', 'تم اعتماد الخطة وستظهر في واجهة الموقع.');
        redirect('admin/plans.php?week=' . (int) $plan['week_id'] . '&status=submitted');
    }
    if ($action === 'reject') {
        if ($note === '') {
            flash('error', 'يرجى كتابة سبب الإرجاع ليتمكن المعلم من التعديل.');
            redirect('admin/review.php?id=' . $planId);
        }
        Plans::reject($planId, (int) $admin['id'], $note);
        flash('success', 'تم إرجاع الخطة للمعلم مع الملاحظة.');
        redirect('admin/plans.php?week=' . (int) $plan['week_id'] . '&status=submitted');
    }
    if ($action === 'reopen') {
        Plans::reopen($planId);
        flash('success', 'تم فتح الخطة للتعديل من قبل المعلم.');
        redirect('admin/review.php?id=' . $planId);
    }
}

$fields = PLAN_FIELDS;
if (!Settings::bool('show_notes_row')) {
    unset($fields['notes']);
}

page_start('مراجعة الخطة', 'admin', 'plans');
?>
<div class="toolbar">
  <a class="btn btn-outline btn-sm" href="<?= h(url('admin/plans.php?week=' . (int) $plan['week_id'])) ?>">&rarr; رجوع</a>
  <span class="pill-week"><?= h($plan['subject_name']) ?> — <?= h($plan['grade_name'] . ' / ' . $plan['class_name']) ?></span>
  <span class="pill-week">المعلم: <?= h($plan['teacher_name']) ?></span>
  <span class="pill-week"><?= h($plan['week_title'] ?: ('الأسبوع ' . $plan['week_no'])) ?></span>
  <span class="<?= status_class((string) $plan['status']) ?>"><?= h(status_label((string) $plan['status'])) ?></span>
  <span class="spacer"></span>
  <a class="btn btn-outline btn-sm" target="_blank"
     href="<?= h(url('sheet.php?week=' . (int) $plan['week_id'] . '&class=' . (int) $plan['classroom_id'] . '&preview=1')) ?>">معاينة ورقة الفصل</a>
</div>

<?php if ($plan['review_note']): ?>
  <div class="alert alert-info"><b>آخر ملاحظة:</b> <?= h($plan['review_note']) ?>
    <?php if ($plan['reviewer_name']): ?><small>— <?= h($plan['reviewer_name']) ?> (<?= h((string) $plan['reviewed_at']) ?>)</small><?php endif; ?>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <h3>محتوى الخطة</h3>
    <span class="text-muted">
      <?= h($plan['hijri_start'] ?: $plan['start_date']) ?> — <?= h($plan['hijri_end'] ?: $plan['end_date']) ?>
    </span>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="sheet-table">
        <thead>
          <tr>
            <th>البند</th>
            <?php foreach (WEEK_DAYS as $dayIndex => $dayName): ?>
              <th><?= h($dayName) ?><small><?= h(ar_digits(Hijri::short(week_day_date($plan['start_date'], $dayIndex)))) ?></small></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($fields as $fieldKey => $fieldLabel): ?>
          <tr>
            <td class="rowlbl"><?= h($fieldLabel) ?></td>
            <?php foreach (array_keys(WEEK_DAYS) as $dayIndex): ?>
              <td><?= nl2br_e($plan['days'][$dayIndex][$fieldKey] ?? '') ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <div class="field">
        <label>ملاحظة الإدارة (إلزامية عند الإرجاع)</label>
        <textarea name="note" rows="2" placeholder="اكتب ملاحظتك للمعلم..."></textarea>
      </div>
      <div class="toolbar mb0">
        <?php if ($plan['status'] !== 'approved'): ?>
          <button class="btn btn-success" type="submit" name="action" value="approve">اعتماد الخطة</button>
        <?php endif; ?>
        <button class="btn btn-danger" type="submit" name="action" value="reject"
                data-confirm="سيتم إرجاع الخطة للمعلم للتعديل. متابعة؟">إرجاع للتعديل</button>
        <?php if ($plan['status'] === 'approved'): ?>
          <button class="btn btn-outline" type="submit" name="action" value="reopen"
                  data-confirm="سيتم إلغاء الاعتماد وفتح الخطة للتعديل. متابعة؟">فتح للتعديل (إلغاء الاعتماد)</button>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php page_end('admin'); ?>
