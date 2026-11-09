<div class="row justify-content-center"><div class="col-lg-6">
  <div class="card text-center"><div class="card-body p-5">
    <div class="display-3 text-success mb-2"><i class="bi bi-check-circle-fill"></i></div>
    <h1 class="h4">تم تسليم الاختبار بنجاح</h1>
    <p class="text-muted mb-1"><?= e($attempt['title']) ?></p>
    <?php if ($answered !== null): ?><p class="mb-1">أجبت عن <b><?= (int) $answered ?></b> من <?= (int) $total ?> سؤالاً.</p><?php endif; ?>
    <?php if ((int) $attempt['auto_submitted'] === 1): ?><p class="text-warning-emphasis small">سُلّم تلقائياً عند انتهاء الوقت بالإجابات المحفوظة.</p><?php endif; ?>
    <p class="small text-muted">وقت التسليم: <?= e(dt($attempt['submitted_at'])) ?></p>
    <?php if ($attempt['status'] === 'needs_grading'): ?>
      <div class="alert alert-info">بعض الإجابات تحتاج تصحيحاً من المعلم، وستظهر النتيجة النهائية بعد اكتماله.</div>
    <?php endif; ?>
    <?php if ($visible): ?>
      <a class="btn btn-primary btn-lg" href="<?= e(url('/student/results/' . $attempt['id'])) ?>"><i class="bi bi-bar-chart"></i> عرض النتيجة</a>
    <?php else: ?>
      <div class="alert alert-secondary">ستظهر النتيجة <?= $attempt['result_visibility'] === 'after_close' ? 'بعد إغلاق الاختبار' : 'عند إعلانها من المعلم' ?>، وسيصلك إشعار بذلك.</div>
    <?php endif; ?>
    <a class="btn btn-light btn-lg" href="<?= e(url('/student')) ?>">الرئيسية</a>
  </div></div>
</div></div>
<script>try { localStorage.removeItem('nafes.attempt.<?= (int) $attempt['id'] ?>'); } catch (e) {}</script>
