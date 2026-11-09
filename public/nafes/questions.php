<?php
declare(strict_types=1);
define('APP_AREA', 'nafes');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';
require APP_PATH . '/views/nafes.php';

$user = Auth::requireStaff();

$filters = [
    'subject'    => input_int('subject'),
    'grade'      => input_int('grade'),
    'skill'      => input_int('skill'),
    'difficulty' => input_int('difficulty'),
    'type'       => (string) input('type', ''),
    'passage'    => input_int('passage'),
    'q'          => (string) input('q', ''),
    'mine'       => input('mine') === '1' ? (int) $user['id'] : 0,
    'active'     => input('active', ''),
];
$query = array_filter([
    'subject' => $filters['subject'], 'grade' => $filters['grade'], 'skill' => $filters['skill'],
    'difficulty' => $filters['difficulty'], 'type' => $filters['type'], 'passage' => $filters['passage'],
    'q' => $filters['q'], 'mine' => $filters['mine'] ? '1' : '', 'active' => $filters['active'],
], static fn ($v) => $v !== '' && $v !== 0);
$back = 'nafes/questions.php' . ($query ? '?' . http_build_query($query) : '');

/* ---------------- التصدير والنموذج ---------------- */
if (input('export') === 'csv') {
    Csv::download('بنك-أسئلة-نافس.csv', QuestionBank::exportRows($filters));
}
if (input('template') === '1') {
    Csv::download('نموذج-أسئلة-نافس.csv', [
        QuestionBank::CSV_HEADERS,
        ['الرياضيات', 'الثالث الابتدائي', 'ر3-1', 'يجمع الأعداد ضمن 1000', 'اختيار من متعدد', 'سهل', 'ناتج 245 + 132 = ؟', '377', '367', '387', '477', '1', 'نجمع الآحاد ثم العشرات ثم المئات'],
        ['العلوم', 'الثالث الابتدائي', '', 'يميز خصائص الكائنات الحية', 'صح أو خطأ', 'سهل', 'النبات كائن حي ينمو ويتكاثر.', '', '', '', '', 'صح', ''],
    ]);
}

