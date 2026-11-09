<?php
use App\Services\QuestionBank as QB;
use App\Services\QuestionRenderer as R;
use App\Services\TestEngine as TE;
[$sl, $sc] = TE::STATUSES[$test['status']];
$tid = (int) $test['id'];
$locked = $hasAttempts;
$bankQuery = array_filter(['bq' => $_GET['bq'] ?? null, 'btype' => $_GET['btype'] ?? null, 'bdiff' => $_GET['bdiff'] ?? null, 'bskill' => $_GET['bskill'] ?? null, 'bsubject' => $_GET['bsubject'] ?? null]);
?>
<div class="page-header">
  <div><h1><?= e($test['title']) ?> <span class="badge badge-soft-<?= $sc ?> fs-6"><?= $sl ?></span></h1>
    <div class="sub"><?= e(TE::TYPES[$test['type']]) ?> · <?= e($test['subject_name'] ?? 'شامل لعدة مواد') ?> · <?= e($test['grade_name']) ?> · <?= e($test['year_name']) ?> · أعده <?= e($test['author_name'] ?? '—') ?></div></div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-light" href="<?= e(url('/tests/' . $tid . '/results')) ?>"><i class="bi bi-bar-chart"></i> النتائج</a>
    <div class="dropdown">
      <button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-printer"></i> ورقي</button>
      <ul class="dropdown-menu">
        <li><a class="dropdown-item" target="_blank" href="<?= e(url('/tests/' . $tid . '/paper')) ?>">نسخة الطالب</a></li>
        <?php if ($canManage): ?><li><a class="dropdown-item" target="_blank" href="<?= e(url('/tests/' . $tid . '/paper', ['key' => 1])) ?>">مع مفتاح الإجابات</a></li><?php endif; ?>
      </ul>
    </div>
    <?php if (can('tests.create')): ?><form method="post" action="<?= e(url('/tests/' . $tid . '/duplicate')) ?>"><?= csrf_field() ?><button class="btn btn-light" title="نسخ الاختبار"><i class="bi bi-copy"></i> نسخ</button></form><?php endif; ?>
    <?php if ($canManage): ?>
      <a class="btn btn-light" href="<?= e(url('/tests/' . $tid . '/edit')) ?>"><i class="bi bi-sliders"></i> الإعدادات</a>
      <form method="post" action="<?= e(url('/tests/' . $tid . '/status')) ?>" class="d-flex gap-1"><?= csrf_field() ?>
        <?php if (in_array($test['status'], ['draft', 'closed'], true)): ?>
          <button class="btn btn-success" name="status" value="published" data-confirm="<?= $test['status'] === 'draft' ? 'نشر الاختبار وإشعار الطلاب المستهدفين؟' : 'إعادة فتح الاختبار للطلاب؟' ?>"><i class="bi bi-send"></i> <?= $test['status'] === 'draft' ? 'نشر' : 'إعادة فتح' ?></button>
        <?php endif; ?>
        <?php if ($test['status'] === 'published'): ?>
          <button class="btn btn-warning" name="status" value="closed" data-confirm="إغلاق الاختبار؟ تُسلّم المحاولات الجارية المنتهية تلقائياً ولن يبدأ طلاب جدد."><i class="bi bi-lock"></i> إغلاق</button>
          <?php if (!$hasAttempts): ?><button class="btn btn-light" name="status" value="draft">إرجاع لمسودة</button><?php endif; ?>
        <?php endif; ?>
        <?php if ($test['status'] !== 'archived'): ?><button class="btn btn-light" name="status" value="archived" data-confirm="أرشفة الاختبار؟ يختفي من قوائم الطلاب وتبقى نتائجه.">أرشفة</button><?php else: ?>
          <button class="btn btn-light" name="status" value="closed">استعادة (مغلق)</button><?php endif; ?>
      </form>
      <form method="post" action="<?= e(url('/tests/' . $tid . '/delete')) ?>"><?= csrf_field() ?><button class="btn btn-light text-danger" title="حذف" data-confirm="حذف الاختبار؟ إن كانت له محاولات فسيُؤرشف فقط."><i class="bi bi-trash"></i></button></form>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-list-ol"></i></div><div><div class="stat-value"><?= count($questions) ?></div><div class="stat-label">سؤال · <?= num((float) $test['total_points'], 1) ?> درجة</div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-stopwatch"></i></div><div><div class="stat-value"><?= (int) $test['duration_minutes'] ?: '∞' ?></div><div class="stat-label">دقيقة · <?= (int) $test['max_attempts'] ?: 'غير محدود' ?> محاولة</div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-calendar-event"></i></div><div><div class="stat-value fs-6"><?= $test['available_until'] ? e(dt($test['available_until'])) : 'مفتوح' ?></div><div class="stat-label">الإغلاق<?= $test['available_from'] ? ' · من ' . e(dt($test['available_from'])) : '' ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon"><i class="bi bi-eye"></i></div><div><div class="stat-value fs-6"><?= e(TE::VISIBILITY[$test['result_visibility']]) ?></div><div class="stat-label">إظهار النتيجة<?= (int) $test['allow_review'] ? ' · مراجعة' : '' ?></div></div></div></div>
