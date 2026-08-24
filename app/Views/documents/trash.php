<div class="page-head">
  <h1>🗑️ الوثائق المحذوفة</h1>
  <span class="spacer"></span>
  <a class="btn ghost" href="<?= e(url('documents/index')) ?>">↩︎ رجوع</a>
</div>

<div class="card">
  <div class="card-body tight">
    <?php if (!$page->items): ?>
      <div class="empty"><span class="big">✨</span>لا توجد وثائق محذوفة</div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>العنوان</th><th>المجلد</th><th>تاريخ الحذف</th><th>إجراءات</th></tr></thead>
        <tbody>
        <?php foreach ($page->items as $d): ?>
          <tr>
            <td><?= e(excerpt($d['title'], 55)) ?></td>
            <td class="small"><?= e($d['folder_name']) ?></td>
            <td class="nowrap small"><?= e(gdate($d['deleted_at'], true)) ?></td>
            <td class="actions">
              <div class="btn-group">
                <form method="post" action="<?= e(url('documents/restore')) ?>" style="display:inline">
                  <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                  <button class="btn ok xs">♻️ استرجاع</button>
                </form>
                <form method="post" action="<?= e(url('documents/forceDelete')) ?>" style="display:inline"
                      data-confirm="حذف نهائي للوثيقة ومرفقاتها؟">
                  <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                  <button class="btn danger xs">حذف نهائي</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= $page->links() ?>
    <?php endif; ?>
  </div>
</div>
