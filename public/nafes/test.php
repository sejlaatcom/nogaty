<?php
declare(strict_types=1);
define('APP_AREA', 'nafes');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';
require APP_PATH . '/views/nafes.php';

$user = Auth::requireStaff();

$id   = input_int('id');
$test = $id ? Exams::find($id) : null;
if ($id && !$test) {
    http_response_code(404);
    exit('الاختبار غير موجود.');
}
if ($test && !Exams::canManage($user, $test)) {
    http_response_code(403);
    exit('هذا الاختبار أنشأه معلم آخر.');
}

/** تحويل قيمة datetime-local إلى صيغة قاعدة البيانات */
function dt_input(string $key): ?string
{
    $value = (string) input($key, '');
    if ($value === '') {
        return null;
    }
    $time = strtotime($value);
    return $time ? date('Y-m-d H:i:s', $time) : null;
}

$errors = [];

if (is_post()) {
    csrf_verify();
    $action = (string) input('action', '');

    if ($action === 'save') {
        $data = [
            'title'             => (string) input('title', ''),
            'subject_id'        => input_int('subject_id'),
            'grade_id'          => input_int('grade_id'),
            'kind'              => (string) input('kind', 'training'),
            'instructions'      => (string) ($_POST['instructions'] ?? ''),
            'duration_min'      => input_int('duration_min'),
            'starts_at'         => dt_input('starts_at'),
            'ends_at'           => dt_input('ends_at'),
            'max_attempts'      => input_int('max_attempts', 1),
            'shuffle_questions' => isset($_POST['shuffle_questions']),
            'shuffle_options'   => isset($_POST['shuffle_options']),
            'show_review'       => isset($_POST['show_review']),
        ];
        [$savedId, $errors] = Exams::save($id ?: null, $data, (array) ($_POST['classrooms'] ?? []), $user);
        if (!$errors) {
            Activity::log($id ? 'test_update' : 'test_create', 'nafes_tests', $savedId, $data['title']);
            flash('success', $id ? 'تم حفظ إعدادات الاختبار.' : 'تم إنشاء الاختبار — أضف الأسئلة الآن.');
            redirect('nafes/test.php?id=' . $savedId . ($id ? '' : '#questions'));
        }
        $test = array_merge($test ?? [], $data, ['classroom_ids' => array_map('intval', (array) ($_POST['classrooms'] ?? []))]);
    }

    if ($id && $action !== 'save') {
        $back = 'nafes/test.php?id=' . $id;

        if (in_array($action, ['publish', 'draft', 'close'], true)) {
            $status = ['publish' => 'published', 'draft' => 'draft', 'close' => 'closed'][$action];
            $problems = Exams::setStatus($id, $status);
            if ($problems) {
                flash('error', implode(' ', $problems));
            } else {
                Activity::log('test_' . $action, 'nafes_tests', $id);
                flash('success', match ($status) {
                    'published' => 'تم نشر الاختبار وأصبح متاحاً لطلاب الفصول المحددة.',
                    'closed'    => 'تم إغلاق الاختبار.',
                    default     => 'أُعيد الاختبار إلى المسودة وأُخفي عن الطلاب.',
                });
            }
        }

        if ($action === 'duplicate') {
            $newId = Exams::duplicate($id, (int) $user['id']);
            flash('success', 'تم نسخ الاختبار. عدّل الإعدادات والفصول ثم انشره.');
            redirect('nafes/test.php?id=' . $newId);
        }

        if ($action === 'delete') {
            $result = Exams::delete($id);
            Activity::log('test_delete', 'nafes_tests', $id);
            flash($result === 'deleted' ? 'success' : 'warning',
                $result === 'deleted' ? 'تم حذف الاختبار.' : 'للاختبار محاولات طلاب، تم إغلاقه بدلاً من حذفه للحفاظ على النتائج.');
            redirect('nafes/tests.php');
        }

        if ($action === 'add_questions') {
            $added = Exams::addQuestions($id, (array) ($_POST['qids'] ?? []));
            flash('success', 'تمت إضافة ' . $added . ' سؤال.');
            $back .= '#questions';
        }

        if ($action === 'remove_questions') {
            Exams::removeQuestions($id, (array) ($_POST['remove'] ?? []));
            flash('success', 'تمت إزالة الأسئلة المحددة من الاختبار.');
            $back .= '#questions';
        }

        if ($action === 'move') {
            Exams::moveQuestion($id, input_int('qid'), input_int('dir'));
            $back .= '#questions';
        }

        if ($action === 'generate') {
            $perSkill = array_filter(array_map('intval', (array) ($_POST['per_skill'] ?? [])));
            $added = Exams::generate($id, $perSkill, input_int('random_count'), input_int('gen_difficulty'));
            flash($added ? 'success' : 'warning', $added ? 'تم توليد ' . $added . ' سؤال عشوائياً من البنك.' : 'لم يتم العثور على أسئلة إضافية مطابقة في البنك.');
            $back .= '#questions';
        }

        redirect($back);
    }
}

