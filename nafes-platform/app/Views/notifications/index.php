<?php use App\Services\Notifier; ?>
<div class="page-header">
  <div><h1>الإشعارات</h1><div class="sub">آخر 200 إشعار</div></div>
  <form method="post" action="<?= e(url('/notifications/read-all')) ?>"><?= csrf_field() ?><button class="btn btn-soft"><i class="bi bi-check2-all"></i> تعليم الكل كمقروء</button></form>
</div>
<div class="card">
  <div class="list-group list-group-flush">
    <?php foreach ($items as $n): ?>
      <?php [$label, $icon, $color] = Notifier::TYPES[$n['type']] ?? Notifier::TYPES['system']; ?>
      <a href="<?= e(url('/notifications/' . (int) $n['id'] . '/open')) ?>" class="list-group-item list-group-item-action d-flex gap-3 py-3 <?= (int) $n['is_read'] ? '' : 'bg-light' ?>">
        <span class="stat-card p-0"><span class="icon" style="width:42px;height:42px;font-size:1.1rem"><i class="bi bi-<?= e($icon) ?>"></i></span></span>
        <span class="flex-grow-1">
          <strong class="d-block"><?= e($n['title']) ?> <?= (int) $n['is_read'] ? '' : '<span class="badge badge-soft-danger">جديد</span>' ?></strong>
          <?php if ($n['body']): ?><span class="text-muted small"><?= e($n['body']) ?></span><br><?php endif; ?>
          <small class="text-muted"><?= e($label) ?> · <?= e(ago($n['created_at'])) ?></small>
        </span>
      </a>
    <?php endforeach; ?>
    <?php if (!$items): ?><div class="empty-state"><i class="bi bi-bell-slash"></i>لا توجد إشعارات.</div><?php endif; ?>
  </div>
</div>
