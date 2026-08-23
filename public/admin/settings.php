<?php
declare(strict_types=1);
define('APP_AREA', 'admin');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';

Auth::requireAdmin();

if (is_post()) {
    csrf_verify();

    $keys = ['school_name', 'ministry_name', 'country_name', 'education_dept', 'principal_title',
             'principal_name', 'stage', 'sheet_title', 'family_message', 'footer_text', 'contact_email'];
    $values = [];
    foreach ($keys as $key) {
        $values[$key] = (string) input($key, '');
    }
    $values['show_notes_row']    = isset($_POST['show_notes_row']) ? '1' : '0';
    $values['show_teacher_name'] = isset($_POST['show_teacher_name']) ? '1' : '0';
    $values['public_enabled']    = isset($_POST['public_enabled']) ? '1' : '0';

    // رفع الشعارات
    foreach (['logo' => 'شعار المدرسة', 'vision_logo' => 'شعار الرؤية'] as $field => $label) {
        if (!empty($_FILES[$field]['name']) && is_uploaded_file($_FILES[$field]['tmp_name'])) {
            $file = $_FILES[$field];
            $info = @getimagesize($file['tmp_name']);
            $allowed = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];

            if ($info === false || !isset($allowed[$info[2]])) {
                flash('error', $label . ': يجب أن يكون صورة بصيغة PNG أو JPG.');
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                flash('error', $label . ': حجم الصورة يجب ألا يتجاوز 2 ميجابايت.');
            } else {
                $name = $field . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$info[2]];
                $dir  = PUBLIC_PATH . '/uploads';
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                if (move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
                    $old = Settings::get($field);
                    if ($old !== '' && is_file($dir . '/' . $old)) {
                        @unlink($dir . '/' . $old);
                    }
                    $values[$field] = $name;
                }
            }
        }
    }

    if (input('remove_logo') === '1') {
        $values['logo'] = '';
    }

    Settings::setMany($values);
    Activity::log('settings_update');
    flash('success', 'تم حفظ إعدادات المدرسة.');
    redirect('admin/settings.php');
}

page_start('إعدادات المدرسة', 'admin', 'settings');
?>
<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>

  <div class="card">
    <div class="card-head"><h2>بيانات المدرسة (تظهر في ترويسة الخطة)</h2></div>
    <div class="card-body">
      <div class="grid grid-2">
        <div class="field"><label>اسم المدرسة</label><input type="text" name="school_name" value="<?= h(Settings::get('school_name')) ?>" required></div>
        <div class="field"><label>إدارة التعليم</label><input type="text" name="education_dept" value="<?= h(Settings::get('education_dept')) ?>"></div>
        <div class="field"><label>الوزارة</label><input type="text" name="ministry_name" value="<?= h(Settings::get('ministry_name')) ?>"></div>
        <div class="field"><label>الدولة</label><input type="text" name="country_name" value="<?= h(Settings::get('country_name')) ?>"></div>
        <div class="field"><label>المرحلة</label><input type="text" name="stage" value="<?= h(Settings::get('stage')) ?>"></div>
        <div class="field"><label>مسمى مدير المدرسة</label><input type="text" name="principal_title" value="<?= h(Settings::get('principal_title')) ?>"></div>
        <div class="field"><label>اسم مدير/مديرة المدرسة</label><input type="text" name="principal_name" value="<?= h(Settings::get('principal_name')) ?>"></div>
        <div class="field"><label>البريد للتواصل</label><input type="email" name="contact_email" value="<?= h(Settings::get('contact_email')) ?>"></div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2>ورقة الخطة الأسبوعية</h2></div>
    <div class="card-body">
      <div class="grid grid-2">
        <div class="field"><label>عنوان الورقة</label><input type="text" name="sheet_title" value="<?= h(Settings::get('sheet_title')) ?>"></div>
        <div class="field"><label>نص التذييل</label><input type="text" name="footer_text" value="<?= h(Settings::get('footer_text')) ?>"></div>
      </div>
      <div class="field"><label>رسالة إلى الأسرة (النص الافتراضي)</label>
        <textarea name="family_message" rows="2"><?= h(Settings::get('family_message')) ?></textarea></div>
      <div class="grid grid-3">
        <label class="flex"><input type="checkbox" name="show_notes_row" style="width:auto" <?= Settings::bool('show_notes_row') ? 'checked' : '' ?>>
          إظهار صف «ملاحظات / إثراء»</label>
        <label class="flex"><input type="checkbox" name="show_teacher_name" style="width:auto" <?= Settings::bool('show_teacher_name') ? 'checked' : '' ?>>
          إظهار اسم المعلم بجانب المادة</label>
        <label class="flex"><input type="checkbox" name="public_enabled" style="width:auto" <?= Settings::bool('public_enabled') ? 'checked' : '' ?>>
          نشر الخطط المعتمدة في واجهة الموقع</label>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2>الشعارات</h2></div>
    <div class="card-body">
      <div class="grid grid-2">
        <div class="field">
          <label>شعار المدرسة</label>
          <?php if (Settings::get('logo') !== ''): ?>
            <div class="flex mb16">
              <img src="<?= h(url('uploads/' . Settings::get('logo'))) ?>" alt="شعار" style="height:60px">
              <label class="flex"><input type="checkbox" name="remove_logo" value="1" style="width:auto"> حذف الشعار</label>
            </div>
          <?php endif; ?>
          <input type="file" name="logo" accept="image/png,image/jpeg,image/gif,image/webp">
        </div>
        <div class="field">
          <label>شعار الرؤية (اختياري)</label>
          <?php if (Settings::get('vision_logo') !== ''): ?>
            <img src="<?= h(url('uploads/' . Settings::get('vision_logo'))) ?>" alt="رؤية" style="height:60px" class="mb16">
          <?php endif; ?>
          <input type="file" name="vision_logo" accept="image/png,image/jpeg,image/gif,image/webp">
        </div>
      </div>
      <p class="help">الحد الأقصى لحجم الصورة 2 ميجابايت، ويفضّل صيغة PNG بخلفية شفافة.</p>
    </div>
  </div>

  <div class="toolbar">
    <button class="btn btn-primary" type="submit">حفظ الإعدادات</button>
    <a class="btn btn-outline" target="_blank" href="<?= h(url('index.php')) ?>">معاينة واجهة الموقع</a>
  </div>
</form>
<?php page_end('admin'); ?>
