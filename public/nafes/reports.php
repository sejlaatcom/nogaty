<?php
declare(strict_types=1);
define('APP_AREA', 'nafes');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';
require APP_PATH . '/views/nafes.php';

$user = Auth::requireStaff();

$subjects  = Nafes::subjects();
$subjectId = input_int('subject') ?: (int) ($subjects[0]['id'] ?? 0);
$gradeIds  = array_map(static fn ($g) => (int) $g['id'], Nafes::grades());
$classrooms = array_values(array_filter(Nafes::staffClassrooms($user), static fn ($c) => in_array((int) $c['grade_id'], $gradeIds, true)));
$classId   = input_int('class');
$classroom = null;
foreach ($classrooms as $c) {
    if ((int) $c['id'] === $classId) {
        $classroom = $c;
    }
}
$includePractice = input('practice', '1') === '1';

if ($classroom) {
    [$skills, $students, $cells] = NafesReports::classSkillMatrix($classId, $subjectId, $includePractice);
    $averages = NafesReports::skillAverages($skills, $cells);
    $groups   = NafesReports::remedialGroups($skills, $students, $cells);

    if (input('export') === 'csv') {
        $header = ['الطالب'];
        foreach ($skills as $skill) {
            $header[] = QuestionBank::skillLabel($skill);
        }
        $rows = [$header];
        foreach ($students as $student) {
            $line = [$student['name']];
            foreach (array_keys($skills) as $skillId) {
                $line[] = $cells[(int) $student['id']][$skillId]['percent'] ?? '';
            }
            $rows[] = $line;
        }
        Csv::download(safe_filename('إتقان المهارات ' . Plans::classLabel($classroom)) . '.csv', $rows);
    }
} else {
    $overview = NafesReports::classOverview($classrooms, $subjectId);
}

$subjectName = '';
foreach ($subjects as $s) {
    if ((int) $s['id'] === $subjectId) {
        $subjectName = $s['name'];
    }
}

page_start('تقارير نافس', nafes_area(), 'nafes_reports');
?>

