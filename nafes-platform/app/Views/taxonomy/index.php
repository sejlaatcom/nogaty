<?php
$q = ['subject' => $subjectId, 'grade' => $gradeId];
$hidden = csrf_field() . '<input type="hidden" name="subject" value="' . (int) $subjectId . '"><input type="hidden" name="grade" value="' . (int) $gradeId . '">';
$action = e(url('/taxonomy'));
?>
<div class="page-header">
  <div><h1>المجالات والمهارات</h1><div class="sub">الصف ← المادة ← المجال ← المهارة ← مؤشر الأداء — أساس تحليل الإتقان والخطط العلاجية</div></div>
  <?php if ($canManage): ?><a class="btn btn-soft" href="<?= e(url('/import/taxonomy')) ?>"><i class="bi bi-file-earmark-arrow-up"></i> استيراد التصنيفات</a><?php endif; ?>
</div>

<form class="filter-bar row g-2" method="get">
  <div class="col-md-4"><label class="form-label">الصف</label><select class="form-select" name="grade" data-auto-submit>
    <?php foreach ($grades as $g): ?><option value="<?= (int) $g['id'] ?>"<?= selected($gradeId, $g['id']) ?>><?= e($g['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-4"><label class="form-label">المادة</label><select class="form-select" name="subject" data-auto-submit>
    <?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected($subjectId, $s['id']) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
</form>

<?php foreach ($domains as $d): $did = (int) $d['id']; ?>
<div class="card mb-3">
  <div class="card-header">
    <h2><i class="bi bi-diagram-2 text-primary"></i> <?= e($d['name']) ?> <?= $d['grade_id'] === null ? '<span class="badge badge-soft-secondary">لكل الصفوف</span>' : '' ?>
      <span class="badge badge-soft-primary"><?= (int) $d['q_count'] ?> سؤال منشور</span></h2>
    <?php if ($canManage): ?>
      <div class="d-flex gap-1">
        <button class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#ed<?= $did ?>"><i class="bi bi-pencil"></i></button>
        <form method="post" action="<?= $action ?>"><?= $hidden ?><input type="hidden" name="entity" value="domain"><input type="hidden" name="id" value="<?= $did ?>">
          <button class="btn btn-sm btn-light text-danger" name="action" value="delete" data-confirm="حذف المجال وكل مهاراته ومؤشراته؟"><i class="bi bi-trash"></i></button></form>
      </div>
    <?php endif; ?>
  </div>
  <?php if ($canManage): ?>
    <form class="collapse card-body border-bottom bg-light" id="ed<?= $did ?>" method="post" action="<?= $action ?>">
      <?= $hidden ?><input type="hidden" name="entity" value="domain"><input type="hidden" name="id" value="<?= $did ?>">
      <div class="row g-2"><div class="col-md-8"><input class="form-control" name="name" value="<?= e($d['name']) ?>" required></div>
        <div class="col-md-2"><input type="number" class="form-control" name="sort_order" value="<?= (int) $d['sort_order'] ?>"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">حفظ</button></div></div>
    </form>
  <?php endif; ?>
  <div class="list-group list-group-flush">
    <?php foreach ($skills[$did] ?? [] as $s): $sid = (int) $s['id']; ?>
      <div class="list-group-item">
        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
          <div><span class="badge badge-soft-accent ltr"><?= e($s['code'] ?: '—') ?></span> <strong><?= e($s['name']) ?></strong>
            <a class="small ms-2" href="<?= e(url('/questions', ['skill' => $sid, 'subject' => $subjectId, 'grade' => $gradeId])) ?>"><?= (int) $s['q_count'] ?> سؤال</a></div>
          <?php if ($canManage): ?>
          <div class="d-flex gap-1">
            <button class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#es<?= $sid ?>"><i class="bi bi-pencil"></i></button>
            <form method="post" action="<?= $action ?>"><?= $hidden ?><input type="hidden" name="entity" value="skill"><input type="hidden" name="id" value="<?= $sid ?>">
              <button class="btn btn-sm btn-light text-danger" name="action" value="delete" data-confirm="حذف المهارة ومؤشراتها؟"><i class="bi bi-trash"></i></button></form>
          </div>
          <?php endif; ?>
        </div>
        <?php if ($canManage): ?>
        <form class="collapse mt-2" id="es<?= $sid ?>" method="post" action="<?= $action ?>">
          <?= $hidden ?><input type="hidden" name="entity" value="skill"><input type="hidden" name="id" value="<?= $sid ?>">
          <div class="row g-2"><div class="col-md-2"><input class="form-control form-control-sm ltr" name="code" value="<?= e($s['code']) ?>" placeholder="الرمز"></div>
            <div class="col-md-7"><input class="form-control form-control-sm" name="name" value="<?= e($s['name']) ?>" required></div>
            <div class="col-md-1"><input type="number" class="form-control form-control-sm" name="sort_order" value="<?= (int) $s['sort_order'] ?>"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">حفظ</button></div></div>
        </form>
        <?php endif; ?>
        <ul class="small text-muted mt-2 mb-1 ps-0" style="list-style:none">
          <?php foreach ($indicators[$sid] ?? [] as $i): ?>
            <li class="d-flex align-items-center gap-2 mb-1"><i class="bi bi-dot"></i><span class="ltr"><?= e($i['code'] ?? '') ?></span> <?= e($i['description']) ?>
              <?php if ($canManage): ?><form method="post" action="<?= $action ?>" class="d-inline"><?= $hidden ?><input type="hidden" name="entity" value="indicator"><input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
                <button class="btn btn-link btn-sm text-danger p-0" name="action" value="delete" data-confirm="حذف مؤشر الأداء؟"><i class="bi bi-x"></i></button></form><?php endif; ?></li>
          <?php endforeach; ?>
        </ul>
        <?php if ($canManage): ?>
        <form method="post" action="<?= $action ?>" class="d-flex gap-2 mt-1"><?= $hidden ?><input type="hidden" name="entity" value="indicator"><input type="hidden" name="parent_id" value="<?= $sid ?>">
          <input class="form-control form-control-sm" name="name" placeholder="+ مؤشر أداء جديد" required><button class="btn btn-sm btn-soft">إضافة</button></form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if ($canManage): ?>
      <form class="list-group-item bg-light" method="post" action="<?= $action ?>"><?= $hidden ?><input type="hidden" name="entity" value="skill"><input type="hidden" name="parent_id" value="<?= $did ?>">
        <div class="row g-2"><div class="col-md-2"><input class="form-control form-control-sm ltr" name="code" placeholder="الرمز"></div>
          <div class="col-md-8"><input class="form-control form-control-sm" name="name" placeholder="مهارة جديدة في هذا المجال" required></div>
          <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">إضافة مهارة</button></div></div>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<?php if (!$domains): ?><div class="card"><div class="empty-state"><i class="bi bi-diagram-2"></i>لا توجد مجالات لهذه المادة والصف.</div></div><?php endif; ?>

<?php if ($canManage): ?>
<form class="card" method="post" action="<?= $action ?>">
  <?= $hidden ?><input type="hidden" name="entity" value="domain">
  <div class="card-header"><h2>إضافة مجال</h2></div>
  <div class="card-body row g-2 align-items-center">
    <div class="col-md-7"><input class="form-control" name="name" placeholder="مثل: الهندسة والقياس" required></div>
    <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="all_grades" value="1" id="ag"><label class="form-check-label" for="ag">لكل الصفوف</label></div></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">إضافة</button></div>
  </div>
</form>
<?php endif; ?>
