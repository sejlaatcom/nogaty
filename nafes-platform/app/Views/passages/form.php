<?php $p = $passage; ?>
<div class="page-header"><h1><?= e($title) ?></h1>
  <div class="d-flex gap-2"><?php if ($p): ?><a class="btn btn-soft" href="<?= e(url('/questions/create', ['passage' => $p['id'], 'subject' => $p['subject_id'], 'grade' => $p['grade_id']])) ?>">+ سؤال على هذا النص</a><?php endif; ?>
  <a class="btn btn-light" href="<?= e(url('/passages')) ?>">رجوع</a></div></div>
<div class="row g-3">
  <div class="col-lg-8">
    <form class="card" method="post" enctype="multipart/form-data" action="<?= e(url($p ? '/passages/' . $p['id'] : '/passages')) ?>">
      <?= csrf_field() ?>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label required">العنوان</label><input class="form-control" name="title" value="<?= e(old('title', $p['title'] ?? '')) ?>" required></div>
          <div class="col-md-3"><label class="form-label required">المادة</label><select class="form-select" name="subject_id"><?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected($p['subject_id'] ?? ($s['code'] === 'reading' ? $s['id'] : 0), $s['id']) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-3"><label class="form-label required">الصف</label><select class="form-select" name="grade_id"><?php foreach ($grades as $g): ?><option value="<?= (int) $g['id'] ?>"<?= selected($p['grade_id'] ?? 0, $g['id']) ?>><?= e($g['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-12"><label class="form-label required">النص</label><textarea class="form-control" name="body" rows="12" required><?= e(old('body', $p['body'] ?? '')) ?></textarea>
            <div class="form-text">يدعم **الغامق** والجداول بصيغة | عمود | عمود |.</div></div>
          <div class="col-md-6"><label class="form-label">المصدر وحقوق الاستخدام</label><input class="form-control" name="source" value="<?= e(old('source', $p['source'] ?? '')) ?>"></div>
          <div class="col-md-6"><label class="form-label">صورة مرافقة (اختياري)</label><input type="file" class="form-control" name="image" accept="image/*"><input class="form-control form-control-sm mt-1" name="alt_text" placeholder="وصف الصورة"></div>
        </div>
        <?php if ($media): ?><div class="d-flex gap-2 mt-3 flex-wrap"><?php foreach ($media as $m): ?>
          <label class="border rounded p-1 text-center small"><img src="<?= e(url('/media/' . $m['id'])) ?>" style="max-height:90px" alt=""><br><input type="checkbox" name="remove_media[]" value="<?= (int) $m['id'] ?>"> حذف</label>
        <?php endforeach; ?></div><?php endif; ?>
      </div>
      <div class="card-footer bg-transparent"><button class="btn btn-primary">حفظ النص</button></div>
    </form>
  </div>
  <div class="col-lg-4">
    <div class="card"><div class="card-header"><h2>أسئلة النص (<?= count($questions) ?>)</h2></div>
      <ul class="list-group list-group-flush small">
        <?php foreach ($questions as $q): ?><li class="list-group-item"><a href="<?= e(url('/questions/' . $q['id'])) ?>">#<?= (int) $q['id'] ?> <?= e(str_limit($q['stem'], 70)) ?></a></li><?php endforeach; ?>
        <?php if (!$questions): ?><li class="list-group-item text-muted">لا أسئلة بعد.</li><?php endif; ?>
      </ul></div>
  </div>
</div>
