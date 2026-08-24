<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>معاملة <?= e($trx['transaction_no']) ?></title>
<link rel="stylesheet" href="assets/css/app.css?v=<?= APP_VERSION ?>">
</head>
<body style="background:#fff">
<div class="print-doc">
  <div class="no-print center mb">
    <button class="btn" onclick="window.print()">🖨️ طباعة</button>
    <a class="btn outline" href="<?= e(url('transactions/show', ['id' => $trx['id']])) ?>">رجوع</a>
  </div>

  <div class="print-header">
    <div class="org">المملكة العربية السعودية — وزارة التعليم</div>
    <div class="org"><?= e(setting('education_admin', '')) ?></div>
    <h2><?= e(setting('school_name', 'المدرسة')) ?></h2>
    <div class="org">نموذج قيد معاملة <?= e(labelDirection($trx['direction'])) ?>ة</div>
  </div>

  <div style="text-align:center;margin-bottom:1rem"><?= $barcode ?></div>

  <div class="print-meta">
    <div><b>رقم القيد:</b> <span dir="ltr"><?= e($trx['transaction_no']) ?></span></div>
    <div><b>رقم الجهة:</b> <span dir="ltr"><?= e($trx['reference_no'] ?: '-') ?></span></div>
    <div><b>التاريخ الميلادي:</b> <?= e(gdate($trx['transaction_date'])) ?></div>
    <div><b>التاريخ الهجري:</b> <?= e($trx['hijri_date'] ?: Hijri::format($trx['transaction_date'])) ?></div>
    <div><b>الجهة:</b> <?= e($trx['correspondent_name'] ?: '-') ?></div>
    <div><b>التصنيف:</b> <?= e($trx['category_name'] ?: '-') ?></div>
    <div><b>من إدارة:</b> <?= e($trx['from_dept'] ?: '-') ?></div>
    <div><b>إلى إدارة:</b> <?= e($trx['to_dept'] ?: '-') ?></div>
    <div><b>الأهمية:</b> <?= e(labelPriority($trx['priority'])) ?></div>
    <div><b>السرية:</b> <?= e(labelConfidentiality($trx['confidentiality'])) ?></div>
    <div><b>الحالة:</b> <?= e(labelStatus($trx['status'])) ?></div>
    <div><b>تاريخ الاستحقاق:</b> <?= e($trx['due_date'] ? gdate($trx['due_date']) : '-') ?></div>
  </div>

  <h3 style="border-bottom:1px solid #333;padding-bottom:.3rem">الموضوع</h3>
  <p style="font-size:1.05rem;font-weight:600"><?= e($trx['subject']) ?></p>

  <?php if ($trx['body']): ?>
    <h3 style="border-bottom:1px solid #333;padding-bottom:.3rem">نص المعاملة</h3>
    <div style="white-space:pre-wrap;line-height:2"><?= e($trx['body']) ?></div>
  <?php endif; ?>

  <?php if ($attachments): ?>
    <h3 style="border-bottom:1px solid #333;padding-bottom:.3rem;margin-top:1.2rem">المرفقات (<?= count($attachments) ?>)</h3>
    <table class="data" style="font-size:.85rem">
      <thead><tr><th>#</th><th>الوصف</th><th>النوع</th><th>المصدر</th></tr></thead>
      <tbody>
      <?php foreach ($attachments as $i => $a): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= e($a['title'] ?: $a['file_name']) ?></td>
          <td><?= e(labelMediaGroup($a['media_group'])) ?></td>
          <td><?= $a['kind'] === 'drive' ? 'قوقل درايف' : 'مخزّن على الخادم (' . e(Uploader::humanSize((int) $a['file_size'])) . ')' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if ($routings): ?>
    <h3 style="border-bottom:1px solid #333;padding-bottom:.3rem;margin-top:1.2rem">سجل الإحالات</h3>
    <table class="data" style="font-size:.85rem">
      <thead><tr><th>من</th><th>إلى</th><th>نوع الإحالة</th><th>التأشيرة</th><th>الحالة</th><th>التاريخ</th></tr></thead>
      <tbody>
      <?php foreach ($routings as $r): ?>
        <tr>
          <td><?= e($r['from_name'] ?: '-') ?></td>
          <td><?= e($r['to_name'] ?: '-') ?></td>
          <td><?= e(labelRoutingAction($r['action'])) ?></td>
          <td><?= e(excerpt($r['instructions'], 40)) ?></td>
          <td><?= e(labelRoutingStatus($r['status'])) ?></td>
          <td><?= e(gdate($r['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <div class="sign-row">
    <div>المُدخل<br><b><?= e($trx['creator_name'] ?: '-') ?></b></div>
    <div>مسؤول الأرشفة<br>&nbsp;</div>
    <div>مدير المدرسة<br>&nbsp;</div>
  </div>

  <div class="center xsmall muted" style="margin-top:2rem;border-top:1px solid #ddd;padding-top:.5rem">
    طُبع من <?= e(setting('app_name', 'نظام الأرشفة الإلكترونية')) ?>
    بتاريخ <?= e(date('Y-m-d H:i')) ?> م — <?= e(Hijri::format(date('Y-m-d'))) ?>
    بواسطة <?= e(Auth::user()['full_name']) ?>
  </div>
</div>
</body>
</html>
