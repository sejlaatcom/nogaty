<div class="page-head">
  <h1>لوحة المعلومات</h1>
  <span class="spacer"></span>
  <span class="chip">📅 <?= e(gdate(date('Y-m-d'))) ?> م</span>
  <span class="chip">🌙 <?= e(Hijri::format(date('Y-m-d'), true)) ?></span>
</div>

<!-- ============================ بطاقات الإحصاء ============================ -->
<div class="grid grid-6 mb">
  <a class="stat" href="<?= e(url('transactions/index', ['direction' => 'incoming'])) ?>">
    <span class="ico bg-info">📩</span>
    <span><span class="num"><?= num($stats['incoming']) ?></span><span class="lbl">معاملة واردة</span></span>
  </a>
  <a class="stat" href="<?= e(url('transactions/index', ['direction' => 'outgoing'])) ?>">
    <span class="ico bg-brand">📤</span>
    <span><span class="num"><?= num($stats['outgoing']) ?></span><span class="lbl">معاملة صادرة</span></span>
  </a>
  <a class="stat" href="<?= e(url('transactions/index', ['direction' => 'internal'])) ?>">
    <span class="ico bg-purple">📝</span>
    <span><span class="num"><?= num($stats['internal']) ?></span><span class="lbl">مذكرة داخلية</span></span>
  </a>
  <a class="stat" href="<?= e(url('transactions/index', ['status' => 'in_progress'])) ?>">
    <span class="ico bg-warn">⏳</span>
    <span><span class="num"><?= num($stats['in_progress']) ?></span><span class="lbl">قيد الإجراء</span></span>
  </a>
  <a class="stat" href="<?= e(url('transactions/index', ['status' => 'completed'])) ?>">
    <span class="ico bg-ok">✅</span>
    <span><span class="num"><?= num($stats['completed']) ?></span><span class="lbl">منجزة</span></span>
  </a>
  <a class="stat" href="<?= e(url('transactions/index', ['overdue' => 1])) ?>">
    <span class="ico bg-danger">🔴</span>
    <span><span class="num"><?= num($stats['overdue']) ?></span><span class="lbl">متأخرة عن موعدها</span></span>
  </a>
</div>

<div class="grid grid-6 mb">
  <a class="stat" href="<?= e(url('documents/index')) ?>">
    <span class="ico bg-brand">📄</span>
    <span><span class="num"><?= num($stats['documents']) ?></span><span class="lbl">وثيقة مؤرشفة</span></span>
  </a>
  <a class="stat" href="<?= e(url('attachments/gallery')) ?>">
    <span class="ico bg-info">📎</span>
    <span><span class="num"><?= num($stats['attachments']) ?></span><span class="lbl">مرفق</span></span>
  </a>
  <a class="stat" href="<?= e(url('attachments/gallery', ['kind' => 'drive'])) ?>">
    <span class="ico bg-warn">☁️</span>
    <span><span class="num"><?= num($stats['drive_links']) ?></span><span class="lbl">رابط قوقل درايف</span></span>
  </a>
  <a class="stat" href="<?= e(url('folders/index')) ?>">
    <span class="ico bg-purple">🗂️</span>
    <span><span class="num"><?= num($stats['folders']) ?></span><span class="lbl">مجلد أرشيف</span></span>
  </a>
  <div class="stat">
    <span class="ico bg-muted">💾</span>
    <span><span class="num" style="font-size:1.1rem"><?= e(Uploader::humanSize($stats['storage'])) ?></span><span class="lbl">حجم الملفات</span></span>
  </div>
  <div class="stat">
    <span class="ico bg-ok">👥</span>
    <span><span class="num"><?= num($stats['users']) ?></span><span class="lbl">مستخدم نشط</span></span>
  </div>
</div>