$subjects   = Nafes::subjects();
$grades     = Nafes::grades();
$classrooms = Nafes::staffClassrooms($user);

$t = $test ?? [
    'title' => '', 'subject_id' => input_int('subject'), 'grade_id' => input_int('grade'), 'kind' => 'training',
    'instructions' => 'اقرأ كل سؤال بعناية ثم اختر الإجابة الصحيحة. يمكنك التنقل بين الأسئلة وتمييز ما تريد مراجعته قبل التسليم.',
    'duration_min' => 30, 'starts_at' => null, 'ends_at' => null, 'max_attempts' => 1,
    'shuffle_questions' => 1, 'shuffle_options' => 1, 'show_review' => 1, 'classroom_ids' => [], 'status' => 'draft',
];

$questions = $id ? Exams::questions($id) : [];
$attempts  = $id ? (int) Database::value('SELECT COUNT(*) FROM nafes_attempts WHERE test_id = ?', [$id], 0) : 0;

// أسئلة البنك المتاحة للإضافة
$bank = null;
$domains = [];
if ($id) {
    $bankFilters = [
        'subject' => (int) $t['subject_id'], 'grade' => (int) $t['grade_id'], 'active' => 1,
        'skill' => input_int('bskill'), 'difficulty' => input_int('bdiff'), 'q' => (string) input('bq', ''),
        'exclude' => array_map('intval', array_column($questions, 'id')) ?: [0],
    ];
    $bank    = QuestionBank::search($bankFilters, max(1, input_int('bpage', 1)), 15);
    $domains = QuestionBank::domainsWithSkills((int) $t['subject_id'], (int) $t['grade_id']);

    // عدد الأسئلة المتاحة لكل مهارة (غير المضافة)
    $available = [];
    foreach (Database::all(
        'SELECT skill_id, COUNT(*) AS n FROM nafes_questions
          WHERE subject_id = ? AND grade_id = ? AND is_active = 1 AND skill_id IS NOT NULL
            AND id NOT IN (SELECT question_id FROM nafes_test_questions WHERE test_id = ?)
       GROUP BY skill_id',
        [$t['subject_id'], $t['grade_id'], $id]
    ) as $row) {
        $available[(int) $row['skill_id']] = (int) $row['n'];
    }

    // توزيع أسئلة الاختبار على المهارات والصعوبة
    $bySkill = [];
    $byDifficulty = [1 => 0, 2 => 0, 3 => 0];
    foreach ($questions as $question) {
        $label = $question['skill_name'] ? QuestionBank::skillLabel(['code' => $question['skill_code'], 'name' => $question['skill_name']]) : 'بدون مهارة';
        $bySkill[$label] = ($bySkill[$label] ?? 0) + 1;
        $byDifficulty[(int) $question['difficulty']]++;
    }
}

$skillsAll = $id ? QuestionBank::allSkills() : [];

page_start($id ? 'إدارة الاختبار' : 'اختبار جديد', nafes_area(), 'nafes_tests');
?>

<?php foreach ($errors as $error): ?>
  <div class="alert alert-error"><?= h($error) ?></div>
