<?php
declare(strict_types=1);
define('APP_AREA', 'admin');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';

Auth::requireAdmin();

$classFilter = input_int('class');
$search      = (string) input('q', '');
$backUrl     = 'admin/students.php' . ($classFilter ? '?class=' . $classFilter : '');

/* ---------------- التصدير ---------------- */
if (in_array(input('export'), ['csv', 'xlsx'], true)) {
    $rows = [['الاسم', 'اسم المستخدم', 'رقم الهوية', 'الصف', 'الفصل', 'جوال ولي الأمر', 'الحالة']];
    foreach (Nafes::students($classFilter ?: null, null, $search) as $student) {
        $rows[] = [$student['name'], $student['username'], $student['national_id'] ?? '', $student['grade_name'] ?? '',
                   $student['class_name'] ?? '', $student['phone'] ?? '', (int) $student['is_active'] === 1 ? 'نشط' : 'موقوف'];
    }
    if (input('export') === 'csv') {
        Csv::download('الطلاب.csv', $rows);
    }
    $xlsx = new Xlsx('الطلاب');
    $xlsx->setColumnWidths([30, 18, 16, 22, 10, 16, 10]);
    $xlsx->addRow(array_shift($rows), Xlsx::STYLE_HEADER, 26);
    foreach ($rows as $line) {
        $xlsx->addRow($line, Xlsx::STYLE_BODY, 22);
    }
    $xlsx->download('الطلاب.xlsx');
    exit;
}

if (input('template') === '1') {
    Csv::download('نموذج-الطلاب.csv', [
        ['الاسم', 'اسم المستخدم', 'كلمة المرور', 'رقم الهوية', 'الصف', 'الفصل', 'جوال ولي الأمر'],
        ['عبدالله بن محمد العتيبي', '', '', '1122334455', 'الثالث الابتدائي', 'أ', '05xxxxxxxx'],
    ]);
}

/* ---------------- العمليات ---------------- */
$cards = null;

