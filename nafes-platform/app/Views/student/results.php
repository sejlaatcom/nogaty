<?php use App\Services\Levels; use App\Services\TestEngine as TE; ?>
<div class="page-header"><h1>نتائجي</h1></div>
<div class="card"><div class="table-responsive"><table class="table table-hover" data-table>
  <thead><tr><th>الاختبار</th><th>النوع</th><th>المادة</th><th>التاريخ</th><th>النتيجة</th><th>المستوى</th><th data-sort="false"></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><?= e($r['title']) ?> <small class="text-muted">(محاولة <?= (int) $r['attempt_no'] ?>)</small></td><td class="small"><?= e(TE::TYPES[$r['type']]) ?></td>
      <td><?= e($r['subject_name'] ?? 'شامل') ?></td><td class="small" data-value="<?= e($r['submitted_at']) ?>"><?= e(dt($r['submitted_at'])) ?></td>
      <?php if ($r['visible'] && $r['percent'] !== null): ?>
        <td data-value="<?= e($r['percent']) ?>"><?= pct((float) $r['percent']) ?><?= (int) $r['is_final'] ? '' : ' <small class="text-warning">(مبدئية)</small>' ?></td><td><?= Levels::badge($r['level']) ?></td>
        <td><a class="btn btn-sm btn-soft" href="<?= e(url('/student/results/' . $r['attempt_id'])) ?>">التفاصيل</a></td>
      <?php else: ?><td colspan="3" class="text-muted small">تظهر النتيجة لاحقاً</td><?php endif; ?></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr class="empty-row"><td colspan="7"><div class="empty-state"><i class="bi bi-bar-chart"></i>لا توجد نتائج بعد.</div></td></tr><?php endif; ?>
  </tbody></table></div></div>
