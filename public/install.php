<?php
/**
 * مثبّت النظام — يُنشئ ملف الإعدادات وجداول قاعدة البيانات وحساب المدير.
 * احذف هذا الملف بعد إتمام التثبيت.
 */
declare(strict_types=1);

define('APP_INSTALLER', true);
require __DIR__ . '/../app/bootstrap.php';

$configFile = CONFIG_PATH . '/config.php';
$installed  = is_file($configFile);

// إذا كان النظام مثبتاً ويحتوي على مدير، امنع إعادة التثبيت
if ($installed) {
    try {
        $adminCount = (int) Database::value("SELECT COUNT(*) FROM users WHERE role = 'admin'", [], 0);
        if ($adminCount > 0) {
            http_response_code(403);
            exit('النظام مثبت مسبقاً. لإعادة التثبيت احذف الملف config/config.php ثم أعد المحاولة، ويفضل حذف install.php لحماية النظام.');
        }
    } catch (Throwable) {
        // قاعدة البيانات غير جاهزة — أكمل التثبيت
    }
}

$errors = [];
$done   = false;

$form = [
    'db_host'    => input('db_host', 'localhost'),
    'db_port'    => input('db_port', '3306'),
    'db_name'    => input('db_name', 'weekly_plans'),
    'db_user'    => input('db_user', 'root'),
    'db_pass'    => $_POST['db_pass'] ?? '',
    'school'     => input('school', 'مدرسة النموذج الابتدائية'),
    'dept'       => input('dept', 'الإدارة العامة للتعليم بمنطقة ...'),
    'principal'  => input('principal', ''),
    'admin_name' => input('admin_name', 'مدير المدرسة'),
    'admin_user' => input('admin_user', 'admin'),
    'admin_pass' => $_POST['admin_pass'] ?? '',
    'demo'       => isset($_POST['demo']),
];