/* ---------------- العمليات ---------------- */
if (is_post()) {
    csrf_verify();
    $action = (string) input('action', '');

    if ($action === 'import') {
        if (empty($_FILES['file']['name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            flash('error', 'يرجى اختيار ملف CSV.');
        } else {
            $result = QuestionBank::import(Csv::read($_FILES['file']['tmp_name']), (int) $user['id'], isset($_POST['create_skills']));
            flash($result['errors'] ? 'warning' : 'success', 'تم استيراد ' . $result['created'] . ' سؤال'
                . ($result['errors'] ? ' — ' . count($result['errors']) . ' سطر لم يُستورد: ' . implode(' | ', array_slice($result['errors'], 0, 5)) : '.'));
        }
        redirect($back);
    }

    $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
    if (input_int('id')) {
        $ids = [input_int('id')];
    }

    // المعلم يعدّل أسئلته فقط
    if ($ids && $user['role'] !== 'admin') {
        $in  = implode(',', $ids);
        $ids = array_map('intval', array_column(
            Database::all("SELECT id FROM nafes_questions WHERE id IN ($in) AND created_by = ?", [$user['id']]), 'id'
        ));
    }

    if ($ids && in_array($action, ['activate', 'deactivate'], true)) {
        $in = implode(',', $ids);
        Database::run("UPDATE nafes_questions SET is_active = ? WHERE id IN ($in)", [$action === 'activate' ? 1 : 0]);
        flash('success', 'تم تحديث ' . count($ids) . ' سؤال.');
    }

    if ($ids && $action === 'delete') {
        $deleted = $disabled = 0;
        foreach ($ids as $qid) {
            QuestionBank::delete($qid) === 'deleted' ? $deleted++ : $disabled++;
        }
        Activity::log('question_delete', 'nafes_questions', null, 'deleted=' . $deleted . ' disabled=' . $disabled);
        flash($disabled ? 'warning' : 'success', 'حُذف ' . $deleted . ' سؤال'
            . ($disabled ? '، وعُطّل ' . $disabled . ' سؤال لأنه مستخدم في اختبارات أو أجاب عنه طلاب.' : '.'));
    }

    if (!$ids && $action !== '') {
        flash('warning', 'لم يتم تحديد أسئلة يمكنك تعديلها.');
    }
    redirect($back);
}

$page     = max(1, input_int('page', 1));
$result   = QuestionBank::search($filters, $page);
$subjects = Nafes::subjects();
$grades   = Nafes::grades();
$skills   = QuestionBank::allSkills();

$totals = Database::all(
    'SELECT s.name AS subject_name, g.name AS grade_name, COUNT(*) AS n
       FROM nafes_questions q JOIN subjects s ON s.id = q.subject_id JOIN grades g ON g.id = q.grade_id
      WHERE q.is_active = 1
   GROUP BY q.subject_id, q.grade_id ORDER BY s.sort_order, g.sort_order'
);

page_start('بنك الأسئلة', nafes_area(), 'nafes_bank');
?>

<?php if ($totals): ?>
<div class="stats">
  <?php foreach (array_slice($totals, 0, 8) as $item): ?>
    <div class="stat teal"><div class="num"><?= (int) $item['n'] ?></div><div class="lbl"><?= h($item['subject_name'] . ' — ' . $item['grade_name']) ?></div></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="toolbar">
  <a class="btn btn-primary" href="<?= h(url('nafes/question.php?subject=' . $filters['subject'] . '&grade=' . $filters['grade'] . '&skill=' . $filters['skill'])) ?>">+ سؤال جديد</a>
  <a class="btn btn-outline" href="<?= h(url('nafes/passages.php')) ?>">نصوص القراءة</a>
  <span class="spacer"></span>
  <a class="btn btn-outline" href="<?= h(url($back . (str_contains($back, '?') ? '&' : '?') . 'export=csv')) ?>">تصدير CSV</a>
  <button class="btn btn-outline" type="button" onclick="document.getElementById('import-box').classList.toggle('hidden')">استيراد من ملف</button>
</div>

<div class="card hidden" id="import-box">
  <div class="card-head"><h2>استيراد الأسئلة</h2><a class="btn btn-sm btn-outline" href="<?= h(url('nafes/questions.php?template=1')) ?>">تنزيل النموذج</a></div>
  <div class="card-body">
    <p class="help">ملف CSV بالأعمدة: <?= h(implode('، ', QuestionBank::CSV_HEADERS)) ?>.
      عمود «الإجابة الصحيحة» رقم الخيار (1–4) أو «صح/خطأ». يمكن تصدير الأسئلة وتعديلها في Excel ثم إعادة استيرادها كأسئلة جديدة.</p>
    <form method="post" enctype="multipart/form-data" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="import">
      <div class="field"><input type="file" name="file" accept=".csv,text/csv" required></div>
      <label class="flex"><input type="checkbox" name="create_skills" style="width:auto" checked> إنشاء المهارات غير الموجودة</label>
      <button class="btn btn-primary">استيراد</button>
    </form>
  </div>
</div>

<div class="filters">
  <form method="get">
    <div class="field"><label>المادة</label><select name="subject" id="q-subject" data-auto-submit><?= options_html($subjects, $filters['subject'], 'كل المواد') ?></select></div>
    <div class="field"><label>الصف</label><select name="grade" id="q-grade" data-auto-submit><?= options_html($grades, $filters['grade'], 'كل الصفوف') ?></select></div>
    <div class="field"><label>المهارة</label><select name="skill" id="q-skill" data-auto-submit><?= skill_options_html($skills, $filters['skill'], 'كل المهارات') ?></select></div>
    <div class="field" style="min-width:110px"><label>الصعوبة</label>
      <select name="difficulty" data-auto-submit>
        <option value="0">الكل</option>
        <?php foreach (QuestionBank::DIFFICULTIES as $value => $label): ?>
          <option value="<?= $value ?>" <?= $filters['difficulty'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="min-width:110px"><label>الحالة</label>
      <select name="active" data-auto-submit>
        <option value="">الكل</option>
        <option value="1" <?= $filters['active'] === '1' ? 'selected' : '' ?>>متاح</option>
        <option value="0" <?= $filters['active'] === '0' ? 'selected' : '' ?>>معطّل</option>
      </select>
    </div>
    <div class="field"><label>بحث في النص</label><input type="text" name="q" value="<?= h($filters['q']) ?>"></div>
    <label class="flex"><input type="checkbox" name="mine" value="1" style="width:auto" <?= $filters['mine'] ? 'checked' : '' ?> data-auto-submit> أسئلتي فقط</label>
    <?php if ($filters['passage']): ?><input type="hidden" name="passage" value="<?= $filters['passage'] ?>"><?php endif; ?>
    <button class="btn btn-outline">بحث</button>
  </form>
</div>

<form method="post" class="card">
  <?= csrf_field() ?>
  <div class="card-head">
    <h2>الأسئلة (<?= $result['total'] ?>)</h2>
    <div class="flex">
      <button class="btn btn-sm btn-outline" name="action" value="activate">تفعيل المحدد</button>
      <button class="btn btn-sm btn-outline" name="action" value="deactivate">تعطيل المحدد</button>
      <button class="btn btn-sm btn-danger" name="action" value="delete" data-confirm="حذف الأسئلة المحددة؟">حذف المحدد</button>
    </div>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th><input type="checkbox" data-check-all="ids[]"></th><th>#</th><th>السؤال</th><th>المادة / الصف</th><th>المهارة</th><th>النوع</th><th>الصعوبة</th><th>الاستخدام</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($result['rows'] as $row): ?>
          <tr>
            <td><input type="checkbox" name="ids[]" value="<?= (int) $row['id'] ?>"></td>
            <td class="text-muted"><?= (int) $row['id'] ?></td>
            <td style="min-width:260px">
              <?= h(str_short($row['stem'], 90)) ?>
              <?php if ($row['image']): ?><span class="badge badge-gray">صورة</span><?php endif; ?>
              <?php if ($row['passage_title']): ?><br><small>النص: <?= h($row['passage_title']) ?></small><?php endif; ?>
              <br><small><?= h($row['author_name'] ?? '') ?></small>
            </td>
            <td class="nowrap"><?= h($row['subject_name']) ?><br><small><?= h($row['grade_name']) ?></small></td>
            <td style="min-width:180px"><small><?= h($row['skill_name'] ? str_short(($row['skill_code'] ? $row['skill_code'] . ' ' : '') . $row['skill_name'], 50) : '—') ?></small></td>
            <td class="nowrap"><small><?= h(QuestionBank::TYPES[$row['type']]) ?></small></td>
            <td><?= difficulty_badge((int) $row['difficulty']) ?></td>
            <td><?= (int) $row['used_count'] ?> اختبار</td>
            <td><?= (int) $row['is_active'] === 1 ? '<span class="badge badge-green">متاح</span>' : '<span class="badge badge-gray">معطّل</span>' ?></td>
            <td class="actions">
              <a class="btn btn-sm btn-outline" href="<?= h(url('nafes/question.php?id=' . (int) $row['id'])) ?>"><?= QuestionBank::canEdit($user, $row) ? 'تعديل' : 'عرض' ?></a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$result['rows']): ?>
          <tr><td colspan="10" class="empty">لا توجد أسئلة مطابقة. ابدأ بإضافة سؤال أو استيراد ملف.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</form>
<?= pagination_html($result['page'], $result['pages'], $query) ?>

<?php page_end(nafes_area()); ?>
