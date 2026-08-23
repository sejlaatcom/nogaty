<?php
declare(strict_types=1);
define('APP_AREA', 'teacher');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';

$user = Auth::requireTeacher();

$weekId      = input_int('week');
$classroomId = input_int('class');
$subjectId   = input_int('subject');

$week = $weekId ? Plans::week($weekId) : null;
if (!$week) {
    flash('error', 'الأسبوع المطلوب غير موجود.');
    redirect('teacher/index.php');
}

// التحقق من أن المادة مسندة لهذا المعلم
$assignment = Database::first(
    'SELECT a.*, s.name AS subject_name, c.name AS class_name, g.name AS grade_name
       FROM assignments a
       JOIN subjects s   ON s.id = a.subject_id
       JOIN classrooms c ON c.id = a.classroom_id
       JOIN grades g     ON g.id = c.grade_id
      WHERE a.teacher_id = ? AND a.classroom_id = ? AND a.subject_id = ? AND a.year_id = ?',
    [$user['id'], $classroomId, $subjectId, (int) ($week['year_id'] ?? 0)]
);

if (!$assignment) {
    http_response_code(403);
    exit('هذه المادة غير مسندة لك في هذا الفصل.');
}

$plan     = Plans::findOrCreate($weekId, $classroomId, $subjectId, (int) $user['id']);
$planId   = (int) $plan['id'];
$locked   = $plan['status'] === 'approved' || $week['status'] === 'closed';

/* ------------------------- المعالجة ------------------------- */
if (is_post()) {
    csrf_verify();
    $action = (string) input('action', 'save');
    $isAjax = input('ajax') === '1';

    if ($locked && $action !== 'noop') {
        if ($isAjax) {
            header('Content-Type: application/json');
            exit(json_encode(['ok' => false, 'message' => 'الخطة مغلقة للتعديل']));
        }
        flash('error', 'لا يمكن تعديل خطة معتمدة أو أسبوع مغلق. راجع إدارة المدرسة.');
        redirect('teacher/plan.php?week=' . $weekId . '&class=' . $classroomId . '&subject=' . $subjectId);
    }

    if ($action === 'copy_prev') {
        $prev = Database::first(
            'SELECT id FROM weeks WHERE term_id = ? AND week_no < ? ORDER BY week_no DESC LIMIT 1',
            [$week['term_id'], $week['week_no']]
        );
        if ($prev && Plans::copyFromWeek($planId, (int) $prev['id'])) {
            flash('success', 'تم نسخ محتوى الأسبوع السابق. راجع البيانات قبل الإرسال.');
        } else {
            flash('warning', 'لا توجد خطة في الأسبوع السابق لنسخها.');
        }
        redirect('teacher/plan.php?week=' . $weekId . '&class=' . $classroomId . '&subject=' . $subjectId);
    }

    // حفظ محتوى الأيام
    $days = [];
    foreach (array_keys(WEEK_DAYS) as $dayIndex) {
        foreach (array_keys(PLAN_FIELDS) as $field) {
            $days[$dayIndex][$field] = (string) ($_POST['day'][$dayIndex][$field] ?? '');
        }
    }
    Plans::saveDays($planId, $days);

    if ($plan['status'] === 'rejected' && $action === 'save') {
        Database::run("UPDATE plans SET status = 'draft' WHERE id = ?", [$planId]);
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        exit(json_encode(['ok' => true, 'time' => date('H:i')]));
    }

    if ($action === 'submit') {
        if (Plans::submit($planId)) {
            flash('success', 'تم إرسال الخطة لإدارة المدرسة بانتظار الاعتماد.');
            redirect('teacher/index.php?week=' . $weekId);
        }
        flash('error', 'لا يمكن إرسال خطة فارغة، يرجى تعبئة محتوى الخطة أولاً.');
    } else {
        flash('success', 'تم حفظ الخطة كمسودة.');
    }
    redirect('teacher/plan.php?week=' . $weekId . '&class=' . $classroomId . '&subject=' . $subjectId);
}

/* ------------------------- العرض ------------------------- */
$days   = Plans::days($planId);
$plan   = Database::first('SELECT * FROM plans WHERE id = ?', [$planId]) ?? $plan;
$fields = PLAN_FIELDS;
if (!Settings::bool('show_notes_row')) {
    unset($fields['notes']);
}

