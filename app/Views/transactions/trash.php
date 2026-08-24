<div class="page-head">
  <h1>🗑️ سلة المحذوفات — المعاملات</h1>
  <span class="spacer"></span>
  <a class="btn ghost" href="<?= e(url('transactions/index')) ?>">↩︎ رجوع للمعاملات</a>
</div>

<div class="alert warning">
  <span>⚠️</span>
  <span>المعاملات هنا محذوفة منطقياً ويمكن استرجاعها. الحذف النهائي يزيل المعاملة ومرفقاتها من الخادم ولا يمكن التراجع عنه.</span>
</div>

<div class="card">
  <div class="card-body tight">
    <?php if (!$page->items): ?>
      <div class="empty"><span class="big">✨</span>سلة المحذوفات فارغة</div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>الرقم</th><th>النوع</th><th>الموضوع</th><th>تاريخ الحذف</th><th>المُدخل</th><th>إجراءات</th></tr></thead>
        <tbody>
        <?php foreach ($page->items as $t): ?>
          <tr>
            <td class="nowrap"><b><?= e($t['transaction_no']) ?></b></td>
            <td class="nowrap"><span class="badge muted"><?= e(labelDirection($t['direction'])) ?></span></td>
            <td><?= e(excerpt($t['subject'], 55)) ?></td>
            <td class="nowrap small"><?= e(gdate($t['deleted_at'], true)) ?></td>
            <td class="small"><?= e($t['creator_name'] ?? '-') ?></td>
            <td class="actions">
              <div class="btn-group">
                <form method="post" action="<?= e(url('transactions/restore')) ?>" style="display:inline">
                  <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                  <button class="btn ok xs">♻️ استرجاع</button>
                </form>
                <form method="post" action="<?= e(url('transactions/forceDelete')) ?>" style="display:inline"
                      data-confirm="حذف نهائي للمعاملة ومرفقاتها. لا يمكن التراجع. متابعة؟">
                  <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
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
