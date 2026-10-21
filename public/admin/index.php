<?php
declare(strict_types=1);
define('APP_AREA', 'admin');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';

Auth::requireAdmin();

$weeks  = Plans::weeks();
$weekId = input_int('week') ?: (int) (Plans::currentWeek()['id'] ?? ($weeks[0]['id'] ?? 0));
$week   = $weekId ? Plans::week($weekId) : null;

$stats = [
    'teachers'    => (int) Database::value("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND is_active = 1", [], 0),
    'classrooms'  => (int) Database::value('SELECT COUNT(*) FROM classrooms WHERE is_active = 1', [], 0),
    'subjects'    => (int) Database::value('SELECT COUNT(*) FROM subjects WHERE is_active = 1', [], 0),
    'assignments' => (int) Database::value('SELECT COUNT(*) FROM assignments WHERE year_id = ?', [(int) (Plans::activeYear()['id'] ?? 0)], 0),
];
$weekStats = $week ? Plans::weekStats($weekId) : ['total' => 0, 'draft' => 0, 'submitted' => 0, 'approved' => 0, 'rejected' => 0];
$expected  = $stats['assignments'];
$rate      = $expected > 0 ? round($weekStats['approved'] / $expected * 100) : 0;

$pending = $week ? Database::all(
    "SELECT p.*, s.name AS subject_name, u.name AS teacher_name, c.name AS class_name, g.name AS grade_name
       FROM plans p
       JOIN subjects s   ON s.id = p.subject_id
       JOIN users u      ON u.id = p.teacher_id
       JOIN classrooms c ON c.id = p.classroom_id
       JOIN grades g     ON g.id = c.grade_id
      WHERE p.week_id = ? AND p.status = 'submitted'
   ORDER BY p.submitted_at ASC
      LIMIT 12",
    [$weekId]
) : [];

$followClasses = $week ? Plans::followUpClasses($weekId) : [];

page_start('لوحة التحكم', 'admin', 'index');
?>

<?php if (Migrator::needed()): ?>
  <div class="alert alert-warning">
    تحتوي هذه النسخة على مزايا جديدة (منصة نافس) وتحتاج قاعدة البيانات إلى ترقية.
    <a href="<?= h(url('admin/nafes_settings.php')) ?>">اذهب إلى إعدادات نافس للترقية بضغطة واحدة</a>.
  </div>
<?php endif; ?>