page_start('إدخال الخطة الأسبوعية', 'teacher', 'index');
?>

<div class="toolbar">
  <a class="btn btn-outline btn-sm" href="<?= h(url('teacher/index.php?week=' . $weekId)) ?>">&rarr; رجوع</a>
  <span class="pill-week"><?= h($assignment['subject_name']) ?> — <?= h($assignment['grade_name'] . ' / ' . $assignment['class_name']) ?></span>
  <span class="pill-week"><?= h($week['title'] ?: ('الأسبوع ' . $week['week_no'])) ?>:
    <?= h($week['hijri_start'] ?: $week['start_date']) ?> — <?= h($week['hijri_end'] ?: $week['end_date']) ?></span>
  <span class="<?= status_class((string) $plan['status']) ?>"><?= h(status_label((string) $plan['status'])) ?></span>
  <span class="spacer"></span>
  <span class="text-muted" id="autosave-status"></span>
</div>

<?php if ($plan['status'] === 'rejected' && $plan['review_note']): ?>
  <div class="alert alert-error"><b>ملاحظة الإدارة:</b> <?= h($plan['review_note']) ?></div>
<?php endif; ?>
<?php if ($plan['status'] === 'approved'): ?>
  <div class="alert alert-success">هذه الخطة معتمدة من إدارة المدرسة وظاهرة في واجهة الموقع. لتعديلها اطلب من الإدارة إعادتها.</div>
<?php elseif ($week['status'] === 'closed'): ?>
  <div class="alert alert-warning">تم إغلاق الإدخال لهذا الأسبوع من قبل إدارة المدرسة.</div>
<?php endif; ?>

<form method="post" id="plan-form" data-autosave="<?= $locked ? '0' : '1' ?>"
      action="<?= h(url('teacher/plan.php?week=' . $weekId . '&class=' . $classroomId . '&subject=' . $subjectId)) ?>">
  <?= csrf_field() ?>

  <div class="card">
    <div class="card-body tight">
      <div class="plan-editor">
        <table class="plan-edit">
          <thead>
            <tr>
              <th class="rowlbl">البند</th>
              <?php foreach (WEEK_DAYS as $dayIndex => $dayName): ?>
                <th>
                  <?= h($dayName) ?>
                  <small><?= h(ar_digits(Hijri::short(week_day_date($week['start_date'], $dayIndex)))) ?></small>
                </th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($fields as $fieldKey => $fieldLabel): ?>
            <tr>
              <th class="rowlbl"><?= h($fieldLabel) ?></th>
              <?php foreach (array_keys(WEEK_DAYS) as $dayIndex): ?>
                <td>
                  <textarea name="day[<?= $dayIndex ?>][<?= $fieldKey ?>]" data-grow
                            <?= $locked ? 'readonly' : '' ?>
                            placeholder="<?= h($fieldLabel) ?>"><?= h($days[$dayIndex][$fieldKey] ?? '') ?></textarea>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php if (!$locked): ?>
  <div class="toolbar">
    <button class="btn btn-primary" type="submit" name="action" value="save">حفظ كمسودة</button>
    <button class="btn btn-success" type="submit" name="action" value="submit"
            data-confirm="سيتم إرسال الخطة لإدارة المدرسة للاعتماد. هل أنت متأكد؟">إرسال للإدارة للاعتماد</button>
    <button class="btn btn-outline" type="submit" name="action" value="copy_prev"
            data-confirm="سيتم استبدال المحتوى الحالي بمحتوى الأسبوع السابق. متابعة؟">نسخ من الأسبوع السابق</button>
    <span class="spacer"></span>
    <a class="btn btn-outline" target="_blank"
       href="<?= h(url('sheet.php?week=' . $weekId . '&class=' . $classroomId . '&preview=1')) ?>">معاينة ورقة الفصل</a>
  </div>
  <?php else: ?>
  <div class="toolbar">
    <a class="btn btn-outline" target="_blank"
       href="<?= h(url('sheet.php?week=' . $weekId . '&class=' . $classroomId)) ?>">عرض ورقة الفصل</a>
  </div>
  <?php endif; ?>
</form>

<?php page_end('teacher'); ?>
