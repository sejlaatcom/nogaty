<div class="page-header"><div><h1><?= e($attempt['title']) ?></h1><div class="sub"><?= e($attempt['subject_name'] ?? 'اختبار شامل') ?></div></div>
  <a class="btn btn-light" href="<?= e(url('/student/results')) ?>">كل نتائجي</a></div>
<?php include APP_PATH . '/Views/reports/_attempt.php'; ?>
