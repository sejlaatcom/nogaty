<div class="page-head"><h1>📜 سجل الأحداث</h1></div>

<form class="filters" method="get" action="index.php">
  <input type="hidden" name="r" value="audit/index">
  <div class="row">
    <div><label>المستخدم</label>
      <select name="user">
        <option value="">الكل</option>
        <?php foreach ($users as $u): ?>
          <option value="<?= (int) $u['id'] ?>" <?= (int) $filters['user'] === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label>العملية</label>
      <select name="action">
        <option value="">الكل</option>
        <?php foreach ($actions as $a): ?>
          <option value="<?= e($a) ?>" <?= $filters['action'] === $a ? 'selected' : '' ?>><?= e(Audit::actionLabel($a)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label>نوع العنصر</label>
      <select name="entity">
        <option value="">الكل</option>
        <?php foreach ($entities as $en): ?>
          <option value="<?= e($en) ?>" <?= $filters['entity'] === $en ? 'selected' : '' ?>><?= e($en) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label>من تاريخ</label><input type="date" name="from" value="<?= e($filters['from']) ?>"></div>
    <div><label>إلى تاريخ</label><input type="date" name="to" value="<?= e($filters['to']) ?>"></div>
    <div><label>بحث في الوصف</label><input type="search" name="q" value="<?= e($filters['q']) ?>"></div>
    <div class="flex"><button class="btn sm">بحث</button><a class="btn ghost sm" href="<?= e(url('audit/index')) ?>">إلغاء</a></div>
  </div>
</form>

<div class="card">
  <div class="card-head"><h3>السجل</h3><span class="spacer"></span><span class="chip"><?= num($page->total) ?> حدث</span></div>
  <div class="card-body tight">
    <?php if (!$page->items): ?>
      <div class="empty"><span class="big">📜</span>لا توجد أحداث مطابقة</div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>التاريخ والوقت</th><th>المستخدم</th><th>العملية</th><th>العنصر</th><th>الوصف</th><th>عنوان IP</th></tr></thead>
        <tbody>
        <?php foreach ($page->items as $l): ?>
          <tr>
            <td class="nowrap small"><?= e(gdate($l['created_at'], true)) ?></td>
            <td class="small"><?= e($l['user_name'] ?? 'النظام') ?></td>
            <td class="nowrap">
              <span class="badge <?= in_array($l['action'], ['delete'], true) ? 'danger'
                    : (in_array($l['action'], ['create','login'], true) ? 'ok' : 'info') ?>">
                <?= e(Audit::actionLabel($l['action'])) ?>
              </span>
            </td>
            <td class="small nowrap"><?= e($l['entity_type'] ?: '-') ?><?= $l['entity_id'] ? ' #' . (int) $l['entity_id'] : '' ?></td>
            <td class="small"><?= e($l['description'] ?: '-') ?></td>
            <td class="small ltr"><?= e($l['ip_address']) ?></td>
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
