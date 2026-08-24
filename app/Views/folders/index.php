<div class="page-head">
  <h1>🌳 شجرة الأرشفة</h1>
  <span class="spacer"></span>
  <?php if (can('folders.manage')): ?>
    <button class="btn" data-modal="modal-folder-new"
            data-set-parent_id="<?= $current ? (int) $current['id'] : '' ?>">➕ مجلد جديد</button>
  <?php endif; ?>
  <?php if ($current && can('documents.create')): ?>
    <a class="btn outline" href="<?= e(url('documents/create', ['folder' => $current['id']])) ?>">📄 وثيقة في هذا المجلد</a>
  <?php endif; ?>
</div>

<div class="grid" style="grid-template-columns:320px 1fr">
  <!-- ================================ الشجرة ================================ -->
  <div class="card" style="align-self:start;position:sticky;top:70px;max-height:calc(100vh - 100px);overflow:auto">
    <div class="card-head"><h3>المجلدات</h3></div>
    <div class="card-body tight">
      <?php if (!$tree): ?>
        <div class="empty"><span class="big">🌱</span>لم تُنشأ مجلدات بعد</div>
      <?php else: View::partial('folder_tree', ['nodes' => $tree, 'currentId' => $current['id'] ?? 0]); endif; ?>
    </div>
  </div>

  <!-- ============================== محتوى المجلد ============================== -->
  <div>
    <?php if (!$current): ?>
      <div class="card"><div class="card-body">
        <div class="empty">
          <span class="big">👉</span>
          اختر مجلداً من الشجرة لعرض محتوياته من الوثائق والمعاملات المؤرشفة.
        </div>
      </div></div>

      <div class="card">
        <div class="card-head"><h3>ℹ️ عن شجرة الأرشفة</h3></div>
        <div class="card-body small">
          <p>شجرة الأرشفة تدعم مستويات غير محدودة، ويمكن تنظيمها بما يتوافق مع الهيكل التنظيمي للمدرسة. مثال:</p>
          <ul>
            <li><b>أرشيف المدرسة</b> ← <b>شؤون الطلاب</b> ← <b>ملفات الطلاب</b> ← <b>الصف الأول</b></li>
            <li><b>أرشيف المدرسة</b> ← <b>المعاملات الواردة</b> ← <b>1447 هـ</b> ← <b>الفصل الأول</b></li>
          </ul>
          <p>كل مجلد يمكن ربطه بإدارة من الهيكل التنظيمي، وتُحفظ داخله الوثائق والمعاملات ومرفقاتها.</p>
        </div>
      </div>
    <?php else: ?>

      <div class="breadcrumb">
        <a href="<?= e(url('folders/index')) ?>">🌳 الجذر</a>
        <?php foreach ($breadcrumb as $b): ?>
          <span class="sep">/</span>
          <a href="<?= e(url('folders/index', ['id' => $b['id']])) ?>"><?= e($b['name']) ?></a>
        <?php endforeach; ?>
      </div>

      <div class="card">
        <div class="card-head">
          <h3>📂 <?= e($current['name']) ?></h3>
          <span class="spacer"></span>
          <?php if (can('folders.manage')): ?>
            <button class="btn outline sm" data-modal="modal-folder-edit"
              data-set-id="<?= (int) $current['id'] ?>"
              data-set-name="<?= e($current['name']) ?>"
              data-set-code="<?= e($current['code']) ?>"
              data-set-description="<?= e($current['description']) ?>"
              data-set-parent_id="<?= (int) $current['parent_id'] ?>"
              data-set-department_id="<?= (int) $current['department_id'] ?>"
              data-set-sort_order="<?= (int) $current['sort_order'] ?>"
              data-set-is_active="<?= (int) $current['is_active'] ?>">✏️ تعديل</button>
            <form method="post" action="<?= e(url('folders/delete')) ?>" style="display:inline"
                  data-confirm="حذف المجلد وفروعه الفارغة؟">
              <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $current['id'] ?>">
              <button class="btn danger sm">🗑️</button>
            </form>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <dl class="dl">
            <?php if ($current['code']): ?><dt>الرمز</dt><dd dir="ltr"><?= e($current['code']) ?></dd><?php endif; ?>
            <?php if ($current['department_name']): ?><dt>الإدارة المالكة</dt><dd><?= e($current['department_name']) ?></dd><?php endif; ?>
            <?php if ($current['description']): ?><dt>الوصف</dt><dd><?= e($current['description']) ?></dd><?php endif; ?>
            <dt>المستوى</dt><dd><?= (int) $current['depth'] + 1 ?></dd>
            <dt>أُنشئ بواسطة</dt><dd><?= e($current['creator'] ?? '-') ?> — <?= e(gdate($current['created_at'])) ?></dd>
          </dl>
        </div>
      </div>

      <?php if ($children): ?>
      <div class="card">
        <div class="card-head"><h3>📁 المجلدات الفرعية (<?= count($children) ?>)</h3></div>
        <div class="card-body">
          <div class="att-cards">
            <?php foreach ($children as $c): ?>
              <a class="att-card" href="<?= e(url('folders/index', ['id' => $c['id']])) ?>">
                <div class="thumb" style="height:70px">📂</div>
                <div class="body">
                  <b><?= e($c['name']) ?></b>
                  <small><?= (int) $c['docs_count'] ?> وثيقة · <?= (int) $c['trx_count'] ?> معاملة</small>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-head">
          <h3>📄 الوثائق (<?= count($documents) ?>)</h3>
          <span class="spacer"></span>
          <?php if (can('documents.create')): ?>
            <a class="btn outline sm" href="<?= e(url('documents/create', ['folder' => $current['id']])) ?>">➕ إضافة</a>
          <?php endif; ?>
        </div>
        <div class="card-body tight">
          <?php if (!$documents): ?>
            <div class="empty"><span class="big">📄</span>لا توجد وثائق في هذا المجلد</div>
          <?php else: ?>
          <div class="table-wrap">
            <table class="data">
              <thead><tr><th>العنوان</th><th>الرقم</th><th>النوع</th><th>التاريخ</th><th>مرفقات</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($documents as $d): ?>
                <tr>
                  <td><a href="<?= e(url('documents/show', ['id' => $d['id']])) ?>"><?= e(excerpt($d['title'], 50)) ?></a></td>
                  <td class="nowrap small"><?= e($d['doc_no'] ?: '-') ?></td>
                  <td class="nowrap small"><?= e($d['doc_type'] ?: '-') ?></td>
                  <td class="nowrap small"><?= e(gdate($d['doc_date'] ?: $d['created_at'])) ?></td>
                  <td class="center"><?= (int) $d['attachments_count'] ? '📎 ' . (int) $d['attachments_count'] : '-' ?></td>
                  <td class="actions"><a class="btn outline xs" href="<?= e(url('documents/show', ['id' => $d['id']])) ?>">عرض</a></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($transactions): ?>
      <div class="card">
        <div class="card-head"><h3>🗃️ المعاملات المؤرشفة هنا (<?= count($transactions) ?>)</h3></div>
        <div class="card-body tight">
          <div class="table-wrap">
            <table class="data">
              <thead><tr><th>الرقم</th><th>النوع</th><th>الموضوع</th><th>الجهة</th><th>التاريخ</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($transactions as $t): ?>
                <tr>
                  <td class="nowrap"><b><?= e($t['transaction_no']) ?></b></td>
                  <td class="nowrap"><span class="badge brand"><?= e(labelDirection($t['direction'])) ?></span></td>
                  <td><a href="<?= e(url('transactions/show', ['id' => $t['id']])) ?>"><?= e(excerpt($t['subject'], 45)) ?></a></td>
                  <td class="small"><?= e(excerpt($t['correspondent_name'], 22)) ?></td>
                  <td class="nowrap small"><?= e(gdate($t['transaction_date'])) ?></td>
                  <td class="actions"><a class="btn outline xs" href="<?= e(url('transactions/show', ['id' => $t['id']])) ?>">عرض</a></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- ============================= نوافذ إدارة المجلدات ============================= -->
