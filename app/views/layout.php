<?php
declare(strict_types=1);

/** قوائم التنقل حسب نوع المستخدم */
function nav_items(string $area): array
{
    if ($area === 'admin') {
        return [
            'index'       => ['لوحة التحكم', 'admin/index.php', '▦'],
            'plans'       => ['اعتماد الخطط', 'admin/plans.php', '✔'],
            'followup'    => ['لوحة المتابعة', 'admin/followup.php', '◷'],
            'teachers'    => ['المعلمون', 'admin/teachers.php', '☺'],
            'assignments' => ['الإسناد', 'admin/assignments.php', '⇄'],
            'subjects'    => ['المواد', 'admin/subjects.php', '✎'],
            'grades'      => ['الصفوف والفصول', 'admin/grades.php', '▤'],
            'weeks'       => ['التقويم والأسابيع', 'admin/weeks.php', '▧'],
            'transfer'    => ['استيراد وتصدير', 'admin/transfer.php', '↧'],
            'settings'    => ['إعدادات المدرسة', 'admin/settings.php', '⚙'],
            'logs'        => ['سجل العمليات', 'admin/logs.php', '≡'],
        ] + nafes_nav_items('admin');
    }

    if ($area === 'teacher') {
        return [
            'index'   => ['خطط الأسبوع', 'teacher/index.php', '▦'],
            'plans'   => ['جميع خططي', 'teacher/plans.php', '≡'],
            'profile' => ['حسابي', 'profile.php', '☺'],
        ] + nafes_nav_items('teacher');
    }

    if ($area === 'student') {
        return [
            'index'    => ['الرئيسية', 'student/index.php', '▦'],
            'profile'  => ['حسابي', 'profile.php', '☺'],
        ];
    }

    return [];
}

/** عناصر منصة نافس في قائمة الإدارة والمعلم (عنصر بمسار فارغ = عنوان قسم) */
function nafes_nav_items(string $area): array
{
    if (!Settings::bool('nafes_enabled') && $area !== 'admin') {
        return [];
    }
    $items = ['_nafes' => ['منصة نافس', null, '']];
    if ($area === 'admin') {
        $items['students']       = ['الطلاب', 'admin/students.php', '☻'];
        $items['nafes_settings'] = ['إعدادات نافس', 'admin/nafes_settings.php', '⚙'];
    }
    return $items;
}

/** وصف دور المستخدم في الشريط العلوي */
function user_role_label(?array $user): string
{
    $role = $user['role'] ?? '';
    if ($role === 'student') {
        $classroom = !empty($user['classroom_id']) ? Plans::classroom((int) $user['classroom_id']) : null;
        return 'طالب' . ($classroom ? ' — ' . Plans::classLabel($classroom) : '');
    }
    return $role === 'admin' ? 'إدارة' : 'معلم';
}

/**
 * بداية الصفحة.
 * @param string $area public|admin|teacher|auth|print
 */
function page_start(string $title, string $area = 'public', string $active = ''): void
{
    $appName    = $GLOBALS['config']['app_name'] ?? 'نظام الخطط الأسبوعية';
    $schoolName = Settings::get('school_name');
    $user       = Auth::user();
    ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> — <?= h($schoolName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>?v=2">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📘</text></svg>">
</head>
<body class="area-<?= h($area) ?>">
<?php if ($area === 'print') { return; } ?>

<?php if (in_array($area, ['admin', 'teacher', 'student'], true)): ?>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <?php if (Settings::get('logo') !== ''): ?>
        <img src="<?= h(url('uploads/' . Settings::get('logo'))) ?>" alt="شعار المدرسة">
      <?php else: ?>
        <span class="brand-mark">📘</span>
      <?php endif; ?>
      <div>
        <strong><?= h($schoolName) ?></strong>
        <small><?= match ($area) { 'admin' => 'لوحة إدارة المدرسة', 'student' => 'منصة نافس — بوابة الطالب', default => 'لوحة المعلم' } ?></small>
      </div>
    </div>
    <nav class="sidebar-nav">
      <?php foreach (nav_items($area) as $key => [$label, $path, $icon]): ?>
        <?php if ($path === null): ?>
          <div class="nav-section"><?= h($label) ?></div>
        <?php else: ?>
        <a href="<?= h(url($path)) ?>" class="<?= $active === $key ? 'active' : '' ?>">
          <span class="ico"><?= $icon ?></span><?= h($label) ?>
        </a>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if ($area !== 'student'): ?>
      <a href="<?= h(url('index.php')) ?>" target="_blank"><span class="ico">◎</span>واجهة الموقع</a>
      <?php endif; ?>
    </nav>
    <div class="sidebar-foot">
      <a class="btn btn-ghost btn-block" href="<?= h(url('logout.php')) ?>">تسجيل الخروج</a>
    </div>
  </aside>

  <div class="app-main">
    <header class="topbar">
      <button class="menu-toggle" type="button" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
      <h1><?= h($title) ?></h1>
      <div class="topbar-user">
        <span class="user-name"><?= h($user['name'] ?? '') ?></span>
        <span class="user-role"><?= h(user_role_label($user)) ?></span>
      </div>
    </header>
    <main class="content">
      <?php render_flash(); ?>
<?php else: ?>
<header class="site-header">
  <div class="wrap site-header-inner">
    <a class="site-brand" href="<?= h(url('index.php')) ?>">
      <?php if (Settings::get('logo') !== ''): ?>
        <img src="<?= h(url('uploads/' . Settings::get('logo'))) ?>" alt="شعار المدرسة">
      <?php endif; ?>
      <span>
        <strong><?= h($schoolName) ?></strong>
        <small><?= h(Settings::get('sheet_title')) ?></small>
      </span>
    </a>
    <nav class="site-nav">
      <a href="<?= h(url('index.php')) ?>">الرئيسية</a>
      <?php if ($user): ?>
        <a href="<?= h(url(Auth::home())) ?>">لوحة التحكم</a>
        <a href="<?= h(url('logout.php')) ?>">خروج</a>
      <?php else: ?>
        <a class="btn btn-primary btn-sm" href="<?= h(url('login.php')) ?>">تسجيل الدخول</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="wrap page">
  <?php render_flash(); ?>
<?php endif; ?>
<?php
}

function render_flash(): void
{
    foreach (flash_take() as $item) {
        $type = in_array($item['type'], ['success', 'error', 'info', 'warning'], true) ? $item['type'] : 'info';
        echo '<div class="alert alert-' . $type . '">' . h($item['message']) . '</div>';
    }
}

function page_end(string $area = 'public'): void
{
    if ($area === 'print') {
        echo '</body></html>';
        return;
    }

    if (in_array($area, ['admin', 'teacher', 'student'], true)) {
        ?>
    </main>
  </div>
</div>
<?php } else { ?>
</main>
<footer class="site-footer">
  <div class="wrap">
    <span><?= h(Settings::get('footer_text')) ?> — <?= h(Settings::get('school_name')) ?></span>
    <span><?= h(Settings::get('education_dept')) ?></span>
  </div>
</footer>
<?php } ?>
<script src="<?= asset('js/app.js') ?>?v=2"></script>
</body>
</html>
<?php
}

/** شريط أدوات علوي داخل الصفحة */
function page_toolbar(string $html): void
{
    echo '<div class="toolbar">' . $html . '</div>';
}