</div>

<?php if ($locked): ?><div class="alert alert-info"><i class="bi bi-lock"></i> بدأ طلاب في هذا الاختبار، لذا أُقفلت أسئلته ودرجاته حفاظاً على عدالة النتائج. يمكنك تعديل الترتيب والإسناد والمواعيد، أو نسخ الاختبار لإنشاء نسخة معدلة.</div><?php endif; ?>

<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#questions" type="button">الأسئلة (<?= count($questions) ?>)</button></li>
  <?php if ($canManage && !$locked): ?>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#generator" type="button">المولّد التلقائي</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#bank" type="button">إضافة من البنك</button></li>
  <?php endif; ?>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#assign" type="button">الإسناد (<?= count($test['class_ids']) ?> فصل<?= $test['student_ids'] ? ' + ' . count($test['student_ids']) . ' طالب' : '' ?>)</button></li>
</ul>

<div class="tab-content">
  <!-- ===== الأسئلة ===== -->
  <div class="tab-pane fade show active" id="questions">
    <div class="row g-3">
      <div class="col-xl-9">
        <form method="post" action="<?= e(url('/tests/' . $tid . '/questions')) ?>" class="card">
          <?= csrf_field() ?>
          <?php if ($canManage && $questions && !$locked): ?>
          <div class="table-tools">
            <button class="btn btn-sm btn-primary" name="action" value="points"><i class="bi bi-save"></i> حفظ الدرجات</button>
            <button class="btn btn-sm btn-light text-danger" name="action" value="remove" data-confirm="إزالة الأسئلة المحددة من الاختبار؟ تبقى في البنك."><i class="bi bi-x-lg"></i> إزالة المحدد</button>
            <span class="small text-muted ms-auto">كل سؤال نسخة مجمدة؛ تعديل البنك لا يغيّر الاختبار إلا بزر «تحديث».</span>
          </div>
          <?php endif; ?>
          <div class="list-group list-group-flush">
          <?php foreach ($questions as $i => $q): $snap = $q['snap']; $stale = $q['question_id'] && (int) $q['bank_version'] > (int) $q['question_version']; ?>
            <div class="list-group-item">
              <div class="d-flex gap-2 align-items-start">
                <?php if ($canManage && !$locked): ?><input class="form-check-input mt-1" type="checkbox" name="tq[]" value="<?= (int) $q['id'] ?>" aria-label="تحديد"><?php endif; ?>
                <span class="badge text-bg-primary"><?= $i + 1 ?></span>
                <div class="flex-grow-1 min-w-0">
                  <a class="text-body" data-bs-toggle="collapse" href="#pv<?= (int) $q['id'] ?>"><?= e(str_limit(plain_text((string) $snap['stem']), 140)) ?></a>
                  <div class="small text-muted mt-1">
                    <?= e(QB::TYPES[$q['type']] ?? $q['type']) ?> ·
                    <span class="badge badge-soft-<?= [1 => 'success', 2 => 'warning', 3 => 'danger'][(int) $snap['difficulty']] ?? 'secondary' ?>"><?= QB::DIFFICULTIES[(int) $snap['difficulty']] ?? '' ?></span>
                    · <?= e($q['skill_name'] ? ($q['skill_code'] ? $q['skill_code'] . ' ' : '') . $q['skill_name'] : 'بدون مهارة') ?>
                    <?= !empty($snap['passage']) ? ' · <i class="bi bi-book"></i> ' . e($snap['passage']['title']) : '' ?>
                    <?php if ($q['question_id']): ?> · <a href="<?= e(url('/questions/' . $q['question_id'])) ?>" target="_blank">#<?= (int) $q['question_id'] ?> ن<?= (int) $q['question_version'] ?></a><?php endif; ?>
                    <?= $stale ? ' · <span class="text-warning-emphasis"><i class="bi bi-exclamation-circle"></i> توجد نسخة أحدث في البنك</span>' : '' ?>
                  </div>
                  <div class="collapse mt-2" id="pv<?= (int) $q['id'] ?>"><div class="border rounded p-3 bg-body-tertiary"><?= R::passage($snap['passage'] ?? null) ?><?= R::stem($snap) ?><?= R::body($snap, 'preview') ?></div></div>
                </div>
                <div class="d-flex gap-1 align-items-center flex-shrink-0">
                  <?php if ($canManage && !$locked): ?>
                    <input type="number" step="0.25" min="0.25" max="100" class="form-control form-control-sm" style="width:5rem" name="points[<?= (int) $q['id'] ?>]" value="<?= e(rtrim(rtrim((string) $q['points'], '0'), '.')) ?>" title="الدرجة">
                    <?php if ($stale): ?><button class="btn btn-sm btn-light" name="action" value="refresh:<?= (int) $q['id'] ?>" title="تحديث لآخر نسخة"><i class="bi bi-arrow-repeat"></i></button><?php endif; ?>
                  <?php else: ?><span class="small text-muted"><?= num((float) $q['points'], 2) ?> درجة</span><?php endif; ?>
                  <?php if ($canManage): ?>
                    <button class="btn btn-sm btn-light" name="action" value="move:<?= (int) $q['id'] ?>:-1" title="أعلى" <?= $i === 0 ? 'disabled' : '' ?>><i class="bi bi-arrow-up"></i></button>
                    <button class="btn btn-sm btn-light" name="action" value="move:<?= (int) $q['id'] ?>:1" title="أسفل" <?= $i === count($questions) - 1 ? 'disabled' : '' ?>><i class="bi bi-arrow-down"></i></button>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if (!$questions): ?><div class="empty-state"><i class="bi bi-list-ol"></i>لا توجد أسئلة بعد — استخدم «المولّد التلقائي» أو «إضافة من البنك».</div><?php endif; ?>
          </div>
        </form>
      </div>
      <div class="col-xl-3">
        <div class="card mb-3"><div class="card-header"><h2>التوزيع</h2></div><div class="card-body small">
          <div class="fw-bold mb-1">الصعوبة</div>
          <?php foreach ($dist['difficulty'] as $k => $n): ?><div class="d-flex justify-content-between"><span><?= QB::DIFFICULTIES[$k] ?></span><b><?= $n ?></b></div><?php endforeach; ?>
          <div class="fw-bold mt-3 mb-1">الأنواع</div>
          <?php foreach ($dist['types'] as $k => $n): ?><div class="d-flex justify-content-between"><span><?= e(QB::TYPES[$k] ?? $k) ?></span><b><?= $n ?></b></div><?php endforeach; ?>
          <div class="fw-bold mt-3 mb-1">المهارات</div>
          <?php foreach ($dist['skills'] as $k => $n): ?><div class="d-flex justify-content-between gap-2"><span class="text-truncate" title="<?= e($k) ?>"><?= e($k) ?></span><b><?= $n ?></b></div><?php endforeach; ?>
          <?php if (!$questions): ?><span class="text-muted">—</span><?php endif; ?>
        </div></div>
      </div>
    </div>
  </div>

  <?php if ($canManage && !$locked): ?>
  <!-- ===== المولّد ===== -->
  <div class="tab-pane fade" id="generator">
    <form method="post" action="<?= e(url('/tests/' . $tid . '/questions')) ?>" class="card" data-once>
      <?= csrf_field() ?><input type="hidden" name="action" value="generate">
      <div class="card-header"><h2>توليد الاختبار من مخطط المهارات</h2></div>
      <div class="card-body">
        <p class="small text-muted">حدّد عدد الأسئلة المطلوب من كل مهارة ومستوى الصعوبة، فيسحب المولّد أسئلة منشورة عشوائياً من البنك دون تكرار ما أضيف سابقاً. يمكنك بعدها تعديل الاختيار يدوياً.</p>
        <div class="table-responsive"><table class="table table-sm align-middle">
          <thead><tr><th>المهارة</th><th>المتاح</th><th style="width:7rem">العدد</th><th style="width:10rem">الصعوبة</th></tr></thead>
          <tbody>
          <?php $lastDomain = null; foreach ($skills as $i => $s): if ($lastDomain !== $s['domain_id']): $lastDomain = $s['domain_id']; ?>
            <tr class="table-light"><td colspan="4" class="fw-bold small"><?= e(($test['subject_id'] ? '' : $s['subject_name'] . ' ← ') . $s['domain_name']) ?></td></tr>
          <?php endif; ?>
            <tr>
              <td class="small"><?= e(($s['code'] ? $s['code'] . ' — ' : '') . $s['name']) ?><input type="hidden" name="bp[<?= $i ?>][skill]" value="<?= (int) $s['id'] ?>"></td>
              <td><span class="badge badge-soft-<?= (int) $s['available'] ? 'primary' : 'secondary' ?>"><?= (int) $s['available'] ?></span></td>
              <td><input type="number" min="0" max="<?= min(50, (int) $s['available']) ?>" class="form-control form-control-sm" name="bp[<?= $i ?>][count]" value="0" <?= (int) $s['available'] ? '' : 'disabled' ?>></td>
              <td><select class="form-select form-select-sm" name="bp[<?= $i ?>][difficulty]" <?= (int) $s['available'] ? '' : 'disabled' ?>><option value="0">مختلط</option><?php foreach (QB::DIFFICULTIES as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$skills): ?><tr><td colspan="4" class="text-muted">لا توجد مهارات مصنفة لهذا الصف والمادة.</td></tr><?php endif; ?>
          </tbody>
        </table></div>
        <div class="row g-2 align-items-end">
          <div class="col-md-3"><label class="form-label">+ أسئلة عشوائية من المادة كلها</label><input type="number" min="0" max="50" class="form-control" name="random_count" value="0"></div>
          <div class="col-md-3"><label class="form-label">صعوبتها</label><select class="form-select" name="random_difficulty"><option value="0">مختلط</option><?php foreach (QB::DIFFICULTIES as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
          <div class="col-md-3"><button class="btn btn-primary w-100"><i class="bi bi-magic"></i> توليد وإضافة</button></div>
        </div>
      </div>
    </form>
  </div>

  <!-- ===== البنك ===== -->
  <div class="tab-pane fade" id="bank">
    <form method="get" class="filter-bar row g-2 align-items-end" action="<?= e(url('/tests/' . $tid)) ?>">
      <input type="hidden" name="tab" value="bank">
      <?php if (!$test['subject_id']): ?>
      <div class="col-md-2"><label class="form-label">المادة</label><select class="form-select" name="bsubject"><option value="">الكل</option><?php foreach ($bankSubjects as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected($_GET['bsubject'] ?? '', $s['id']) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
      <?php endif; ?>
      <div class="col-md-3"><label class="form-label">المهارة</label><select class="form-select" name="bskill"><option value="">الكل</option><?php foreach ($bankSkills as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected($_GET['bskill'] ?? '', $s['id']) ?>><?= e(($s['code'] ? $s['code'] . ' — ' : '') . str_limit($s['name'], 50)) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label">النوع</label><select class="form-select" name="btype"><option value="">الكل</option><?php foreach (QB::TYPES as $k => $v): ?><option value="<?= $k ?>"<?= selected($_GET['btype'] ?? '', $k) ?>><?= $v ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label">الصعوبة</label><select class="form-select" name="bdiff"><option value="">الكل</option><?php foreach (QB::DIFFICULTIES as $k => $v): ?><option value="<?= $k ?>"<?= selected($_GET['bdiff'] ?? '', $k) ?>><?= $v ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label">بحث</label><input class="form-control" name="bq" value="<?= e($_GET['bq'] ?? '') ?>"></div>
      <div class="col-md-1"><button class="btn btn-outline-primary w-100"><i class="bi bi-search"></i></button></div>
    </form>
    <form method="post" action="<?= e(url('/tests/' . $tid . '/questions')) ?>" class="card">
      <?= csrf_field() ?><input type="hidden" name="action" value="add">
      <div class="table-tools"><button class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> إضافة المحدد</button><span class="small text-muted"><?= num($bank['total']) ?> سؤال منشور متاح (غير المضاف)</span></div>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th><input class="form-check-input" type="checkbox" data-check-all="qids[]"></th><th>السؤال</th><th>المهارة</th><th>النوع</th><th>الصعوبة</th></tr></thead>
        <tbody>
        <?php foreach ($bank['rows'] as $q): ?>
          <tr><td><input class="form-check-input" type="checkbox" name="qids[]" value="<?= (int) $q['id'] ?>"></td>
            <td><a href="<?= e(url('/questions/' . $q['id'])) ?>" target="_blank" class="text-body"><?= e(str_limit($q['stem'], 120)) ?></a><?= $q['passage_title'] ? '<br><small class="text-muted"><i class="bi bi-book"></i> ' . e($q['passage_title']) . '</small>' : '' ?></td>
            <td class="small"><?= e($q['skill_name'] ? ($q['skill_code'] ? $q['skill_code'] . ' ' : '') . str_limit($q['skill_name'], 40) : '—') ?></td>
            <td class="small"><?= e(QB::TYPES[$q['type']]) ?></td>
            <td><span class="badge badge-soft-<?= [1 => 'success', 2 => 'warning', 3 => 'danger'][(int) $q['difficulty']] ?>"><?= QB::DIFFICULTIES[(int) $q['difficulty']] ?></span></td></tr>
        <?php endforeach; ?>
        <?php if (!$bank['rows']): ?><tr><td colspan="5"><div class="empty-state"><i class="bi bi-collection"></i>لا توجد أسئلة منشورة مطابقة لم تُضف بعد.</div></td></tr><?php endif; ?>
        </tbody>
      </table></div>
      <?php if ($bank['pages'] > 1): ?>
        <nav class="p-3 border-top"><ul class="pagination pagination-sm mb-0 flex-wrap">
          <?php for ($p = 1; $p <= $bank['pages']; $p++): ?><li class="page-item <?= $p === (int) $bank['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= e(url('/tests/' . $tid, $bankQuery + ['tab' => 'bank', 'bpage' => $p])) ?>"><?= $p ?></a></li><?php endfor; ?>
        </ul></nav>
      <?php endif; ?>
    </form>
  </div>
  <?php endif; ?>

  <!-- ===== الإسناد ===== -->
  <div class="tab-pane fade" id="assign">
    <form method="post" action="<?= e(url('/tests/' . $tid . '/assign')) ?>" class="card">
      <?= csrf_field() ?>
      <div class="card-header"><h2>الفصول والطلاب المستهدفون</h2></div>
      <div class="card-body">
        <?php if (!$canManage): ?><div class="alert alert-light small">يمكنك الاطلاع على الإسناد فقط؛ التعديل لمعدّ الاختبار.</div><?php endif; ?>
        <div class="fw-bold mb-2">فصول <?= e($test['grade_name']) ?></div>
        <div class="d-flex flex-wrap gap-2 mb-3">
          <?php foreach ($classes as $c): ?>
            <input type="checkbox" class="btn-check" name="class_ids[]" value="<?= (int) $c['id'] ?>" id="cl<?= (int) $c['id'] ?>"<?= checked(in_array((int) $c['id'], $test['class_ids'], true)) ?> <?= $canManage ? '' : 'disabled' ?>>
            <label class="btn btn-outline-primary" for="cl<?= (int) $c['id'] ?>"><?= e($c['name']) ?> <small class="opacity-75">(<?= (int) ($c['students_count'] ?? 0) ?>)</small></label>
          <?php endforeach; ?>
          <?php if (!$classes): ?><span class="text-muted">لا توجد فصول مسندة إليك في هذا الصف.</span><?php endif; ?>
        </div>
        <div class="fw-bold mb-1">أو طلاب محددون <small class="text-muted fw-normal">(للاختبارات الفردية والعلاجية)</small></div>
        <div data-table-tools="assignStudents" class="mb-2"><input class="form-control form-control-sm" data-table-search placeholder="ابحث باسم الطالب أو الفصل" style="max-width:20rem"></div>
        <div class="border rounded" style="max-height:320px;overflow:auto">
          <table class="table table-sm mb-0" data-table id="assignStudents">
            <thead><tr><th style="width:2rem"><input class="form-check-input" type="checkbox" data-check-all="student_ids[]" <?= $canManage ? '' : 'disabled' ?>></th><th>الطالب</th><th>الفصل</th></tr></thead>
            <tbody>
            <?php foreach ($students as $s): ?>
              <tr><td><input class="form-check-input" type="checkbox" name="student_ids[]" value="<?= (int) $s['id'] ?>"<?= checked(in_array((int) $s['id'], $test['student_ids'], true)) ?> <?= $canManage ? '' : 'disabled' ?>></td><td><?= e($s['full_name']) ?></td><td class="small"><?= e($s['class_name']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($canManage): ?><button class="btn btn-primary mt-3"><i class="bi bi-check2"></i> حفظ الإسناد</button>
          <?php if ($test['status'] === 'published'): ?><span class="small text-muted ms-2">سيُشعر الطلاب الجدد بالاختبار.</span><?php endif; ?><?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php \App\Core\View::push('scripts', '<script>(function(){var t=new URLSearchParams(location.search).get("tab")||location.hash.replace("#","");var b=t&&document.querySelector(\'[data-bs-target="#\'+t+\'"]\');if(b&&window.bootstrap){bootstrap.Tab.getOrCreateInstance(b).show();}})();</script>'); ?>
