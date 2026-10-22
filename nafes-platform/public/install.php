<?php
/**
 * مثبّت المنصة: يتحقق من المتطلبات، يختبر الاتصال بقاعدة البيانات، ينشئ الجداول والبيانات الأساسية
 * وحساب المدير الأول، ثم يُعطّل نفسه بإنشاء storage/installed.lock.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Core\Env;
use App\Core\Session;
use App\Models\User;
use App\Services\Migrator;
use App\Services\Settings;

if (is_file(STORAGE_PATH . '/installed.lock')) {
    http_response_code(403);
    exit('المنصة مثبتة مسبقاً، وتم تعطيل المثبّت. لإعادة التثبيت احذف الملف storage/installed.lock يدوياً من الخادم.');
}

Session::start();

/* ---------------- فحص المتطلبات ---------------- */
$requirements = [
    ['PHP 8.2 أو أحدث', version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION, true],
    ['امتداد PDO MySQL', extension_loaded('pdo_mysql'), '', true],
    ['امتداد mbstring', extension_loaded('mbstring'), '', true],
    ['امتداد zip (ملفات Excel)', extension_loaded('zip'), '', true],
    ['امتداد xml', extension_loaded('xml') && extension_loaded('simplexml'), '', true],
    ['امتداد fileinfo (التحقق من الملفات المرفوعة)', extension_loaded('fileinfo'), '', true],
    ['امتداد gd (الصور وPDF)', extension_loaded('gd'), '', false],
    ['امتداد intl (التقويم الهجري)', extension_loaded('intl'), '', false],
    ['مكتبات Composer (Excel المتقدم وPDF)', is_file(BASE_PATH . '/vendor/autoload.php'), 'اختياري — توجد بدائل مدمجة', false],
    ['صلاحية الكتابة على مجلد storage', is_writable(STORAGE_PATH), STORAGE_PATH, true],
    ['صلاحية الكتابة على المجلد الرئيسي (لإنشاء .env)', is_writable(BASE_PATH) || is_writable(BASE_PATH . '/.env'), BASE_PATH, true],
];
$requirementsOk = !array_filter($requirements, static fn ($r) => $r[3] && !$r[1]);

