<?php use App\Models\ClassRoom; use App\Models\Student; $s = $student; ?>
<div class="page-header"><h1><?= e($title) ?></h1><a class="btn btn-light" href="<?= e(url($s ? '/students/' . $s['id'] : '/students')) ?>">رجوع</a></div>
<form method="post" action="<?= e(url($s ? '/students/' . $s['id'] : '/students')) ?>" class="card" data-once>
  <?= csrf_field() ?>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label required">الاسم الكامل</label><input class="form-control" name="full_name" value="<?= e(old('full_name', $s['full_name'] ?? '')) ?>" required maxlength="150"></div>
      <div class="col-md-3"><label class="form-label required">الرقم المدرسي / معرف الطالب</label><input class="form-control" name="student_number" value="<?= e(old('student_number', $s['student_number'] ?? '')) ?>" required maxlength="30"></div>
      <div class="col-md-3"><label class="form-label">اسم المستخدم</label><input class="form-control ltr" name="username" value="<?= e(old('username', $s['username'] ?? '')) ?>" placeholder="الرقم المدرسي افتراضياً">
        <div class="form-text">أحرف إنجليزية أو أرقام.</div></div>
      <div class="col-md-4"><label class="form-label <?= $s ? '' : 'required' ?>">الفصل</label>
        <select class="form-select" name="class_id" <?= $s ? '' : 'required' ?>><option value="">— اختر —</option>
          <?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected(old('class_id', $s['class_id'] ?? $defaultClass), $c['id']) ?>><?= e(ClassRoom::label($c)) ?></option><?php endforeach; ?></select>
        <?php if ($s): ?><div class="form-text">تغيير الفصل يُسجّل نقلاً ويحتفظ بالتسجيل السابق.</div><?php endif; ?></div>
      <div class="col-md-4"><label class="form-label required">الحالة</label>
        <select class="form-select" name="status"><?php foreach (Student::STATUSES as $k => $v): ?><option value="<?= $k ?>"<?= selected(old('status', $s['status'] ?? 'active'), $k) ?>><?= $v ?></option><?php endforeach; ?></select></div>
      <div class="col-md-4"><label class="form-label"><?= $s ? 'كلمة مرور جديدة (اختياري)' : 'كلمة المرور الأولية (تُولد تلقائياً إن تُركت فارغة)' ?></label>
        <input type="password" class="form-control ltr" name="password" autocomplete="new-password"></div>
      <div class="col-12"><label class="form-label">ملاحظات إدارية (اختياري)</label><textarea class="form-control" name="notes" rows="2" maxlength="500"><?= e(old('notes', $s['notes'] ?? '')) ?></textarea></div>
    </div>
    <p class="form-text mt-3"><i class="bi bi-shield-check"></i> لا تُجمع بيانات شخصية غير ضرورية — رقم الهوية الوطنية غير مطلوب.</p>
  </div>
  <div class="card-footer bg-transparent"><button class="btn btn-primary"><i class="bi bi-check2"></i> حفظ</button></div>
</form>
