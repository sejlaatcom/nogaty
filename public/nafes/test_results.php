<?php
declare(strict_types=1);
define('APP_AREA', 'nafes');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';
require APP_PATH . '/views/nafes.php';

$user = Auth::requireStaff();
$test = Exams::find(input_int('id'));
if (!$test || !Exams::canView($user, $test)) {
    http_response_code(404);
    exit('الاختبار غير موجود.');
}
$testId = (int) $test['id'];

// الفصول المتاحة: فصول الاختبار (والمعلم غير المنشئ يرى فصوله منها فقط)
$allowed = $test['classroom_ids'];
if (!Exams::canManage($user, $test)) {
    $allowed = array_values(array_intersect($allowed, array_map(static fn ($c) => (int) $c['id'], Nafes::staffClassrooms($user))));
}
$classFilter = input_int('class');
$classIds    = $classFilter && in_array($classFilter, $allowed, true) ? [$classFilter] : $allowed;
$back        = 'nafes/test_results.php?id=' . $testId . ($classFilter ? '&class=' . $classFilter : '');

if (is_post()) {
    csrf_verify();
    $studentId = input_int('student');
    $student   = Nafes::student($studentId);
    if (input('action') === 'reset' && $student && in_array((int) $student['classroom_id'], $allowed, true)) {
        Database::delete('nafes_attempts', 'test_id = ? AND student_id = ?', [$testId, $studentId]);
        Activity::log('attempt_reset', 'nafes_tests', $testId, $student['name']);
        flash('success', 'تم حذف محاولات ' . $student['name'] . ' ويمكنه إعادة الاختبار.');
    }
    redirect($back);
}

$students  = NafesReports::testStudents($test, $classIds);
$summary   = NafesReports::summarize($students);
$attempts  = NafesReports::bestAttemptIds($students);
$skills    = NafesReports::skillsFor($attempts);
$items     = NafesReports::itemAnalysis($attempts);

/* ---------------- التصدير ---------------- */
if (in_array(input('export'), ['xlsx', 'csv'], true)) {
    $rows = [['الطالب', 'الصف / الفصل', 'الدرجة', 'من', 'النسبة', 'المستوى', 'عدد المحاولات', 'تاريخ التسليم']];
    foreach ($students as $s) {
        $b = $s['best'];
        $rows[] = [$s['name'], Nafes::classOf($s), $b ? (int) $b['score'] : '', $b ? (int) $b['total'] : '',
                   $b ? (float) $b['percent'] : '', $b ? Nafes::levelLabel((float) $b['percent']) : 'لم يختبر',
                   count($s['attempts']), $b['submitted_at'] ?? ''];
    }
    $name = safe_filename('نتائج ' . $test['title']);
    if (input('export') === 'csv') {
        Csv::download($name . '.csv', $rows);
    }
    $xlsx = new Xlsx('النتائج');
    $xlsx->setColumnWidths([30, 22, 9, 7, 9, 13, 12, 20]);
    $r = $xlsx->addRow([$test['title'] . ' — ' . $test['subject_name'] . ' — ' . $test['grade_name']], Xlsx::STYLE_TITLE, 26);
    $xlsx->merge($r, 1, $r, 8);
    $xlsx->addRow(array_shift($rows), Xlsx::STYLE_HEADER, 24);
    foreach ($rows as $line) {
        $xlsx->addRow($line, Xlsx::STYLE_BODY, 20);
    }
    $xlsx->addBlankRow();
    $xlsx->addRow(['المهارة', 'نسبة الإتقان', 'المستوى'], Xlsx::STYLE_HEADER, 22);
    foreach ($skills as $skill) {
        $xlsx->addRow([($skill['skill_code'] ? $skill['skill_code'] . ' ' : '') . $skill['skill_name'], (float) $skill['percent'], Nafes::levelLabel((float) $skill['percent'])], Xlsx::STYLE_BODY, 20);
    }
    $xlsx->download($name . '.xlsx');
    exit;
}