<?php endforeach; ?>

<div class="toolbar">
  <a class="btn btn-outline" href="<?= h(url('nafes/tests.php')) ?>">← الاختبارات</a>
  <?php if ($id): ?>
    <span class="badge <?= match ($t['status']) { 'published' => 'badge-green', 'closed' => 'badge-gray', default => 'badge-amber' } ?>"><?= h(Exams::STATUSES[$t['status']]) ?></span>
    <span class="spacer"></span>
    <form method="post" class="flex">
      <?= csrf_field() ?>
      <?php if ($t['status'] !== 'published'): ?>
        <button class="btn btn-success" name="action" value="publish" data-confirm="نشر الاختبار للطلاب؟">نشر للطلاب</button>
      <?php else: ?>
        <button class="btn btn-outline" name="action" value="close" data-confirm="إغلاق الاختبار؟ لن يتمكن الطلاب من بدئه.">إغلاق</button>
        <button class="btn btn-outline" name="action" value="draft" data-confirm="إخفاء الاختبار عن الطلاب؟">إعادة لمسودة</button>
      <?php endif; ?>
      <a class="btn btn-outline" target="_blank" href="<?= h(url('nafes/test_print.php?id=' . $id)) ?>">نسخة ورقية</a>
      <button class="btn btn-outline" name="action" value="duplicate">نسخ</button>
      <button class="btn btn-danger" name="action" value="delete" data-confirm="حذف الاختبار؟">حذف</button>
    </form>
  <?php endif; ?>
</div>

<?php if ($attempts > 0): ?>
  <div class="alert alert-info">بدأ <?= $attempts ?> محاولة في هذا الاختبار. تعديل الأسئلة يؤثر في المحاولات الجديدة فقط.</div>
<?php endif; ?>

