<div class="page-head">
  <h1>📈 التقارير والإحصائيات</h1>
  <span class="spacer"></span>
  <a class="btn outline sm" target="_blank"
     href="<?= e(url('reports/movement', ['from' => $from, 'to' => $to])) ?>">🖨️ تقرير حركة المعاملات</a>
</div>

<form class="filters" method="get" action="index.php">
  <input type="hidden" name="r" value="reports/index">
  <div class="row">
    <div><label>من تاريخ</label><input type="date" name="from" value="<?= e($from) ?>"></div>
    <div><label>إلى تاريخ</label><input type="date" name="to" value="<?= e($to) ?>"></div>
    <div class="flex"><button class="btn sm">تحديث التقرير</button></div>
  </div>
</form>

<!-- ============================= ملخص الفترة ============================= -->
<div class="grid grid-4 mb">
  <div class="stat"><span class="ico bg-brand">🗃️</span>
    <span><span class="num"><?= num($summary['total']) ?></span><span class="lbl">إجمالي المعاملات</span></span></div>
  <div class="stat"><span class="ico bg-info">📩</span>
    <span><span class="num"><?= num($summary['incoming']) ?></span><span class="lbl">واردة</span></span></div>
  <div class="stat"><span class="ico bg-ok">📤</span>
    <span><span class="num"><?= num($summary['outgoing']) ?></span><span class="lbl">صادرة</span></span></div>
  <div class="stat"><span class="ico bg-purple">📝</span>
    <span><span class="num"><?= num($summary['internal']) ?></span><span class="lbl">داخلية</span></span></div>
  <div class="stat"><span class="ico bg-ok">✅</span>
    <span><span class="num"><?= num($summary['completed']) ?></span><span class="lbl">منجزة</span></span></div>
  <div class="stat"><span class="ico bg-warn">⏳</span>
    <span><span class="num"><?= num($summary['in_progress']) ?></span><span class="lbl">قيد الإجراء</span></span></div>
  <div class="stat"><span class="ico bg-danger">🔥</span>
    <span><span class="num"><?= num($summary['very_urgent']) ?></span><span class="lbl">عاجلة جداً</span></span></div>
  <div class="stat"><span class="ico bg-muted">🔒</span>
    <span><span class="num"><?= num($summary['confidential']) ?></span><span class="lbl">سرية</span></span></div>
</div>