if (is_post()) {
    csrf_verify();
    $action = (string) input('action', '');
    $id     = input_int('id');

    if ($action === 'save') {
        $name        = (string) input('name', '');
        $nationalId  = preg_replace('/\D+/', '', (string) input('national_id', '')) ?? '';
        $username    = strtolower((string) input('username', '')) ?: $nationalId;
        $password    = (string) ($_POST['password'] ?? '');
        $classroomId = input_int('classroom_id');

        if ($name === '' || $username === '' || !$classroomId) {
            flash('error', 'الاسم والفصل واسم المستخدم (أو رقم الهوية) مطلوبة.');
            redirect('admin/students.php' . ($id ? '?edit=' . $id : ''));
        }
        if (!preg_match('/^[a-z0-9_.-]{3,64}$/', $username)) {
            flash('error', 'اسم المستخدم يجب أن يكون بأحرف إنجليزية أو أرقام وبطول 3 أحرف فأكثر.');
            redirect('admin/students.php' . ($id ? '?edit=' . $id : ''));
        }
        if (Database::value('SELECT id FROM users WHERE username = ? AND id <> ?', [$username, $id])) {
            flash('error', 'اسم المستخدم مستخدم مسبقاً.');
            redirect('admin/students.php' . ($id ? '?edit=' . $id : ''));
        }

        $data = [
            'name'         => $name,
            'username'     => $username,
            'national_id'  => $nationalId ?: null,
            'phone'        => input('phone') ?: null,
            'classroom_id' => $classroomId,
            'is_active'    => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($id) {
            if ($password !== '') {
                $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }
            Database::update('users', $data, "id = :uid AND role = 'student'", ['uid' => $id]);
            Activity::log('student_update', 'users', $id, $name);
            flash('success', 'تم تحديث بيانات الطالب ' . $name . '.');
        } else {
            $plain = $password !== '' ? $password : random_password(6);
            $newId = Database::insert('users', $data + [
                'password_hash' => password_hash($plain, PASSWORD_DEFAULT),
                'role'          => 'student',
                'job_title'     => 'طالب',
            ]);
            Activity::log('student_create', 'users', $newId, $name);
            flash('success', 'تمت إضافة ' . $name . ' — اسم المستخدم: ' . $username . ' وكلمة المرور: ' . $plain);
        }
        redirect('admin/students.php?class=' . $classroomId);
    }

    if ($action === 'import') {
        if (empty($_FILES['file']['name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            flash('error', 'يرجى اختيار ملف CSV للاستيراد.');
            redirect($backUrl);
        }
        $result = Importer::students(Csv::read($_FILES['file']['tmp_name']), isset($_POST['create_missing']), input_int('import_class') ?: null);
        flash($result['errors'] ? 'warning' : 'success',
            'تم الاستيراد: ' . $result['created'] . ' طالب جديد، و' . $result['updated'] . ' تحديث'
            . ($result['errors'] ? ' — ' . count($result['errors']) . ' سطر به أخطاء: ' . implode(' | ', array_slice($result['errors'], 0, 5)) : '.'));
        $cards = $result['passwords'];
    }

    $selected = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));

    if ($action === 'reset_password' && $id) {
        $selected = [$id];
        $action   = 'bulk_reset';
    }

    if ($action === 'bulk_reset' && $selected) {
        $cards = [];
        foreach ($selected as $studentId) {
            $student = Nafes::student($studentId);
            if (!$student) {
                continue;
            }
            $plain = random_password(6);
            Database::update('users', ['password_hash' => password_hash($plain, PASSWORD_DEFAULT)], 'id = :uid', ['uid' => $studentId]);
            $cards[] = ['name' => $student['name'], 'username' => $student['username'], 'password' => $plain,
                        'class' => Nafes::classOf($student)];
        }
        Activity::log('student_passwords', 'users', null, 'count=' . count($cards));
        flash('success', 'تم إنشاء كلمات مرور جديدة لـ ' . count($cards) . ' طالب — اطبع بطاقات الدخول أدناه قبل مغادرة الصفحة.');
    }

    if ($action === 'bulk_move' && $selected && input_int('target_class')) {
        $in = implode(',', $selected);
        Database::run("UPDATE users SET classroom_id = ? WHERE role = 'student' AND id IN ($in)", [input_int('target_class')]);
        flash('success', 'تم نقل ' . count($selected) . ' طالب إلى الفصل المحدد.');
        redirect('admin/students.php?class=' . input_int('target_class'));
    }

    if (in_array($action, ['bulk_activate', 'bulk_deactivate'], true) && $selected) {
        $in = implode(',', $selected);
        Database::run("UPDATE users SET is_active = ? WHERE role = 'student' AND id IN ($in)", [$action === 'bulk_activate' ? 1 : 0]);
        flash('success', 'تم تحديث حالة ' . count($selected) . ' طالب.');
        redirect($backUrl);
    }

    if ($action === 'toggle' && $id) {
        Database::run("UPDATE users SET is_active = 1 - is_active WHERE id = ? AND role = 'student'", [$id]);
        flash('success', 'تم تغيير حالة الحساب.');
        redirect($backUrl);
    }

    if ($action === 'delete' && $id) {
        if (Nafes::studentHasRecords($id)) {
            Database::update('users', ['is_active' => 0], "id = :uid AND role = 'student'", ['uid' => $id]);
            flash('warning', 'للطالب نتائج مسجلة، تم إيقاف الحساب بدلاً من حذفه للحفاظ على السجلات.');
        } else {
            Database::delete('users', "id = ? AND role = 'student'", [$id]);
            Activity::log('student_delete', 'users', $id);
            flash('success', 'تم حذف حساب الطالب.');
        }
        redirect($backUrl);
    }

    if ($cards === null) {
        redirect($backUrl);
    }
}

$classrooms = Plans::classrooms();
$editId     = input_int('edit');
$edit       = $editId ? Nafes::student($editId) : null;
$students   = Nafes::students($classFilter ?: null, null, $search);

$countsByClass = [];
foreach (Database::all("SELECT classroom_id, COUNT(*) AS n FROM users WHERE role = 'student' AND is_active = 1 GROUP BY classroom_id") as $row) {
    $countsByClass[(int) $row['classroom_id']] = (int) $row['n'];
}

page_start('الطلاب', 'admin', 'students');
?>

<?php if ($cards): ?>
<div class="card" id="login-cards">
  <div class="card-head">
    <h2>بطاقات دخول الطلاب (<?= count($cards) ?>)</h2>
    <button class="btn btn-sm btn-primary" data-print>طباعة البطاقات</button>
  </div>
  <div class="card-body">
    <div class="login-cards">
      <?php foreach ($cards as $card): ?>
        <div class="login-card">
          <strong><?= h($card['name']) ?></strong>
          <?php if (!empty($card['class'])): ?><small><?= h($card['class']) ?></small><br><?php endif; ?>
          اسم المستخدم: <code><?= h($card['username']) ?></code><br>
          كلمة المرور: <code><?= h($card['password']) ?></code><br>
          <small>رابط الدخول: <?= h(url('login.php')) ?></small>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="stats">
  <div class="stat teal"><div class="num"><?= array_sum($countsByClass) ?></div><div class="lbl">طالب نشط</div></div>
  <div class="stat"><div class="num"><?= count(array_filter($countsByClass)) ?></div><div class="lbl">فصل به طلاب</div></div>
  <div class="stat amber"><div class="num"><?= count(array_filter($students, static fn ($s) => $s['last_login_at'] === null)) ?></div><div class="lbl">لم يدخلوا بعد (في القائمة)</div></div>
</div>

<div class="grid grid-2">
<div class="card">
  <div class="card-head"><h2><?= $edit ? 'تعديل بيانات: ' . h($edit['name']) : 'إضافة طالب' ?></h2>
    <?php if ($edit): ?><a class="btn btn-sm btn-outline" href="<?= h(url('admin/students.php')) ?>">إلغاء التعديل</a><?php endif; ?>
  </div>
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <div class="grid grid-2">
        <div class="field"><label>اسم الطالب *</label><input type="text" name="name" value="<?= h($edit['name'] ?? '') ?>" required></div>
        <div class="field">
          <label>الفصل *</label>
          <select name="classroom_id" required>
            <option value="">— اختر —</option>
            <?php foreach ($classrooms as $classroom): ?>
              <option value="<?= (int) $classroom['id'] ?>" <?= (int) ($edit['classroom_id'] ?? $classFilter) === (int) $classroom['id'] ? 'selected' : '' ?>>
                <?= h(Plans::classLabel($classroom)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>رقم الهوية / السجل المدني</label><input type="text" name="national_id" inputmode="numeric" value="<?= h($edit['national_id'] ?? '') ?>"></div>
        <div class="field"><label>اسم المستخدم <small>(يُستخدم رقم الهوية إن تُرك فارغاً)</small></label><input type="text" name="username" value="<?= h($edit['username'] ?? '') ?>"></div>
        <div class="field">
          <label>كلمة المرور <small><?= $edit ? '(اتركها فارغة لعدم التغيير)' : '(تُنشأ تلقائياً إن تُركت فارغة)' ?></small></label>
          <input type="text" name="password" autocomplete="new-password">
        </div>
        <div class="field"><label>جوال ولي الأمر</label><input type="tel" name="phone" value="<?= h($edit['phone'] ?? '') ?>"></div>
      </div>
      <label class="flex mb16"><input type="checkbox" name="is_active" style="width:auto" <?= (int) ($edit['is_active'] ?? 1) === 1 ? 'checked' : '' ?>> الحساب نشط</label>
      <button class="btn btn-primary" type="submit"><?= $edit ? 'حفظ التعديلات' : 'إضافة الطالب' ?></button>
    </form>
  </div>
</div>

<div class="card" id="import">
  <div class="card-head"><h2>استيراد الطلاب من ملف</h2>
    <a class="btn btn-sm btn-outline" href="<?= h(url('admin/students.php?template=1')) ?>">تنزيل النموذج</a>
  </div>
  <div class="card-body">
    <p class="help">ملف CSV بالأعمدة: الاسم، اسم المستخدم، كلمة المرور، رقم الهوية، الصف، الفصل، جوال ولي الأمر.
      يمكن تصدير قائمة الطلاب من نظام نور ثم حفظها بصيغة <b>CSV UTF-8</b>. الطالب الموجود مسبقاً (بنفس اسم المستخدم) يُحدَّث ولا يُكرَّر.</p>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="import">
      <div class="field"><label>الملف</label><input type="file" name="file" accept=".csv,text/csv" required></div>
      <div class="field">
        <label>فصل افتراضي <small>(للأسطر التي لا تحتوي صفاً وفصلاً)</small></label>
        <select name="import_class">
          <option value="">— بدون —</option>
          <?php foreach ($classrooms as $classroom): ?>
            <option value="<?= (int) $classroom['id'] ?>"><?= h(Plans::classLabel($classroom)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <label class="flex mb16"><input type="checkbox" name="create_missing" style="width:auto" checked> إنشاء الصفوف والفصول غير الموجودة</label>
      <button class="btn btn-primary" type="submit">استيراد</button>
    </form>
  </div>
</div>
</div>

<div class="filters">
  <form method="get">
    <div class="field">
      <label>الفصل</label>
      <select name="class" data-auto-submit>
        <option value="0">كل الفصول</option>
        <?php foreach ($classrooms as $classroom): ?>
          <option value="<?= (int) $classroom['id'] ?>" <?= $classFilter === (int) $classroom['id'] ? 'selected' : '' ?>>
            <?= h(Plans::classLabel($classroom)) ?> (<?= $countsByClass[(int) $classroom['id']] ?? 0 ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>بحث</label><input type="text" name="q" value="<?= h($search) ?>" placeholder="الاسم أو الهوية"></div>
    <button class="btn btn-outline" type="submit">بحث</button>
    <span class="spacer"></span>
    <a class="btn btn-outline" href="<?= h(url('admin/students.php?export=xlsx&class=' . $classFilter . '&q=' . rawurlencode($search))) ?>">تصدير Excel</a>
    <a class="btn btn-outline" href="<?= h(url('admin/students.php?export=csv&class=' . $classFilter . '&q=' . rawurlencode($search))) ?>">تصدير CSV</a>
  </form>
</div>

<form method="post" class="card">
  <?= csrf_field() ?>
  <div class="card-head">
    <h2>قائمة الطلاب (<?= count($students) ?>)</h2>
    <div class="flex">
      <button class="btn btn-sm btn-outline" name="action" value="bulk_reset" data-confirm="إنشاء كلمات مرور جديدة للطلاب المحددين؟">كلمات مرور وبطاقات دخول</button>
      <button class="btn btn-sm btn-outline" name="action" value="bulk_activate">تفعيل</button>
      <button class="btn btn-sm btn-outline" name="action" value="bulk_deactivate">إيقاف</button>
      <select name="target_class" style="width:auto">
        <option value="">نقل إلى فصل…</option>
        <?php foreach ($classrooms as $classroom): ?>
          <option value="<?= (int) $classroom['id'] ?>"><?= h(Plans::classLabel($classroom)) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-sm btn-outline" name="action" value="bulk_move">نقل</button>
    </div>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr><th><input type="checkbox" data-check-all="ids[]"></th><th>الطالب</th><th>اسم المستخدم</th><th>الصف / الفصل</th><th>جوال ولي الأمر</th><th>آخر دخول</th><th>الحالة</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($students as $student): ?>
          <tr>
            <td><input type="checkbox" name="ids[]" value="<?= (int) $student['id'] ?>"></td>
            <td><strong><?= h($student['name']) ?></strong><?php if ($student['national_id']): ?><br><small><?= h($student['national_id']) ?></small><?php endif; ?></td>
            <td><?= h($student['username']) ?></td>
            <td><?= $student['class_name'] !== null ? h(Nafes::classOf($student)) : '<span class="badge badge-red">بدون فصل</span>' ?></td>
            <td class="text-muted"><?= h($student['phone'] ?: '—') ?></td>
            <td class="text-muted nowrap"><?= h($student['last_login_at'] ?: 'لم يدخل بعد') ?></td>
            <td><?= (int) $student['is_active'] === 1 ? '<span class="badge badge-green">نشط</span>' : '<span class="badge badge-gray">موقوف</span>' ?></td>
            <td class="actions">
              <a class="btn btn-sm btn-outline" href="<?= h(url('admin/students.php?edit=' . (int) $student['id'] . '&class=' . $classFilter)) ?>">تعديل</a>
              <?php if ($student['classroom_id']): ?>
                <a class="btn btn-sm btn-outline" href="<?= h(url('nafes/student_report.php?id=' . (int) $student['id'])) ?>">التقرير</a>
              <?php endif; ?>
              <button class="btn btn-sm btn-outline" form="row-<?= (int) $student['id'] ?>" name="action" value="reset_password"
                      data-confirm="إنشاء كلمة مرور جديدة لهذا الطالب؟">كلمة مرور</button>
              <button class="btn btn-sm btn-outline" form="row-<?= (int) $student['id'] ?>" name="action" value="toggle">
                <?= (int) $student['is_active'] === 1 ? 'إيقاف' : 'تفعيل' ?>
              </button>
              <button class="btn btn-sm btn-danger" form="row-<?= (int) $student['id'] ?>" name="action" value="delete"
                      data-confirm="حذف حساب الطالب نهائياً؟">حذف</button>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$students): ?>
          <tr><td colspan="8" class="empty">لا يوجد طلاب. أضفهم يدوياً أو استوردهم من ملف.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</form>

<?php foreach ($students as $student): ?>
  <form method="post" id="row-<?= (int) $student['id'] ?>" class="hidden">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $student['id'] ?>">
  </form>
<?php endforeach; ?>

<?php page_end('admin'); ?>