if (is_post()) {
    if (mb_strlen((string) $form['admin_pass']) < 6) {
        $errors[] = 'كلمة مرور المدير يجب ألا تقل عن 6 أحرف.';
    }
    if ($form['admin_user'] === '' || $form['school'] === '') {
        $errors[] = 'يرجى تعبئة اسم المستخدم واسم المدرسة.';
    }

    if (!$errors) {
        try {
            // 1) الاتصال بالخادم وإنشاء قاعدة البيانات إن لم تكن موجودة
            $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $form['db_host'], (int) $form['db_port']);
            $pdo = new PDO($dsn, $form['db_user'], $form['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec(sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                str_replace('`', '', $form['db_name'])
            ));
            $pdo->exec('USE `' . str_replace('`', '', $form['db_name']) . '`');

            // 2) تنفيذ ملف البنية
            $sql = file_get_contents(APP_ROOT . '/database/schema.sql');
            if ($sql === false) {
                throw new RuntimeException('تعذر قراءة ملف database/schema.sql');
            }
            // إزالة التعليقات ثم تنفيذ كل جملة على حدة
            $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
            foreach (explode(';', $sql) as $statement) {
                $statement = trim($statement);
                if ($statement !== '') {
                    $pdo->exec($statement);
                }
            }

            // 3) كتابة ملف الإعدادات
            $config = [
                'db' => [
                    'host'     => $form['db_host'],
                    'port'     => (int) $form['db_port'],
                    'database' => $form['db_name'],
                    'username' => $form['db_user'],
                    'password' => $form['db_pass'],
                    'charset'  => 'utf8mb4',
                ],
                'app_name' => 'نظام الخطط الأسبوعية',
                'timezone' => 'Asia/Riyadh',
                'debug'    => false,
                'app_key'  => bin2hex(random_bytes(16)),
            ];
            $export = "<?php\n// أُنشئ تلقائياً بواسطة install.php\nreturn " . var_export($config, true) . ";\n";
            if (@file_put_contents($configFile, $export) === false) {
                throw new RuntimeException('تعذر إنشاء ملف config/config.php — تأكد من صلاحيات الكتابة على مجلد config.');
            }

            // 4) تهيئة البيانات الأساسية
            Database::configure($config['db']);
            require APP_ROOT . '/database/seed.php';
            seed_base([
                'school'    => $form['school'],
                'dept'      => $form['dept'],
                'principal' => $form['principal'],
            ]);

            Database::insert('users', [
                'name'          => $form['admin_name'] ?: 'مدير المدرسة',
                'username'      => $form['admin_user'],
                'password_hash' => password_hash((string) $form['admin_pass'], PASSWORD_DEFAULT),
                'role'          => 'admin',
                'job_title'     => 'مدير المدرسة',
                'is_active'     => 1,
            ]);

            if ($form['demo']) {
                seed_demo();
            }

            $done = true;
        } catch (Throwable $e) {
            $errors[] = 'خطأ أثناء التثبيت: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تثبيت نظام الخطط الأسبوعية</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<div class="install-wrap">
  <div class="card">
    <div class="card-head"><h2>تثبيت نظام الخطط الأسبوعية</h2><span class="badge badge-teal">إصدار 1.0</span></div>
    <div class="card-body">

    <?php if ($done): ?>
      <div class="alert alert-success">
        تم التثبيت بنجاح! يمكنك الآن الدخول باسم المستخدم <b><?= h($form['admin_user']) ?></b>.
      </div>
      <div class="alert alert-warning">
        للأمان: احذف الملف <code>public/install.php</code> من الخادم الآن.
      </div>
      <a class="btn btn-primary" href="<?= h(url('login.php')) ?>">الانتقال لصفحة الدخول</a>
      <a class="btn btn-outline" href="<?= h(url('index.php')) ?>">واجهة الموقع</a>
    <?php else: ?>

      <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
      <?php endforeach; ?>

      <form method="post">
        <h3>1) بيانات قاعدة البيانات</h3>
        <div class="grid grid-2">
          <div class="field"><label>خادم قاعدة البيانات</label><input type="text" name="db_host" value="<?= h($form['db_host']) ?>" required></div>
          <div class="field"><label>المنفذ</label><input type="text" name="db_port" value="<?= h($form['db_port']) ?>"></div>
          <div class="field"><label>اسم قاعدة البيانات</label><input type="text" name="db_name" value="<?= h($form['db_name']) ?>" required></div>
          <div class="field"><label>المستخدم</label><input type="text" name="db_user" value="<?= h($form['db_user']) ?>" required></div>
          <div class="field"><label>كلمة المرور</label><input type="password" name="db_pass" value=""></div>
        </div>

        <h3>2) بيانات المدرسة</h3>
        <div class="grid grid-2">
          <div class="field"><label>اسم المدرسة</label><input type="text" name="school" value="<?= h($form['school']) ?>" required></div>
          <div class="field"><label>إدارة التعليم</label><input type="text" name="dept" value="<?= h($form['dept']) ?>"></div>
          <div class="field"><label>اسم مدير/مديرة المدرسة</label><input type="text" name="principal" value="<?= h($form['principal']) ?>"></div>
        </div>

        <h3>3) حساب مدير النظام</h3>
        <div class="grid grid-3">
          <div class="field"><label>الاسم</label><input type="text" name="admin_name" value="<?= h($form['admin_name']) ?>" required></div>
          <div class="field"><label>اسم المستخدم</label><input type="text" name="admin_user" value="<?= h($form['admin_user']) ?>" required></div>
          <div class="field"><label>كلمة المرور</label><input type="password" name="admin_pass" required minlength="6"></div>
        </div>

        <label class="flex"><input type="checkbox" name="demo" style="width:auto" <?= $form['demo'] ? 'checked' : '' ?>>
          إضافة بيانات تجريبية (صفوف ومعلمون وخطط جاهزة للتجربة)</label>

        <div class="toolbar mt16">
          <button class="btn btn-primary" type="submit">بدء التثبيت</button>
        </div>
      </form>
    <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
