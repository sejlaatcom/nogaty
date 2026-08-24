<?php
/**
 * عرض قائمة المرفقات
 * المتغيرات : $attachments, $ownerType, $ownerId, $readonly (اختياري)
 */
$readonly = $readonly ?? false;
?>
<?php if (!$attachments): ?>
  <div class="empty"><span class="big">📎</span>لا توجد مرفقات</div>
<?php else: ?>
<div class="att-list">
  <?php foreach ($attachments as $a): $L = AttachmentService::links($a); ?>
    <div class="att <?= $a['kind'] === 'drive' ? 'drive' : '' ?>">
      <div class="ico"><?= $a['kind'] === 'drive' ? '☁️' : fileIcon($a['ext']) ?></div>
      <div class="info">
        <b><?= e($a['title'] ?: $a['file_name']) ?></b>
        <small>
          <?= e(labelMediaGroup($a['media_group'])) ?>
          <?php if ($a['kind'] === 'file'): ?>
            · <?= e(strtoupper((string) $a['ext'])) ?> · <?= e(Uploader::humanSize((int) $a['file_size'])) ?>
          <?php else: ?>
            · قوقل درايف
          <?php endif; ?>
          · <?= e($a['uploader_name'] ?? '-') ?>
          · <?= e(gdate($a['created_at'])) ?>
          <?php if ((int) $a['downloads'] > 0): ?> · <?= (int) $a['downloads'] ?> تحميل<?php endif; ?>
        </small>
      </div>
      <div class="acts">
        <a class="btn outline xs" href="<?= e(url('attachments/viewer', ['id' => $a['id']])) ?>">👁️ عرض</a>
        <a class="btn outline xs" href="<?= e($L['download']) ?>" <?= $L['external'] ? 'target="_blank" rel="noopener"' : '' ?>>⬇️ تحميل</a>
        <?php if ($a['kind'] === 'drive'): ?>
          <a class="btn outline xs" href="<?= e($L['open']) ?>" target="_blank" rel="noopener">☁️ درايف</a>
        <?php endif; ?>
        <?php if (!$readonly && can('attachments.delete')): ?>
          <form method="post" action="<?= e(url('attachments/delete')) ?>" style="display:inline"
                data-confirm="هل تريد حذف هذا المرفق نهائياً؟">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
            <button type="submit" class="btn danger xs">🗑️</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$readonly && can('attachments.upload')): ?>
<details class="mt">
  <summary class="btn outline sm" style="display:inline-flex">➕ إضافة مرفقات جديدة</summary>
  <form method="post" action="<?= e(url('attachments/store')) ?>" enctype="multipart/form-data" class="mt" data-once>
    <?= Csrf::field() ?>
    <input type="hidden" name="attachable_type" value="<?= e($ownerType) ?>">
    <input type="hidden" name="attachable_id" value="<?= (int) $ownerId ?>">
    <?php View::partial('attachment_fields'); ?>
    <button type="submit" class="btn">حفظ المرفقات</button>
  </form>
</details>
<?php endif; ?>
