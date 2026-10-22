<?php
use App\Core\Auth;
use App\Helpers\Navigation;

$user    = Auth::user();
$current = (new \App\Core\Request())->path();
$roles   = \App\Core\DB::column('SELECT r.name FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?', [$user['id'] ?? 0]);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<?php include APP_PATH . '/Views/partials/head.php'; ?>
</head>
<body>
<div class="app">
  <aside class="sidebar" id="sidebar" aria-label="القائمة الرئيسية">
    <div class="sidebar-brand">
      <?php if (setting('logo') !== ''): ?>
        <img src="<?= e(url('/media/logo')) ?>" alt="شعار المدرسة">
      <?php else: ?>
        <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
      <?php endif; ?>
      <div class="titles">
        <strong title="<?= e(setting('school_name')) ?>"><?= e(setting('school_name')) ?></strong>
        <small><?= e(setting('platform_title')) ?></small>
      </div>
    </div>
    <nav class="sidebar-nav">
      <?php foreach (Navigation::items() as $item): ?>
        <?php if (isset($item['section'])): ?>
          <div class="nav-section"><?= e($item['section']) ?></div>
        <?php else: ?>
          <a href="<?= e(url($item[1])) ?>" class="<?= Navigation::active($item[1], $current) ? 'active' : '' ?>" title="<?= e($item[0]) ?>">
            <i class="bi bi-<?= e($item[2]) ?>"></i><span><?= e($item[0]) ?></span>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot"><?= e(setting('school_section')) ?></div>
  </aside>
  <div class="sidebar-backdrop" data-sidebar-close></div>

  <div class="main">
    <header class="topbar">
      <button class="btn btn-icon btn-light" type="button" data-sidebar-toggle aria-label="إظهار/طي القائمة"><i class="bi bi-list fs-5"></i></button>
      <h1 class="page-title"><?= e($title ?? '') ?></h1>
      <span class="school-chip d-none d-lg-inline"><i class="bi bi-calendar3"></i> <?= e(\App\Models\Year::current()['name'] ?? '') ?></span>

      <div class="dropdown">
        <button class="btn btn-icon btn-light notif-btn" type="button" data-bs-toggle="dropdown" data-notif-toggle aria-label="الإشعارات">
          <i class="bi bi-bell fs-5"></i><span class="count d-none" data-notif-count>0</span>
        </button>
        <div class="dropdown-menu dropdown-menu-start notif-menu shadow">
          <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
            <strong>الإشعارات</strong>
            <a href="<?= e(url('/notifications')) ?>" class="small">عرض الكل</a>
          </div>
          <div data-notif-list><div class="p-3 text-muted small">جارٍ التحميل…</div></div>
        </div>
      </div>

      <div class="dropdown">
        <button class="user-chip" type="button" data-bs-toggle="dropdown" aria-label="حسابي">
          <span class="avatar"><?= e(mb_substr($user['full_name'] ?? '؟', 0, 1)) ?></span>
          <span class="meta"><strong class="d-block small"><?= e($user['full_name'] ?? '') ?></strong><small><?= e(implode('، ', $roles)) ?></small></span>
          <i class="bi bi-chevron-down small text-muted"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-start shadow">
          <li><a class="dropdown-item" href="<?= e(url('/account')) ?>"><i class="bi bi-person-circle ms-2"></i>حسابي</a></li>
          <li><a class="dropdown-item" href="<?= e(url('/account/password')) ?>"><i class="bi bi-key ms-2"></i>تغيير كلمة المرور</a></li>
          <li><hr class="dropdown-divider"></li>
          <li>
            <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?>
              <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-left ms-2"></i>تسجيل الخروج</button>
            </form>
          </li>
        </ul>
      </div>
    </header>

    <main class="content">
      <?php include APP_PATH . '/Views/partials/flash.php'; ?>
      <?= $content ?>
    </main>
    <footer class="footer d-flex justify-content-between flex-wrap gap-2">
      <span><?= e(setting('school_name')) ?> — <?= e(setting('school_section')) ?></span>
      <span><?= e(setting('platform_title')) ?></span>
    </footer>
  </div>
</div>

<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="bi bi-exclamation-triangle text-warning ms-2"></i>تأكيد العملية</h5>
        <button type="button" class="btn-close ms-0 me-auto" data-bs-dismiss="modal" aria-label="إغلاق"></button></div>
      <div class="modal-body" data-confirm-text></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
        <button type="button" class="btn btn-danger" data-confirm-ok>تأكيد</button>
      </div>
    </div>
  </div>
</div>
<script src="<?= asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<?= $scripts ?? '' ?>
</body>
</html>
