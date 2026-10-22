<?php
use App\Services\Grader; use App\Services\Levels; use App\Services\QuestionRenderer as R;
$r = $result; $a = $attempt;
$percent = (float) ($r['percent'] ?? 0);
?>
<div class="row g-3 mb-3">
  <div class="col-lg-4"><div class="card h-100"><div class="card-body text-center">
    <div class="score-ring" style="--p:<?= round($percent) ?>;--c:<?= Levels::COLORS[$r['level'] ?? 'below'] ?>"><div><b><?= pct($percent) ?></b><small><?= num((float) $r['score'], 2) ?> من <?= num((float) $r['max_score'], 2) ?></small></div></div>
    <div class="mb-2"><?= Levels::badge($r['level']) ?> <?= (int) $r['is_final'] ? '' : '<span class="badge badge-soft-warning">بانتظار التصحيح اليدوي</span>' ?></div>
    <div class="row g-2 small">
      <div class="col-4"><div class="text-success fw-bold fs-5"><?= (int) $r['correct_count'] ?></div>صحيحة</div>
      <div class="col-4"><div class="text-danger fw-bold fs-5"><?= (int) $r['wrong_count'] ?></div>خاطئة</div>
      <div class="col-4"><div class="text-muted fw-bold fs-5"><?= (int) $r['blank_count'] ?></div>دون إجابة</div>
    </div>
    <hr>
    <div class="small text-muted">المدة: <?= e(duration_label($a['duration_seconds'] !== null ? (int) $a['duration_seconds'] : null)) ?> · المحاولة <?= (int) $a['attempt_no'] ?> · <?= e(dt($a['submitted_at'])) ?>
      <?= (int) $a['auto_submitted'] ? '<br><span class="text-warning-emphasis">سُلّم تلقائياً عند انتهاء الوقت</span>' : '' ?></div>
  </div></div></div>

  <div class="col-lg-8"><div class="card h-100"><div class="card-header"><h2>النتيجة حسب المجال والمهارة</h2></div><div class="card-body">
    <?php if (count($bySubject) > 1): ?>
      <h3 class="h6 text-muted">حسب المادة</h3>
      <?php foreach ($bySubject as $name => $v): $p = $v['max'] ? $v['score'] / $v['max'] * 100 : null; ?>
        <div class="skill-row"><span><?= e($name) ?></span><?= Levels::bar($p) ?><b><?= pct($p) ?></b></div>
      <?php endforeach; ?>
    <?php endif; ?>
    <h3 class="h6 text-muted mt-2">حسب المجال</h3>
    <?php foreach ($byDomain as $name => $v): $p = $v['max'] ? $v['score'] / $v['max'] * 100 : null; ?>
      <div class="skill-row"><span><?= e($name) ?></span><?= Levels::bar($p) ?><b><?= pct($p) ?></b></div>
    <?php endforeach; ?>
    <h3 class="h6 text-muted mt-3">حسب المهارة</h3>
    <?php foreach ($bySkill as $s): ?>
      <div class="skill-row"><span><?= e(($s['code'] ? $s['code'] . ' — ' : '') . ($s['skill_name'] ?? 'غير مصنف')) ?></span><?= Levels::bar((float) $s['percent']) ?><b><?= pct((float) $s['percent']) ?></b></div>
    <?php endforeach; ?>
  </div></div></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h2>المقارنة</h2></div><div class="card-body">
    <?php if ($previous): ?>
      <div class="small text-muted mb-2">المحاولات السابقة في الاختبار نفسه</div>
      <?php foreach ($previous as $p): ?><div class="d-flex justify-content-between border-bottom py-1 small"><span>المحاولة <?= (int) $p['attempt_no'] ?> — <?= e(dt($p['submitted_at'], false)) ?></span><span><?= pct((float) $p['percent']) ?> <?= Levels::badge($p['level']) ?></span></div><?php endforeach; ?>
      <?php $diff = $percent - (float) end($previous)['percent']; ?>
      <p class="mt-2 mb-0 fw-bold <?= $diff >= 0 ? 'text-success' : 'text-danger' ?>"><i class="bi bi-<?= $diff >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i> <?= $diff >= 0 ? 'تحسن' : 'تراجع' ?> بمقدار <?= num(abs($diff), 1) ?> نقطة مئوية عن المحاولة السابقة</p>
    <?php endif; ?>
    <?php if ($subjectAvg !== null): ?>
      <p class="mb-0 mt-2">متوسط نتائجك السابقة في <?= e($a['subject_name']) ?>: <b><?= pct($subjectAvg) ?></b> — هذه النتيجة
        <b class="<?= $percent >= $subjectAvg ? 'text-success' : 'text-danger' ?>"><?= $percent >= $subjectAvg ? 'أعلى' : 'أقل' ?></b> منه.</p>
    <?php endif; ?>
    <?php if (!$previous && $subjectAvg === null): ?><p class="text-muted mb-0">هذه أول نتيجة في هذه المادة — ستظهر المقارنة بعد اختبارات أخرى.</p><?php endif; ?>
  </div></div></div>
  <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h2>الخطوة التالية</h2></div><div class="card-body">
    <?php if ($weak): ?><p class="small">مهارات تحتاج إلى تدريب: <?= e(implode('، ', array_map(static fn ($w) => $w['skill_name'], $weak))) ?></p><?php endif; ?>
    <?php foreach ($recommendations as $rec): ?>
      <div class="d-flex gap-2 align-items-start mb-2"><i class="bi bi-<?= e($rec['icon']) ?> text-primary fs-5"></i><div class="flex-grow-1"><?= e($rec['text']) ?></div>
        <?php if ($forStudent && $rec['action']): ?><form method="post" action="<?= e(url($rec['action'][0])) ?>"><?= csrf_field() ?>
          <?php foreach ($rec['action'][1] as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= (int) $v ?>"><?php endforeach; ?>
          <button class="btn btn-sm btn-soft">تدرّب الآن</button></form><?php endif; ?></div>
    <?php endforeach; ?>
    <?php if (!$recommendations): ?><p class="text-muted mb-0">لا توجد توصيات إضافية.</p><?php endif; ?>
  </div></div></div>
</div>

<p class="disclaimer"><i class="bi bi-info-circle"></i> <?= e(setting('report_disclaimer')) ?></p>

<?php if ($review): ?>
<div class="card mt-3"><div class="card-header"><h2>مراجعة الإجابات</h2>
  <div class="btn-group btn-group-sm" role="group">
    <button type="button" class="btn btn-light active" onclick="document.querySelectorAll('.rv').forEach(e=>e.classList.remove('d-none'));">الكل</button>
    <button type="button" class="btn btn-light" onclick="document.querySelectorAll('.rv').forEach(e=>e.classList.toggle('d-none', e.dataset.ok==='1'));">الأخطاء فقط</button>
  </div></div>
  <div class="card-body">
    <?php foreach ($items as $i => $it): $blank = Grader::isBlank($it['type'], $it['ans']); $ok = (int) $it['is_correct'] === 1; ?>
      <div class="rv border rounded-3 p-3 mb-3 <?= $ok ? 'border-success' : ((int) $it['needs_manual'] ? 'border-warning' : 'border-danger') ?>" data-ok="<?= $ok ? 1 : 0 ?>">
        <div class="d-flex justify-content-between mb-2 flex-wrap gap-2">
          <strong>السؤال <?= $i + 1 ?></strong>
          <span>
            <?php if ((int) $it['needs_manual']): ?><span class="badge badge-soft-warning">بانتظار التصحيح</span>
            <?php elseif ($blank): ?><span class="badge badge-soft-secondary">دون إجابة</span>
            <?php elseif ($ok): ?><span class="badge badge-soft-success"><i class="bi bi-check2"></i> صحيحة</span>
            <?php else: ?><span class="badge badge-soft-danger"><i class="bi bi-x"></i> <?= (float) $it['score'] > 0 ? 'صحيحة جزئياً' : 'خاطئة' ?></span><?php endif; ?>
            <small class="text-muted"><?= num((float) ($it['score'] ?? 0), 2) ?> / <?= num((float) $it['max_points'], 2) ?></small>
            <?php if (!$forStudent && (int) $it['needs_manual'] && can('results.grade')): ?><a class="btn btn-sm btn-warning" href="<?= e(url('/grading', ['attempt' => $a['id']])) ?>">صحّح</a><?php endif; ?>
          </span>
        </div>
        <?php if (!empty($it['snap']['passage'])): ?><details class="mb-2"><summary class="small text-primary">عرض النص: <?= e($it['snap']['passage']['title']) ?></summary><?= R::passage($it['snap']['passage']) ?></details><?php endif; ?>
        <?= R::stem($it['snap']) ?>
        <?= R::body($it['snap'], 'review', '', $it['ans'], $it['order'], ['show_correct' => true, 'show_explanation' => $explain]) ?>
        <?php if ($it['grader_note']): ?><div class="small mt-2"><b>ملاحظة المعلم:</b> <?= e($it['grader_note']) ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div></div>
<?php elseif ($forStudent): ?>
  <div class="alert alert-secondary mt-3">مراجعة الإجابات غير متاحة لهذا الاختبار.</div>
<?php endif; ?>
