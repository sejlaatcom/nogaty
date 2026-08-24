<div class="page-head">
  <h1>🔔 الإشعارات</h1>
  <span class="spacer"></span>
  <form method="post" action="<?= e(url('notifications/readAll')) ?>">
    <?= Csrf::field() ?>
    <button class="btn outline sm">✓ تعليم الكل كمقروء</button>
  </form>
</div>

<div class="card">
  <div class="card-body tight">
    <?php if (!$page->items): ?>
      <div class="empty"><span class="big">🔕</span>لا توجد إشعارات</div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th></th><th>الإشعار</th><th>التفاصيل</th><th>الوقت</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($page->items as $n): ?>
          <tr style="<?= $n['is_read'] ? '' : 'background:#f0fdfa' ?>">
            <td class="center"><?= $n['is_read'] ? '📖' : '🔵' ?></td>
            <td><b><?= e($n['title']) ?></b></td>
            <td class="small muted"><?= e($n['message'] ?: '-') ?></td>
            <td class="nowrap small"><?= e(timeAgo($n['created_at'])) ?></td>
            <td class="actions">
              <a class="btn outline xs" href="<?= e(url('notifications/read', ['id' => $n['id']])) ?>">فتح</a>
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
