<div class="page-header">
  <div><h1><?= e($attempt['student_name']) ?></h1><div class="sub"><?= e($attempt['title']) ?> · <?= e(($attempt['grade_name'] ?? '') . ' / ' . ($attempt['class_name'] ?? '')) ?> · رقم <?= e($attempt['student_number']) ?></div></div>
  <div class="d-flex gap-2">
    <?php if (can('students.view')): ?><a class="btn btn-light" href="<?= e(url('/students/' . $attempt['student_id'])) ?>"><i class="bi bi-person"></i> ملف الطالب</a><?php endif; ?>
    <a class="btn btn-light" href="<?= e(url('/tests/' . $attempt['test_id'] . '/results')) ?>">نتائج الاختبار</a>
    <button class="btn btn-light" onclick="window.print()"><i class="bi bi-printer"></i></button>
  </div>
</div>
<?php if (!$result): ?><div class="alert alert-warning">لم تُحسب نتيجة هذه المحاولة بعد.</div><?php else: include APP_PATH . '/Views/reports/_attempt.php'; endif; ?>
