<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>تقرير حركة المعاملات</title>
<link rel="stylesheet" href="assets/css/app.css?v=<?= APP_VERSION ?>">
</head>
<body style="background:#fff">
<div class="print-doc" style="max-width:1100px">
  <div class="no-print center mb">
    <button class="btn" onclick="window.print()">🖨️ طباعة</button>
    <a class="btn outline" href="<?= e(url('reports/index')) ?>">رجوع</a>
  </div>

  <div class="print-header">
    <div class="org">المملكة العربية السعودية — وزارة التعليم</div>
    <div class="org"><?= e(setting('education_admin', '')) ?></div>
    <h2><?= e(setting('school_name', 'المدرسة')) ?></h2>
    <div class="org">
      تقرير حركة المعاملات <?= $direction ? 'ال' . e(labelDirection($direction)) . 'ة' : '' ?>
      خلال الفترة من <?= e(gdate($from)) ?> إلى <?= e(gdate($to)) ?>
    </div>
  </div>

  <p class="small">عدد المعاملات في الفترة : <b><?= num(count($rows)) ?></b></p>

  <table class="data" style="font-size:.8rem">
    <thead>
      <tr><th>م</th><th>رقم القيد</th><th>النوع</th><th>الموضوع</th><th>الجهة</th>
          <th>الإدارة</th><th>التاريخ</th><th>الهجري</th><th>الحالة</th><th>مرفقات</th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $i => $r): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td class="nowrap"><?= e($r['transaction_no']) ?></td>
        <td class="nowrap"><?= e(labelDirection($r['direction'])) ?></td>
        <td><?= e(excerpt($r['subject'], 60)) ?></td>
        <td><?= e(excerpt($r['correspondent_name'], 22)) ?></td>
        <td><?= e(excerpt($r['to_dept'], 20)) ?></td>
        <td class="nowrap"><?= e(gdate($r['transaction_date'])) ?></td>
        <td class="nowrap"><?= e($r['hijri_date']) ?></td>
        <td class="nowrap"><?= e(labelStatus($r['status'])) ?></td>
        <td class="center"><?= (int) $r['attachments_count'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="sign-row">
    <div>مسؤول الأرشفة<br>&nbsp;</div>
    <div>وكيل المدرسة<br>&nbsp;</div>
    <div>مدير المدرسة<br>&nbsp;</div>
  </div>

  <div class="center xsmall muted" style="margin-top:1.5rem;border-top:1px solid #ddd;padding-top:.5rem">
    طُبع بتاريخ <?= e(date('Y-m-d H:i')) ?> م — <?= e(Hijri::format(date('Y-m-d'))) ?>
    بواسطة <?= e(Auth::user()['full_name']) ?>
  </div>
</div>
</body>
</html>