$classrooms = array_filter(Plans::classrooms(), static fn ($c) => in_array((int) $c['id'], $allowed, true));
$tab = (string) input('tab', 'students');

page_start('نتائج: ' . $test['title'], nafes_area(), 'nafes_tests');
?>

<div class="filters">
  <form method="get">
    <input type="hidden" name="id" value="<?= $testId ?>">
    <input type="hidden" name="tab" value="<?= h($tab) ?>">
    <div class="field"><label>الفصل</label>
      <select name="class" data-auto-submit>
        <option value="0">كل الفصول (<?= count($allowed) ?>)</option>
        <?php foreach ($classrooms as $classroom): ?>
          <option value="<?= (int) $classroom['id'] ?>" <?= $classFilter === (int) $classroom['id'] ? 'selected' : '' ?>><?= h(Plans::classLabel($classroom)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>&nbsp;</label><span class="pill-week"><?= h($test['subject_name'] . ' — ' . $test['grade_name'] . ' — ' . (Exams::KINDS[$test['kind']] ?? '')) ?></span></div>
    <span class="spacer"></span>
    <a class="btn btn-outline" href="<?= h(url($back . '&export=xlsx')) ?>">تصدير Excel</a>
    <a class="btn btn-outline" href="<?= h(url($back . '&export=csv')) ?>">CSV</a>
    <?php if (Exams::canManage($user, $test)): ?><a class="btn btn-outline" href="<?= h(url('nafes/test.php?id=' . $testId)) ?>">إدارة الاختبار</a><?php endif; ?>
    <button class="btn btn-outline" type="button" data-print>طباعة</button>
  </form>
</div>

<div class="stats">
  <div class="stat teal"><div class="num"><?= $summary['done'] ?> / <?= $summary['students'] ?></div><div class="lbl">أنجزوا الاختبار</div></div>
  <div class="stat"><div class="num"><?= Nafes::pct($summary['avg'], 1) ?></div><div class="lbl">المتوسط <?= $summary['avg'] !== null ? Nafes::levelBadge($summary['avg']) : '' ?></div></div>
  <div class="stat green"><div class="num"><?= Nafes::pct($summary['proficient_rate']) ?></div><div class="lbl">متمكن فأعلى</div></div>
  <div class="stat"><div class="num"><?= Nafes::pct($summary['median']) ?></div><div class="lbl">الوسيط (أعلى <?= Nafes::pct($summary['max']) ?> · أدنى <?= Nafes::pct($summary['min']) ?>)</div></div>
</div>

<?php if ($summary['done']): ?>
<div class="card">
  <div class="card-head"><h2>توزيع مستويات الأداء</h2></div>
  <div class="card-body"><?= levels_bar($summary['levels']) ?></div>
</div>
<?php endif; ?>

<div class="toolbar no-print">
  <?php foreach (['students' => 'الطلاب', 'skills' => 'المهارات', 'items' => 'تحليل الأسئلة'] as $key => $label): ?>
    <a class="btn <?= $tab === $key ? 'btn-primary' : 'btn-outline' ?>" href="<?= h(url('nafes/test_results.php?id=' . $testId . '&class=' . $classFilter . '&tab=' . $key)) ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'skills'): ?>
<div class="card">
  <div class="card-head"><h2>إتقان المهارات</h2></div>
  <div class="card-body">
    <?php foreach ($skills as $skill): ?>
      <?= skill_bar(($skill['skill_code'] ? $skill['skill_code'] . ' — ' : '') . $skill['skill_name'], (float) $skill['percent'], (int) $skill['correct'] . '/' . (int) $skill['total'] . ' إجابة') ?>
    <?php endforeach; ?>
    <?php if (!$skills): ?><p class="empty">لا توجد نتائج بعد.</p><?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'items'): ?>
