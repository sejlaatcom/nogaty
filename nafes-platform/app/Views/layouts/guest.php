<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<?php include APP_PATH . '/Views/partials/head.php'; ?>
</head>
<body>
<?php if (!empty($bare)): ?>
  <?= $content ?>
<?php else: ?>
  <div class="container py-5" style="max-width:640px">
    <?php include APP_PATH . '/Views/partials/flash.php'; ?>
    <?= $content ?>
  </div>
<?php endif; ?>
<script src="<?= asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