<form method="post" class="card">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">
  <div class="card-head"><h2>إعدادات الاختبار</h2></div>
  <div class="card-body">
    <div class="grid grid-4">
      <div class="field" style="grid-column:span 2"><label>عنوان الاختبار *</label><input type="text" name="title" value="<?= h($t['title']) ?>" placeholder="مثال: اختبار تشخيصي — الرياضيات" required></div>
      <div class="field"><label>المادة *</label><select name="subject_id" required><?= options_html($subjects, (int) $t['subject_id']) ?></select></div>
      <div class="field"><label>الصف *</label><select name="grade_id" id="test-grade" required><?= options_html($grades, (int) $t['grade_id']) ?></select></div>
      <div class="field"><label>نوع الاختبار</label>
        <select name="kind">
          <?php foreach (Exams::KINDS as $key => $label): ?>
            <option value="<?= $key ?>" <?= $t['kind'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>المدة بالدقائق <small>(0 = مفتوح)</small></label><input type="number" min="0" max="600" name="duration_min" value="<?= (int) $t['duration_min'] ?>"></div>
      <div class="field"><label>يبدأ في <small>(اختياري)</small></label><input type="datetime-local" name="starts_at" value="<?= $t['starts_at'] ? h(date('Y-m-d\TH:i', strtotime($t['starts_at']))) : '' ?>"></div>
      <div class="field"><label>ينتهي في <small>(اختياري)</small></label><input type="datetime-local" name="ends_at" value="<?= $t['ends_at'] ? h(date('Y-m-d\TH:i', strtotime($t['ends_at']))) : '' ?>"></div>
      <div class="field"><label>عدد المحاولات <small>(0 = غير محدود)</small></label><input type="number" min="0" max="20" name="max_attempts" value="<?= (int) $t['max_attempts'] ?>"></div>
    </div>

    <div class="field">
      <label>الفصول المستهدفة</label>
      <div class="flex" id="test-classes">
        <?php foreach ($classrooms as $classroom): ?>
          <label class="chip-check" data-grade="<?= (int) $classroom['grade_id'] ?>">
            <input type="checkbox" name="classrooms[]" value="<?= (int) $classroom['id'] ?>" <?= in_array((int) $classroom['id'], $t['classroom_ids'], true) ? 'checked' : '' ?>>
            <?= h(Plans::classLabel($classroom)) ?>
          </label>
        <?php endforeach; ?>
        <?php if (!$classrooms): ?><span class="text-muted">لا توجد فصول مسندة لك.</span><?php endif; ?>
      </div>
      <small class="help">تظهر فصول الصف المختار فقط.</small>
    </div>

    <div class="field"><label>تعليمات للطالب</label><textarea name="instructions" rows="2" data-grow><?= h((string) $t['instructions']) ?></textarea></div>

    <div class="flex mb16">
      <label class="flex"><input type="checkbox" name="shuffle_questions" style="width:auto" <?= (int) $t['shuffle_questions'] === 1 ? 'checked' : '' ?>> خلط ترتيب الأسئلة</label>
      <label class="flex"><input type="checkbox" name="shuffle_options" style="width:auto" <?= (int) $t['shuffle_options'] === 1 ? 'checked' : '' ?>> خلط ترتيب الخيارات</label>
      <label class="flex"><input type="checkbox" name="show_review" style="width:auto" <?= (int) $t['show_review'] === 1 ? 'checked' : '' ?>> إظهار الإجابات الصحيحة للطالب بعد التسليم</label>
    </div>
    <button class="btn btn-primary" type="submit"><?= $id ? 'حفظ الإعدادات' : 'إنشاء الاختبار والانتقال للأسئلة' ?></button>
  </div>
</form>

<?php if ($id): ?>
<div class="card" id="questions">
  <div class="card-head">
    <h2>أسئلة الاختبار (<?= count($questions) ?>)</h2>
    <div class="flex">
      <?php foreach ($byDifficulty as $level => $n): ?>
        <?php if ($n): ?><?= difficulty_badge($level) ?> <small><?= $n ?></small><?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
  <?php if ($bySkill): ?>
    <div class="card-body" style="padding-bottom:0">
      <div class="flex">
        <?php foreach ($bySkill as $label => $n): ?>
          <span class="badge badge-gray"><?= h(str_short($label, 45)) ?>: <?= $n ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
  <form method="post" class="card-body tight">
    <?= csrf_field() ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th><input type="checkbox" data-check-all="remove[]"></th><th>#</th><th>السؤال</th><th>المهارة</th><th>الصعوبة</th><th>الترتيب</th></tr></thead>
        <tbody>
        <?php foreach ($questions as $i => $question): ?>
          <tr>
            <td><input type="checkbox" name="remove[]" value="<?= (int) $question['id'] ?>"></td>
            <td><?= $i + 1 ?></td>
            <td><a href="<?= h(url('nafes/question.php?id=' . (int) $question['id'])) ?>" target="_blank"><?= h(str_short($question['stem'], 100)) ?></a>
              <?php if ($question['passage_title']): ?><br><small>النص: <?= h($question['passage_title']) ?></small><?php endif; ?>
              <?php if ((int) $question['is_active'] !== 1): ?> <span class="badge badge-red">معطّل في البنك</span><?php endif; ?></td>
            <td><small><?= h($question['skill_name'] ? str_short($question['skill_name'], 45) : '—') ?></small></td>
            <td><?= difficulty_badge((int) $question['difficulty']) ?></td>
            <td class="nowrap">
              <button class="btn btn-sm btn-outline" name="action" value="move" formaction="?id=<?= $id ?>&qid=<?= (int) $question['id'] ?>&dir=-1" <?= $i === 0 ? 'disabled' : '' ?>>▲</button>
              <button class="btn btn-sm btn-outline" name="action" value="move" formaction="?id=<?= $id ?>&qid=<?= (int) $question['id'] ?>&dir=1" <?= $i === count($questions) - 1 ? 'disabled' : '' ?>>▼</button>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$questions): ?>
          <tr><td colspan="6" class="empty">لم تُضف أسئلة بعد — استخدم التوليد التلقائي أو اختر من البنك أدناه.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($questions): ?>
      <div class="card-body"><button class="btn btn-sm btn-danger" name="action" value="remove_questions" data-confirm="إزالة الأسئلة المحددة من الاختبار؟">إزالة المحدد من الاختبار</button></div>
    <?php endif; ?>
  </form>
