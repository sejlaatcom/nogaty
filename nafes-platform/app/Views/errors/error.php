<div class="card text-center mx-auto" style="max-width:560px">
  <div class="card-body p-5">
    <div class="display-5 fw-bold text-primary mb-2"><?= (int) $status ?></div>
    <p class="fs-5 mb-4"><?= e($message) ?></p>
    <a href="<?= e(url(\App\Core\Auth::check() ? \App\Core\Auth::home() : '/login')) ?>" class="btn btn-primary">العودة للرئيسية</a>
    <button class="btn btn-light" onclick="history.back()">رجوع</button>
  </div>
</div>
