<?php use App\Services\QuestionRenderer as R; ?>
<div class="page-header">
  <div><h1>التصحيح اليدوي</h1><div class="sub">الإجابات القصيرة التي تحتاج تقدير المعلم — تُحدَّث نتيجة الطالب فور الحفظ</div></div>
  <?php if (!empty($_GET['attempt'])): ?><a class="btn btn-light" href="<?= e(url('/grading')) ?>">كل الإجابات</a><?php endif; ?>
</div>
<?php foreach ($items as $it): $q = $it['snap']; $pts = (float) $it['points']; ?>
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between flex-wrap gap-2">
      <div><b><?= e($it['full_name']) ?></b> <span class="text-muted small">· <?= e($it['class_name'] ?? '') ?> · <?= e($it['title']) ?></span></div>
      <a class="small" href="<?= e(url('/attempts/' . $it['attempt_id'])) ?>">تقرير المحاولة</a>
    </div>
    <div class="card-body">
      <?= R::passage($q['passage'] ?? null) ?><?= R::stem($q) ?>
      <div class="border rounded-3 p-3 bg-body-tertiary mb-2">إجابة الطالب: <b><?= e(($it['ans']['text'] ?? '') !== '' ? $it['ans']['text'] : '— لم يجب —') ?></b></div>
      <?php if ($q['options']): ?><div class="small text-success mb-2">الإجابات النموذجية: <?= e(implode(' / ', array_column($q['options'], 'body'))) ?></div><?php endif; ?>
      <?php if (!empty($q['explanation'])): ?><div class="small text-muted mb-2"><b>التفسير:</b> <?= rich_inline($q['explanation']) ?></div><?php endif; ?>
      <form method="post" action="<?= e(url('/grading/' . $it['id'])) ?>" class="row g-2 align-items-end" data-once>
        <?= csrf_field() ?>
        <div class="col-auto"><label class="form-label">الدرجة من <?= num($pts, 2) ?></label>
          <div class="btn-group d-flex" role="group">
            <?php foreach (array_unique([0, $pts / 2, $pts]) as $v): ?><button type="button" class="btn btn-outline-primary btn-sm" onclick="this.form.score.value='<?= $v ?>'"><?= num($v, 2) ?></button><?php endforeach; ?>
          </div></div>
        <div class="col-auto"><input type="number" step="0.25" min="0" max="<?= $pts ?>" class="form-control" name="score" required style="width:7rem"></div>
        <div class="col"><input class="form-control" name="note" maxlength="500" placeholder="ملاحظة للطالب (اختياري)"></div>
        <div class="col-auto"><button class="btn btn-primary"><i class="bi bi-check2"></i> حفظ</button></div>
      </form>
    </div>
  </div>
<?php endforeach; ?>
<?php if (!$items): ?><div class="card"><div class="empty-state"><i class="bi bi-check2-circle"></i>لا توجد إجابات بانتظار التصحيح.</div></div><?php endif; ?>