</div>

<div class="grid grid-2">
  <form method="post" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="generate">
    <div class="card-head"><h2>توليد تلقائي حسب المهارات</h2></div>
    <div class="card-body">
      <p class="help">حدد عدد الأسئلة المطلوب من كل مهارة، فيختارها النظام عشوائياً من البنك (المتاح بين قوسين).</p>
      <?php foreach ($domains as $domain): ?>
        <h4 class="mb0 mt16"><?= h($domain['name']) ?></h4>
        <?php foreach ($domain['skills'] as $skill): ?>
          <div class="gen-row">
            <span><?= h(QuestionBank::skillLabel($skill)) ?> <small>(<?= $available[(int) $skill['id']] ?? 0 ?>)</small></span>
            <input type="number" min="0" max="<?= $available[(int) $skill['id']] ?? 0 ?>" name="per_skill[<?= (int) $skill['id'] ?>]" value="0" <?= empty($available[(int) $skill['id']]) ? 'disabled' : '' ?>>
          </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <div class="grid grid-2 mt16">
        <div class="field"><label>أو عدد عشوائي من كل المهارات</label><input type="number" min="0" max="100" name="random_count" value="0"></div>
        <div class="field"><label>الصعوبة</label>
          <select name="gen_difficulty">
            <option value="0">كل المستويات</option>
            <?php foreach (QuestionBank::DIFFICULTIES as $value => $label): ?><option value="<?= $value ?>"><?= h($label) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <button class="btn btn-primary">توليد الأسئلة</button>
    </div>
  </form>

  <div class="card">
    <div class="card-head"><h2>اختيار من البنك (<?= $bank['total'] ?>)</h2>
      <a class="btn btn-sm btn-outline" target="_blank" href="<?= h(url('nafes/question.php?subject=' . (int) $t['subject_id'] . '&grade=' . (int) $t['grade_id'])) ?>">+ سؤال جديد للبنك</a></div>
    <div class="card-body">
      <form method="get" class="inline-form mb16" action="#questions">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="field"><select name="bskill" data-auto-submit><?= skill_options_html(array_filter($skillsAll, static fn ($s) => (int) $s['subject_id'] === (int) $t['subject_id'] && in_array((int) $s['grade_id'], [0, (int) $t['grade_id']], true)), input_int('bskill'), 'كل المهارات') ?></select></div>
        <div class="field" style="min-width:100px"><select name="bdiff" data-auto-submit>
          <option value="0">كل الصعوبات</option>
          <?php foreach (QuestionBank::DIFFICULTIES as $value => $label): ?><option value="<?= $value ?>" <?= input_int('bdiff') === $value ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
        </select></div>
        <div class="field"><input type="text" name="bq" value="<?= h((string) input('bq', '')) ?>" placeholder="بحث"></div>
        <button class="btn btn-sm btn-outline">بحث</button>
      </form>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_questions">
        <div class="bank-list">
          <?php foreach ($bank['rows'] as $row): ?>
            <label class="bank-item">
              <input type="checkbox" name="qids[]" value="<?= (int) $row['id'] ?>">
              <span><?= h(str_short($row['stem'], 110)) ?><br>
                <small><?= h($row['skill_name'] ? str_short($row['skill_name'], 50) : 'بدون مهارة') ?></small> <?= difficulty_badge((int) $row['difficulty']) ?></span>
            </label>
          <?php endforeach; ?>
          <?php if (!$bank['rows']): ?><p class="empty">لا توجد أسئلة أخرى مطابقة في البنك.</p><?php endif; ?>
        </div>
        <?= pagination_html($bank['page'], $bank['pages'], array_filter(['id' => $id, 'bskill' => input_int('bskill'), 'bdiff' => input_int('bdiff'), 'bq' => (string) input('bq', '')]), 'bpage') ?>
        <?php if ($bank['rows']): ?><button class="btn btn-primary">إضافة المحدد للاختبار</button><?php endif; ?>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php page_end(nafes_area()); ?>
