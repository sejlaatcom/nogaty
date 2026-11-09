<?php use App\Services\QuestionRenderer as R; $n = count($items); ?>
<form method="post" action="<?= e(url('/student/attempts/' . $attempt['id'] . '/submit')) ?>" id="exam"
      data-attempt="<?= (int) $attempt['id'] ?>" data-save-url="/student/attempts/<?= (int) $attempt['id'] ?>/save"
      data-autosave="<?= (int) $autosave ?>" <?= $secondsLeft !== null ? 'data-seconds="' . (int) $secondsLeft . '"' : '' ?>>
  <?= csrf_field() ?>
  <header class="exam-top">
    <div class="inner">
      <div class="flex-grow-1">
        <strong class="d-block"><?= e($attempt['title']) ?></strong>
        <small class="text-muted"><?= e($attempt['subject_name'] ?? 'اختبار شامل') ?> · أجبت عن <b id="answered-count">0</b> من <?= $n ?></small>
        <div class="progress mt-1" style="height:6px;max-width:320px"><div class="progress-bar" id="progress-bar" style="width:0%"></div></div>
      </div>
      <span class="net-status text-success" id="net-status"><i class="bi bi-cloud-check"></i> محفوظ</span>
      <?php if ($secondsLeft !== null): ?><div class="exam-timer" id="timer" title="الوقت المتبقي"><i class="bi bi-stopwatch"></i> <span>--:--</span></div><?php endif; ?>
    </div>
  </header>

  <div class="exam-wrap">
    <main>
      <?php foreach ($items as $i => $it): $q = $it['snap']; ?>
        <section class="exam-q card" data-index="<?= $i ?>" data-tq="<?= (int) $it['test_question_id'] ?>" data-type="<?= e($it['type']) ?>"<?= $it['type'] === 'ordering' && !empty($it['ans']['order']) ? ' data-touched="1"' : '' ?>>
          <div class="card-header">
            <h2 class="h6 mb-0">السؤال <?= $i + 1 ?> من <?= $n ?> <small class="text-muted">(<?= num((float) $it['max_points'], 2) ?> درجة)</small></h2>
            <label class="form-check mb-0 text-warning-emphasis"><input class="form-check-input" type="checkbox" data-flag<?= checked($it['flagged']) ?>> <i class="bi bi-flag-fill"></i> للمراجعة</label>
          </div>
          <div class="card-body">
            <?= R::passage($q['passage'] ?? null) ?>
            <?= R::stem($q) ?>
            <?= R::body($q, 'take', 'a[' . (int) $it['test_question_id'] . ']', $it['ans'], $it['order']) ?>
          </div>
          <div class="card-footer bg-transparent d-flex justify-content-between">
            <button type="button" class="btn btn-light" data-step="-1" <?= $i === 0 ? 'disabled' : '' ?>><i class="bi bi-arrow-right"></i> السابق</button>
            <?php if ($i < $n - 1): ?><button type="button" class="btn btn-primary" data-step="1">التالي <i class="bi bi-arrow-left"></i></button>
            <?php else: ?><button type="button" class="btn btn-success" data-review>مراجعة وتسليم <i class="bi bi-check2-all"></i></button><?php endif; ?>
          </div>
        </section>
      <?php endforeach; ?>

      <section class="exam-q card" data-index="<?= $n ?>" id="review-panel">
        <div class="card-header"><h2 class="h6 mb-0">مراجعة قبل التسليم</h2></div>
        <div class="card-body">
          <div class="row g-2 text-center mb-3">
            <div class="col-4"><div class="border rounded-3 p-2"><div class="fs-4 fw-bold text-success" id="sum-answered">0</div><small>مُجاب</small></div></div>
            <div class="col-4"><div class="border rounded-3 p-2"><div class="fs-4 fw-bold text-danger" id="sum-blank">0</div><small>غير مُجاب</small></div></div>
            <div class="col-4"><div class="border rounded-3 p-2"><div class="fs-4 fw-bold text-warning" id="sum-flagged">0</div><small>للمراجعة</small></div></div>
          </div>
          <p class="text-muted small">اضغط على رقم أي سؤال للرجوع إليه. بعد التسليم لا يمكن تعديل الإجابات.</p>
          <button type="button" class="btn btn-success btn-lg w-100" id="submit-btn"><i class="bi bi-send-check"></i> تسليم الاختبار</button>
        </div>
      </section>
    </main>

    <aside class="exam-sidebar">
      <div class="card"><div class="card-body">
        <div class="small text-muted mb-2 qnav-title">أرقام الأسئلة</div>
        <div class="qnav" id="qnav">
          <?php foreach ($items as $i => $it): ?><button type="button" data-goto="<?= $i ?>" class="<?= $it['flagged'] ? 'flagged' : '' ?>"><?= $i + 1 ?></button><?php endforeach; ?>
        </div>
        <div class="small text-muted mt-3 legend">
          <div><span class="legend-dot" style="background:var(--accent)"></span>مُجاب</div>
          <div><span class="legend-dot border"></span>غير مُجاب</div>
          <div><span class="legend-dot" style="background:var(--warning)"></span>معلّم للمراجعة</div>
        </div>
        <button type="button" class="btn btn-outline-success w-100 mt-3" data-review><i class="bi bi-check2-all"></i> مراجعة وتسليم</button>
      </div></div>
    </aside>
  </div>
</form>

<div class="modal fade" id="submitModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">تأكيد التسليم</h5></div>
  <div class="modal-body" id="submit-text"></div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">متابعة الإجابة</button>
    <button type="button" class="btn btn-success" id="confirm-submit">نعم، سلّم الاختبار</button></div>
</div></div></div>
<?php \App\Core\View::push('scripts', '<script src="' . asset('js/exam.js') . '"></script>'); ?>