<!-- ============================ الحركة الشهرية ============================ -->
<div class="card">
  <div class="card-head"><h3>📊 الحركة الشهرية</h3></div>
  <div class="card-body">
    <?php if (!$monthly): ?>
      <div class="empty"><span class="big">📊</span>لا توجد بيانات في هذه الفترة</div>
    <?php else: $max = max(array_map(static fn($m) => (int) $m['total'], $monthly)) ?: 1; ?>
      <div class="bars">
        <?php foreach ($monthly as $m): ?>
          <div class="col" title="<?= e($m['ym']) ?>: <?= (int) $m['total'] ?>">
            <div class="stack">
              <div class="seg" style="height:<?= round($m['incoming'] / $max * 150) ?>px;background:var(--info)"></div>
              <div class="seg" style="height:<?= round($m['outgoing'] / $max * 150) ?>px;background:var(--brand)"></div>
              <div class="seg" style="height:<?= round($m['internal'] / $max * 150) ?>px;background:var(--purple)"></div>
            </div>
            <div class="lbl"><?= e($m['ym']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="legend">
        <span><i style="background:var(--info)"></i>وارد</span>
        <span><i style="background:var(--brand)"></i>صادر</span>
        <span><i style="background:var(--purple)"></i>داخلي</span>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="grid grid-3">
  <?php
  $rank = static function (string $title, array $rows, string $labelKey = 'name'): void {
      $max = $rows ? max(array_map(static fn($r) => (int) $r['total'], $rows)) : 1;
      $max = $max ?: 1; ?>
    <div class="card">
      <div class="card-head"><h3><?= e($title) ?></h3></div>
      <div class="card-body">
        <?php if (!$rows): ?>
          <div class="empty">لا توجد بيانات</div>
        <?php else: ?>
        <ul class="rank-list">
          <?php foreach ($rows as $r): ?>
            <li>
              <span class="name"><?= e($r[$labelKey]) ?></span>
              <span class="bar"><span style="width:<?= round($r['total'] / $max * 100) ?>%;
                    background:<?= e($r['color'] ?? 'var(--brand)') ?>"></span></span>
              <span class="val"><?= num($r['total']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </div>
  <?php };

  $rank('🏛️ الأكثر مراسلة (الجهات)', $byCorrespondent);
  $rank('🏫 الأكثر نشاطاً (الإدارات)', $byDepartment);
  $rank('🏷️ التوزيع بالتصنيف', $byCategory);
  $rank('👤 الأكثر إدخالاً (المستخدمون)', $byUser, 'full_name');
  ?>

  <!-- ============================ توزيع الحالات ============================ -->
  <div class="card">
    <div class="card-head"><h3>🔄 توزيع الحالات</h3></div>
    <div class="card-body">
      <?php $totalS = array_sum(array_map(static fn($s) => (int) $s['total'], $byStatus)) ?: 1; ?>
      <ul class="rank-list">
        <?php foreach ($byStatus as $s): ?>
          <li>
            <span class="badge <?= e(statusClass($s['status'])) ?>"><?= e(labelStatus($s['status'])) ?></span>
            <span class="name"></span>
            <span class="bar"><span style="width:<?= round($s['total'] / $totalS * 100) ?>%"></span></span>
            <span class="val"><?= num($s['total']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>

  <!-- ============================= أكثر المجلدات ============================= -->
  <div class="card">
    <div class="card-head"><h3>🗂️ أكثر المجلدات محتوى</h3></div>
    <div class="card-body">
      <?php $maxF = 1; foreach ($folderStats as $f) { $maxF = max($maxF, (int) $f['docs'] + (int) $f['trx']); } ?>
      <ul class="rank-list">
        <?php foreach ($folderStats as $f): $t = (int) $f['docs'] + (int) $f['trx']; if (!$t) continue; ?>
          <li>
            <span class="name"><a href="<?= e(url('folders/index', ['id' => $f['id']])) ?>"><?= e($f['name']) ?></a></span>
            <span class="bar"><span style="width:<?= round($t / $maxF * 100) ?>%"></span></span>
            <span class="val"><?= num($t) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>

<div class="grid grid-2">
  <!-- ============================== المرفقات ============================== -->
  <div class="card">
    <div class="card-head"><h3>📎 إحصاء المرفقات</h3></div>
    <div class="card-body tight">
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>النوع</th><th>المصدر</th><th>العدد</th><th>الحجم</th></tr></thead>
          <tbody>
          <?php $tot = 0; $totSize = 0; foreach ($attachStats as $a):
            $tot += (int) $a['total']; $totSize += (int) $a['size']; ?>
            <tr>
              <td><?= e(labelMediaGroup($a['media_group'])) ?></td>
              <td><?= $a['kind'] === 'drive' ? '☁️ قوقل درايف' : '💾 الخادم' ?></td>
              <td><?= num($a['total']) ?></td>
              <td><?= $a['kind'] === 'drive' ? '-' : e(Uploader::humanSize((int) $a['size'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot><tr style="background:#f8fafc;font-weight:700">
            <td colspan="2">الإجمالي</td><td><?= num($tot) ?></td><td><?= e(Uploader::humanSize($totSize)) ?></td>
          </tr></tfoot>
        </table>
      </div>
    </div>
  </div>

  <!-- ========================== المعاملات المتأخرة ========================== -->
  <div class="card">
    <div class="card-head"><h3>🔴 المعاملات المتأخرة عن موعد الإنجاز</h3></div>
    <div class="card-body tight">
      <?php if (!$overdue): ?>
        <div class="empty"><span class="big">👏</span>لا توجد معاملات متأخرة</div>
      <?php else: ?>
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>الرقم</th><th>الموضوع</th><th>موعد الإنجاز</th><th>التأخير</th></tr></thead>
          <tbody>
          <?php foreach ($overdue as $o): ?>
            <tr>
              <td class="nowrap"><a href="<?= e(url('transactions/show', ['id' => $o['id']])) ?>"><b><?= e($o['transaction_no']) ?></b></a></td>
              <td><?= e(excerpt($o['subject'], 40)) ?></td>
              <td class="nowrap small"><?= e(gdate($o['due_date'])) ?></td>
              <td class="nowrap"><span class="badge danger"><?= (int) $o['days_late'] ?> يوم</span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
