<?php use App\Services\QuestionBank as QB; use App\Services\QuestionRenderer as R; [$sl, $sc] = QB::STATUSES[$q['status']]; ?>
<div class="page-header">
  <div><h1>السؤال #<?= (int) $q['id'] ?> <span class="badge badge-soft-<?= $sc ?> fs-6"><?= $sl ?></span></h1>
    <div class="sub"><?= e($q['subject_name'] . ' — ' . $q['grade_name']) ?> · <?= e(QB::TYPES[$q['type']]) ?> · النسخة <?= (int) $q['version'] ?></div></div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($canEdit): ?><a class="btn btn-primary" href="<?= e(url('/questions/' . $q['id'] . '/edit')) ?>"><i class="bi bi-pencil"></i> تعديل</a><?php endif; ?>
    <?php if (can('questions.create')): ?><form method="post" action="<?= e(url('/questions/' . $q['id'] . '/duplicate')) ?>"><?= csrf_field() ?><button class="btn btn-light"><i class="bi bi-copy"></i> استنساخ</button></form><?php endif; ?>
    <?php if (can('questions.review', 'questions.edit_any')): ?>
      <form method="post" action="<?= e(url('/questions/' . $q['id'] . '/status')) ?>" class="d-flex gap-1"><?= csrf_field() ?>
        <?php if ($q['status'] !== 'published'): ?><button class="btn btn-success" name="status" value="published"><i class="bi bi-check2-circle"></i> اعتماد ونشر</button><?php endif; ?>
        <?php if ($q['status'] === 'review'): ?><input class="form-control" name="note" placeholder="ملاحظة للمعد"><button class="btn btn-light" name="status" value="draft">إعادة للمعد</button><?php endif; ?>
        <?php if ($q['status'] !== 'archived'): ?><button class="btn btn-light" name="status" value="archived" data-confirm="أرشفة السؤال؟ لن يظهر في الاختبارات الجديدة.">أرشفة</button><?php endif; ?>
      </form>
    <?php elseif ($canEdit && $q['status'] === 'draft'): ?>
      <form method="post" action="<?= e(url('/questions/' . $q['id'] . '/status')) ?>"><?= csrf_field() ?><button class="btn btn-soft" name="status" value="review">إرسال للمراجعة</button></form>
    <?php endif; ?>
    <?php if ($canEdit): ?><form method="post" action="<?= e(url('/questions/' . $q['id'] . '/delete')) ?>"><?= csrf_field() ?><button class="btn btn-light text-danger" data-confirm="حذف السؤال؟ إن كان مستخدماً في اختبارات فسيُؤرشف فقط."><i class="bi bi-trash"></i></button></form><?php endif; ?>
  </div>
</div>
<?php if ($q['review_note']): ?><div class="alert alert-warning"><b>ملاحظة المراجع:</b> <?= e($q['review_note']) ?></div><?php endif; ?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card mb-3"><div class="card-header"><h2>معاينة (للمعلم)</h2></div>
      <div class="card-body"><?= R::passage($snapshot['passage']) ?><?= R::stem($snapshot) ?><?= R::body($snapshot, 'preview') ?></div></div>
    <?php if ($revisions): ?>
    <div class="card"><div class="card-header"><h2>سجل النسخ</h2></div>
      <div class="table-responsive"><table class="table table-sm">
        <thead><tr><th>النسخة</th><th>التاريخ</th><th>بواسطة</th><th>الملاحظة</th><th>نص السؤال حينها</th><th></th></tr></thead>
        <tbody><?php foreach ($revisions as $r): $snap = json_decode($r['snapshot'], true); ?>
          <tr><td><?= (int) $r['version'] ?></td><td class="small"><?= e(dt($r['created_at'])) ?></td><td class="small"><?= e($r['full_name'] ?? '—') ?></td><td class="small"><?= e($r['change_note'] ?? '') ?></td>
            <td class="small"><?= e(str_limit($snap['stem'] ?? '', 70)) ?></td>
            <td><?php if ($canEdit): ?><form method="post" action="<?= e(url('/questions/' . $q['id'] . '/revisions/' . $r['id'] . '/restore')) ?>"><?= csrf_field() ?>
              <button class="btn btn-sm btn-light" data-confirm="استعادة النسخة <?= (int) $r['version'] ?>؟ ستُحفظ النسخة الحالية في السجل.">استعادة</button></form><?php endif; ?></td></tr>
        <?php endforeach; ?></tbody>
      </table></div></div>
    <?php endif; ?>
  </div>
  <div class="col-lg-4">
    <div class="card mb-3"><div class="card-body small">
      <dl class="row mb-0">
        <dt class="col-5">المجال</dt><dd class="col-7"><?= e($q['domain_name'] ?? '—') ?></dd>
        <dt class="col-5">المهارة</dt><dd class="col-7"><?= e(($q['skill_code'] ? $q['skill_code'] . ' ' : '') . ($q['skill_name'] ?? '—')) ?></dd>
        <dt class="col-5">مؤشر الأداء</dt><dd class="col-7"><?= e($q['indicator_name'] ?? '—') ?></dd>
        <dt class="col-5">الصعوبة</dt><dd class="col-7"><?= QB::DIFFICULTIES[(int) $q['difficulty']] ?></dd>
        <dt class="col-5">المستوى المعرفي</dt><dd class="col-7"><?= QB::COGNITIVE[$q['cognitive_level']] ?></dd>
        <dt class="col-5">الدرجة</dt><dd class="col-7"><?= num((float) $q['points'], 2) ?></dd>
        <dt class="col-5">المصدر</dt><dd class="col-7"><?= e($q['source'] ?? '—') ?></dd>
        <dt class="col-5">حقوق الاستخدام</dt><dd class="col-7"><?= e($q['usage_rights'] ?? '—') ?></dd>
        <dt class="col-5">المُعد</dt><dd class="col-7"><?= e($q['author_name'] ?? '—') ?></dd>
        <dt class="col-5">المراجع</dt><dd class="col-7"><?= e($q['reviewer_name'] ?? '—') ?> <?= $q['reviewed_at'] ? '<br>' . e(dt($q['reviewed_at'])) : '' ?></dd>
        <dt class="col-5">الإنشاء</dt><dd class="col-7"><?= e(dt($q['created_at'])) ?></dd>
        <dt class="col-5">آخر تعديل</dt><dd class="col-7"><?= e(dt($q['updated_at'])) ?></dd>
      </dl>
    </div></div>
    <div class="card mb-3"><div class="card-body">
      <div class="small text-muted">إحصاءات الإجابة</div>
      <?php $n = (int) ($stats['n'] ?? 0); ?>
      <div class="fs-4 fw-bold"><?= $n ? pct((int) $stats['correct'] / $n * 100) : '—' ?></div>
      <div class="small text-muted">نسبة الإجابات الصحيحة من <?= $n ?> إجابة</div>
    </div></div>
    <div class="card"><div class="card-header"><h2>مستخدم في</h2></div>
      <ul class="list-group list-group-flush small"><?php foreach ($usage as $u): ?><li class="list-group-item"><a href="<?= e(url('/tests/' . $u['id'])) ?>"><?= e($u['title']) ?></a></li><?php endforeach; ?>
      <?php if (!$usage): ?><li class="list-group-item text-muted">لم يُستخدم في اختبارات بعد.</li><?php endif; ?></ul></div>
  </div>
</div>
