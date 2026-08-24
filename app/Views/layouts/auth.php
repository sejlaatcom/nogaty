<?php /** قالب الصفحات العامة (الدخول والمشاركة) */ ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title ?? '') ?> — <?= e(setting('app_name', 'نظام الأرشفة الإلكترونية')) ?></title>
<link rel="stylesheet" href="assets/css/app.css?v=<?= APP_VERSION ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🗂️</text></svg>">
</head>
<body>
<div class="auth-wrap">
  <div style="width:100%;max-width:<?= isset($wide) && $wide ? '820px' : '420px' ?>">
    <?php foreach (getFlashes() as $f): ?>
      <div class="alert <?= e($f['type']) ?>" style="max-width:100%"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
  </div>
</div>
<script src="assets/js/app.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>
