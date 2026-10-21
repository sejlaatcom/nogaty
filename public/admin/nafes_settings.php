<?php
declare(strict_types=1);
define('APP_AREA', 'admin');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';

Auth::requireAdmin();

if (is_post()) {
    csrf_verify();

    if (input('action') === 'migrate') {
        try {
            $done = Migrator::run();
            Activity::log('db_migrate', null, null, implode(' | ', $done));
            flash('success', 'تمت ترقية قاعدة البيانات: ' . implode(' ', $done));
        } catch (Throwable $e) {
            flash('error', 'تعذرت الترقية: ' . $e->getMessage());
        }
        redirect('admin/nafes_settings.php');
    }

    $advanced   = max(0, min(100, input_int('nafes_level_advanced', 85)));
    $proficient = max(0, min(100, input_int('nafes_level_proficient', 65)));
    $basic      = max(0, min(100, input_int('nafes_level_basic', 50)));

    if (!($advanced > $proficient && $proficient > $basic)) {
        flash('error', 'حدود المستويات يجب أن تكون تنازلية: متقدم > متمكن > أساسي.');
        redirect('admin/nafes_settings.php');
    }

    $subjectIds = array_map('intval', (array) ($_POST['subject_ids'] ?? []));
    $gradeIds   = array_map('intval', (array) ($_POST['grade_ids'] ?? []));

    Settings::setMany([
        'nafes_enabled'          => isset($_POST['nafes_enabled']) ? '1' : '0',
        'nafes_student_review'   => isset($_POST['nafes_student_review']) ? '1' : '0',
        'nafes_title'            => (string) input('nafes_title', Settings::DEFAULTS['nafes_title']),
        'nafes_subject_ids'      => implode(',', array_filter($subjectIds)),
        'nafes_grade_ids'        => implode(',', array_filter($gradeIds)),
        'nafes_level_advanced'   => (string) $advanced,
        'nafes_level_proficient' => (string) $proficient,
        'nafes_level_basic'      => (string) $basic,
        'nafes_practice_size'    => (string) max(5, min(40, input_int('nafes_practice_size', 10))),
    ]);
    Activity::log('nafes_settings');
    flash('success', 'تم حفظ إعدادات منصة نافس.');
    redirect('admin/nafes_settings.php');
}

$subjects        = Database::all('SELECT * FROM subjects ORDER BY sort_order, name');
$grades          = Database::all('SELECT * FROM grades ORDER BY sort_order, name');
$selectedSubject = Nafes::idList(Settings::get('nafes_subject_ids'));
$selectedGrade   = Nafes::idList(Settings::get('nafes_grade_ids'));
$needsMigration  = Migrator::needed();

page_start('إعدادات منصة نافس', 'admin', 'nafes_settings');
?>

<?php if ($needsMigration): ?>
  <div class="alert alert-warning flex">
    <span>قاعدة البيانات تحتاج إلى ترقية لتفعيل جميع مزايا منصة نافس.</span>
    <form method="post" class="mb0">
      <?= csrf_field() ?>
      <button class="btn btn-sm btn-primary" name="action" value="migrate">ترقية قاعدة البيانات الآن</button>
    </form>
  </div>
<?php endif; ?>

<form method="post">
  <?= csrf_field() ?>
  <div class="card">
    <div class="card-head"><h2>عام</h2></div>
    <div class="card-body">
      <div class="grid grid-2">
        <div class="field"><label>عنوان المنصة</label><input type="text" name="nafes_title" value="<?= h(Settings::get('nafes_title')) ?>"></div>
        <div class="field"><label>عدد أسئلة التدريب الذاتي</label><input type="number" min="5" max="40" name="nafes_practice_size" value="<?= h(Settings::get('nafes_practice_size')) ?>"></div>
      </div>
      <label class="flex"><input type="checkbox" name="nafes_enabled" style="width:auto" <?= Settings::bool('nafes_enabled') ? 'checked' : '' ?>>
        تفعيل منصة نافس (دخول الطلاب والاختبارات)</label>
      <label class="flex"><input type="checkbox" name="nafes_student_review" style="width:auto" <?= Settings::bool('nafes_student_review') ? 'checked' : '' ?>>
        السماح للطالب بمراجعة إجاباته والإجابات الصحيحة بعد التسليم (ما لم يُخفِها الاختبار)</label>
    </div>
  </div>

  <div class="grid grid-2">
    <div class="card">
      <div class="card-head"><h2>مواد نافس</h2></div>
      <div class="card-body">
        <p class="help">المواد التي تظهر في بنك الأسئلة والاختبارات. اختبارات نافس الوطنية تقيس: <b>القراءة (لغتي)، الرياضيات، العلوم</b>. عدم اختيار أي مادة يعني إظهار كل المواد.</p>
        <?php foreach ($subjects as $subject): ?>
          <label class="flex"><input type="checkbox" style="width:auto" name="subject_ids[]" value="<?= (int) $subject['id'] ?>"
                 <?= in_array((int) $subject['id'], $selectedSubject, true) ? 'checked' : '' ?>> <?= h($subject['name']) ?></label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h2>صفوف نافس</h2></div>
      <div class="card-body">
        <p class="help">الصفوف المستهدفة: <b>الثالث والسادس الابتدائي والثالث المتوسط</b>. يمكن إضافة صفوف أخرى للتهيئة المبكرة. عدم اختيار أي صف يعني إظهار كل الصفوف.</p>
        <?php foreach ($grades as $grade): ?>
          <label class="flex"><input type="checkbox" style="width:auto" name="grade_ids[]" value="<?= (int) $grade['id'] ?>"
                 <?= in_array((int) $grade['id'], $selectedGrade, true) ? 'checked' : '' ?>> <?= h($grade['name']) ?></label>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2>مستويات الأداء</h2></div>
    <div class="card-body">
      <p class="help">تُصنَّف نتيجة الطالب في كل اختبار ومهارة إلى أربعة مستويات حسب النسبة المئوية للإجابات الصحيحة.</p>
      <div class="grid grid-4">
        <div class="field"><label><span class="badge level-advanced">متقدم</span> من</label><input type="number" min="1" max="100" name="nafes_level_advanced" value="<?= h(Settings::get('nafes_level_advanced')) ?>"></div>
        <div class="field"><label><span class="badge badge-green">متمكن</span> من</label><input type="number" min="1" max="100" name="nafes_level_proficient" value="<?= h(Settings::get('nafes_level_proficient')) ?>"></div>
        <div class="field"><label><span class="badge badge-amber">أساسي</span> من</label><input type="number" min="1" max="100" name="nafes_level_basic" value="<?= h(Settings::get('nafes_level_basic')) ?>"></div>
        <div class="field"><label><span class="badge badge-red">دون الأساسي</span></label><input type="text" value="أقل من حد «أساسي»" disabled></div>
      </div>
    </div>
  </div>

  <div class="toolbar"><button class="btn btn-primary" type="submit">حفظ الإعدادات</button></div>
</form>

<?php page_end('admin'); ?>
