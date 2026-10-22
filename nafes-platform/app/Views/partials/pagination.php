<?php
/** @var array $result rows,total,page,pages */
if (($result['pages'] ?? 1) > 1):
    $q = $_GET; ?>
<nav class="d-flex justify-content-between align-items-center p-3 border-top flex-wrap gap-2">
  <span class="small text-muted">صفحة <?= (int) $result['page'] ?> من <?= (int) $result['pages'] ?> — <?= num($result['total']) ?> سجل</span>
  <ul class="pagination pagination-sm mb-0">
    <?php for ($i = 1; $i <= $result['pages']; $i++):
        if ($result['pages'] > 11 && $i > 2 && $i < $result['pages'] - 1 && abs($i - $result['page']) > 2) { if (empty($dots)) { echo '<li class="page-item disabled"><span class="page-link">…</span></li>'; $dots = true; } continue; }
        $dots = false; $q['page'] = $i; ?>
      <li class="page-item <?= $i === (int) $result['page'] ? 'active' : '' ?>"><a class="page-link" href="?<?= e(http_build_query($q)) ?>"><?= $i ?></a></li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>
