<div class="page-head"><h1>🔍 البحث الشامل</h1></div>

<form class="filters" method="get" action="index.php">
  <input type="hidden" name="r" value="search/index">
  <div class="row">
    <div style="grid-column:span 2">
      <label>كلمة البحث</label>
      <input type="search" name="q" value="<?= e($q) ?>" autofocus placeholder="ابحث في المعاملات والوثائق والمرفقات...">
    </div>
    <div><label>نطاق البحث</label>
      <select name="scope">
        <option value="all"          <?= $scope === 'all' ? 'selected' : '' ?>>الكل</option>
        <option value="transactions" <?= $scope === 'transactions' ? 'selected' : '' ?>>المعاملات</option>
        <option value="documents"    <?= $scope === 'documents' ? 'selected' : '' ?>>الوثائق</option>
        <option value="attachments"  <?= $scope === 'attachments' ? 'selected' : '' ?>>المرفقات</option>
      </select>
    </div>
    <div class="flex">
      <button class="btn sm">بحث</button>
      <a class="btn ghost sm" href="<?= e(url('search/advanced')) ?>">بحث متقدم ⚙️</a>
    </div>
  </div>
</form>

<?php if (mb_strlen($q) < 2): ?>
  <div class="card"><div class="card-body">
    <div class="empty"><span class="big">🔎</span>اكتب حرفين على الأقل لبدء البحث</div>
  </div></div>
<?php else: ?>

  <p class="muted small">
    نتائج البحث عن «<b><?= e($q) ?></b>» —
    <?= num($counts['transactions']) ?> معاملة، <?= num($counts['documents']) ?> وثيقة، <?= num($counts['attachments']) ?> مرفق
  </p>

  <?php if ($results['transactions']): ?>
  <div class="card">
    <div class="card-head"><h3>🗃️ المعاملات (<?= count($results['transactions']) ?>)</h3></div>
    <div class="card-body tight">
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>الرقم</th><th>النوع</th><th>الموضوع</th><th>الجهة</th><th>التاريخ</th><th>الحالة</th></tr></thead>
          <tbody>
          <?php foreach ($results['transactions'] as $t): ?>
            <tr>
              <td class="nowrap"><a href="<?= e(url('transactions/show', ['id' => $t['id']])) ?>"><b><?= e($t['transaction_no']) ?></b></a></td>
              <td class="nowrap"><span class="badge brand"><?= e(labelDirection($t['direction'])) ?></span></td>
              <td><?= e(excerpt($t['subject'], 55)) ?></td>
              <td class="small"><?= e(excerpt($t['correspondent_name'], 22)) ?></td>
              <td class="nowrap small"><?= e(gdate($t['transaction_date'])) ?></td>
              <td class="nowrap"><span class="badge <?= e(statusClass($t['status'])) ?>"><?= e(labelStatus($t['status'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($results['documents']): ?>
  <div class="card">
    <div class="card-head"><h3>📄 الوثائق (<?= count($results['documents']) ?>)</h3></div>
    <div class="card-body tight">
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>العنوان</th><th>الرقم</th><th>النوع</th><th>المجلد</th><th>التاريخ</th><th>مرفقات</th></tr></thead>
          <tbody>
          <?php foreach ($results['documents'] as $d): ?>
            <tr>
              <td><a href="<?= e(url('documents/show', ['id' => $d['id']])) ?>"><b><?= e(excerpt($d['title'], 50)) ?></b></a></td>
              <td class="small nowrap"><?= e($d['doc_no'] ?: '-') ?></td>
              <td class="small nowrap"><?= e($d['doc_type'] ?: '-') ?></td>
              <td class="small"><?= e(excerpt($d['folder_name'], 22)) ?></td>
              <td class="small nowrap"><?= e(gdate($d['doc_date'])) ?></td>
              <td class="center"><?= (int) $d['attachments_count'] ?: '-' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($results['attachments']): ?>
  <div class="card">
    <div class="card-head"><h3>📎 المرفقات (<?= count($results['attachments']) ?>)</h3></div>
    <div class="card-body">
      <div class="att-list">
        <?php foreach ($results['attachments'] as $a): $L = AttachmentService::links($a); ?>
          <div class="att <?= $a['kind'] === 'drive' ? 'drive' : '' ?>">
            <div class="ico"><?= $a['kind'] === 'drive' ? '☁️' : fileIcon($a['ext']) ?></div>
            <div class="info">
              <b><?= e($a['title'] ?: $a['file_name']) ?></b>
              <small><?= e(labelMediaGroup($a['media_group'])) ?> · <?= e(excerpt($a['owner_title'], 45)) ?></small>
            </div>
            <div class="acts">
              <a class="btn outline xs" href="<?= e(url('attachments/viewer', ['id' => $a['id']])) ?>">عرض</a>
              <a class="btn outline xs" href="<?= e(url($a['attachable_type'] === 'transaction' ? 'transactions/show' : 'documents/show',
                   ['id' => $a['attachable_id']])) ?>">المصدر</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!array_filter($counts)): ?>
    <div class="card"><div class="card-body">
      <div class="empty"><span class="big">🤷</span>لا توجد نتائج مطابقة لبحثك</div>
    </div></div>
  <?php endif; ?>
<?php endif; ?>
