<?php
/**
 * تصدير ورقة الخطة الأسبوعية:
 *   export.php?week=1&class=2&format=xlsx   ملف إكسل منسّق جاهز للطباعة
 *   export.php?week=1&class=2&format=csv    ملف CSV بصيغة قابلة لإعادة الاستيراد
 *   export.php?week=1&all=1&format=xlsx     كل فصول الأسبوع في ملف واحد
 */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$weekId  = input_int('week');
$classId = input_int('class');
$all     = input('all') === '1';
$format  = input('format', 'xlsx') === 'csv' ? 'csv' : 'xlsx';
$preview = input('preview') === '1';

$week = $weekId ? Plans::week($weekId) : Plans::currentWeek();
if (!$week) {
    exit('لا يوجد أسبوع دراسي محدد.');
}
$weekId = (int) $week['id'];

if ($preview) {
    Auth::requireLogin();
} elseif (!Settings::bool('public_enabled') && !Auth::check()) {
    exit('التصدير غير متاح حالياً.');
}
$onlyApproved = !$preview;

/* ------------ الفصول المطلوبة ------------ */
$classrooms = [];
if ($all) {
    if ($preview) {
        $classrooms = Plans::classrooms();
    } else {
        foreach (Plans::classroomsWithApproved($weekId) as $row) {
            $classroom = Plans::classroom((int) $row['id']);
            if ($classroom) {
                $classrooms[] = $classroom;
            }
        }
    }
} else {
    $classroom = $classId ? Plans::classroom($classId) : null;
    if (!$classroom) {
        exit('الفصل المطلوب غير موجود.');
    }
    $classrooms = [$classroom];
}

$fields = PLAN_FIELDS;
if (!Settings::bool('show_notes_row')) {
    unset($fields['notes']);
}

$weekLabel = $week['title'] ?: ('الأسبوع ' . $week['week_no']);

/* ------------ تصدير CSV (قابل لإعادة الاستيراد) ------------ */
if ($format === 'csv') {
    $rows = [['الأسبوع', 'الصف', 'الفصل', 'المادة', 'البند', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس']];

    foreach ($classrooms as $classroom) {
        foreach (Plans::classSheet($weekId, (int) $classroom['id'], $onlyApproved) as $row) {
            foreach ($fields as $fieldKey => $fieldLabel) {
                $line = [(int) $week['week_no'], $classroom['grade_name'], $classroom['name'], $row['subject_name'], $fieldLabel];
                foreach (array_keys(WEEK_DAYS) as $dayIndex) {
                    $line[] = (string) ($row['days'][$dayIndex][$fieldKey] ?? '');
                }
                $rows[] = $line;
            }
        }
    }

    $name = safe_filename('الخطة الأسبوعية - ' . $weekLabel . ($all ? ' - كل الفصول' : ' - ' . Plans::classLabel($classrooms[0]))) . '.csv';
    Csv::download($name, $rows);
}

/* ------------ تصدير إكسل منسّق ------------ */
$xlsx = new Xlsx($weekLabel);
$xlsx->setColumnWidths([18, 20, 24, 24, 24, 24, 24]);
$columns = count(WEEK_DAYS) + 2; // المادة + البند + الأيام

foreach ($classrooms as $index => $classroom) {
    if ($index > 0) {
        $xlsx->addBlankRow(14);
    }

    $r = $xlsx->addRow([Settings::get('sheet_title')], Xlsx::STYLE_TITLE, 28);
    $xlsx->merge($r, 1, $r, $columns);

    $r = $xlsx->addRow([Settings::get('country_name') . ' — ' . Settings::get('ministry_name') . ' — '
        . Settings::get('education_dept') . ' — ' . Settings::get('school_name')], Xlsx::STYLE_DEFAULT, 20);
    $xlsx->merge($r, 1, $r, $columns);

    $meta = sprintf(
        'الصف: %s   |   %s   |   %s   |   من %s إلى %s',
        Plans::classLabel($classroom),
        $week['term_name'] ?? '',
        $weekLabel,
        $week['hijri_start'] ?: $week['start_date'],
        $week['hijri_end'] ?: $week['end_date']
    );
    $r = $xlsx->addRow([$meta], Xlsx::STYLE_NOTE, 22);
    $xlsx->merge($r, 1, $r, $columns);

    $header = ['المادة', 'البند'];
    foreach (WEEK_DAYS as $dayIndex => $dayName) {
        $header[] = $dayName . "\n" . Hijri::short(week_day_date($week['start_date'], $dayIndex));
    }
    $xlsx->addRow($header, Xlsx::STYLE_HEADER, 32);

    foreach (Plans::classSheet($weekId, (int) $classroom['id'], $onlyApproved) as $row) {
        $first    = true;
        $firstRow = 0;
        foreach ($fields as $fieldKey => $fieldLabel) {
            $cells = [];
            $cells[] = ['v' => $first ? $row['subject_name'] . (Settings::bool('show_teacher_name') ? "\n" . teacher_short($row['teacher_name']) : '') : '', 's' => Xlsx::STYLE_SUBJECT];
            $cells[] = ['v' => $fieldLabel, 's' => Xlsx::STYLE_LABEL];
            foreach (array_keys(WEEK_DAYS) as $dayIndex) {
                $cells[] = ['v' => (string) ($row['days'][$dayIndex][$fieldKey] ?? ''), 's' => Xlsx::STYLE_BODY];
            }
            $current = $xlsx->addRow($cells, Xlsx::STYLE_BODY, 34);
            if ($first) {
                $firstRow = $current;
                $first = false;
            }
        }
        if ($firstRow > 0 && count($fields) > 1) {
            $xlsx->merge($firstRow, 1, $firstRow + count($fields) - 1, 1);
        }
    }

    $r = $xlsx->addRow(['رسالة إلى الأسرة: ' . Plans::familyMessage($weekId, (int) $classroom['id'])], Xlsx::STYLE_NOTE, 30);
    $xlsx->merge($r, 1, $r, $columns);

    $r = $xlsx->addRow([Settings::get('principal_title') . ': ' . Settings::get('principal_name') . '            التوقيع: ....................'], Xlsx::STYLE_NOTE, 26);
    $xlsx->merge($r, 1, $r, $columns);
}

$name = safe_filename('الخطة الأسبوعية - ' . $weekLabel . ($all ? ' - كل الفصول' : ' - ' . Plans::classLabel($classrooms[0]))) . '.xlsx';
$xlsx->download($name);
