<div class="page-head">
  <h1>🎞️ مكتبة المرفقات</h1>
  <span class="spacer"></span>
  <div class="btn-group">
    <a class="btn <?= $media === null && $kind === null ? '' : 'outline' ?> sm" href="<?= e(url('attachments/gallery')) ?>">الكل</a>
    <?php foreach (['document' => '📄 مستندات', 'image' => '🖼️ صور', 'video' => '🎬 فيديو', 'audio' => '🎵 صوت', 'archive' => '🗜️ مضغوطة'] as $k => $lbl): ?>
      <a class="btn <?= $media === $k ? '' : 'outline' ?> sm" href="<?= e(url('attachments/gallery', ['media' => $k])) ?>"><?= $lbl ?></a>
    <?php endforeach; ?>
    <a class="btn <?= $kind === 'drive' ? '' : 'outline' ?> sm" href="<?= e(url('attachments/gallery', ['kind' => 'drive'])) ?>">☁️ درايف</a>
  </div>
</div>

<div class="grid grid-6 mb">
  <?php
  $icons = ['document' => '📄', 'image' => '🖼️', 'video' => '🎬', 'audio' => '🎵', 'archive' => '🗜️', 'other' => '📦'];
  foreach ($stats as $s): ?>
    <a class="stat" href="<?= e(url('attachments/gallery', ['media' => $s['media_group']])) ?>">
      <span class="ico bg-muted"><?= $icons[$s['media_group']] ?? '📎' ?></span>
      <span>
        <span class="num"><?= num($s['total']) ?></span>
        <span class="lbl"><?= e(labelMediaGroup($s['media_group'])) ?> · <?= e(Uploader::humanSize((int) $s['size'])) ?></span>
      </span>
    </a>
  <?php endforeach; ?>
</div>

<form class="filters" method="get" action="index.php">
  <input type="hidden" name="r" value="attachments/gallery">
  <?php if ($media): ?><input type="hidden" name="media" value="<?= e($media) ?>"><?php endif; ?>
  <?php if ($kind): ?><input type="hidden" name="kind" value="<?= e($kind) ?>"><?php endif; ?>
  <div class="row">
    <div style="grid-column:span 2"><label>بحث في أسماء الملفات</label>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="اسم الملف أو وصفه"></div>
    <div class="flex"><button class="btn sm">🔍 بحث</button>
      <a class="btn ghost sm" href="<?= e(url('attachments/gallery')) ?>">إلغاء</a></div>
  </div>
</form>

<div class="card">
  <div class="card-head"><h3>المرفقات</h3><span class="spacer"></span>
    <span class="chip"><?= num($page->total) ?> مرفق</span></div>
  <div class="card-body">
    <?php if (!$page->items): ?>
      <div class="empty"><span class="big">📎</span>لا توجد مرفقات مطابقة</div>
    <?php else: ?>
    <div class="att-cards">
      <?php foreach ($page->items as $a): $L = AttachmentService::links($a); ?>
        <div class="att-card">
          <div class="thumb">
            <?php if ($a['kind'] === 'drive' && $a['media_group'] === 'image'): ?>
              <img src="<?= e($L['thumb']) ?>" alt="" loading="lazy">
            <?php elseif ($a['kind'] === 'file' && $a['media_group'] === 'image'): ?>
              <img src="<?= e($L['preview']) ?>" alt="" loading="lazy">
            <?php else: ?>
              <?= $a['kind'] === 'drive' ? '☁️' : fileIcon($a['ext']) ?>
            <?php endif; ?>
          </div>
          <div class="body">
            <b><?= e(excerpt($a['title'] ?: $a['file_name'], 28)) ?></b>
            <small><?= e(labelMediaGroup($a['media_group'])) ?>
              <?= $a['kind'] === 'file' ? ' · ' . e(Uploader::humanSize((int) $a['file_size'])) : ' · درايف' ?></small>
            <small class="truncate"><?= e(excerpt($a['owner_title'], 30)) ?></small>
            <div class="acts">
              <a class="btn outline xs" href="<?= e(url('attachments/viewer', ['id' => $a['id']])) ?>">عرض</a>
              <a class="btn outline xs" href="<?= e($L['download']) ?>" <?= $L['external'] ? 'target="_blank" rel="noopener"' : '' ?>>تحميل</a>
              <a class="btn ghost xs" href="<?= e(url($a['attachable_type'] === 'transaction' ? 'transactions/show' : 'documents/show',
                   ['id' => $a['attachable_id']])) ?>">المصدر</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?= $page->links() ?>
    <?php endif; ?>
  </div>
</div>
