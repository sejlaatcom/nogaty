<div class="page-head">
  <h1>📥 الوارد إليّ</h1>
  <span class="spacer"></span>
  <div class="btn-group">
    <a class="btn <?= $status === null ? '' : 'outline' ?> sm" href="<?= e(url('transactions/inbox')) ?>">الكل</a>
    <a class="btn <?= $status === 'pending' ? '' : 'outline' ?> sm" href="<?= e(url('transactions/inbox', ['status' => 'pending'])) ?>">جديدة</a>
    <a class="btn <?= $status === 'seen' ? '' : 'outline' ?> sm" href="<?= e(url('transactions/inbox', ['status' => 'seen'])) ?>">مطّلع عليها</a>
    <a class="btn <?= $status === 'done' ? '' : 'outline' ?> sm" href="<?= e(url('transactions/inbox', ['status' => 'done'])) ?>">منجزة</a>
  </div>
</div>

<div class="card">
  <div class="card-body tight">
    <?php if (!$page->items): ?>
      <div class="empty"><span class="big">🎉</span>لا توجد معاملات محالة إليك</div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr><th>الرقم</th><th>الموضوع</th><th>نوع الإحالة</th><th>التأشيرة</th>
              <th>من</th><th>موعد الإنجاز</th><th>الأهمية</th><th>الحالة</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($page->items as $t): ?>
          <tr style="<?= $t['routing_status'] === 'pending' ? 'background:#f0fdfa' : '' ?>">
            <td class="nowrap"><a href="<?= e(url('transactions/show', ['id' => $t['id']])) ?>"><b><?= e($t['transaction_no']) ?></b></a></td>
            <td><?= e(excerpt($t['subject'], 50)) ?></td>
            <td class="nowrap"><span class="badge info"><?= e(labelRoutingAction($t['action'])) ?></span></td>
            <td class="small muted"><?= e(excerpt($t['instructions'], 45)) ?></td>
            <td class="small nowrap"><?= e($t['from_name'] ?? '-') ?></td>
            <td class="nowrap small">
              <?php if ($t['routing_due']): ?>
                <?= e(gdate($t['routing_due'])) ?>
                <?php if ($t['routing_due'] < date('Y-m-d') && $t['routing_status'] !== 'done'): ?>
                  <span class="badge danger">متأخر</span>
                <?php endif; ?>
              <?php else: ?><span class="muted">-</span><?php endif; ?>
            </td>
            <td class="nowrap"><span class="badge <?= e(priorityClass($t['priority'])) ?>"><?= e(labelPriority($t['priority'])) ?></span></td>
            <td class="nowrap">
              <span class="badge <?= $t['routing_status'] === 'done' ? 'ok' : ($t['routing_status'] === 'returned' ? 'danger' : 'warn') ?>">
                <?= e(labelRoutingStatus($t['routing_status'])) ?>
              </span>
            </td>
            <td class="actions"><a class="btn outline xs" href="<?= e(url('transactions/show', ['id' => $t['id']])) ?>">فتح</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= $page->links() ?>
    <?php endif; ?>
  </div>
</div>
