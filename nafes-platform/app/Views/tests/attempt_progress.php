<div class="page-header">
  <div><h1>محاولة جارية</h1><div class="sub"><?= e($attempt['student_name'] . ' — ' . $attempt['title']) ?></div></div>
  <a class="btn btn-light" href="<?= e(url('/tests/' . $attempt['test_id'] . '/results')) ?>">نتائج الاختبار</a>
</div>
<div class="card"><div class="card-body">
  <p>بدأ الطالب في <b><?= e(dt($attempt['started_at'])) ?></b><?= $attempt['deadline_at'] ? ' وينتهي وقته في <b>' . e(dt($attempt['deadline_at'])) . '</b>' : '' ?>.</p>
  <p class="mb-2">أجاب حتى الآن عن <b><?= $answered ?></b> من <b><?= $total ?></b> سؤالاً (حسب آخر حفظ تلقائي).</p>
  <div class="progress" style="height:10px"><div class="progress-bar" style="width:<?= $total ? round($answered / $total * 100) : 0 ?>%"></div></div>
  <p class="small text-muted mt-3 mb-0">تظهر النتيجة والتحليل بعد تسليم الطالب أو انتهاء وقته (تُسلّم المحاولة تلقائياً عند انتهاء الوقت).</p>
</div></div>
