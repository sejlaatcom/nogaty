<?php use App\Services\TestEngine as TE; ?>
<div class="row justify-content-center"><div class="col-lg-7">
  <div class="card">
    <div class="card-header"><h2><?= e($test['title']) ?></h2><span class="badge badge-soft-primary"><?= e(TE::TYPES[$test['type']]) ?></span></div>
    <div class="card-body">
      <?php if ($test['description']): ?><p><?= nl2br(e($test['description'])) ?></p><?php endif; ?>
      <div class="row g-2 text-center mb-3">
        <div class="col-4"><div class="border rounded-3 p-2"><div class="fw-bold fs-5"><?= (int) $test['questions_count'] ?></div><small>سؤالاً</small></div></div>
        <div class="col-4"><div class="border rounded-3 p-2"><div class="fw-bold fs-5"><?= (int) $test['duration_minutes'] ?: '∞' ?></div><small>دقيقة</small></div></div>
        <div class="col-4"><div class="border rounded-3 p-2"><div class="fw-bold fs-5"><?= (int) $test['max_attempts'] ?: '∞' ?></div><small>محاولة (استخدمت <?= count($attempts) ?>)</small></div></div>
      </div>
      <h3 class="h6">تعليمات</h3>
      <ul class="small">
        <li>اقرأ كل سؤال بعناية، وتنقّل بين الأسئلة بحرية من أرقامها.</li>
        <li>تُحفظ إجاباتك تلقائياً، وإن انقطع الاتصال تُحفظ على جهازك وتُرسل عند عودته.</li>
        <li>يمكنك تعليم أي سؤال «للمراجعة» والرجوع إليه قبل التسليم.</li>
        <?php if ((int) $test['duration_minutes']): ?><li>يبدأ احتساب الوقت عند الضغط على «ابدأ»، ويُسلَّم الاختبار تلقائياً عند انتهائه.</li><?php endif; ?>
        <li>بعد التسليم لا يمكن تعديل الإجابات.</li>
        <li>النتيجة: <?= e(TE::VISIBILITY[$test['result_visibility']]) ?>.</li>
      </ul>
      <?php if (in_array($state, ['available', 'in_progress'], true)): ?>
        <form method="post" action="<?= e(url('/student/tests/' . $test['id'] . '/start')) ?>" data-once><?= csrf_field() ?>
          <button class="btn btn-primary btn-lg w-100"><?= $state === 'in_progress' ? 'متابعة الاختبار' : 'ابدأ الاختبار' ?> <i class="bi bi-play-fill"></i></button></form>
      <?php else: ?>
        <div class="alert alert-secondary mb-0"><?= ['upcoming' => 'لم يحن موعد الاختبار بعد.', 'done' => 'أكملت كل المحاولات المتاحة.', 'missed' => 'انتهى موعد الاختبار.'][$state] ?? '' ?></div>
      <?php endif; ?>
    </div>
  </div>
  <a class="btn btn-light mt-3" href="<?= e(url('/student/tests')) ?>">رجوع</a>
</div></div>
