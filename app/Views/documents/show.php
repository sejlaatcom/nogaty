<div class="page-head">
  <h1>📄 <?= e(excerpt($doc['title'], 60)) ?></h1>
  <span class="spacer"></span>
  <?php if (can('documents.edit')): ?>
    <a class="btn outline sm" href="<?= e(url('documents/edit', ['id' => $doc['id']])) ?>">✏️ تعديل</a>
  <?php endif; ?>
  <?php if (can('shares.manage')): ?>
    <button class="btn outline sm" data-modal="modal-share">🔗 مشاركة</button>
  <?php endif; ?>
  <?php if (can('documents.delete')): ?>
    <form method="post" action="<?= e(url('documents/delete')) ?>" style="display:inline" data-confirm="نقل الوثيقة للمحذوفات؟">
      <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $doc['id'] ?>">
      <button class="btn danger sm">🗑️ حذف</button>
    </form>
  <?php endif; ?>
</div>

<div class="breadcrumb">
  <a href="<?= e(url('folders/index')) ?>">🌳 الجذر</a>
  <?php foreach ($breadcrumb as $b): ?>
    <span class="sep">/</span>
    <a href="<?= e(url('folders/index', ['id' => $b['id']])) ?>"><?= e($b['name']) ?></a>
  <?php endforeach; ?>
</div>

<div class="grid" style="grid-template-columns:2fr 1fr">
  <div>
    <div class="card">
      <div class="card-head"><h3><?= e($doc['title']) ?></h3></div>
      <div class="card-body">
        <div class="flex mb">
          <?php if ($doc['doc_type']): ?><span class="badge brand"><?= e($doc['doc_type']) ?></span><?php endif; ?>
          <?php if ($doc['confidentiality'] !== 'normal'): ?>
            <span class="badge danger">🔒 <?= e(labelConfidentiality($doc['confidentiality'])) ?></span>
          <?php endif; ?>
          <?php foreach ($tags as $t): ?><span class="chip">#<?= e($t) ?></span><?php endforeach; ?>
        </div>
        <dl class="dl">
          <?php if ($doc['doc_no']): ?><dt>رقم الوثيقة</dt><dd dir="ltr"><?= e($doc['doc_no']) ?></dd><?php endif; ?>
          <?php if ($doc['doc_date']): ?>
            <dt>التاريخ</dt><dd><?= e(gdate($doc['doc_date'])) ?> م — <?= e($doc['hijri_date'] ?: Hijri::format($doc['doc_date'])) ?></dd>
          <?php endif; ?>
          <dt>المجلد</dt><dd><a href="<?= e(url('folders/index', ['id' => $doc['folder_id']])) ?>">🗂️ <?= e($doc['folder_name']) ?></a></dd>
          <?php if ($doc['year_name']): ?><dt>العام الدراسي</dt><dd><?= e($doc['year_name']) ?></dd><?php endif; ?>
          <?php if ($doc['keywords']): ?><dt>كلمات مفتاحية</dt><dd class="small"><?= e($doc['keywords']) ?></dd><?php endif; ?>
          <dt>أُضيفت بواسطة</dt><dd><?= e($doc['creator'] ?? '-') ?> — <?= e(gdate($doc['created_at'], true)) ?></dd>
          <?php if ($doc['updater']): ?><dt>آخر تعديل</dt><dd><?= e($doc['updater']) ?> — <?= e(gdate($doc['updated_at'], true)) ?></dd><?php endif; ?>
        </dl>
        <?php if ($doc['description']): ?>
          <hr><div style="white-space:pre-wrap"><?= e($doc['description']) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <h3>📎 المرفقات</h3><span class="spacer"></span>
        <span class="chip"><?= count($attachments) ?> مرفق</span>
      </div>
      <div class="card-body">
        <?php View::partial('attachment_list', [
          'attachments' => $attachments, 'ownerType' => 'document', 'ownerId' => (int) $doc['id'],
        ]); ?>
      </div>
    </div>
  </div>

  <div>
    <?php
    // معاينة سريعة لأول مرفق قابل للعرض
    $previewable = null;
    foreach ($attachments as $a) {
        if ($a['kind'] === 'drive' || in_array($a['media_group'], ['image','video','audio'], true)
            || strtolower((string) $a['ext']) === 'pdf') { $previewable = $a; break; }
    }
    ?>
    <?php if ($previewable): $L = AttachmentService::links($previewable); ?>
    <div class="card">
      <div class="card-head"><h3>👁️ معاينة سريعة</h3></div>
      <div class="card-body tight">
        <?php if ($previewable['kind'] === 'drive'): ?>
          <iframe src="<?= e($L['preview']) ?>" class="viewer-frame" style="height:320px" allow="autoplay"></iframe>
        <?php elseif ($previewable['media_group'] === 'image'): ?>
          <img src="<?= e($L['preview']) ?>" alt="<?= e($previewable['title']) ?>" style="width:100%;border-radius:8px">
        <?php elseif ($previewable['media_group'] === 'video'): ?>
          <video src="<?= e($L['preview']) ?>" class="media-box" controls></video>
        <?php elseif ($previewable['media_group'] === 'audio'): ?>
          <audio src="<?= e($L['preview']) ?>" style="width:100%" controls></audio>
        <?php else: ?>
          <iframe src="<?= e($L['preview']) ?>" class="viewer-frame" style="height:320px"></iframe>
        <?php endif; ?>
        <div class="center mt-sm">
          <a class="btn outline sm" href="<?= e(url('attachments/viewer', ['id' => $previewable['id']])) ?>">فتح العارض الكامل</a>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if (can('shares.manage') && $shares): ?>
    <div class="card">
      <div class="card-head"><h3>🔗 روابط المشاركة</h3></div>
      <div class="card-body">
        <?php foreach ($shares as $s):
          $link = (($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '')
                 . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\') . '/index.php?r=share/view&token=' . $s['token']); ?>
          <div class="sep-y small">
            <div class="flex-between">
              <span class="truncate ltr xsmall" style="max-width:55%"><?= e($s['token']) ?></span>
              <span class="btn-group">
                <button class="btn outline xs" data-copy="<?= e($link) ?>">نسخ</button>
                <form method="post" action="<?= e(url('share/revoke')) ?>" style="display:inline" data-confirm="إلغاء الرابط؟">
                  <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                  <button class="btn danger xs">إلغاء</button>
                </form>
              </span>
            </div>
            <div class="muted xsmall">ينتهي <?= e(gdate($s['expires_at'], true)) ?> · <?= (int) $s['views'] ?> مشاهدة</div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if (can('shares.manage')): ?>
<div class="modal" id="modal-share">
  <div class="modal-box">
    <form method="post" action="<?= e(url('share/create')) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="shareable_type" value="document">
      <input type="hidden" name="shareable_id" value="<?= (int) $doc['id'] ?>">
      <div class="modal-head"><h3>🔗 إنشاء رابط مشاركة</h3>
        <button type="button" class="x" onclick="closeModal('modal-share')">&times;</button></div>
      <div class="modal-body">
        <div class="form-grid two">
          <div class="field"><label>مدة الصلاحية (أيام)</label><input type="number" name="expires_days" value="7" min="1" max="365"></div>
          <div class="field"><label>كلمة مرور (اختياري)</label><input type="text" name="share_password" dir="ltr"></div>
        </div>
        <label class="check mt-sm"><input type="checkbox" name="allow_download" value="1" checked> السماح بتحميل المرفقات</label>
      </div>
      <div class="modal-foot">
        <button class="btn">إنشاء</button>
        <button type="button" class="btn outline" onclick="closeModal('modal-share')">إلغاء</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
