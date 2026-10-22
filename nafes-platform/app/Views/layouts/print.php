<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<?php include APP_PATH . '/Views/partials/head.php'; ?>
</head>
<body style="background:#fff">
<div class="no-print text-center py-3 border-bottom bg-light">
  <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer ms-1"></i>طباعة / حفظ PDF</button>
  <?php if (!empty($pdfUrl)): ?><a class="btn btn-outline-primary" href="<?= e($pdfUrl) ?>"><i class="bi bi-file-earmark-pdf ms-1"></i>تنزيل PDF</a><?php endif; ?>
  <button class="btn btn-light" onclick="history.back()">رجوع</button>
</div>
<div class="print-sheet">
  <?php include APP_PATH . '/Views/partials/print_head.php'; ?>
  <?= $content ?>
</div>
</body>
</html>
