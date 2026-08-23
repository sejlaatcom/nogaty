<?php
/**
 * عرض ورقة الخطة الأسبوعية لفصل واحد أو لكل الفصول (جاهزة للطباعة / حفظ PDF).
 *   sheet.php?week=1&class=2            عرض الخطط المعتمدة
 *   sheet.php?week=1&class=2&preview=1  معاينة كل الخطط (للمعلم والإدارة)
 *   sheet.php?week=1&all=1              ورقة لكل فصل — بضغطة واحدة
 */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require APP_PATH . '/views/layout.php';
require APP_PATH . '/views/partials/sheet.php';

$weekId  = input_int('week');
$classId = input_int('class');
$all     = input('all') === '1';
$preview = input('preview') === '1';
$auto    = input('print') === '1';

$week = $weekId ? Plans::week($weekId) : Plans::currentWeek();
if (!$week) {
    exit('لا يوجد أسبوع دراسي محدد.');
}
$weekId = (int) $week['id'];

// المعاينة (عرض غير المعتمد) تتطلب تسجيل الدخول
if ($preview) {
    Auth::requireLogin();
}
if (!$preview && !Settings::bool('public_enabled') && !Auth::check()) {
    exit('عرض الخطط في الواجهة العامة موقوف حالياً.');
}
$onlyApproved = !$preview;

// تحديد الفصول المطلوبة
$classrooms = [];
if ($all) {
    if ($preview) {
        $classrooms = Plans::classrooms();
    } else {
        foreach (Plans::classroomsWithApproved($weekId) as $row) {
            $classroom = Plans::classroom((int) $row['id']);
            if ($classroom) {
                $classrooms[] = $classroom;
            }
        }
    }
} else {
    $classroom = $classId ? Plans::classroom($classId) : null;
    if (!$classroom) {
        exit('الفصل المطلوب غير موجود.');
    }
    $classrooms = [$classroom];
}

page_start('ورقة الخطة الأسبوعية', 'public');
?>
<div class="toolbar no-print">
  <button class="btn btn-primary" data-print>طباعة / حفظ PDF</button>
  <?php if (!$all): ?>
    <a class="btn btn-outline" href="<?= h(url('export.php?week=' . $weekId . '&class=' . (int) $classrooms[0]['id'] . '&format=xlsx' . ($preview ? '&preview=1' : ''))) ?>">تصدير Excel</a>
    <a class="btn btn-outline" href="<?= h(url('export.php?week=' . $weekId . '&class=' . (int) $classrooms[0]['id'] . '&format=csv' . ($preview ? '&preview=1' : ''))) ?>">تصدير CSV</a>
  <?php else: ?>
    <span class="pill-week">عدد الأوراق: <?= count($classrooms) ?></span>
  <?php endif; ?>
  <span class="spacer"></span>
  <a class="btn btn-outline btn-sm" href="<?= h(url(Auth::check() ? Auth::home() : 'index.php')) ?>">رجوع</a>
</div>

<?php foreach ($classrooms as $classroom): ?>
  <?php
  $rows = Plans::classSheet($weekId, (int) $classroom['id'], $onlyApproved);
  if ($onlyApproved) {
      // في العرض العام تُخفى المواد غير المعتمدة من العدّ لكن تبقى ظاهرة كـ«لم تُعبأ»
      $hasAny = array_filter($rows, static fn ($row) => $row['filled']);
      if (!$hasAny && $all) {
          continue;
      }
  }
  $completion = Plans::completion($weekId, (int) $classroom['id'], $onlyApproved);
  render_sheet($week, $classroom, $rows, ['completion' => $completion]);
  ?>
<?php endforeach; ?>

<?php if ($auto): ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>
<?php page_end('public'); ?>
