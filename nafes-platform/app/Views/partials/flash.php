<?php foreach (\App\Core\Session::takeFlash() as [$type, $message]): ?>
  <?php $type = in_array($type, ['success', 'danger', 'warning', 'info'], true) ? $type : 'info'; ?>
  <div class="alert alert-<?= $type ?> alert-dismissible fade show d-flex align-items-start gap-2" role="alert">
    <i class="bi bi-<?= ['success' => 'check-circle', 'danger' => 'x-octagon', 'warning' => 'exclamation-triangle', 'info' => 'info-circle'][$type] ?> mt-1"></i>
    <div><?= nl2br(e($message)) ?></div>
    <button type="button" class="btn-close ms-0 me-auto" data-bs-dismiss="alert" aria-label="إغلاق"></button>
  </div>
<?php endforeach; ?>
