<div class="page-head">
  <h1><?= e($title) ?></h1>
  <span class="spacer"></span>
  <?php if (can('transactions.export')): ?>
    <a class="btn outline sm" href="<?= e(url('transactions/export', array_merge($_GET, ['r' => null]))) ?>">📊 تصدير Excel</a>
  <?php endif; ?>
  <?php if (can('transactions.delete')): ?>
    <a class="btn outline sm" href="<?= e(url('transactions/trash')) ?>">🗑️ المحذوفات</a>
  <?php endif; ?>
  <?php if (can('transactions.create')): ?>
    <a class="btn" href="<?= e(url('transactions/create', ['direction' => $direction ?: 'incoming'])) ?>">➕ قيد معاملة</a>
  <?php endif; ?>
</div>

<!-- ================================ الفلاتر ================================ -->
<form class="filters" method="get" action="index.php">
  <input type="hidden" name="r" value="transactions/index">
  <?php if ($direction): ?><input type="hidden" name="direction" value="<?= e($direction) ?>"><?php endif; ?>
  <div class="row">
    <div><label>بحث</label><input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="رقم أو موضوع..."></div>
    <div><label>الحالة</label>
      <select name="status">
        <option value="">الكل</option>
        <?php foreach (['draft','registered','in_progress','completed','archived','closed'] as $s): ?>
          <option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(labelStatus($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label>الأهمية</label>
      <select name="priority">
        <option value="">الكل</option>
        <?php foreach (['normal','urgent','very_urgent'] as $s): ?>
          <option value="<?= $s ?>" <?= $filters['priority'] === $s ? 'selected' : '' ?>><?= e(labelPriority($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label>الجهة</label>
      <select name="corr">
        <option value="">الكل</option>
        <?php foreach ($lists['correspondents'] as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (int) $filters['corr'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label>التصنيف</label>
      <select name="category">
        <option value="">الكل</option>
        <?php foreach ($lists['categories'] as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (int) $filters['category'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label>مجلد الأرشيف</label>
      <select name="folder">
        <option value="">الكل</option>
        <?php foreach ($lists['folders'] as $f): ?>
          <option value="<?= (int) $f['id'] ?>" <?= (int) $filters['folder'] === (int) $f['id'] ? 'selected' : '' ?>><?= e($f['_label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label>من تاريخ</label><input type="date" name="from" value="<?= e($filters['from']) ?>"></div>
    <div><label>إلى تاريخ</label><input type="date" name="to" value="<?= e($filters['to']) ?>"></div>
    <div>
      <label>&nbsp;</label>
      <label class="check"><input type="checkbox" name="overdue" value="1" <?= $filters['overdue'] ? 'checked' : '' ?>> المتأخرة فقط</label>
    </div>
    <div>
      <label>&nbsp;</label>
      <label class="check"><input type="checkbox" name="has_attach" value="1" <?= $filters['has_attach'] ? 'checked' : '' ?>> لها مرفقات</label>
    </div>
    <div class="flex">
      <button class="btn sm" type="submit">🔍 بحث</button>
      <a class="btn ghost sm" href="<?= e(url('transactions/index', $direction ? ['direction' => $direction] : [])) ?>">إلغاء</a>
    </div>
  </div>
</form>

<!-- ================================ النتائج ================================ -->
<div class="card">
  <div class="card-head">
    <h3>النتائج</h3>
    <span class="spacer"></span>
    <span class="chip"><?= num($page->total) ?> معاملة</span>
  </div>
  <div class="card-body tight">
    <?php if (!$page->items): ?>
      <div class="empty"><span class="big">📭</span>لا توجد معاملات مطابقة لمعايير البحث</div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>رقم المعاملة</th><th>النوع</th><th>الموضوع</th><th>الجهة</th>
            <th>التاريخ</th><th>التصنيف</th><th>الأهمية</th><th>الحالة</th>
            <th>مرفقات</th><th>إجراءات</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($page->items as $t): ?>
          <tr>
            <td class="nowrap">
              <a href="<?= e(url('transactions/show', ['id' => $t['id']])) ?>"><b><?= e($t['transaction_no']) ?></b></a>
              <?php if ($t['reference_no']): ?><br><small class="muted"><?= e($t['reference_no']) ?></small><?php endif; ?>
            </td>
            <td class="nowrap"><span class="badge brand"><?= e(labelDirection($t['direction'])) ?></span></td>
            <td>
              <a href="<?= e(url('transactions/show', ['id' => $t['id']])) ?>"><?= e(excerpt($t['subject'], 55)) ?></a>
              <?php if ($t['confidentiality'] !== 'normal'): ?>
                <span class="badge danger">🔒 <?= e(labelConfidentiality($t['confidentiality'])) ?></span>
              <?php endif; ?>
              <?php if ($t['due_date'] && $t['due_date'] < date('Y-m-d')
                        && !in_array($t['status'], ['completed','closed','archived'], true)): ?>
                <span class="badge danger">متأخرة</span>
              <?php endif; ?>
            </td>
            <td class="small"><?= e(excerpt($t['correspondent_name'] ?? ($t['from_dept'] ?: '-'), 24)) ?></td>
            <td class="nowrap small">
              <?= e(gdate($t['transaction_date'])) ?>
              <br><span class="muted xsmall"><?= e($t['hijri_date']) ?></span>
            </td>
            <td class="nowrap">
              <?php if ($t['category_name']): ?>
                <span class="badge" style="background:<?= e($t['category_color'] ?: '#f1f5f9') ?>22;color:<?= e($t['category_color'] ?: '#64748b') ?>"><?= e($t['category_name']) ?></span>
              <?php else: ?><span class="muted">-</span><?php endif; ?>
            </td>
            <td class="nowrap"><span class="badge <?= e(priorityClass($t['priority'])) ?>"><?= e(labelPriority($t['priority'])) ?></span></td>
            <td class="nowrap"><span class="badge <?= e(statusClass($t['status'])) ?>"><?= e(labelStatus($t['status'])) ?></span></td>
            <td class="center"><?= (int) $t['attachments_count'] ? '📎 ' . (int) $t['attachments_count'] : '-' ?></td>
            <td class="actions">
              <div class="btn-group">
                <a class="btn outline xs" href="<?= e(url('transactions/show', ['id' => $t['id']])) ?>">عرض</a>
                <?php if (can('transactions.edit')): ?>
                  <a class="btn outline xs" href="<?= e(url('transactions/edit', ['id' => $t['id']])) ?>">تعديل</a>
                <?php endif; ?>
                <?php if (can('transactions.export')): ?>
                  <a class="btn outline xs" href="<?= e(url('transactions/printView', ['id' => $t['id']])) ?>" target="_blank">🖨️</a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= $page->links() ?>
    <div class="pager-info">عرض <?= num($page->from()) ?> – <?= num($page->to()) ?> من <?= num($page->total) ?></div>
    <?php endif; ?>
  </div>
</div>
