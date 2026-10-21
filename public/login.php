<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require APP_PATH . '/views/layout.php';

if (Auth::check()) {
    redirect(Auth::home());
}

$error = '';
if (is_post()) {
    csrf_verify();
    [$ok, $message] = Auth::attempt((string) input('username', ''), (string) ($_POST['password'] ?? ''));
    if ($ok) {
        $intended = $_SESSION['_intended'] ?? null;
        unset($_SESSION['_intended']);
        redirect($intended ?: Auth::home());
    }
    $error = $message;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تسجيل الدخول — <?= h(Settings::get('school_name')) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
<div class="auth-wrap">
  <form class="auth-card" method="post">
    <?= csrf_field() ?>
    <h1><?= h(Settings::get('school_name')) ?></h1>
    <p class="sub">نظام الخطط الأسبوعية ومنصة نافس — دخول المعلمين والإدارة والطلاب</p>

    <?php if ($error !== ''): ?>
      <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="field">
      <label for="username">اسم المستخدم</label>
      <input id="username" type="text" name="username" value="<?= h(input('username', '')) ?>" required autofocus>
    </div>
    <div class="field">
      <label for="password">كلمة المرور</label>
      <input id="password" type="password" name="password" required>
    </div>
    <button class="btn btn-primary btn-block" type="submit">دخول</button>
    <p class="text-center mt16 mb0"><a href="<?= h(url('index.php')) ?>">العودة لواجهة الموقع</a></p>
  </form>
</div>
</body>
</html>
