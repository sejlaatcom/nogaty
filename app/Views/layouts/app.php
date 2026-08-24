<?php /** القالب الرئيسي للنظام */ ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title ?? 'النظام') ?> — <?= e(setting('app_name', 'نظام الأرشفة الإلكترونية')) ?></title>
<link rel="stylesheet" href="assets/css/app.css?v=<?= APP_VERSION ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🗂️</text></svg>">
</head>
<body>
<div class="layout">

  <!-- ============================ القائمة الجانبية ============================ -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="logo">🗂️</div>
      <div>
        <b><?= e(setting('app_name', 'الأرشفة الإلكترونية')) ?></b>
        <small><?= e(setting('school_name', 'المدرسة')) ?></small>
      </div>
    </div>

    <nav class="nav">
      <a href="<?= e(url('dashboard/index')) ?>" class="<?= trim(activeIf('dashboard')) ?>">
        <span class="ico">📊</span> لوحة المعلومات
      </a>

      <div class="nav-section">المعاملات</div>
      <?php $pending = (int) Database::value(
            "SELECT COUNT(*) FROM transaction_routings WHERE to_user_id = ? AND status = 'pending'", [Auth::id()]); ?>
      <a href="<?= e(url('transactions/inbox')) ?>" class="<?= trim(activeIf('transactions/inbox')) ?>">
        <span class="ico">📥</span> الوارد إليّ
        <?php if ($pending): ?><span class="count"><?= $pending ?></span><?php endif; ?>
      </a>
      <a href="<?= e(url('transactions/index', ['direction' => 'incoming'])) ?>">
        <span class="ico">📩</span> المعاملات الواردة
      </a>
      <a href="<?= e(url('transactions/index', ['direction' => 'outgoing'])) ?>">
        <span class="ico">📤</span> المعاملات الصادرة
      </a>
      <a href="<?= e(url('transactions/index', ['direction' => 'internal'])) ?>">
        <span class="ico">📝</span> المذكرات الداخلية
      </a>
      <a href="<?= e(url('transactions/index')) ?>">
        <span class="ico">🗃️</span> كل المعاملات
      </a>
      <?php if (can('transactions.create')): ?>
      <a href="<?= e(url('transactions/create')) ?>"><span class="ico">➕</span> قيد معاملة جديدة</a>
      <?php endif; ?>

      <div class="nav-section">الأرشيف والوثائق</div>
      <a href="<?= e(url('folders/index')) ?>" class="<?= trim(activeIf('folders')) ?>">
        <span class="ico">🌳</span> شجرة الأرشفة
      </a>
      <a href="<?= e(url('documents/index')) ?>" class="<?= trim(activeIf('documents')) ?>">
        <span class="ico">📁</span> الوثائق والملفات
      </a>
      <a href="<?= e(url('attachments/gallery')) ?>" class="<?= trim(activeIf('attachments')) ?>">
        <span class="ico">🎞️</span> مكتبة المرفقات
      </a>
      <a href="<?= e(url('search/advanced')) ?>" class="<?= trim(activeIf('search')) ?>">
        <span class="ico">🔎</span> البحث المتقدم
      </a>

      <?php if (can('reports.view') || can('correspondents.manage') || can('departments.manage')): ?>
      <div class="nav-section">البيانات والتقارير</div>
      <?php endif; ?>
      <?php if (can('reports.view')): ?>
      <a href="<?= e(url('reports/index')) ?>" class="<?= trim(activeIf('reports')) ?>">
        <span class="ico">📈</span> التقارير والإحصائيات
      </a>
      <?php endif; ?>
      <?php if (can('correspondents.manage')): ?>
      <a href="<?= e(url('correspondents/index')) ?>" class="<?= trim(activeIf('correspondents/index')) ?>">
        <span class="ico">🏛️</span> الجهات المراسلة
      </a>
      <?php endif; ?>
      <?php if (can('categories.manage')): ?>
      <a href="<?= e(url('correspondents/categories')) ?>" class="<?= trim(activeIf('correspondents/categories')) ?>">
        <span class="ico">🏷️</span> التصنيفات والأعوام
      </a>
      <?php endif; ?>
      <?php if (can('departments.manage')): ?>
      <a href="<?= e(url('departments/index')) ?>" class="<?= trim(activeIf('departments')) ?>">
        <span class="ico">🏫</span> الهيكل التنظيمي
      </a>
      <?php endif; ?>

      <?php if (can('users.manage') || can('roles.manage') || can('settings.manage') || can('audit.view')): ?>
      <div class="nav-section">إدارة النظام</div>
      <?php endif; ?>
      <?php if (can('users.manage')): ?>
      <a href="<?= e(url('users/index')) ?>" class="<?= trim(activeIf('users')) ?>">
        <span class="ico">👥</span> المستخدمون
      </a>
      <?php endif; ?>
      <?php if (can('roles.manage')): ?>
      <a href="<?= e(url('roles/index')) ?>" class="<?= trim(activeIf('roles')) ?>">
        <span class="ico">🔐</span> الأدوار والصلاحيات
      </a>
      <?php endif; ?>
      <?php if (can('audit.view')): ?>
      <a href="<?= e(url('audit/index')) ?>" class="<?= trim(activeIf('audit')) ?>">
        <span class="ico">📜</span> سجل الأحداث
      </a>
      <?php endif; ?>
      <?php if (can('settings.manage')): ?>
      <a href="<?= e(url('settings/index')) ?>" class="<?= trim(activeIf('settings')) ?>">
        <span class="ico">⚙️</span> إعدادات النظام
      </a>
      <?php endif; ?>

      <div class="nav-section">حسابي</div>
      <a href="<?= e(url('profile/index')) ?>" class="<?= trim(activeIf('profile')) ?>">
        <span class="ico">👤</span> ملفي الشخصي
      </a>
      <a href="<?= e(url('auth/logout')) ?>"><span class="ico">🚪</span> تسجيل الخروج</a>
    </nav>
  </aside>
  <div class="backdrop" id="backdrop"></div>

  <!-- ================================ المحتوى ================================ -->
  <div class="main">
    <header class="topbar">
      <button class="icon-btn menu-toggle" data-toggle-sidebar aria-label="القائمة">☰</button>

      <form class="topbar-search" method="get" action="index.php" autocomplete="off">
        <input type="hidden" name="r" value="search/index">
        <input type="search" name="q" id="global-search" placeholder="ابحث في المعاملات والوثائق والمرفقات..."
               value="<?= e($_GET['q'] ?? '') ?>">
        <div class="dropdown" id="search-suggest" style="position:absolute;inset-inline-end:0;top:100%;width:100%">
          <div class="dropdown-menu" style="inset-inline-end:0;width:100%"></div>
        </div>
      </form>

      <div class="topbar-actions">
        <?php $notifs = Notify::latest(6); $unread = Notify::unreadCount(); ?>
        <div class="dropdown">
          <button class="icon-btn" data-dropdown aria-label="الإشعارات">🔔
            <span class="dot" id="notif-count" style="<?= $unread ? '' : 'display:none' ?>"><?= $unread > 99 ? '99+' : $unread ?></span>
          </button>
          <div class="dropdown-menu">
            <div class="head">الإشعارات <?= $unread ? '(' . $unread . ' جديد)' : '' ?></div>
            <?php if (!$notifs): ?>
              <a href="<?= e(url('notifications/index')) ?>" class="muted">لا توجد إشعارات</a>
            <?php else: foreach ($notifs as $n): ?>
              <a href="<?= e(url('notifications/read', ['id' => $n['id']])) ?>" class="<?= $n['is_read'] ? '' : 'unread' ?>">
                <b class="small"><?= e($n['title']) ?></b>
                <div class="xsmall muted"><?= e(excerpt($n['message'], 60)) ?> — <?= e(timeAgo($n['created_at'])) ?></div>
              </a>
            <?php endforeach; endif; ?>
            <a href="<?= e(url('notifications/index')) ?>" class="center bold">عرض كل الإشعارات</a>
          </div>
        </div>

        <?php if (can('transactions.create')): ?>
        <div class="dropdown">
          <button class="icon-btn" data-dropdown aria-label="إضافة سريعة">➕</button>
          <div class="dropdown-menu">
            <div class="head">إضافة سريعة</div>
            <a href="<?= e(url('transactions/create', ['direction' => 'incoming'])) ?>">📩 قيد معاملة واردة</a>
            <a href="<?= e(url('transactions/create', ['direction' => 'outgoing'])) ?>">📤 قيد معاملة صادرة</a>
            <a href="<?= e(url('transactions/create', ['direction' => 'internal'])) ?>">📝 مذكرة داخلية</a>
            <?php if (can('documents.create')): ?>
            <a href="<?= e(url('documents/create')) ?>">📄 وثيقة جديدة</a>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php $me = Auth::user(); ?>
        <a href="<?= e(url('profile/index')) ?>" class="user-chip">
          <span class="avatar"><?= e(mb_substr($me['full_name'], 0, 1)) ?></span>
          <span class="meta">
            <b><?= e($me['full_name']) ?></b>
            <small><?= e($me['role_name']) ?></small>
          </span>
        </a>
      </div>
    </header>

    <main class="content">
      <?php foreach (getFlashes() as $f): ?>
        <div class="alert <?= e($f['type']) ?>">
          <span><?= ['success' => '✅', 'error' => '⛔', 'warning' => '⚠️', 'info' => 'ℹ️'][$f['type']] ?? 'ℹ️' ?></span>
          <span><?= e($f['message']) ?></span>
        </div>
      <?php endforeach; ?>

      <?= $content ?>
    </main>

    <footer class="center xsmall muted" style="padding:1rem;border-top:1px solid var(--line)">
      <?= e(setting('app_name', 'نظام الأرشفة الإلكترونية')) ?> —
      <?= e(setting('school_name', '')) ?> · الإصدار <?= APP_VERSION ?> ·
      <?= e(gdate(date('Y-m-d'))) ?> م / <?= e(Hijri::format(date('Y-m-d'))) ?>
    </footer>
  </div>
</div>
<script src="assets/js/app.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>