<div class="filters">
  <form method="get">
    <div class="field">
      <label>الأسبوع الدراسي</label>
      <select name="week" data-auto-submit>
        <?php foreach ($weeks as $item): ?>
          <option value="<?= (int) $item['id'] ?>" <?= (int) $item['id'] === $weekId ? 'selected' : '' ?>>
            <?= h($item['title'] ?: ('الأسبوع ' . $item['week_no'])) ?> — <?= h($item['hijri_start'] ?: $item['start_date']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($week): ?>
      <div class="field"><label>&nbsp;</label>
        <span class="pill-week"><?= h($week['term_name'] ?? '') ?> — <?= h($week['year_name'] ?? '') ?></span>
      </div>
      <div class="field"><label>&nbsp;</label>
        <a class="btn btn-outline" target="_blank" href="<?= h(url('sheet.php?week=' . $weekId . '&all=1&preview=1&print=1')) ?>">
          طباعة أوراق كل الفصول
        </a>
      </div>
      <div class="field"><label>&nbsp;</label>
        <a class="btn btn-outline" href="<?= h(url('export.php?week=' . $weekId . '&all=1&preview=1&format=xlsx')) ?>">تصدير Excel للأسبوع</a>
      </div>
    <?php endif; ?>
  </form>
</div>

<?php if (!$week): ?>
  <div class="alert alert-warning">
    لم يتم إعداد الأسابيع الدراسية بعد.
    <a href="<?= h(url('admin/weeks.php')) ?>">اذهب لصفحة التقويم والأسابيع</a> لإنشائها.
  </div>
<?php endif; ?>

<div class="stats">
  <div class="stat teal"><div class="num"><?= $stats['teachers'] ?></div><div class="lbl">معلمون نشطون</div></div>
  <div class="stat"><div class="num"><?= $stats['classrooms'] ?></div><div class="lbl">فصول دراسية</div></div>
  <div class="stat"><div class="num"><?= $stats['assignments'] ?></div><div class="lbl">مواد مسندة</div></div>
  <div class="stat amber"><div class="num"><?= $weekStats['submitted'] ?></div><div class="lbl">بانتظار الاعتماد</div></div>
  <div class="stat green"><div class="num"><?= $weekStats['approved'] ?></div><div class="lbl">خطط معتمدة</div></div>
  <div class="stat"><div class="num"><?= $rate ?>%</div><div class="lbl">نسبة الاعتماد للأسبوع</div></div>
</div>

<div class="card">
  <div class="card-head">
    <h2>خطط بانتظار الاعتماد</h2>
    <a class="btn btn-sm btn-outline" href="<?= h(url('admin/plans.php?week=' . $weekId . '&status=submitted')) ?>">عرض الكل</a>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <form method="post" action="<?= h(url('admin/plans.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="bulk_approve">
        <input type="hidden" name="week" value="<?= $weekId ?>">
        <table class="data">
          <thead>
            <tr>
              <th style="width:36px"><input type="checkbox" data-check-all="plan_ids[]"></th>
              <th>المعلم</th><th>المادة</th><th>الفصل</th><th>تاريخ الإرسال</th><th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($pending as $plan): ?>
            <tr>
              <td><input type="checkbox" name="plan_ids[]" value="<?= (int) $plan['id'] ?>"></td>
              <td><?= h($plan['teacher_name']) ?></td>
              <td><strong><?= h($plan['subject_name']) ?></strong></td>
              <td><?= h($plan['grade_name'] . ' / ' . $plan['class_name']) ?></td>
              <td class="text-muted nowrap"><?= h($plan['submitted_at'] ?? '—') ?></td>
              <td class="actions">
                <a class="btn btn-sm btn-primary" href="<?= h(url('admin/review.php?id=' . (int) $plan['id'])) ?>">مراجعة</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$pending): ?>
            <tr><td colspan="6" class="empty">لا توجد خطط بانتظار الاعتماد في هذا الأسبوع.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
        <?php if ($pending): ?>
          <div class="toolbar" style="padding:12px">
            <button class="btn btn-success" type="submit" data-confirm="اعتماد الخطط المحددة؟">اعتماد المحدد</button>
          </div>
        <?php endif; ?>
      </form>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h2>لوحة المتابعة — من أكمل ومن تأخّر</h2>
    <a class="btn btn-sm btn-outline" href="<?= h(url('admin/followup.php?week=' . $weekId)) ?>">التفاصيل الكاملة</a>
  </div>
  <div class="card-body">
    <?php if (!$followClasses): ?>
      <div class="empty">لا توجد فصول بها مواد مسندة.</div>
    <?php else: ?>
      <div class="follow-grid">
        <?php foreach (array_slice($followClasses, 0, 8) as $item): ?>
          <?php
          $ratio = $item['total'] ? $item['done'] / $item['total'] : 0;
          $class = $ratio >= 1 ? '' : ($ratio > 0 ? 'partial' : 'none');
          ?>
          <div class="follow-card">
            <h3><span><?= h($item['grade_name'] . ' / ' . $item['name']) ?></span></h3>
            <div class="follow-ratio <?= $class ?>">
              <?= ar_digits((string) $item['done']) ?>
              <small>من <?= ar_digits((string) $item['total']) ?> مواد اكتملت</small>
            </div>
            <div class="progress"><span style="width:<?= round($ratio * 100) ?>%"></span></div>
            <?php if ($item['late']): ?>
              <div class="text-muted" style="font-size:.83rem">لم يُعبّئوا بعد:</div>
              <div class="late-list">
                <?php foreach (array_slice($item['late'], 0, 6) as $late): ?>
                  <span class="late-chip" title="<?= h($late['subject_name']) ?>"><?= h(teacher_short($late['teacher_name'])) ?></span>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="late-list"><span class="done-chip">اكتملت جميع المواد</span></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php page_end('admin'); ?>
