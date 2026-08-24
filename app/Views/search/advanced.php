<div class="page-head"><h1>⚙️ البحث المتقدم في المعاملات</h1></div>

<form class="card" method="get" action="index.php">
  <input type="hidden" name="r" value="transactions/index">
  <div class="card-head"><h3>معايير البحث</h3></div>
  <div class="card-body">
    <div class="form-grid">
      <div class="field full">
        <label>كلمة البحث (الموضوع أو النص أو الأرقام)</label>
        <input type="search" name="q" placeholder="اكتب كلمة أو رقم معاملة">
      </div>
      <div class="field">
        <label>نوع المعاملة</label>
        <select name="direction">
          <option value="">الكل</option>
          <option value="incoming">وارد</option>
          <option value="outgoing">صادر</option>
          <option value="internal">داخلي</option>
        </select>
      </div>
      <div class="field">
        <label>الحالة</label>
        <select name="status">
          <option value="">الكل</option>
          <?php foreach (['draft','registered','in_progress','completed','archived','closed'] as $s): ?>
            <option value="<?= $s ?>"><?= e(labelStatus($s)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>الأهمية</label>
        <select name="priority">
          <option value="">الكل</option>
          <?php foreach (['normal','urgent','very_urgent'] as $s): ?>
            <option value="<?= $s ?>"><?= e(labelPriority($s)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>درجة السرية</label>
        <select name="conf">
          <option value="">الكل</option>
          <?php foreach (['normal','confidential','top_secret'] as $s): ?>
            <option value="<?= $s ?>"><?= e(labelConfidentiality($s)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>الجهة المراسلة</label>
        <select name="corr">
          <option value="">الكل</option>
          <?php foreach ($lists['correspondents'] as $c): ?>
            <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>الإدارة</label>
        <select name="dept">
          <option value="">الكل</option>
          <?php foreach ($lists['departments'] as $d): ?>
            <option value="<?= (int) $d['id'] ?>"><?= e($d['_label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>مجلد الأرشفة (يشمل الفروع)</label>
        <select name="folder">
          <option value="">الكل</option>
          <?php foreach ($lists['folders'] as $f): ?>
            <option value="<?= (int) $f['id'] ?>"><?= e($f['_label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>التصنيف</label>
        <select name="category">
          <option value="">الكل</option>
          <?php foreach ($lists['categories'] as $c): ?>
            <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>العام الدراسي</label>
        <select name="year">
          <option value="">الكل</option>
          <?php foreach ($lists['years'] as $y): ?>
            <option value="<?= (int) $y['id'] ?>"><?= e($y['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>من تاريخ</label><input type="date" name="from"></div>
      <div class="field"><label>إلى تاريخ</label><input type="date" name="to"></div>
      <div class="field">
        <label>خيارات إضافية</label>
        <label class="check"><input type="checkbox" name="overdue" value="1"> المتأخرة عن موعد الإنجاز فقط</label>
        <label class="check"><input type="checkbox" name="has_attach" value="1"> التي لها مرفقات فقط</label>
      </div>
    </div>
  </div>
  <div class="card-foot">
    <div class="form-actions">
      <button class="btn">🔍 تنفيذ البحث</button>
      <a class="btn outline" href="<?= e(url('search/index')) ?>">البحث الشامل</a>
    </div>
  </div>
</form>

<?php if ($lists['tags']): ?>
<div class="card">
  <div class="card-head"><h3>🏷️ الوسوم المستخدمة</h3></div>
  <div class="card-body flex">
    <?php foreach ($lists['tags'] as $t): ?>
      <a class="chip" href="<?= e(url('search/index', ['q' => $t['name']])) ?>">#<?= e($t['name']) ?></a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
