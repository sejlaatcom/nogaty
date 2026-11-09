<?php
$stages = ['primary' => 'ابتدائي', 'intermediate' => 'متوسط', 'secondary' => 'ثانوي'];
$tabs = ['classes' => ['الفصول والشعب', 'grid-3x3-gap'], 'grades' => ['الصفوف', 'layers'], 'subjects' => ['المواد', 'book'], 'years' => ['الأعوام الدراسية', 'calendar3']];
?>
<div class="page-header">
  <div><h1>الهيكل المدرسي</h1><div class="sub">الأعوام الدراسية والصفوف المستهدفة والفصول والمواد</div></div>
</div>

<ul class="nav nav-pills mb-3 gap-1">
  <?php foreach ($tabs as $key => [$label, $icon]): ?>
    <li class="nav-item"><a class="nav-link <?= $tab === $key ? 'active' : '' ?>" href="<?= e(url('/structure', ['tab' => $key])) ?>"><i class="bi bi-<?= $icon ?>"></i> <?= $label ?></a></li>
  <?php endforeach; ?>
</ul>

<?php if ($tab === 'classes'): ?>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header"><h2>إضافة فصل</h2></div>
      <form class="card-body" method="post" action="<?= e(url('/structure/classes')) ?>">
        <?= csrf_field() ?><input type="hidden" name="year" value="<?= (int) $yearId ?>">
        <div class="mb-2"><label class="form-label required">الصف</label>
          <select class="form-select" name="grade_id" required><?php foreach ($grades as $g): if ((int) $g['is_active']): ?><option value="<?= (int) $g['id'] ?>"<?= selected($g['is_nafes_target'] && $g['stage'] === 'intermediate' ? $g['id'] : 0, $g['id']) ?>><?= e($g['name']) ?></option><?php endif; endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label required">اسم الفصل / الشعبة</label><input class="form-control" name="name" required maxlength="40" placeholder="مثل: 1 أو أ"></div>
        <button class="btn btn-primary w-100">إضافة</button>
      </form>
      <form class="card-body border-top" method="post" action="<?= e(url('/structure/classes')) ?>">
        <?= csrf_field() ?><input type="hidden" name="year" value="<?= (int) $yearId ?>"><input type="hidden" name="mode" value="bulk">
        <label class="form-label">إنشاء عدة فصول مرقمة دفعة واحدة</label>
        <div class="input-group">
          <select class="form-select" name="grade_id"><?php foreach ($grades as $g): if ((int) $g['is_active']): ?><option value="<?= (int) $g['id'] ?>"><?= e($g['name']) ?></option><?php endif; endforeach; ?></select>
          <input type="number" class="form-control" name="count" min="1" max="20" value="3" style="max-width:80px">
          <button class="btn btn-soft">إنشاء</button>
        </div>
      </form>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><h2>فصول العام</h2>
        <form method="get" class="d-flex gap-2"><input type="hidden" name="tab" value="classes">
          <select class="form-select form-select-sm" name="year" data-auto-submit><?php foreach ($years as $y): ?><option value="<?= (int) $y['id'] ?>"<?= selected($yearId, $y['id']) ?>><?= e($y['name']) ?><?= $y['is_current'] ? ' (الحالي)' : '' ?></option><?php endforeach; ?></select></form>
      </div>
      <div class="table-responsive">
        <table class="table table-hover" data-table id="classes-table">
          <thead><tr><th>الصف</th><th>الفصل</th><th>الطلاب</th><th>الحالة</th><th data-sort="false"></th></tr></thead>
          <tbody>
          <?php foreach ($classes as $c): ?>
            <tr>
              <td><?= e($c['grade_name']) ?> <?= $c['is_nafes_target'] ? '<span class="badge badge-soft-accent">نافس</span>' : '' ?></td>
              <td><strong><?= e($c['name']) ?></strong></td>
              <td><a href="<?= e(url('/students', ['class' => $c['id']])) ?>"><?= (int) $c['students_count'] ?></a></td>
              <td><?= (int) $c['is_active'] ? '<span class="badge badge-soft-success">مفعّل</span>' : '<span class="badge badge-soft-secondary">معطّل</span>' ?></td>
              <td class="actions">
                <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#cls<?= (int) $c['id'] ?>"><i class="bi bi-pencil"></i></button>
                <form class="d-inline" method="post" action="<?= e(url('/structure/classes/' . $c['id'] . '/toggle')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-light"><i class="bi bi-<?= (int) $c['is_active'] ? 'pause' : 'play' ?>-circle"></i></button></form>
                <form class="d-inline" method="post" action="<?= e(url('/structure/classes/' . $c['id'] . '/delete')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-light text-danger" data-confirm="حذف الفصل؟ إن كان مرتبطاً بطلاب أو نتائج فسيُعطّل فقط."><i class="bi bi-trash"></i></button></form>
              </td>
            </tr>
            <tr class="collapse empty-row" id="cls<?= (int) $c['id'] ?>"><td colspan="5">
              <form class="row g-2 align-items-end" method="post" action="<?= e(url('/structure/classes')) ?>">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="year" value="<?= (int) $yearId ?>">
                <div class="col-md-4"><select class="form-select form-select-sm" name="grade_id"><?php foreach ($grades as $g): ?><option value="<?= (int) $g['id'] ?>"<?= selected($c['grade_id'], $g['id']) ?>><?= e($g['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><input class="form-control form-control-sm" name="name" value="<?= e($c['name']) ?>" required></div>
                <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1"<?= checked($c['is_active']) ?>><label class="form-check-label">مفعّل</label></div></div>
                <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">حفظ</button></div>
              </form>
            </td></tr>
          <?php endforeach; ?>
          <?php if (!$classes): ?><tr class="empty-row"><td colspan="5"><div class="empty-state"><i class="bi bi-grid-3x3-gap"></i>لا توجد فصول لهذا العام.</div></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php elseif ($tab === 'grades'): ?>
<div class="card">
  <div class="card-header"><h2>الصفوف الدراسية</h2><span class="text-muted small">«مستهدف بنافس» يُظهر الصف في بنك الأسئلة والاختبارات والتقارير. المنصة مهيأة افتراضياً للثالث المتوسط.</span></div>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>الصف</th><th>المرحلة</th><th>الترتيب</th><th>مستهدف بنافس</th><th>مفعّل</th><th>فصول العام</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($grades as $g): $f = 'gf' . (int) $g['id']; ?>
        <tr>
          <td><input class="form-control form-control-sm" form="<?= $f ?>" name="name" value="<?= e($g['name']) ?>" required></td>
          <td><select class="form-select form-select-sm" form="<?= $f ?>" name="stage"><?php foreach ($stages as $k => $v): ?><option value="<?= $k ?>"<?= selected($g['stage'], $k) ?>><?= $v ?></option><?php endforeach; ?></select></td>
          <td style="width:90px"><input type="number" class="form-control form-control-sm" form="<?= $f ?>" name="level_order" value="<?= (int) $g['level_order'] ?>"></td>
          <td><input class="form-check-input" type="checkbox" form="<?= $f ?>" name="is_nafes_target" value="1"<?= checked($g['is_nafes_target']) ?>></td>
          <td><input class="form-check-input" type="checkbox" form="<?= $f ?>" name="is_active" value="1"<?= checked($g['is_active']) ?>></td>
          <td><?= (int) $g['classes_count'] ?></td>
          <td><form id="<?= $f ?>" method="post" action="<?= e(url('/structure/grades')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $g['id'] ?>"><button class="btn btn-sm btn-soft">حفظ</button></form></td>
        </tr>
      <?php endforeach; ?>
        <tr class="table-light">
          <td><input class="form-control form-control-sm" form="gnew" name="name" placeholder="صف جديد" required></td>
          <td><select class="form-select form-select-sm" form="gnew" name="stage"><?php foreach ($stages as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></td>
          <td><input type="number" class="form-control form-control-sm" form="gnew" name="level_order" value="13"></td>
          <td><input class="form-check-input" type="checkbox" form="gnew" name="is_nafes_target" value="1"></td>
          <td><input class="form-check-input" type="checkbox" form="gnew" name="is_active" value="1" checked></td>
          <td></td><td><form id="gnew" method="post" action="<?= e(url('/structure/grades')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-primary">إضافة</button></form></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'subjects'): ?>
<div class="card">
  <div class="card-header"><h2>المواد</h2><span class="text-muted small">مواد نافس الأساسية: القراءة والرياضيات والعلوم. يمكن إضافة مواد أخرى.</span></div>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>المادة</th><th>الرمز</th><th>اللون</th><th>أيقونة</th><th>الترتيب</th><th>مفعّلة</th><th>الأسئلة</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($subjects as $s): $f = 'sf' . (int) $s['id']; ?>
        <tr>
          <td><input class="form-control form-control-sm" form="<?= $f ?>" name="name" value="<?= e($s['name']) ?>" required></td>
          <td><input class="form-control form-control-sm ltr" form="<?= $f ?>" name="code" value="<?= e($s['code']) ?>" required style="max-width:110px"></td>
          <td><input type="color" class="form-control form-control-color" form="<?= $f ?>" name="color" value="<?= e($s['color'] ?: '#0b5394') ?>"></td>
          <td><input class="form-control form-control-sm ltr" form="<?= $f ?>" name="icon" value="<?= e($s['icon']) ?>" style="max-width:120px"></td>
          <td style="width:80px"><input type="number" class="form-control form-control-sm" form="<?= $f ?>" name="sort_order" value="<?= (int) $s['sort_order'] ?>"></td>
          <td><input class="form-check-input" type="checkbox" form="<?= $f ?>" name="is_active" value="1"<?= checked($s['is_active']) ?>></td>
          <td><?= (int) $s['questions_count'] ?></td>
          <td><form id="<?= $f ?>" method="post" action="<?= e(url('/structure/subjects')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn btn-sm btn-soft">حفظ</button></form></td>
        </tr>
      <?php endforeach; ?>
        <tr class="table-light">
          <td><input class="form-control form-control-sm" form="snew" name="name" placeholder="مادة جديدة" required></td>
          <td><input class="form-control form-control-sm ltr" form="snew" name="code" placeholder="code" required></td>
          <td><input type="color" class="form-control form-control-color" form="snew" name="color" value="#0b5394"></td>
          <td><input class="form-control form-control-sm ltr" form="snew" name="icon" placeholder="book"></td>
          <td><input type="number" class="form-control form-control-sm" form="snew" name="sort_order" value="9"></td>
          <td><input class="form-check-input" type="checkbox" form="snew" name="is_active" value="1" checked></td><td></td>
          <td><form id="snew" method="post" action="<?= e(url('/structure/subjects')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-primary">إضافة</button></form></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<?php else: ?>
<div class="row g-3">
  <div class="col-lg-4">
    <form class="card" method="post" action="<?= e(url('/structure/years')) ?>">
      <?= csrf_field() ?>
      <div class="card-header"><h2>عام دراسي جديد</h2></div>
      <div class="card-body">
        <div class="mb-2"><label class="form-label required">الاسم</label><input class="form-control" name="name" placeholder="1449هـ" required></div>
        <div class="row g-2 mb-2">
          <div class="col"><label class="form-label">البداية</label><input type="date" class="form-control" name="starts_on"></div>
          <div class="col"><label class="form-label">النهاية</label><input type="date" class="form-control" name="ends_on"></div>
        </div>
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="copy_classes" value="1" id="cc" checked><label class="form-check-label" for="cc">نسخ هيكل الفصول من العام الحالي</label></div>
        <button class="btn btn-primary w-100">إضافة العام</button>
      </div>
    </form>
  </div>
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><h2>الأعوام الدراسية</h2></div>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>العام</th><th>الفترة</th><th>الفصول</th><th>النتائج المحفوظة</th><th>الحالة</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($years as $y): ?>
            <tr>
              <td><strong><?= e($y['name']) ?></strong></td>
              <td class="small"><?= e(dt($y['starts_on'], false)) ?> — <?= e(dt($y['ends_on'], false)) ?></td>
              <td><?= (int) $y['classes_count'] ?></td>
              <td><?= num((int) $y['results_count']) ?></td>
              <td><?= $y['is_current'] ? '<span class="badge badge-soft-success">الحالي</span>' : ($y['is_archived'] ? '<span class="badge badge-soft-secondary">مؤرشف</span>' : '<span class="badge badge-soft-primary">متاح</span>') ?></td>
              <td class="actions">
                <?php if (!$y['is_current']): ?>
                  <form class="d-inline" method="post" action="<?= e(url('/structure/years/' . $y['id'] . '/current')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-soft" data-confirm="تعيين <?= e($y['name']) ?> عاماً دراسياً حالياً؟ ستعمل الاختبارات والفصول الجديدة عليه.">تعيين حالياً</button></form>
                  <?php if (!$y['is_archived']): ?><form class="d-inline" method="post" action="<?= e(url('/structure/years/' . $y['id'] . '/archive')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-light" data-confirm="أرشفة العام؟ تُغلق تسجيلات الطلاب فيه وتبقى نتائجه محفوظة.">أرشفة</button></form><?php endif; ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
