<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<?php include APP_PATH . '/Views/partials/head.php'; ?>
</head>
<body class="exam-body">
<?= $content ?>
<script src="<?= asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<?= \App\Core\View::stack('scripts') ?>
</body>
</html>
