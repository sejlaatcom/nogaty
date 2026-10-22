<?php
declare(strict_types=1);
define('APP_AREA', 'nafes');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';
require APP_PATH . '/views/nafes.php';

$user    = Auth::requireStaff();
$student = Nafes::student(input_int('id'));
if (!$student || !$student['classroom_id'] || !Nafes::canAccessClassroom($user, (int) $student['classroom_id'])) {
    http_response_code(404);
    exit('الطالب غير موجود أو لا تملك صلاحية عرض تقريره.');
}

$subjects = NafesReports::studentSubjects((int) $student['id']);
$attempts = NafesReports::studentAttempts((int) $student['id']);
$cut      = (float) Settings::get('nafes_level_proficient');

$sections = [];
foreach ($subjects as $subject) {
    $stats  = Exams::studentSkillStats((int) $student['id'], (int) $subject['id']);
    $skills = [];
    foreach (QuestionBank::domainsWithSkills((int) $subject['id'], (int) $student['grade_id']) as $domain) {
        foreach ($domain['skills'] as $skill) {
            if (isset($stats[(int) $skill['id']])) {
                $skills[] = $skill + $stats[(int) $skill['id']];
            }
        }
    }
    $sections[] = [
        'subject'   => $subject,
        'skills'    => $skills,
        'strengths' => array_filter($skills, static fn ($s) => $s['percent'] >= $cut),
        'needs'     => array_filter($skills, static fn ($s) => $s['percent'] < $cut),
    ];
}

page_start('تقرير الطالب: ' . $student['name'], nafes_area(), 'nafes_reports');
?>
<div class="toolbar no-print">
  <a class="btn btn-outline" href="<?= h(url('nafes/reports.php?class=' . (int) $student['classroom_id'])) ?>">← تقارير الفصل</a>
  <button class="btn btn-primary" data-print>طباعة التقرير / حفظ PDF</button>
</div>

<div class="report-sheet">
  <div class="card">
    <div class="card-body">
      <div class="sheet-head">
        <div class="col"><strong><?= h(Settings::get('country_name')) ?></strong><?= h(Settings::get('ministry_name')) ?><br><?= h(Settings::get('education_dept')) ?><br><?= h(Settings::get('school_name')) ?></div>
        <div class="sheet-title"><h2>تقرير أداء الطالب في مهارات نافس</h2><small><?= h(Settings::get('nafes_title')) ?></small></div>
        <?php if (Settings::get('logo') !== ''): ?><img class="sheet-logo" src="<?= h(url('uploads/' . Settings::get('logo'))) ?>" alt=""><?php else: ?><div class="col"></div><?php endif; ?>
      </div>
      <div class="sheet-meta">
        <div>الطالب: <b><?= h($student['name']) ?></b></div>
        <div>الصف / الفصل: <b><?= h(Nafes::classOf($student)) ?></b></div>
        <div>تاريخ التقرير: <b><?= h(date('Y-m-d')) ?> — <?= h(Hijri::long(date('Y-m-d'))) ?></b></div>
      </div>

      <?php if (!$subjects): ?>
        <p class="empty">لا توجد نتائج مسجلة لهذا الطالب بعد.</p>
      <?php endif; ?>

      <?php if ($subjects): ?>
      <table class="data mb16">
        <thead><tr><th>المادة</th><th>الاختبارات</th><th>متوسط الاختبارات</th><th>المستوى</th><th>التدريب الذاتي</th><th>متوسط التدريب</th></tr></thead>
        <tbody>
        <?php foreach ($subjects as $subject): ?>
          <tr>
            <td><strong><?= h($subject['name']) ?></strong></td>
            <td><?= (int) $subject['tests'] ?></td>
            <td><?= Nafes::pct($subject['test_avg'] !== null ? (float) $subject['test_avg'] : null, 1) ?></td>
            <td><?= $subject['test_avg'] !== null ? Nafes::levelBadge((float) $subject['test_avg']) : '—' ?></td>
            <td><?= (int) $subject['practices'] ?></td>
            <td><?= Nafes::pct($subject['practice_avg'] !== null ? (float) $subject['practice_avg'] : null, 1) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <?php foreach ($sections as $section): ?>
    <div class="card">
      <div class="card-head"><h2><?= h($section['subject']['name']) ?> — إتقان المهارات</h2></div>
      <div class="card-body">
        <?php foreach ($section['skills'] as $skill): ?>
          <?= skill_bar(QuestionBank::skillLabel($skill), $skill['percent'], $skill['correct'] . '/' . $skill['total']) ?>
        <?php endforeach; ?>
        <?php if (!$section['skills']): ?><p class="text-muted">لا توجد أسئلة مرتبطة بمهارات في نتائج هذه المادة.</p><?php endif; ?>
        <div class="grid grid-2 mt16">
          <div><b class="text-green">نقاط القوة</b>
            <p class="mb0"><?= $section['strengths'] ? h(implode('، ', array_map(static fn ($s) => $s['name'], $section['strengths']))) : '—' ?></p></div>
          <div><b class="text-red">مهارات تحتاج إلى دعم</b>
            <p class="mb0"><?= $section['needs'] ? h(implode('، ', array_map(static fn ($s) => $s['name'], $section['needs']))) : '—' ?></p></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if ($attempts): ?>
  <div class="card">
    <div class="card-head"><h2>سجل الاختبارات والتدريب</h2></div>
    <div class="card-body tight">
      <table class="data">
        <thead><tr><th>التاريخ</th><th>النوع</th><th>العنوان</th><th>المادة</th><th>الدرجة</th><th>النسبة</th><th class="no-print"></th></tr></thead>
        <tbody>
        <?php foreach (array_slice($attempts, 0, 30) as $a): ?>
          <tr>
            <td class="nowrap"><?= h(substr((string) $a['submitted_at'], 0, 10)) ?></td>
            <td><?= $a['mode'] === 'test' ? 'اختبار' : 'تدريب' ?></td>
            <td><?= h($a['mode'] === 'test' ? (string) $a['test_title'] : ($a['skill_name'] ?: 'تدريب شامل')) ?></td>
            <td><?= h($a['subject_name']) ?></td>
            <td><?= (int) $a['score'] ?>/<?= (int) $a['total'] ?></td>
            <td><?= Nafes::pct((float) $a['percent']) ?> <?= Nafes::levelBadge((float) $a['percent']) ?></td>
            <td class="no-print"><a class="btn btn-sm btn-outline" href="<?= h(url('nafes/attempt.php?id=' . (int) $a['id'])) ?>">الإجابات</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <div class="sheet-foot">
    <div class="family-msg">ولي الأمر الكريم: نأمل متابعة ابنكم في المهارات التي تحتاج إلى دعم وتشجيعه على التدريب الذاتي عبر المنصة باسم المستخدم: <b><?= h($student['username']) ?></b></div>
    <div class="sign-box">معلم المادة<br><br>..........................</div>
    <div class="sign-box"><?= h(Settings::get('principal_title')) ?><br><?= h(Settings::get('principal_name')) ?: '<br>' ?>..........................</div>
  </div>
</div>
<?php page_end(nafes_area()); ?>