<?php if (can('folders.manage')):
  $folderFields = static function (array $flatFolders, array $departments, ?array $current): void { ?>
    <div class="form-grid two">
      <div class="field"><label>اسم المجلد <span class="req">*</span></label>
        <input type="text" name="name" required maxlength="200"></div>
      <div class="field"><label>الرمز</label><input type="text" name="code" maxlength="40" dir="ltr"></div>
      <div class="field"><label>المجلد الأب</label>
        <select name="parent_id">
          <option value="">— مجلد رئيسي —</option>
          <?php foreach ($flatFolders as $f): ?>
            <option value="<?= (int) $f['id'] ?>"><?= e($f['_label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>الإدارة المالكة</label>
        <select name="department_id">
          <option value="">— غير محدد —</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= (int) $d['id'] ?>"><?= e($d['_label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>ترتيب العرض</label><input type="number" name="sort_order" value="0"></div>
      <div class="field"><label>اللون</label><input type="color" name="color" value="#0f766e"></div>
      <div class="field full"><label>الوصف</label><textarea name="description" rows="2" maxlength="500"></textarea></div>
    </div>
  <?php }; ?>

<div class="modal" id="modal-folder-new">
  <div class="modal-box">
    <form method="post" action="<?= e(url('folders/store')) ?>">
      <?= Csrf::field() ?>
      <div class="modal-head"><h3>➕ مجلد أرشفة جديد</h3>
        <button type="button" class="x" onclick="closeModal('modal-folder-new')">&times;</button></div>
      <div class="modal-body"><?php $folderFields($flatFolders, $departments, $current); ?></div>
      <div class="modal-foot">
        <button class="btn">حفظ</button>
        <button type="button" class="btn outline" onclick="closeModal('modal-folder-new')">إلغاء</button>
      </div>
    </form>
  </div>
</div>

<div class="modal" id="modal-folder-edit">
  <div class="modal-box">
    <form method="post" action="<?= e(url('folders/update')) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="id" value="">
      <div class="modal-head"><h3>✏️ تعديل المجلد</h3>
        <button type="button" class="x" onclick="closeModal('modal-folder-edit')">&times;</button></div>
      <div class="modal-body">
        <?php $folderFields($flatFolders, $departments, $current); ?>
        <label class="check mt-sm"><input type="checkbox" name="is_active" value="1" checked> المجلد نشط</label>
      </div>
      <div class="modal-foot">
        <button class="btn">حفظ التعديلات</button>
        <button type="button" class="btn outline" onclick="closeModal('modal-folder-edit')">إلغاء</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
