<?php /** حقول إضافة المرفقات : ملفات مرفوعة + روابط قوقل درايف */ ?>
<fieldset>
  <legend>📎 المرفقات</legend>

  <div class="mb">
    <div class="flex-between mb-sm">
      <b class="small">ملفات مرفوعة على الخادم</b>
      <button type="button" class="btn outline sm" onclick="addFileRow()">➕ إضافة ملف</button>
    </div>
    <div id="file-rows">
      <div class="flex mb-sm">
        <input type="file" name="attachments[]" style="flex:2">
        <input type="text" name="attachment_titles[]" placeholder="وصف المرفق (اختياري)" style="flex:1">
      </div>
    </div>
    <div class="hint">
      الصيغ المسموحة : <?= e(str_replace(',', '، ', (string) setting('allowed_extensions'))) ?><br>
      الحد الأقصى لحجم الملف الواحد : <?= e(setting('max_upload_mb', 50)) ?> ميجابايت
      (حد الخادم الحالي : <?= e(ini_get('upload_max_filesize')) ?>)
    </div>
  </div>

  <hr>

  <div>
    <div class="flex-between mb-sm">
      <b class="small">☁️ روابط قوقل درايف — للملفات الكبيرة والفيديو والصوت</b>
      <button type="button" class="btn outline sm" onclick="addDriveRow()">➕ إضافة رابط</button>
    </div>
    <div id="drive-rows">
      <div class="flex mb-sm">
        <input type="url" name="drive_urls[]" placeholder="https://drive.google.com/file/d/..." style="flex:2" dir="ltr">
        <input type="text" name="drive_titles[]" placeholder="اسم الملف" style="flex:1">
        <select name="drive_groups[]" style="flex:0 0 110px">
          <option value="document">مستند</option>
          <option value="image">صورة</option>
          <option value="video">فيديو</option>
          <option value="audio">صوت</option>
          <option value="archive">مضغوط</option>
          <option value="other">أخرى</option>
        </select>
      </div>
    </div>
    <div class="hint">
      انسخ رابط المشاركة من قوقل درايف والصقه هنا. يدعم النظام روابط الملفات والمجلدات
      ومستندات وجداول وعروض جوجل، ويولّد تلقائياً رابط العرض ورابط التحميل المباشر.<br>
      <b>تنبيه :</b> اضبط صلاحية الملف في درايف على «أي شخص لديه الرابط» ليتمكن المستخدمون من فتحه.
    </div>
  </div>
</fieldset>