<div class="filters no-print">
  <form method="get">
    <div class="field"><label>المادة</label><select name="subject" data-auto-submit><?= options_html($subjects, $subjectId) ?></select></div>
    <div class="field"><label>الفصل</label>
      <select name="class" data-auto-submit>
        <option value="0">نظرة عامة على كل الفصول</option>
        <?php foreach ($classrooms as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= $classId === (int) $c['id'] ? 'selected' : '' ?>><?= h(Plans::classLabel($c)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($classroom): ?>
      <div class="field"><label>مصدر البيانات</label>
        <select name="practice" data-auto-submit>
          <option value="1" <?= $includePractice ? 'selected' : '' ?>>الاختبارات والتدريب الذاتي</option>
          <option value="0" <?= !$includePractice ? 'selected' : '' ?>>الاختبارات فقط</option>
        </select>
      </div>
      <span class="spacer"></span>
      <a class="btn btn-outline" href="?subject=<?= $subjectId ?>&class=<?= $classId ?>&practice=<?= $includePractice ? 1 : 0 ?>&export=csv">تصدير CSV</a>
    <?php endif; ?>
    <button class="btn btn-outline" type="button" data-print>طباعة</button>
  </form>
</div>

<?php if (!$classroom): ?>
<div class="card">
  <div class="card-head"><h2>أداء الفصول في <?= h($subjectName) ?></h2><small class="text-muted">متوسط نتائج الاختبارات لكل طالب ثم للفصل</small></div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>الفصل</th><th>الطلاب</th><th>اختبروا</th><th>المتوسط</th><th style="min-width:280px">توزيع المستويات</th><th>تدريب ذاتي</th><th class="no-print"></th></tr></thead>
        <tbody>
        <?php foreach ($overview as $row): ?>
          <tr>
            <td><strong><?= h(Plans::classLabel($row['classroom'])) ?></strong></td>
            <td><?= $row['students'] ?></td>
            <td><?= $row['tested'] ?></td>
            <td><?= Nafes::pct($row['avg'], 1) ?> <?= $row['avg'] !== null ? Nafes::levelBadge($row['avg']) : '' ?></td>
            <td><?= levels_bar($row['levels']) ?></td>
            <td><?= $row['practice'] ?></td>
            <td class="no-print"><a class="btn btn-sm btn-outline" href="?subject=<?= $subjectId ?>&class=<?= (int) $row['classroom']['id'] ?>">تحليل المهارات</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$overview): ?><tr><td colspan="7" class="empty">لا توجد فصول ضمن صفوف نافس متاحة لك.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php else: ?>
<h2><?= h(Plans::classLabel($classroom) . ' — ' . $subjectName) ?></h2>

<div class="card">
  <div class="card-head"><h2>متوسط إتقان الفصل للمهارات</h2></div>
  <div class="card-body">
    <?php foreach ($skills as $skillId => $skill): ?>
      <?= skill_bar(QuestionBank::skillLabel($skill), $averages[$skillId], $averages[$skillId] === null ? 'لم تُقَس بعد' : '') ?>
    <?php endforeach; ?>
    <?php if (!$skills): ?><p class="empty">لا توجد مهارات معرّفة لهذه المادة والصف.</p><?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2>خريطة إتقان الطلاب للمهارات</h2>
    <small class="text-muted">النسبة المئوية للإجابات الصحيحة لكل طالب في كل مهارة — الخلية الفارغة تعني أن المهارة لم تُقَس للطالب</small></div>
  <div class="card-body" style="overflow-x:auto">
    <table class="heatmap">
      <thead>
        <tr><th>الطالب</th>
          <?php foreach ($skills as $skill): ?><th class="skill-h" title="<?= h($skill['name']) ?>"><?= h($skill['code'] ?: str_short($skill['name'], 22)) ?></th><?php endforeach; ?>
          <th>المتوسط</th></tr>
      </thead>
      <tbody>
      <?php foreach ($students as $student): ?>
        <?php $row = $cells[(int) $student['id']] ?? []; $sumC = $sumT = 0; ?>
        <tr>
          <td class="name"><a href="<?= h(url('nafes/student_report.php?id=' . (int) $student['id'])) ?>"><?= h($student['name']) ?></a></td>
          <?php foreach (array_keys($skills) as $skillId): ?>
            <?php if (isset($row[$skillId])): $sumC += $row[$skillId]['correct']; $sumT += $row[$skillId]['total']; ?>
              <td class="c-<?= Nafes::levelKey($row[$skillId]['percent']) ?>" title="<?= $row[$skillId]['correct'] ?>/<?= $row[$skillId]['total'] ?>"><?= round($row[$skillId]['percent']) ?></td>
            <?php else: ?>
              <td></td>
            <?php endif; ?>
          <?php endforeach; ?>
          <?php $avg = $sumT ? $sumC / $sumT * 100 : null; ?>
          <td class="<?= $avg !== null ? 'c-' . Nafes::levelKey($avg) : '' ?>"><b><?= $avg !== null ? round($avg) : '—' ?></b></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$students): ?><tr><td colspan="<?= count($skills) + 2 ?>" class="empty">لا يوجد طلاب في هذا الفصل.</td></tr><?php endif; ?>
      </tbody>
    </table>
    <div class="mt16">
      <div class="levels-legend"><?php foreach (Nafes::LEVELS as $key => $label): ?><span><i class="lv-<?= $key ?>"></i><?= h($label) ?></span><?php endforeach; ?></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2>مجموعات الدعم العلاجي</h2>
    <small class="text-muted">الطلاب دون مستوى «متمكن» (أقل من <?= h(Settings::get('nafes_level_proficient')) ?>٪) في كل مهارة — رتّب حصص التقوية حسبها</small></div>
  <div class="card-body">
    <div class="follow-grid">
      <?php foreach ($groups as $group): ?>
        <div class="group-card">
          <h4><?= h(QuestionBank::skillLabel($group['skill'])) ?></h4>
          <small class="text-muted"><?= h($group['skill']['domain_name']) ?> · <?= count($group['students']) ?> طالب</small>
          <div class="late-list">
            <?php foreach ($group['students'] as $item): ?>
              <span class="late-chip"><?= h($item['name']) ?> (<?= round($item['percent']) ?>٪)</span>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if (!$groups): ?><p class="empty">لا توجد مجموعات — إما أن جميع الطلاب متمكنون أو لا توجد نتائج كافية بعد.</p><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php page_end(nafes_area()); ?>
