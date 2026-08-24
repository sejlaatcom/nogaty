<div class="page-head">
  <h1>👁️ <?= e($att['title'] ?: $att['file_name']) ?></h1>
  <span class="spacer"></span>
  <a class="btn outline sm" href="<?= e($links['download']) ?>" <?= $links['external'] ? 'target="_blank" rel="noopener"' : '' ?>>⬇️ تحميل</a>
  <?php if ($att['kind'] === 'drive'): ?>
    <a class="btn outline sm" href="<?= e($links['open']) ?>" target="_blank" rel="noopener">☁️ فتح في درايف</a>
  <?php endif; ?>
  <a class="btn ghost sm" href="<?= e(url($att['attachable_type'] === 'transaction' ? 'transactions/show' : 'documents/show',
       ['id' => $att['attachable_id']])) ?>">↩︎ رجوع</a>
</div>

<div class="card">
  <div class="card-body">
    <?php if ($att['kind'] === 'drive'): ?>
      <iframe src="<?= e($links['preview']) ?>" class="viewer-frame" allow="autoplay" allowfullscreen></iframe>
      <p class="hint mt-sm">
        إن ظهرت رسالة «ليس لديك إذن»، فتأكد من ضبط مشاركة الملف في قوقل درايف على
        <b>«أي شخص لديه الرابط — مُشاهد»</b>.
      </p>
    <?php elseif ($att['media_group'] === 'image'): ?>
      <div class="center"><img src="<?= e($links['preview']) ?>" alt="<?= e($att['title']) ?>" style="max-height:70vh;border-radius:8px"></div>
    <?php elseif ($att['media_group'] === 'video'): ?>
      <video src="<?= e($links['preview']) ?>" class="media-box" controls autoplay style="width:100%"></video>
    <?php elseif ($att['media_group'] === 'audio'): ?>
      <div style="padding:2rem;background:#f8fafc;border-radius:8px;text-align:center">
        <div style="font-size:3rem">🎵</div>
        <audio src="<?= e($links['preview']) ?>" style="width:100%;max-width:520px" controls></audio>
      </div>
    <?php elseif (strtolower((string) $att['ext']) === 'pdf'): ?>
      <iframe src="<?= e($links['preview']) ?>" class="viewer-frame"></iframe>
    <?php else: ?>
      <div class="empty">
        <span class="big"><?= fileIcon($att['ext']) ?></span>
        هذا النوع من الملفات (<?= e(strtoupper((string) $att['ext'])) ?>) لا يُعرض داخل المتصفح.
        <div class="mt"><a class="btn" href="<?= e($links['download']) ?>">⬇️ تحميل الملف</a></div>
      </div>
    <?php endif; ?>
  </div>
  <div class="card-foot">
    <dl class="dl">
      <dt>المصدر</dt><dd><?= $att['kind'] === 'drive' ? '☁️ قوقل درايف' : '💾 مخزّن على الخادم' ?></dd>
      <dt>النوع</dt><dd><?= e(labelMediaGroup($att['media_group'])) ?><?= $att['ext'] ? ' — ' . e(strtoupper($att['ext'])) : '' ?></dd>
      <?php if ($att['kind'] === 'file'): ?>
        <dt>الحجم</dt><dd><?= e(Uploader::humanSize((int) $att['file_size'])) ?></dd>
        <dt>اسم الملف</dt><dd dir="ltr"><?= e($att['file_name']) ?></dd>
      <?php else: ?>
        <dt>الرابط</dt><dd class="ltr truncate"><?= e($att['drive_url']) ?></dd>
      <?php endif; ?>
      <dt>عدد التحميلات</dt><dd><?= (int) $att['downloads'] ?></dd>
      <dt>تاريخ الإضافة</dt><dd><?= e(gdate($att['created_at'], true)) ?></dd>
    </dl>
  </div>
</div>
