<div class="auth-card wide" style="max-width:100%">
  <div class="center mb">
    <div class="auth-logo">🗂️</div>
    <h1><?= e(setting('school_name', 'المدرسة')) ?></h1>
    <p class="sub">وثيقة مشاركة من <?= e(setting('app_name', 'نظام الأرشفة الإلكترونية')) ?></p>
  </div>

  <?php if ($type === 'transaction'): ?>
    <h2><?= e($item['subject']) ?></h2>
    <dl class="dl mb">
      <dt>رقم المعاملة</dt><dd dir="ltr"><b><?= e($item['transaction_no']) ?></b></dd>
      <dt>النوع</dt><dd><?= e(labelDirection($item['direction'])) ?></dd>
      <dt>التاريخ</dt><dd><?= e(gdate($item['transaction_date'])) ?> م — <?= e($item['hijri_date']) ?></dd>
      <?php if ($item['correspondent_name']): ?><dt>الجهة</dt><dd><?= e($item['correspondent_name']) ?></dd><?php endif; ?>
      <?php if ($item['category_name']): ?><dt>التصنيف</dt><dd><?= e($item['category_name']) ?></dd><?php endif; ?>
    </dl>
    <?php if ($item['body']): ?>
      <div style="white-space:pre-wrap;background:#f8fafc;padding:.8rem;border-radius:8px"><?= e($item['body']) ?></div>
    <?php endif; ?>
  <?php else: ?>
    <h2><?= e($item['title']) ?></h2>
    <dl class="dl mb">
      <?php if ($item['doc_no']): ?><dt>رقم الوثيقة</dt><dd dir="ltr"><?= e($item['doc_no']) ?></dd><?php endif; ?>
      <?php if ($item['doc_type']): ?><dt>النوع</dt><dd><?= e($item['doc_type']) ?></dd><?php endif; ?>
      <?php if ($item['doc_date']): ?><dt>التاريخ</dt><dd><?= e(gdate($item['doc_date'])) ?> م — <?= e($item['hijri_date']) ?></dd><?php endif; ?>
      <dt>المجلد</dt><dd><?= e($item['folder_name']) ?></dd>
    </dl>
    <?php if ($item['description']): ?>
      <div style="white-space:pre-wrap;background:#f8fafc;padding:.8rem;border-radius:8px"><?= e($item['description']) ?></div>
    <?php endif; ?>
  <?php endif; ?>

  <hr>
  <h3>📎 المرفقات (<?= count($attachments) ?>)</h3>
  <?php if (!$attachments): ?>
    <p class="muted">لا توجد مرفقات.</p>
  <?php else: ?>
  <div class="att-list">
    <?php foreach ($attachments as $a): ?>
      <div class="att <?= $a['kind'] === 'drive' ? 'drive' : '' ?>">
        <div class="ico"><?= $a['kind'] === 'drive' ? '☁️' : fileIcon($a['ext']) ?></div>
        <div class="info">
          <b><?= e($a['title'] ?: $a['file_name']) ?></b>
          <small><?= e(labelMediaGroup($a['media_group'])) ?>
            <?= $a['kind'] === 'file' ? ' · ' . e(Uploader::humanSize((int) $a['file_size'])) : ' · قوقل درايف' ?></small>
        </div>
        <div class="acts">
          <?php if ($share['allow_download']): ?>
            <a class="btn outline xs" href="<?= e(url('share/download', ['token' => $share['token'], 'att' => $a['id']])) ?>">⬇️ تحميل</a>
          <?php endif; ?>
          <?php if ($a['kind'] === 'drive'): ?>
            <a class="btn outline xs" target="_blank" rel="noopener"
               href="<?= e(GDrive::openUrl($a['drive_file_id'], $a['ext'] ?: 'file')) ?>">☁️ عرض في درايف</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php
  // معاينة أول ملف من درايف داخل الصفحة
  foreach ($attachments as $a) {
      if ($a['kind'] === 'drive') {
          echo '<div class="mt"><iframe src="' . e(GDrive::previewUrl($a['drive_file_id'], $a['ext'] ?: 'file'))
             . '" class="viewer-frame" style="height:420px" allowfullscreen></iframe></div>';
          break;
      }
  } ?>
  <?php endif; ?>

  <div class="auth-foot">
    هذا الرابط ينتهي في <?= e(gdate($share['expires_at'], true)) ?> —
    عدد المشاهدات <?= (int) $share['views'] ?>
  </div>
</div>
