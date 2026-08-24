<?php
/**
 * برنامج تنصيب نظام الأرشفة الإلكترونية للمدارس
 * احذف مجلد install بعد اكتمال التنصيب.
 */
declare(strict_types=1);
session_start();
mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Riyadh');
error_reporting(E_ALL);
ini_set('display_errors', '1');

define('ROOT', dirname(__DIR__));
define('CONFIG_FILE', ROOT . '/app/config.php');

$step   = (int) ($_GET['step'] ?? 1);
$errors = [];
$notice = '';

function h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

/** فحص المتطلبات */
function requirements(): array
{
    return [
        ['إصدار PHP 8.0 أو أحدث', PHP_VERSION_ID >= 80000, PHP_VERSION],
        ['إضافة PDO MySQL', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'مفعّلة' : 'غير مفعّلة'],
        ['إضافة mbstring', extension_loaded('mbstring'), extension_loaded('mbstring') ? 'مفعّلة' : 'غير مفعّلة'],
        ['إضافة fileinfo', extension_loaded('fileinfo'), extension_loaded('fileinfo') ? 'مفعّلة' : 'مستحسنة'],
        ['إضافة json', extension_loaded('json'), extension_loaded('json') ? 'مفعّلة' : 'غير مفعّلة'],
        ['مجلد app قابل للكتابة', is_writable(ROOT . '/app'), is_writable(ROOT . '/app') ? 'نعم' : 'لا'],
        ['مجلد storage/uploads قابل للكتابة', is_writable(ROOT . '/storage/uploads'),
            is_writable(ROOT . '/storage/uploads') ? 'نعم' : 'لا'],
        ['مجلد storage/logs قابل للكتابة', is_writable(ROOT . '/storage/logs'),
            is_writable(ROOT . '/storage/logs') ? 'نعم' : 'لا'],
    ];
}

/** تنفيذ ملف SQL */
function runSqlFile(PDO $pdo, string $file): void
{
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException('تعذر قراءة الملف : ' . basename($file));
    }
    // إزالة تعليقات السطر
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (array_filter(array_map('trim', explode(";\n", $sql . "\n"))) as $stmt) {
        $stmt = trim($stmt, " \t\n\r\0\x0B;");
        if ($stmt === '') { continue; }
        $pdo->exec($stmt);
    }
}