<div class="card">
  <div class="card-head"><h2>تحليل الأسئلة</h2>
    <small class="text-muted">معامل السهولة = نسبة الإجابات الصحيحة · معامل التمييز = (صحيح أعلى 27٪ − صحيح أدنى 27٪) ÷ حجم المجموعة، والجيد ≥ 0.3</small></div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>السؤال</th><th>المهارة</th><th>السهولة</th><th>التمييز</th><th>توزيع الإجابات</th><th>ملاحظة</th></tr></thead>
        <tbody>
        <?php foreach ($items as $item): ?>
          <tr>
            <td style="min-width:220px"><a href="<?= h(url('nafes/question.php?id=' . (int) $item['id'])) ?>" target="_blank"><?= h(str_short($item['stem'], 80)) ?></a><br><?= difficulty_badge((int) $item['difficulty']) ?></td>
            <td><small><?= h(str_short($item['skill_name'] ?? '—', 40)) ?></small></td>
            <td><b class="<?= $item['p'] < 50 ? 'text-red' : 'text-green' ?>"><?= Nafes::pct($item['p']) ?></b></td>
            <td><?= h(number_format($item['discrimination'], 2)) ?></td>
            <td style="min-width:220px">
              <?php foreach ($item['options'] as $j => $option): ?>
                <div class="opt-dist <?= (int) $option['is_correct'] === 1 ? 'correct' : '' ?>">
                  <span><?= option_letter($j) ?>) <?= h(str_short($option['body'], 25)) ?></span>
                  <b><?= $item['n'] ? round($option['chosen'] / $item['n'] * 100) : 0 ?>٪</b>
                </div>
              <?php endforeach; ?>
              <?php if ($item['blank']): ?><small class="text-muted">بدون إجابة: <?= (int) $item['blank'] ?></small><?php endif; ?>
            </td>
            <td><small class="<?= $item['flag'] !== '' ? 'text-red' : '' ?>"><?= h($item['flag'] ?: '—') ?></small></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="6" class="empty">لا توجد نتائج بعد.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php else: ?>
<div class="card">
  <div class="card-head"><h2>نتائج الطلاب</h2></div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>#</th><th>الطالب</th><th>الفصل</th><th>الدرجة</th><th>النسبة</th><th>المستوى</th><th>المحاولات</th><th>المدة</th><th class="no-print"></th></tr></thead>
        <tbody>
        <?php
        usort($students, static fn ($a, $b) => ((float) ($b['best']['percent'] ?? -1)) <=> ((float) ($a['best']['percent'] ?? -1)));
        foreach ($students as $i => $s):
            $b = $s['best'];
        ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><strong><?= h($s['name']) ?></strong></td>
            <td class="nowrap"><?= h(Nafes::classOf($s)) ?></td>
            <?php if ($b): ?>
              <td><?= (int) $b['score'] ?> / <?= (int) $b['total'] ?></td>
              <td><?= Nafes::pct((float) $b['percent'], 1) ?></td>
              <td><?= Nafes::levelBadge((float) $b['percent']) ?></td>
              <td><?= count($s['attempts']) ?></td>
              <td class="nowrap"><small><?= h(Exams::durationLabel($b)) ?></small></td>
            <?php else: ?>
              <td colspan="5"><?= $s['in_progress'] ? '<span class="badge badge-amber">يختبر الآن</span>' : '<span class="badge badge-gray">لم يختبر</span>' ?></td>
            <?php endif; ?>
            <td class="actions no-print">
              <?php if ($b): ?><a class="btn btn-sm btn-outline" href="<?= h(url('nafes/attempt.php?id=' . (int) $b['id'])) ?>">الإجابات</a><?php endif; ?>
              <a class="btn btn-sm btn-outline" href="<?= h(url('nafes/student_report.php?id=' . (int) $s['id'])) ?>">تقرير الطالب</a>
              <?php if ($s['attempts']): ?>
                <form method="post" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="student" value="<?= (int) $s['id'] ?>">
                  <button class="btn btn-sm btn-danger" name="action" value="reset" data-confirm="حذف محاولات الطالب في هذا الاختبار والسماح له بإعادته؟">إعادة</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$students): ?><tr><td colspan="9" class="empty">لا يوجد طلاب في الفصول المستهدفة.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php page_end(nafes_area()); ?>
