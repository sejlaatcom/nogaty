<?php
declare(strict_types=1);
define('APP_AREA', 'admin');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';

Auth::requireAdmin();

$weeks  = Plans::weeks();
$weekId = input_int('week') ?: (int) (Plans::currentWeek()['id'] ?? ($weeks[0]['id'] ?? 0));
$week   = $weekId ? Plans::week($weekId) : null;
$view   = input('view', 'classes') === 'teachers' ? 'teachers' : 'classes';

$classes  = $week ? Plans::followUpClasses($weekId) : [];
$teachers = $week ? Plans::followUpTeachers($weekId) : [];

$totalExpected = array_sum(array_column($classes, 'total'));
$totalDone     = array_sum(array_column($classes, 'done'));

page_start('لوحة المتابعة', 'admin', 'followup');
?>

<div class="filters">
  <form method="get">
    <div class="field">
      <label>الأسبوع</label>
      <select name="week" data-auto-submit>
        <?php foreach ($weeks as $item): ?>
          <option value="<?= (int) $item['id'] ?>" <?= (int) $item['id'] === $weekId ? 'selected' : '' ?>>
            <?= h($item['title'] ?: ('الأسبوع ' . $item['week_no'])) ?> — <?= h($item['hijri_start'] ?: $item['start_date']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>طريقة العرض</label>
      <select name="view" data-auto-submit>
        <option value="classes"  <?= $view === 'classes' ? 'selected' : '' ?>>حسب الفصول</option>
        <option value="teachers" <?= $view === 'teachers' ? 'selected' : '' ?>>حسب المعلمين</option>
      </select>
    </div>
    <div class="field"><label>&nbsp;</label><button class="btn btn-outline" type="submit">عرض</button></div>
  </form>
</div>

<div class="stats">
  <div class="stat teal"><div class="num"><?= ar_digits((string) $totalDone) ?></div><div class="lbl">خطط مُعبّأة ومرسلة</div></div>
  <div class="stat"><div class="num"><?= ar_digits((string) $totalExpected) ?></div><div class="lbl">إجمالي المطلوب</div></div>
  <div class="stat red"><div class="num"><?= ar_digits((string) max(0, $totalExpected - $totalDone)) ?></div><div class="lbl">لم تُعبّأ بعد</div></div>
  <div class="stat green"><div class="num"><?= $totalExpected ? round($totalDone / $totalExpected * 100) : 0 ?>%</div><div class="lbl">نسبة الإنجاز</div></div>
</div>

<?php if ($view === 'classes'): ?>
  <div class="follow-grid">
    <?php foreach ($classes as $item): ?>
      <?php
      $ratio = $item['total'] ? $item['done'] / $item['total'] : 0;
      $cls   = $ratio >= 1 ? '' : ($ratio > 0 ? 'partial' : 'none');
      ?>
      <div class="follow-card">
        <h3>
          <span><?= h($item['grade_name'] . ' / ' . $item['name']) ?></span>
          <a class="btn btn-sm btn-outline" target="_blank"
             href="<?= h(url('sheet.php?week=' . $weekId . '&class=' . (int) $item['id'] . '&preview=1')) ?>">الورقة</a>
        </h3>
        <div class="follow-ratio <?= $cls ?>">
          <?= ar_digits((string) $item['done']) ?>
          <small>من <?= ar_digits((string) $item['total']) ?> مواد اكتملت</small>
        </div>
        <div class="progress"><span style="width:<?= round($ratio * 100) ?>%"></span></div>
        <?php if ($item['late']): ?>
          <div class="text-muted" style="font-size:.83rem">لم يُعبّئوا بعد:</div>
          <div class="late-list">
            <?php foreach ($item['late'] as $late): ?>
              <span class="late-chip"><?= h(teacher_short($late['teacher_name'])) ?> — <?= h($late['subject_name']) ?></span>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="late-list"><span class="done-chip">اكتملت جميع المواد</span></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$classes): ?>
      <div class="empty">لا توجد فصول بها مواد مسندة.</div>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="card">
    <div class="card-head"><h2>متابعة المعلمين</h2><span class="text-muted">المتأخرون أولاً</span></div>
    <div class="card-body tight">
      <div class="table-wrap">
        <table class="data">
          <thead>
            <tr><th>المعلم</th><th>الجوال</th><th>المطلوب</th><th>مُرسل</th><th>معتمد</th><th>بانتظار</th><th>معاد</th><th>الإنجاز</th><th></th></tr>
          </thead>
          <tbody>
          <?php foreach ($teachers as $teacher): ?>
            <?php
            $total = (int) $teacher['total'];
            $done  = (int) $teacher['done'];
            $ratio = $total ? round($done / $total * 100) : 0;
            ?>
            <tr>
              <td><strong><?= h($teacher['name']) ?></strong></td>
              <td class="text-muted"><?= h($teacher['phone'] ?: '—') ?></td>
              <td><?= $total ?></td>
              <td><?= $done ?></td>
              <td class="text-green"><?= (int) $teacher['approved'] ?></td>
              <td><?= (int) $teacher['waiting'] ?></td>
              <td class="text-red"><?= (int) $teacher['rejected'] ?></td>
              <td style="min-width:120px">
                <div class="progress" style="margin:0"><span style="width:<?= $ratio ?>%"></span></div>
                <small><?= $ratio ?>%</small>
              </td>
              <td class="actions">
                <a class="btn btn-sm btn-outline"
                   href="<?= h(url('admin/plans.php?week=' . $weekId . '&teacher=' . (int) $teacher['id'])) ?>">خططه</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$teachers): ?>
            <tr><td colspan="9" class="empty">لا يوجد معلمون لديهم إسناد في العام الحالي.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php page_end('admin'); ?>
