<?php
declare(strict_types=1);

/**
 * عرض ورقة الخطة الأسبوعية لفصل واحد (تُستخدم في الواجهة العامة، المعاينة، والطباعة).
 *
 * @param array $week      بيانات الأسبوع
 * @param array $classroom بيانات الفصل
 * @param array $rows      مخرجات Plans::classSheet()
 * @param array $opts      ['completion' => [...], 'show_status' => bool]
 */
function render_sheet(array $week, array $classroom, array $rows, array $opts = []): void
{
    $fields = PLAN_FIELDS;
    if (!Settings::bool('show_notes_row')) {
        unset($fields['notes']);
    }
    $showTeacher = Settings::bool('show_teacher_name');
    $completion  = $opts['completion'] ?? null;
    $familyMsg   = $opts['family_message'] ?? Plans::familyMessage((int) $week['id'], (int) $classroom['id']);
    ?>
<div class="sheet">
  <div class="sheet-head">
    <div class="col">
      <strong><?= h(Settings::get('country_name')) ?></strong>
      <span><?= h(Settings::get('ministry_name')) ?></span><br>
      <span><?= h(Settings::get('education_dept')) ?></span><br>
      <span><?= h(Settings::get('school_name')) ?></span>
    </div>
    <div class="sheet-title">
      <h2><?= h(Settings::get('sheet_title')) ?></h2>
      <small><?= h(($week['term_name'] ?? '') . ' — ' . ($week['year_name'] ?? '')) ?></small>
    </div>
    <div class="col">
      <?php if (Settings::get('logo') !== ''): ?>
        <img class="sheet-logo" src="<?= h(url('uploads/' . Settings::get('logo'))) ?>" alt="شعار المدرسة">
      <?php elseif (Settings::get('vision_logo') !== ''): ?>
        <img class="sheet-logo" src="<?= h(url('uploads/' . Settings::get('vision_logo'))) ?>" alt="رؤية 2030">
      <?php else: ?>
        <strong>رؤية 2030</strong>
      <?php endif; ?>
    </div>
  </div>

  <div class="sheet-meta">
    <div>الصف: <b><?= h(Plans::classLabel($classroom)) ?></b></div>
    <div>الأسبوع: <b><?= h($week['title'] ?: week_ordinal((int) $week['week_no'])) ?></b></div>
    <div>من: <b><?= h($week['hijri_start'] ?: Hijri::long($week['start_date'])) ?></b></div>
    <div>إلى: <b><?= h($week['hijri_end'] ?: Hijri::long($week['end_date'])) ?></b></div>
    <?php if ($completion): ?>
      <div>حالة التعبئة: <b><?= (int) $completion['done'] ?> من <?= (int) $completion['total'] ?></b></div>
    <?php endif; ?>
  </div>

  <div class="table-wrap">
    <table class="sheet-table">
      <thead>
        <tr>
          <th>المادة</th>
          <th>البند</th>
          <?php foreach (WEEK_DAYS as $index => $dayName): ?>
            <th>
              <?= h($dayName) ?>
              <small><?= h(ar_digits(Hijri::short(week_day_date($week['start_date'], $index)))) ?></small>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <?php $rowspan = count($fields); $first = true; ?>
        <?php foreach ($fields as $fieldKey => $fieldLabel): ?>
          <tr class="<?= $row['filled'] ? '' : 'missing' ?>">
            <?php if ($first): ?>
              <td class="subject" rowspan="<?= $rowspan ?>">
                <?= h($row['subject_name']) ?>
                <?php if ($showTeacher): ?>
                  <small><?= h(teacher_short($row['teacher_name'])) ?></small>
                <?php endif; ?>
                <?php if (!$row['filled']): ?>
                  <small class="text-red">لم تُعبأ</small>
                <?php endif; ?>
              </td>
            <?php endif; ?>
            <td class="rowlbl"><?= h($fieldLabel) ?></td>
            <?php foreach (array_keys(WEEK_DAYS) as $dayIndex): ?>
              <td><?= nl2br_e($row['days'][$dayIndex][$fieldKey] ?? '') ?></td>
            <?php endforeach; ?>
          </tr>
          <?php $first = false; ?>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="<?= count(WEEK_DAYS) + 2 ?>" class="empty">لا توجد مواد مسندة لهذا الفصل.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="sheet-foot">
    <div class="family-msg"><b>رسالة إلى الأسرة:</b> <?= h($familyMsg) ?></div>
    <div class="sign-box">
      <?= h(Settings::get('principal_title')) ?>: <b><?= h(Settings::get('principal_name') ?: '—') ?></b>
      <div class="text-muted">التوقيع: ....................</div>
    </div>
  </div>
</div>
<?php
}