$errors  = [];
$done    = false;
$tested  = null;
$form = [
    'db_host' => $_POST['db_host'] ?? 'localhost', 'db_port' => $_POST['db_port'] ?? '3306',
    'db_name' => $_POST['db_name'] ?? 'nafes_platform', 'db_user' => $_POST['db_user'] ?? '', 'db_pass' => $_POST['db_pass'] ?? '',
    'app_url' => $_POST['app_url'] ?? ((isset($_SERVER['HTTP_HOST']) ? ((!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . base_path_url()) : '')),
    'school_name'    => $_POST['school_name'] ?? 'مجمع الأمير سعود بن نايف التعليمي بالدمام',
    'school_section' => $_POST['school_section'] ?? 'القسم المتوسط والثانوي — بنين',
    'education_dept' => $_POST['education_dept'] ?? 'الإدارة العامة للتعليم بالمنطقة الشرقية',
    'admin_name' => $_POST['admin_name'] ?? '', 'admin_user' => $_POST['admin_user'] ?? '',
    'admin_email' => $_POST['admin_email'] ?? '',
    'merge_principal' => isset($_POST['merge_principal']),
    'demo' => isset($_POST['demo']),
    'classes' => $_POST['classes'] ?? '3',
];

$token = Session::csrfToken();
$post  = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
if ($post && !hash_equals($token, (string) ($_POST['_token'] ?? ''))) {
    $errors[] = 'انتهت صلاحية الصفحة، حدّثها وأعد المحاولة.';
    $post = false;
}

$dbConfig = static fn (array $f): array => [
    'host' => $f['db_host'], 'port' => (int) $f['db_port'], 'database' => $f['db_name'],
    'username' => $f['db_user'], 'password' => $f['db_pass'], 'charset' => 'utf8mb4',
];

if ($post && ($_POST['action'] ?? '') === 'test') {
    try {
        DB::connect($dbConfig($form))->query('SELECT 1');
        $tested = true;
    } catch (Throwable $e) {
        $tested = false;
        $errors[] = 'تعذر الاتصال بقاعدة البيانات: ' . $e->getMessage();
    }
}

if ($post && ($_POST['action'] ?? '') === 'install') {
    $password = (string) ($_POST['admin_pass'] ?? '');
    if (!$requirementsOk) {
        $errors[] = 'لم تتحقق المتطلبات الإلزامية.';
    }
    if ($form['admin_name'] === '' || !preg_match(User::USERNAME_RULE, mb_strtolower($form['admin_user']))) {
        $errors[] = 'أدخل اسم المدير واسم مستخدم بالإنجليزية (3 أحرف فأكثر).';
    }
    $errors = array_merge($errors, User::passwordErrors($password));
    if ($password !== ($_POST['admin_pass_confirm'] ?? '')) {
        $errors[] = 'كلمتا المرور غير متطابقتين.';
    }
    if (trim($form['school_name']) === '') {
        $errors[] = 'اسم المدرسة مطلوب.';
    }

    if (!$errors) {
        try {
            $pdo = DB::connect($dbConfig($form));
            DB::setPdo($pdo);
            Migrator::run($pdo);

            foreach (glob(BASE_PATH . '/database/seeders/*.php') as $file) {
                require_once $file;
            }
            RolesSeeder::run();
            BaseSeeder::run(['classes' => max(1, min(20, (int) $form['classes']))]);

            Settings::set([
                'school_name' => trim($form['school_name']), 'school_section' => trim($form['school_section']),
                'education_dept' => trim($form['education_dept']),
            ]);

            // شعار المدرسة (اختياري)
            $logo = $_FILES['logo'] ?? null;
            if ($logo && ($logo['error'] ?? 4) === UPLOAD_ERR_OK) {
                $saved = App\Services\Uploads::storeImage($logo, 'branding');
                if ($saved['path'] ?? null) {
                    Settings::set(['logo' => $saved['path']]);
                }
            }

            if (User::usernameTaken($form['admin_user'])) {
                throw new RuntimeException('اسم مستخدم المدير موجود مسبقاً في قاعدة البيانات.');
            }
            $roles = ['admin'];
            if ($form['merge_principal']) {
                $roles[] = 'principal';
            }
            User::create([
                'username' => $form['admin_user'], 'full_name' => $form['admin_name'], 'email' => $form['admin_email'],
                'password' => $password, 'must_change_password' => 0,
            ], $roles);

            if ($form['demo']) {
                DemoSeeder::run();
            }

            $written = Env::write(BASE_PATH . '/.env', [
                'APP_NAME' => 'منصة نافس التدريبية', 'APP_ENV' => 'production', 'APP_DEBUG' => 'false',
                'APP_URL' => rtrim($form['app_url'], '/'), 'APP_KEY' => bin2hex(random_bytes(32)),
                'APP_TIMEZONE' => 'Asia/Riyadh', 'APP_PRETTY_URLS' => isset($_POST['pretty']) ? 'true' : 'false',
                'DB_HOST' => $form['db_host'], 'DB_PORT' => $form['db_port'], 'DB_DATABASE' => $form['db_name'],
                'DB_USERNAME' => $form['db_user'], 'DB_PASSWORD' => $form['db_pass'],
                'SESSION_LIFETIME' => '120', 'SESSION_SECURE' => 'auto', 'UPLOAD_MAX_MB' => '5',
            ]);
            if (!$written) {
                throw new RuntimeException('تعذر كتابة ملف .env — تأكد من صلاحيات الكتابة.');
            }
            @chmod(BASE_PATH . '/.env', 0640);
            file_put_contents(STORAGE_PATH . '/installed.lock', date('c'));
            $done = true;
        } catch (Throwable $e) {
            $errors[] = 'خطأ أثناء التثبيت: ' . $e->getMessage();
        }
    }
}
$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>تثبيت منصة نافس التدريبية</title>
<link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.rtl.min.css">
<link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<div class="container py-4" style="max-width:860px">
  <div class="d-flex align-items-center gap-3 mb-4">
    <span class="auth-logo mb-0"><i class="bi bi-mortarboard-fill"></i></span>
    <div><h1 class="h4 mb-0">تثبيت منصة التدريب على اختبارات نافس</h1>
      <div class="text-muted small">مجمع الأمير سعود بن نايف التعليمي بالدمام — القسم المتوسط والثانوي (بنين)</div></div>
  </div>

  <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= $e($error) ?></div><?php endforeach; ?>

  <?php if ($done): ?>
    <div class="card"><div class="card-body p-4 text-center">
      <i class="bi bi-check-circle-fill text-success display-4"></i>
      <h2 class="h4 mt-3">تم التثبيت بنجاح</h2>
      <p>تم إنشاء الجداول والبيانات الأساسية وحساب المدير <b class="ltr"><?= $e($form['admin_user']) ?></b>، وتم تعطيل المثبّت تلقائياً.</p>
      <div class="alert alert-warning text-start">للأمان: احذف الملف <code>public/install.php</code> من الخادم، وفعّل HTTPS، وتأكد أن جذر الموقع هو مجلد <code>public</code>.</div>
      <a class="btn btn-primary" href="<?= $e(base_path_url() . (isset($_POST['pretty']) ? '' : '/index.php') . '/login') ?>">الانتقال لتسجيل الدخول</a>
    </div></div>
  <?php else: ?>

  <div class="card mb-3">
    <div class="card-header"><h2>1) المتطلبات</h2><?= $requirementsOk ? '<span class="badge badge-soft-success">مكتملة</span>' : '<span class="badge badge-soft-danger">غير مكتملة</span>' ?></div>
    <div class="card-body p-0">
      <table class="table">
        <?php foreach ($requirements as [$label, $ok, $note, $required]): ?>
          <tr><td><?= $e($label) ?> <?= $required ? '' : '<small class="text-muted">(اختياري)</small>' ?></td>
            <td class="text-muted small ltr"><?= $e($note) ?></td>
            <td class="text-end"><?= $ok ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-' . ($required ? 'x-circle-fill text-danger' : 'dash-circle text-warning') . '"></i>' ?></td></tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>

  <form method="post" enctype="multipart/form-data" class="card">
    <input type="hidden" name="_token" value="<?= $e($token) ?>">
    <div class="card-header"><h2>2) قاعدة البيانات</h2>
      <?php if ($tested === true): ?><span class="badge badge-soft-success"><i class="bi bi-check2"></i> الاتصال ناجح</span><?php endif; ?></div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label required">الخادم</label><input class="form-control ltr" name="db_host" value="<?= $e($form['db_host']) ?>" required></div>
        <div class="col-md-2"><label class="form-label">المنفذ</label><input class="form-control ltr" name="db_port" value="<?= $e($form['db_port']) ?>"></div>
        <div class="col-md-4"><label class="form-label required">اسم قاعدة البيانات</label><input class="form-control ltr" name="db_name" value="<?= $e($form['db_name']) ?>" required></div>
        <div class="col-md-6"><label class="form-label required">المستخدم</label><input class="form-control ltr" name="db_user" value="<?= $e($form['db_user']) ?>" required></div>
        <div class="col-md-6"><label class="form-label">كلمة المرور</label><input type="password" class="form-control ltr" name="db_pass" value="<?= $e($form['db_pass']) ?>"></div>
      </div>
      <p class="form-text mt-2">أنشئ قاعدة البيانات ومستخدمها من لوحة الاستضافة أولاً (ترميز utf8mb4).</p>
      <button class="btn btn-soft" name="action" value="test" formnovalidate><i class="bi bi-plug"></i> اختبار الاتصال</button>
    </div>

    <div class="card-header border-top"><h2>3) بيانات المدرسة</h2></div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-8"><label class="form-label required">اسم المدرسة</label><input class="form-control" name="school_name" value="<?= $e($form['school_name']) ?>" required></div>
        <div class="col-md-4"><label class="form-label">القسم</label><input class="form-control" name="school_section" value="<?= $e($form['school_section']) ?>"></div>
        <div class="col-md-8"><label class="form-label">إدارة التعليم</label><input class="form-control" name="education_dept" value="<?= $e($form['education_dept']) ?>"></div>
        <div class="col-md-4"><label class="form-label">عدد فصول الثالث المتوسط</label><input type="number" min="1" max="20" class="form-control" name="classes" value="<?= $e($form['classes']) ?>"></div>
        <div class="col-md-8"><label class="form-label">شعار المدرسة (اختياري)</label><input type="file" class="form-control" name="logo" accept="image/png,image/jpeg,image/webp"></div>
        <div class="col-md-4"><label class="form-label">رابط المنصة</label><input class="form-control ltr" name="app_url" value="<?= $e($form['app_url']) ?>"></div>
      </div>
    </div>

    <div class="card-header border-top"><h2>4) حساب مدير النظام</h2></div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label required">الاسم الكامل</label><input class="form-control" name="admin_name" value="<?= $e($form['admin_name']) ?>" required></div>
        <div class="col-md-6"><label class="form-label required">اسم المستخدم (إنجليزي)</label><input class="form-control ltr" name="admin_user" value="<?= $e($form['admin_user']) ?>" required pattern="[A-Za-z0-9_.\-]{3,64}"></div>
        <div class="col-md-6"><label class="form-label required">كلمة المرور</label><input type="password" class="form-control ltr" name="admin_pass" minlength="8" required autocomplete="new-password">
          <div class="form-text">8 أحرف على الأقل، تحتوي على أحرف وأرقام.</div></div>
        <div class="col-md-6"><label class="form-label required">تأكيد كلمة المرور</label><input type="password" class="form-control ltr" name="admin_pass_confirm" minlength="8" required></div>
        <div class="col-md-6"><label class="form-label">البريد الإلكتروني</label><input type="email" class="form-control ltr" name="admin_email" value="<?= $e($form['admin_email']) ?>"></div>
      </div>
      <div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="merge_principal" id="mp" <?= $form['merge_principal'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="mp">دمج دور «قائد المدرسة» مع حساب مدير النظام</label></div>
      <div class="form-check"><input class="form-check-input" type="checkbox" name="pretty" id="pu" checked>
        <label class="form-check-label" for="pu">الخادم يدعم إعادة كتابة الروابط (Apache mod_rewrite أو Nginx) — أزل التحديد إن ظهرت أخطاء 404 بعد التثبيت</label></div>
      <div class="form-check"><input class="form-check-input" type="checkbox" name="demo" id="dm" <?= $form['demo'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="dm">إضافة بيانات تجريبية (طلاب ومعلمون وأسئلة واختبارات ونتائج) — للتجربة فقط</label></div>
      <button class="btn btn-primary btn-lg mt-3" name="action" value="install" <?= $requirementsOk ? '' : 'disabled' ?>><i class="bi bi-download"></i> تثبيت المنصة</button>
    </div>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