<div class="grid grid-2">
  <!-- ========================== المهام المحالة إلي ========================== -->
  <div class="card">
    <div class="card-head">
      <h3>📥 معاملات بانتظار إجرائي</h3>
      <span class="spacer"></span>
      <a href="<?= e(url('transactions/inbox')) ?>" class="btn outline sm">عرض الكل</a>
    </div>
    <div class="card-body tight">
      <?php if (!$myTasks): ?>
        <div class="empty"><span class="big">🎉</span>لا توجد معاملات بانتظار إجرائك</div>
      <?php else: ?>
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>الرقم</th><th>الموضوع</th><th>نوع الإحالة</th><th>من</th><th>الأهمية</th></tr></thead>
          <tbody>
          <?php foreach ($myTasks as $t): ?>
            <tr onclick="location.href='<?= e(url('transactions/show', ['id' => $t['transaction_id']])) ?>'" style="cursor:pointer">
              <td class="nowrap"><b><?= e($t['transaction_no']) ?></b></td>
              <td><?= e(excerpt($t['subject'], 45)) ?></td>
              <td><span class="badge info"><?= e(labelRoutingAction($t['action'])) ?></span></td>
              <td class="small"><?= e($t['from_name'] ?? '-') ?></td>
              <td><span class="badge <?= e(priorityClass($t['priority'])) ?>"><?= e(labelPriority($t['priority'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- =========================== أحدث المعاملات =========================== -->
  <div class="card">
    <div class="card-head">
      <h3>🕒 أحدث المعاملات</h3>
      <span class="spacer"></span>
      <a href="<?= e(url('transactions/index')) ?>" class="btn outline sm">عرض الكل</a>
    </div>
    <div class="card-body tight">
      <?php if (!$latest): ?>
        <div class="empty"><span class="big">📭</span>لا توجد معاملات بعد</div>
      <?php else: ?>
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>الرقم</th><th>النوع</th><th>الموضوع</th><th>التاريخ</th><th>الحالة</th></tr></thead>
          <tbody>
          <?php foreach ($latest as $t): ?>
            <tr onclick="location.href='<?= e(url('transactions/show', ['id' => $t['id']])) ?>'" style="cursor:pointer">
              <td class="nowrap"><b><?= e($t['transaction_no']) ?></b></td>
              <td class="nowrap"><span class="badge brand"><?= e(labelDirection($t['direction'])) ?></span></td>
              <td><?= e(excerpt($t['subject'], 40)) ?></td>
              <td class="nowrap small"><?= e(gdate($t['transaction_date'])) ?></td>
              <td><span class="badge <?= e(statusClass($t['status'])) ?>"><?= e(labelStatus($t['status'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="grid grid-2">
  <!-- ====================== حركة المعاملات (٦ أشهر) ====================== -->
  <div class="card">
    <div class="card-head"><h3>📈 حركة المعاملات خلال ٦ أشهر</h3></div>
    <div class="card-body">
      <?php if (!$chart): ?>
        <div class="empty"><span class="big">📊</span>لا توجد بيانات كافية</div>
      <?php else:
        $max = 1;
        foreach ($chart as $c) { $max = max($max, (int)$c['incoming'] + (int)$c['outgoing'] + (int)$c['internal']); } ?>
      <div class="bars">
        <?php foreach ($chart as $c):
          $in = (int)$c['incoming']; $out = (int)$c['outgoing']; $int = (int)$c['internal']; ?>
          <div class="col" title="<?= e($c['ym']) ?> — الإجمالي <?= $in + $out + $int ?>">
            <div class="stack">
              <div class="seg" style="height:<?= round($in  / $max * 150) ?>px;background:var(--info)"></div>
              <div class="seg" style="height:<?= round($out / $max * 150) ?>px;background:var(--brand)"></div>
              <div class="seg" style="height:<?= round($int / $max * 150) ?>px;background:var(--purple)"></div>
            </div>
            <div class="lbl"><?= e($c['ym']) ?></div>
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

  <!-- ========================== التوزيع بالتصنيف ========================== -->
  <div class="card">
    <div class="card-head"><h3>🏷️ التوزيع حسب التصنيف</h3></div>
    <div class="card-body">
      <?php if (!$byCategory): ?>
        <div class="empty"><span class="big">🏷️</span>لا توجد بيانات</div>
      <?php else: $maxC = max(array_map(static fn($c) => (int) $c['total'], $byCategory)) ?: 1; ?>
      <ul class="rank-list">
        <?php foreach ($byCategory as $c): ?>
          <li>
            <span class="badge" style="background:<?= e($c['color']) ?>22;color:<?= e($c['color']) ?>">●</span>
            <span class="name"><?= e($c['name']) ?></span>
            <span class="bar"><span style="width:<?= round($c['total'] / $maxC * 100) ?>%;background:<?= e($c['color']) ?>"></span></span>
            <span class="val"><?= num($c['total']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ============================= أحدث الوثائق ============================= -->
<div class="card">
  <div class="card-head">
    <h3>📄 أحدث الوثائق المؤرشفة</h3>
    <span class="spacer"></span>
    <a href="<?= e(url('documents/index')) ?>" class="btn outline sm">عرض الكل</a>
  </div>
  <div class="card-body">
    <?php if (!$latestDocs): ?>
      <div class="empty"><span class="big">📂</span>لم تُضف وثائق بعد</div>
    <?php else: ?>
    <div class="att-cards">
      <?php foreach ($latestDocs as $d): ?>
        <a class="att-card" href="<?= e(url('documents/show', ['id' => $d['id']])) ?>">
          <div class="thumb">📄</div>
          <div class="body">
            <b><?= e(excerpt($d['title'], 32)) ?></b>
            <small>🗂️ <?= e($d['folder_name']) ?></small>
            <small><?= e(gdate($d['doc_date'] ?: $d['created_at'])) ?> · <?= (int) $d['attachments_count'] ?> مرفق</small>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
