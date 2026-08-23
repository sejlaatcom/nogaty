<?php
/** مركز الاستيراد والتصدير */
declare(strict_types=1);
define('APP_AREA', 'admin');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';

Auth::requireAdmin();

$yearId = (int) (Plans::activeYear()['id'] ?? 0);

/* ============================ التصدير ============================ */
$export = (string) input('export', '');
$format = input('format', 'xlsx') === 'csv' ? 'csv' : 'xlsx';

if ($export !== '') {
    $rows  = [];
    $title = '';

    switch ($export) {
        case 'teachers':
            $title = 'المعلمون';
            $rows[] = ['الاسم', 'اسم المستخدم', 'الصلاحية', 'الجوال', 'البريد الإلكتروني', 'رقم الهوية', 'المسمى الوظيفي', 'الحالة'];
            foreach (Database::all('SELECT * FROM users ORDER BY role DESC, name') as $user) {
                $rows[] = [
                    $user['name'], $user['username'], $user['role'] === 'admin' ? 'إدارة' : 'معلم',
                    $user['phone'], $user['email'], $user['national_id'], $user['job_title'],
                    (int) $user['is_active'] === 1 ? 'نشط' : 'موقوف',
                ];
            }
            break;

        case 'assignments':
            $title = 'الإسناد';
            $rows[] = ['الاسم', 'اسم المستخدم', 'المادة', 'الصف', 'الفصل'];
            foreach (Database::all(
                'SELECT u.name, u.username, s.name AS subject, g.name AS grade, c.name AS classroom
                   FROM assignments a
                   JOIN users u ON u.id = a.teacher_id
                   JOIN subjects s ON s.id = a.subject_id
                   JOIN classrooms c ON c.id = a.classroom_id
                   JOIN grades g ON g.id = c.grade_id
                  WHERE a.year_id = ?
               ORDER BY u.name, g.sort_order, c.sort_order',
                [$yearId]
            ) as $item) {
                $rows[] = [$item['name'], $item['username'], $item['subject'], $item['grade'], $item['classroom']];
            }
            break;

        case 'plans':
            $weekId = input_int('week') ?: (int) (Plans::currentWeek()['id'] ?? 0);
            $week   = $weekId ? Plans::week($weekId) : null;
            if (!$week) {
                flash('error', 'لا يوجد أسبوع محدد للتصدير.');
                redirect('admin/transfer.php');
            }
            $title = 'الخطط - ' . ($week['title'] ?: ('الأسبوع ' . $week['week_no']));
            $rows[] = ['الأسبوع', 'الصف', 'الفصل', 'المادة', 'البند', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس'];

            $fields = PLAN_FIELDS;
            if (!Settings::bool('show_notes_row')) {
                unset($fields['notes']);
            }
            foreach (Plans::classrooms() as $classroom) {
                foreach (Plans::classSheet($weekId, (int) $classroom['id'], false) as $planRow) {
                    foreach ($fields as $fieldKey => $fieldLabel) {
                        $line = [(int) $week['week_no'], $classroom['grade_name'], $classroom['name'], $planRow['subject_name'], $fieldLabel];
                        foreach (array_keys(WEEK_DAYS) as $dayIndex) {
                            $line[] = (string) ($planRow['days'][$dayIndex][$fieldKey] ?? '');
                        }
                        $rows[] = $line;
                    }
                }
            }
            break;

        case 'template_teachers':
            $title  = 'نموذج استيراد المعلمين';
            $rows[] = ['الاسم', 'اسم المستخدم', 'كلمة المرور', 'الجوال', 'البريد الإلكتروني', 'رقم الهوية', 'المسمى الوظيفي'];
            $rows[] = ['محمد بن أحمد الأحمدي', 'mohammed', '123456', '05xxxxxxxx', 'mail@example.com', '10xxxxxxxx', 'معلم'];
            break;

        case 'template_assignments':
            $title  = 'نموذج استيراد الإسناد';
            $rows[] = ['الاسم', 'اسم المستخدم', 'المادة', 'الصف', 'الفصل'];
            $rows[] = ['محمد بن أحمد الأحمدي', 'mohammed', 'الرياضيات', 'الخامس الابتدائي', 'أ'];
            break;

        case 'template_plans':
            $title  = 'نموذج استيراد الخطط';
            $rows[] = ['الأسبوع', 'الصف', 'الفصل', 'المادة', 'البند', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس'];
            $rows[] = ['1', 'الخامس الابتدائي', 'أ', 'الرياضيات', 'موضوع الدرس', 'الكسور العشرية', 'جمع الكسور', 'طرح الكسور', 'تمارين', 'اختبار قصير'];
            $rows[] = ['1', 'الخامس الابتدائي', 'أ', 'الرياضيات', 'الهدف التعليمي', 'أن يتعرف على الكسور', '', '', '', ''];
            $rows[] = ['1', 'الخامس الابتدائي', 'أ', 'الرياضيات', 'الواجبات والمهام الأدائية', 'تمارين ص 20', '', '', '', ''];
            break;

        default:
            redirect('admin/transfer.php');
    }

    if ($format === 'csv') {
        Csv::download(safe_filename($title) . '.csv', $rows);
    }

    $xlsx = new Xlsx($title);
    $widths = array_fill(0, max(1, count($rows[0] ?? [1])), 22);
    $xlsx->setColumnWidths($widths);
    $xlsx->addRow(array_shift($rows), Xlsx::STYLE_HEADER, 26);
    foreach ($rows as $line) {
        $xlsx->addRow($line, Xlsx::STYLE_BODY, 22);
    }
    $xlsx->download(safe_filename($title) . '.xlsx');
}

/* ============================ الاستيراد ============================ */
$results = null;
$kind    = '';

if (is_post()) {
    csrf_verify();
    $kind = (string) input('kind', '');

    if (empty($_FILES['file']['name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        flash('error', 'يرجى اختيار ملف CSV للاستيراد.');
        redirect('admin/transfer.php');
    }
    if ($_FILES['file']['size'] > 5 * 1024 * 1024) {
        flash('error', 'حجم الملف يتجاوز 5 ميجابايت.');
        redirect('admin/transfer.php');
    }

    $rows = Csv::read($_FILES['file']['tmp_name']);
    if (count($rows) < 2) {
        flash('error', 'الملف فارغ أو لا يحتوي على بيانات بعد صف الرؤوس.');
        redirect('admin/transfer.php');
    }

    $results = match ($kind) {
        'teachers'    => Importer::teachers($rows),
        'assignments' => Importer::assignments($rows, input('create_missing') === '1'),
        'plans'       => Importer::plans($rows, input_int('week') ?: null, input('approve') === '1'),
        default       => null,
    };

    if ($results === null) {
        flash('error', 'نوع الاستيراد غير معروف.');
        redirect('admin/transfer.php');
    }
    $_SESSION['_import_result'] = ['kind' => $kind, 'data' => $results];
    redirect('admin/transfer.php#result');
}

$saved = $_SESSION['_import_result'] ?? null;
unset($_SESSION['_import_result']);

$weeks = Plans::weeks();
$currentWeekId = (int) (Plans::currentWeek()['id'] ?? 0);

page_start('الاستيراد والتصدير', 'admin', 'transfer');
?>

<?php if ($saved): ?>
<div class="card" id="result">
  <div class="card-head"><h2>نتيجة الاستيراد</h2></div>
  <div class="card-body">
    <?php $data = $saved['data']; ?>
    <div class="stats">
      <?php foreach (['created' => 'سجلات جديدة', 'updated' => 'سجلات محدّثة', 'plans' => 'خطط متأثرة', 'rows' => 'أسطر مقروءة'] as $key => $label): ?>
        <?php if (isset($data[$key])): ?>
          <div class="stat teal"><div class="num"><?= (int) $data[$key] ?></div><div class="lbl"><?= h($label) ?></div></div>
        <?php endif; ?>
      <?php endforeach; ?>
      <div class="stat red"><div class="num"><?= count($data['errors'] ?? []) ?></div><div class="lbl">أخطاء</div></div>
    </div>

    <?php if (!empty($data['passwords'])): ?>
      <h3>كلمات المرور للحسابات الجديدة (احفظها الآن)</h3>
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>الاسم</th><th>اسم المستخدم</th><th>كلمة المرور</th></tr></thead>
          <tbody>
          <?php foreach ($data['passwords'] as $item): ?>
            <tr><td><?= h($item['name']) ?></td><td><?= h($item['username']) ?></td><td><code><?= h($item['password']) ?></code></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if (!empty($data['errors'])): ?>
      <h3 class="mt16">الأخطاء</h3>
      <?php foreach (array_slice($data['errors'], 0, 30) as $error): ?>
        <div class="alert alert-error" style="margin-bottom:6px"><?= h($error) ?></div>
      <?php endforeach; ?>
      <?php if (count($data['errors']) > 30): ?>
        <p class="help">و <?= count($data['errors']) - 30 ?> خطأ آخر…</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head"><h2>تصدير البيانات</h2></div>
  <div class="card-body">
    <div class="grid grid-3">
      <div>
        <h3>المعلمون</h3>
        <div class="flex">
          <a class="btn btn-sm btn-primary" href="?export=teachers&format=xlsx">Excel</a>
          <a class="btn btn-sm btn-outline" href="?export=teachers&format=csv">CSV</a>
        </div>
      </div>
      <div>
        <h3>الإسناد</h3>
        <div class="flex">
          <a class="btn btn-sm btn-primary" href="?export=assignments&format=xlsx">Excel</a>
          <a class="btn btn-sm btn-outline" href="?export=assignments&format=csv">CSV</a>
        </div>
      </div>
      <div>
        <h3>خطط أسبوع</h3>
        <form method="get" class="flex">
          <input type="hidden" name="export" value="plans">
          <select name="week" style="min-width:150px">
            <?php foreach ($weeks as $week): ?>
              <option value="<?= (int) $week['id'] ?>" <?= (int) $week['id'] === $currentWeekId ? 'selected' : '' ?>>
                <?= h($week['title'] ?: ('الأسبوع ' . $week['week_no'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-primary" name="format" value="xlsx">Excel</button>
          <button class="btn btn-sm btn-outline" name="format" value="csv">CSV</button>
        </form>
      </div>
    </div>
    <hr style="margin:18px 0;border:none;border-top:1px solid var(--line)">
    <h3>نماذج الاستيراد الجاهزة</h3>
    <div class="flex">
      <a class="btn btn-sm btn-outline" href="?export=template_teachers&format=csv">نموذج المعلمين (CSV)</a>
      <a class="btn btn-sm btn-outline" href="?export=template_assignments&format=csv">نموذج الإسناد (CSV)</a>
      <a class="btn btn-sm btn-outline" href="?export=template_plans&format=csv">نموذج الخطط (CSV)</a>
    </div>
    <p class="help mt16">افتح النموذج ببرنامج Excel، عبّئ البيانات، ثم احفظه بصيغة CSV UTF-8 وارفعه في القسم التالي.</p>
  </div>
</div>

<div class="grid grid-3">
  <div class="card" id="import-teachers">
    <div class="card-head"><h3>استيراد المعلمين</h3></div>
    <div class="card-body">
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="kind" value="teachers">
        <div class="field"><label>ملف CSV</label><input type="file" name="file" accept=".csv,text/csv" required></div>
        <p class="help">الأعمدة: الاسم، اسم المستخدم، كلمة المرور، الجوال، البريد الإلكتروني، رقم الهوية، المسمى الوظيفي.
          الحسابات الموجودة تُحدَّث بدل تكرارها.</p>
        <button class="btn btn-primary" type="submit">استيراد</button>
      </form>
    </div>
  </div>

  <div class="card" id="import-assignments">
    <div class="card-head"><h3>استيراد الإسناد</h3></div>
    <div class="card-body">
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="kind" value="assignments">
        <div class="field"><label>ملف CSV</label><input type="file" name="file" accept=".csv,text/csv" required></div>
        <label class="flex"><input type="checkbox" name="create_missing" value="1" style="width:auto" checked>
          إنشاء المواد والصفوف والفصول غير الموجودة</label>
        <p class="help">الأعمدة: الاسم أو اسم المستخدم، المادة، الصف، الفصل.</p>
        <button class="btn btn-primary" type="submit">استيراد</button>
      </form>
    </div>
  </div>

  <div class="card" id="import-plans">
    <div class="card-head"><h3>استيراد الخطط</h3></div>
    <div class="card-body">
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="kind" value="plans">
        <div class="field"><label>ملف CSV</label><input type="file" name="file" accept=".csv,text/csv" required></div>
        <div class="field">
          <label>الأسبوع الافتراضي (إن لم يُذكر في الملف)</label>
          <select name="week">
            <?php foreach ($weeks as $week): ?>
              <option value="<?= (int) $week['id'] ?>" <?= (int) $week['id'] === $currentWeekId ? 'selected' : '' ?>>
                <?= h($week['title'] ?: ('الأسبوع ' . $week['week_no'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <label class="flex"><input type="checkbox" name="approve" value="1" style="width:auto"> اعتماد الخطط المستوردة مباشرة</label>
        <p class="help">الأعمدة: الأسبوع، الصف، الفصل، المادة، البند، ثم أيام الأسبوع.</p>
        <button class="btn btn-primary" type="submit">استيراد</button>
      </form>
    </div>
  </div>
</div>

<?php page_end('admin'); ?>
