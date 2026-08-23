<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require APP_PATH . '/views/layout.php';
require APP_PATH . '/views/partials/sheet.php';

if (!Database::isInstalled()) {
    redirect('install.php');
}

$weeks  = Plans::weeks();
$weekId = input_int('week') ?: (int) (Plans::currentWeek()['id'] ?? ($weeks[0]['id'] ?? 0));
$week   = $weekId ? Plans::week($weekId) : null;

$publicOn = Settings::bool('public_enabled');
$classes  = ($week && $publicOn) ? Plans::classroomsWithApproved($weekId) : [];

$selectedClass = input_int('class');
$sheetRows     = [];
$classroom     = null;
if ($selectedClass && $week && $publicOn) {
    $classroom = Plans::classroom($selectedClass);
    if ($classroom) {
        $sheetRows = array_values(array_filter(
            Plans::classSheet($weekId, $selectedClass, true),
            static fn ($row) => $row['filled']
        ));
    }
}

page_start('الخطط الأسبوعية المعتمدة', 'public');
?>

<section class="hero">
  <h1><?= h(Settings::get('sheet_title')) ?></h1>
  <p><?= h(Settings::get('school_name')) ?> — <?= h(Settings::get('education_dept')) ?></p>
  <?php if ($week): ?>
    <div class="hero-meta">
      <span><?= h($week['year_name'] ?? '') ?></span>
      <span><?= h($week['term_name'] ?? '') ?></span>
      <span><?= h($week['title'] ?: ('الأسبوع ' . $week['week_no'])) ?></span>
      <span>من <?= h($week['hijri_start'] ?: $week['start_date']) ?> إلى <?= h($week['hijri_end'] ?: $week['end_date']) ?></span>
    </div>
  <?php endif; ?>
</section>

<?php if (!$publicOn): ?>
  <div class="alert alert-info">عرض الخطط في الواجهة العامة موقوف حالياً من قبل إدارة المدرسة.</div>
<?php elseif (!$week): ?>
  <div class="alert alert-warning">لم يتم إعداد الأسابيع الدراسية بعد.</div>
<?php else: ?>

<div class="filters">
  <form method="get">
    <div class="field">
      <label>اختر الأسبوع</label>
      <select name="week" data-auto-submit>
        <?php foreach ($weeks as $item): ?>
          <option value="<?= (int) $item['id'] ?>" <?= (int) $item['id'] === $weekId ? 'selected' : '' ?>>
            <?= h($item['title'] ?: ('الأسبوع ' . $item['week_no'])) ?> — <?= h($item['hijri_start'] ?: $item['start_date']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($classes): ?>
    <div class="field">
      <label>الفصل</label>
      <select name="class" data-auto-submit>
        <option value="0">— اختر فصلاً لعرض الخطة —</option>
        <?php foreach ($classes as $item): ?>
          <option value="<?= (int) $item['id'] ?>" <?= (int) $item['id'] === $selectedClass ? 'selected' : '' ?>>
            <?= h($item['grade_name'] . ' / ' . $item['class_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="field"><label>&nbsp;</label><button class="btn btn-outline" type="submit">عرض</button></div>
  </form>
</div>

<?php if ($classroom && $sheetRows): ?>
  <div class="toolbar">
    <a class="btn btn-primary" target="_blank"
       href="<?= h(url('sheet.php?week=' . $weekId . '&class=' . $selectedClass . '&print=1')) ?>">طباعة / حفظ PDF</a>
    <a class="btn btn-outline" href="<?= h(url('export.php?week=' . $weekId . '&class=' . $selectedClass . '&format=xlsx')) ?>">تصدير Excel</a>
    <a class="btn btn-outline" href="<?= h(url('export.php?week=' . $weekId . '&class=' . $selectedClass . '&format=csv')) ?>">تصدير CSV</a>
  </div>
  <?php render_sheet($week, $classroom, $sheetRows); ?>
<?php elseif ($classroom): ?>
  <div class="alert alert-warning">لا توجد خطط معتمدة لهذا الفصل في الأسبوع المحدد.</div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <h2>الفصول التي اعتُمدت خططها هذا الأسبوع</h2>
    <span class="badge badge-teal"><?= count($classes) ?> فصل</span>
  </div>
  <div class="card-body">
    <?php if (!$classes): ?>
      <div class="empty">لا توجد خطط معتمدة منشورة لهذا الأسبوع حتى الآن.</div>
    <?php else: ?>
      <div class="follow-grid">
        <?php foreach ($classes as $item): ?>
          <?php $stats = Plans::completion($weekId, (int) $item['id'], true); ?>
          <div class="follow-card">
            <h3>
              <span><?= h($item['grade_name'] . ' / ' . $item['class_name']) ?></span>
              <span class="badge badge-green"><?= (int) $item['approved_count'] ?> مادة</span>
            </h3>
            <div class="progress">
              <span style="width:<?= $stats['total'] ? round($stats['done'] / $stats['total'] * 100) : 0 ?>%"></span>
            </div>
            <p class="text-muted mb0"><?= (int) $stats['done'] ?> من <?= (int) $stats['total'] ?> مواد معتمدة</p>
            <div class="toolbar" style="margin:10px 0 0">
              <a class="btn btn-sm btn-primary" href="<?= h(url('index.php?week=' . $weekId . '&class=' . (int) $item['id'])) ?>">عرض الخطة</a>
              <a class="btn btn-sm btn-outline" target="_blank"
                 href="<?= h(url('sheet.php?week=' . $weekId . '&class=' . (int) $item['id'] . '&print=1')) ?>">طباعة</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php endif; ?>
<?php page_end('public'); ?>
