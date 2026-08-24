<div class="page-head">
  <h1>📄 الوثائق والملفات</h1>
  <span class="spacer"></span>
  <?php if (can('documents.delete')): ?>
    <a class="btn outline sm" href="<?= e(url('documents/trash')) ?>">🗑️ المحذوفات</a>
  <?php endif; ?>
  <?php if (can('documents.create')): ?>
    <a class="btn" href="<?= e(url('documents/create')) ?>">➕ وثيقة جديدة</a>
  <?php endif; ?>
</div>

<form class="filters" method="get" action="index.php">
  <input type="hidden" name="r" value="documents/index">
  <div class="row">
    <div><label>بحث</label><input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="عنوان أو رقم..."></div>
    <div><label>المجلد</label>
      <select name="folder">
        <option value="">كل المجلدات</option>
        <?php foreach ($lists['folders'] as $f): ?>
          <option value="<?= (int) $f['id'] ?>" <?= (int) $filters['folder'] === (int) $f['id'] ? 'selected' : '' ?>><?= e($f['_label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label>نوع الوثيقة</label>
      <select name="type">
        <option value="">الكل</option>
        <?php foreach ($lists['types'] as $t): ?>
          <option value="<?= e($t) ?>" <?= $filters['type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label>نوع المرفق</label>
      <select name="media">
        <option value="">الكل</option>
        <?php foreach (['document','image','video','audio','archive'] as $m): ?>
          <option value="<?= $m ?>" <?= $filters['media'] === $m ? 'selected' : '' ?>><?= e(labelMediaGroup($m)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label>السرية</label>
      <select name="conf">
        <option value="">الكل</option>
        <?php foreach (['normal','confidential','top_secret'] as $c): ?>
          <option value="<?= $c ?>" <?= $filters['conf'] === $c ? 'selected' : '' ?>><?= e(labelConfidentiality($c)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label>العام الدراسي</label>
      <select name="year">
        <option value="">الكل</option>
        <?php foreach ($lists['years'] as $y): ?>
          <option value="<?= (int) $y['id'] ?>" <?= (int) $filters['year'] === (int) $y['id'] ? 'selected' : '' ?>><?= e($y['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label>من تاريخ</label><input type="date" name="from" value="<?= e($filters['from']) ?>"></div>
    <div><label>إلى تاريخ</label><input type="date" name="to" value="<?= e($filters['to']) ?>"></div>
    <div class="flex">
      <button class="btn sm">🔍 بحث</button>
      <a class="btn ghost sm" href="<?= e(url('documents/index')) ?>">إلغاء</a>
    </div>
  </div>
</form>

<div class="card">
  <div class="card-head">
    <h3>النتائج</h3><span class="spacer"></span>
    <span class="chip"><?= num($page->total) ?> وثيقة</span>
  </div>
  <div class="card-body tight">
    <?php if (!$page->items): ?>
      <div class="empty"><span class="big">📂</span>لا توجد وثائق مطابقة</div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>العنوان</th><th>الرقم</th><th>النوع</th><th>المجلد</th><th>التاريخ</th>
                   <th>السرية</th><th>مرفقات</th><th>المُدخل</th><th>إجراءات</th></tr></thead>
        <tbody>
        <?php foreach ($page->items as $d): ?>
          <tr>
            <td><a href="<?= e(url('documents/show', ['id' => $d['id']])) ?>"><b><?= e(excerpt($d['title'], 48)) ?></b></a></td>
            <td class="nowrap small"><?= e($d['doc_no'] ?: '-') ?></td>
            <td class="nowrap small"><?= e($d['doc_type'] ?: '-') ?></td>
            <td class="small"><a href="<?= e(url('folders/index', ['id' => $d['folder_id']])) ?>">🗂️ <?= e(excerpt($d['folder_name'], 20)) ?></a></td>
            <td class="nowrap small"><?= e(gdate($d['doc_date'] ?: $d['created_at'])) ?></td>
            <td class="nowrap">
              <?php if ($d['confidentiality'] !== 'normal'): ?>
                <span class="badge danger">🔒 <?= e(labelConfidentiality($d['confidentiality'])) ?></span>
              <?php else: ?><span class="badge muted">عادي</span><?php endif; ?>
            </td>
            <td class="center"><?= (int) $d['attachments_count'] ? '📎 ' . (int) $d['attachments_count'] : '-' ?></td>
            <td class="small"><?= e($d['creator'] ?? '-') ?></td>
            <td class="actions">
              <div class="btn-group">
                <a class="btn outline xs" href="<?= e(url('documents/show', ['id' => $d['id']])) ?>">عرض</a>
                <?php if (can('documents.edit')): ?>
                  <a class="btn outline xs" href="<?= e(url('documents/edit', ['id' => $d['id']])) ?>">تعديل</a>
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