// ============================ معالجة الخطوات ============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --------------------- الخطوة 2 : الاتصال بقاعدة البيانات ---------------------
    if ($step === 2) {
        $db = [
            'host'     => trim((string) ($_POST['host'] ?? 'localhost')),
            'port'     => (int) ($_POST['port'] ?? 3306),
            'database' => trim((string) ($_POST['database'] ?? '')),
            'username' => trim((string) ($_POST['username'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
            'charset'  => 'utf8mb4',
        ];
        $create = !empty($_POST['create_db']);

        if ($db['database'] === '' || $db['username'] === '') {
            $errors[] = 'اسم قاعدة البيانات واسم المستخدم مطلوبان.';
        } else {
            try {
                $dsn = "mysql:host={$db['host']};port={$db['port']};charset=utf8mb4";
                $pdo = new PDO($dsn, $db['username'], $db['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                if ($create) {
                    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $db['database'])
                        . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                }
                $pdo->exec('USE `' . str_replace('`', '', $db['database']) . '`');
                $_SESSION['install_db'] = $db;
                header('Location: index.php?step=3');
                exit;
            } catch (PDOException $ex) {
                $errors[] = 'فشل الاتصال : ' . $ex->getMessage();
            }
        }
    }

    // ------------------- الخطوة 3 : إنشاء الجداول وحساب المدير -------------------
    if ($step === 3) {
        $db       = $_SESSION['install_db'] ?? null;
        $username = trim((string) ($_POST['admin_username'] ?? 'admin'));
        $fullName = trim((string) ($_POST['admin_name'] ?? ''));
        $password = (string) ($_POST['admin_password'] ?? '');
        $confirm  = (string) ($_POST['admin_password2'] ?? '');
        $school   = trim((string) ($_POST['school_name'] ?? ''));
        $eduAdmin = trim((string) ($_POST['education_admin'] ?? ''));

        if (!$db) {
            $errors[] = 'انتهت الجلسة، أعد إدخال بيانات قاعدة البيانات.';
        }
        if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $username)) {
            $errors[] = 'اسم دخول المدير يجب أن يكون أحرفاً إنجليزية أو أرقاماً (٣ خانات فأكثر).';
        }
        if (mb_strlen($fullName) < 3) { $errors[] = 'اسم المدير الكامل مطلوب.'; }
        if (strlen($password) < 8)    { $errors[] = 'كلمة المرور يجب ألا تقل عن ٨ خانات.'; }
        if ($password !== $confirm)   { $errors[] = 'كلمتا المرور غير متطابقتين.'; }

        if (!$errors) {
            try {
                $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset=utf8mb4";
                $pdo = new PDO($dsn, $db['username'], $db['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

                runSqlFile($pdo, ROOT . '/database/schema.sql');

                // البذور تُنفَّذ فقط إن كانت الجداول فارغة
                $hasRoles = (int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn();
                if ($hasRoles === 0) {
                    runSqlFile($pdo, ROOT . '/database/seed.sql');
                }

                // تحديث حساب المدير
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('SELECT id FROM users WHERE id = 1');
                $stmt->execute();
                if ($stmt->fetchColumn()) {
                    $pdo->prepare('UPDATE users SET username = ?, password_hash = ?, full_name = ? WHERE id = 1')
                        ->execute([$username, $hash, $fullName]);
                } else {
                    $pdo->prepare('INSERT INTO users (id, username, password_hash, full_name, role_id, is_active)
                                   VALUES (1, ?, ?, ?, 1, 1)')
                        ->execute([$username, $hash, $fullName]);
                }

                // إعدادات المدرسة
                $set = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                                      ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
                if ($school !== '')   { $set->execute(['school_name', $school]); }
                if ($eduAdmin !== '') { $set->execute(['education_admin', $eduAdmin]); }

                // كتابة ملف الإعدادات
                $config = "<?php\n/**\n * إعدادات نظام الأرشفة الإلكترونية\n"
                        . " * وُلِّد تلقائياً بتاريخ " . date('Y-m-d H:i') . "\n */\nreturn "
                        . var_export([
                            'app_url' => '',
                            'debug'   => false,
                            'db'      => $db,
                        ], true) . ";\n";

                if (@file_put_contents(CONFIG_FILE, $config) === false) {
                    $errors[] = 'تعذر كتابة ملف app/config.php — انسخ المحتوى يدوياً.';
                    $_SESSION['manual_config'] = $config;
                } else {
                    @chmod(CONFIG_FILE, 0640);
                    $_SESSION['install_done'] = true;
                    unset($_SESSION['install_db']);
                    header('Location: index.php?step=4');
                    exit;
                }
            } catch (Throwable $ex) {
                $errors[] = 'خطأ أثناء التنصيب : ' . $ex->getMessage();
            }
        }
    }
}

$reqs = requirements();
$allOk = !in_array(false, array_column($reqs, 1), true);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تنصيب نظام الأرشفة الإلكترونية</title>
<link rel="stylesheet" href="../public/assets/css/app.css">
<style>
  body{background:linear-gradient(135deg,#0f766e,#0b3b39);min-height:100vh;padding:1.5rem}
  .wrap{max-width:760px;margin:0 auto}
  .steps{display:flex;gap:.5rem;margin-bottom:1rem;justify-content:center;flex-wrap:wrap}
  .steps span{background:rgba(255,255,255,.15);color:#d1faf5;padding:.35rem .9rem;border-radius:999px;font-size:.8rem}
  .steps span.on{background:#fff;color:#0f766e;font-weight:700}
</style>
</head>
<body>
<div class="wrap">
  <div class="center" style="color:#fff;margin-bottom:1rem">
    <div style="font-size:2.5rem">🗂️</div>
    <h1 style="color:#fff">تنصيب نظام الأرشفة الإلكترونية للمدارس</h1>
  </div>

  <div class="steps">
    <span class="<?= $step === 1 ? 'on' : '' ?>">١ · المتطلبات</span>
    <span class="<?= $step === 2 ? 'on' : '' ?>">٢ · قاعدة البيانات</span>
    <span class="<?= $step === 3 ? 'on' : '' ?>">٣ · حساب المدير</span>
    <span class="<?= $step === 4 ? 'on' : '' ?>">٤ · انتهى</span>
  </div>

  <?php foreach ($errors as $err): ?>
    <div class="alert error"><span>⛔</span><span><?= h($err) ?></span></div>
  <?php endforeach; ?>

  <?php if (is_file(CONFIG_FILE) && $step < 4 && empty($_SESSION['install_done'])): ?>
    <div class="alert warning">
      <span>⚠️</span>
      <span>ملف الإعدادات <code>app/config.php</code> موجود مسبقاً — النظام منصّب على الأرجح.
        متابعة التنصيب ستستبدل الإعدادات. <a href="../public/index.php">الذهاب للنظام</a></span>
    </div>
  <?php endif; ?>

  <!-- =========================== الخطوة ١ =========================== -->
  <?php if ($step === 1): ?>
  <div class="card">
    <div class="card-head"><h3>١ · فحص متطلبات التشغيل</h3></div>
    <div class="card-body tight">
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>المتطلب</th><th>الحالة</th><th>القيمة</th></tr></thead>
          <tbody>
          <?php foreach ($reqs as [$name, $ok, $value]): ?>
            <tr>
              <td><?= h($name) ?></td>
              <td><span class="badge <?= $ok ? 'ok' : 'danger' ?>"><?= $ok ? '✓ متوفر' : '✕ ناقص' ?></span></td>
              <td class="small ltr"><?= h($value) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card-foot">
      <?php if ($allOk): ?>
        <a class="btn" href="index.php?step=2">المتابعة ← قاعدة البيانات</a>
      <?php else: ?>
        <div class="alert error" style="margin:0"><span>⛔</span>
          <span>يجب توفير المتطلبات الناقصة أولاً. لصلاحيات المجلدات نفّذ:
            <code>chmod -R 775 storage app</code></span></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- =========================== الخطوة ٢ =========================== -->
  <?php elseif ($step === 2): ?>
  <div class="card">
    <div class="card-head"><h3>٢ · بيانات الاتصال بقاعدة البيانات</h3></div>
    <form method="post" action="index.php?step=2">
      <div class="card-body">
        <div class="form-grid two">
          <div class="field"><label>الخادم <span class="req">*</span></label>
            <input type="text" name="host" value="<?= h($_POST['host'] ?? 'localhost') ?>" required dir="ltr"></div>
          <div class="field"><label>المنفذ</label>
            <input type="number" name="port" value="<?= h($_POST['port'] ?? 3306) ?>" dir="ltr"></div>
          <div class="field"><label>اسم قاعدة البيانات <span class="req">*</span></label>
            <input type="text" name="database" value="<?= h($_POST['database'] ?? 'school_archive') ?>" required dir="ltr"></div>
          <div class="field"><label>اسم المستخدم <span class="req">*</span></label>
            <input type="text" name="username" value="<?= h($_POST['username'] ?? 'root') ?>" required dir="ltr"></div>
          <div class="field full"><label>كلمة المرور</label>
            <input type="password" name="password" dir="ltr"></div>
        </div>
        <label class="check mt"><input type="checkbox" name="create_db" value="1" checked>
          إنشاء قاعدة البيانات إن لم تكن موجودة</label>
      </div>
      <div class="card-foot">
        <div class="form-actions">
          <button class="btn">اختبار الاتصال والمتابعة ←</button>
          <a class="btn outline" href="index.php?step=1">رجوع</a>
        </div>
      </div>
    </form>
  </div>

  <!-- =========================== الخطوة ٣ =========================== -->
  <?php elseif ($step === 3): ?>
  <div class="card">
    <div class="card-head"><h3>٣ · حساب مدير النظام وبيانات المدرسة</h3></div>
    <form method="post" action="index.php?step=3">
      <div class="card-body">
        <div class="form-grid two">
          <div class="field"><label>اسم المدير الكامل <span class="req">*</span></label>
            <input type="text" name="admin_name" required value="<?= h($_POST['admin_name'] ?? '') ?>"></div>
          <div class="field"><label>اسم الدخول <span class="req">*</span></label>
            <input type="text" name="admin_username" required dir="ltr"
                   value="<?= h($_POST['admin_username'] ?? 'admin') ?>" pattern="[A-Za-z0-9._\-]{3,60}"></div>
          <div class="field"><label>كلمة المرور <span class="req">*</span></label>
            <input type="password" name="admin_password" required minlength="8" dir="ltr"></div>
          <div class="field"><label>تأكيد كلمة المرور <span class="req">*</span></label>
            <input type="password" name="admin_password2" required minlength="8" dir="ltr"></div>
          <div class="field"><label>اسم المدرسة</label>
            <input type="text" name="school_name" value="<?= h($_POST['school_name'] ?? '') ?>"></div>
          <div class="field"><label>إدارة التعليم</label>
            <input type="text" name="education_admin" value="<?= h($_POST['education_admin'] ?? '') ?>"></div>
        </div>
        <div class="alert info mt">
          <span>ℹ️</span>
          <span>سيتم إنشاء جداول النظام وتعبئتها ببيانات أولية : الهيكل التنظيمي للمدرسة،
            شجرة أرشفة جاهزة، التصنيفات، الجهات، والأدوار والصلاحيات.</span>
        </div>
      </div>
      <div class="card-foot">
        <div class="form-actions">
          <button class="btn">تنفيذ التنصيب ←</button>
          <a class="btn outline" href="index.php?step=2">رجوع</a>
        </div>
      </div>
    </form>
  </div>

  <?php if (!empty($_SESSION['manual_config'])): ?>
    <div class="card">
      <div class="card-head"><h3>انسخ هذا المحتوى إلى app/config.php</h3></div>
      <div class="card-body">
        <textarea rows="14" dir="ltr" style="font-family:monospace"><?= h($_SESSION['manual_config']) ?></textarea>
      </div>
    </div>
  <?php endif; ?>

  <!-- =========================== الخطوة ٤ =========================== -->
  <?php else: ?>
  <div class="card">
    <div class="card-head"><h3>🎉 تم التنصيب بنجاح</h3></div>
    <div class="card-body">
      <div class="alert success"><span>✅</span>
        <span>تم إنشاء قاعدة البيانات وجداولها وحساب مدير النظام.</span></div>

      <div class="alert error">
        <span>🔐</span>
        <span><b>مهم جداً :</b> احذف مجلد <code>install</code> الآن من الخادم لمنع إعادة التنصيب من قبل الغير.</span>
      </div>

      <h4>الخطوات التالية</h4>
      <ol class="small">
        <li>اجعل <code>public</code> هو مجلد الجذر (Document Root) للموقع إن أمكن.</li>
        <li>تأكد من أن مجلد <code>storage</code> غير قابل للوصول المباشر عبر المتصفح.</li>
        <li>ادخل للنظام وعدّل بيانات المدرسة من «إعدادات النظام».</li>
        <li>أنشئ حسابات المستخدمين ووزّع الأدوار من «المستخدمون».</li>
        <li>هيّئ شجرة الأرشفة والهيكل التنظيمي بما يناسب مدرستك.</li>
      </ol>
    </div>
    <div class="card-foot">
      <a class="btn" href="../public/index.php">الدخول إلى النظام ←</a>
    </div>
  </div>
  <?php endif; ?>

  <div class="center xsmall" style="color:#a7f3ec;margin-top:1rem">
    نظام الأرشفة الإلكترونية للمدارس — الإصدار 1.0.0
  </div>
</div>
</body>
</html>
