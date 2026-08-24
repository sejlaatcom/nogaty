<div class="page-head">
  <h1>
    <span class="badge brand"><?= e(labelDirection($trx['direction'])) ?></span>
    <?= e($trx['transaction_no']) ?>
  </h1>
  <span class="spacer"></span>
  <?php if (can('transactions.export')): ?>
    <a class="btn outline sm" href="<?= e(url('transactions/printView', ['id' => $trx['id']])) ?>" target="_blank">🖨️ طباعة</a>
  <?php endif; ?>
  <?php if (can('transactions.edit')): ?>
    <a class="btn outline sm" href="<?= e(url('transactions/edit', ['id' => $trx['id']])) ?>">✏️ تعديل</a>
  <?php endif; ?>
  <?php if (can('transactions.route')): ?>
    <button class="btn sm" data-modal="modal-route">➡️ إحالة</button>
  <?php endif; ?>
  <?php if (can('transactions.delete')): ?>
    <form method="post" action="<?= e(url('transactions/delete')) ?>" style="display:inline"
          data-confirm="سيتم نقل المعاملة إلى سلة المحذوفات. متابعة؟">
      <?= Csrf::field() ?>
      <input type="hidden" name="id" value="<?= (int) $trx['id'] ?>">
      <button class="btn danger sm">🗑️ حذف</button>
    </form>
  <?php endif; ?>
</div>

<div class="grid" style="grid-template-columns:2fr 1fr">
  <div>
    <!-- ============================ تفاصيل المعاملة ============================ -->
    <div class="card">
      <div class="card-head">
        <h3>📋 <?= e($trx['subject']) ?></h3>
      </div>
      <div class="card-body">
        <div class="flex mb">
          <span class="badge <?= e(statusClass($trx['status'])) ?>"><?= e(labelStatus($trx['status'])) ?></span>
          <span class="badge <?= e(priorityClass($trx['priority'])) ?>"><?= e(labelPriority($trx['priority'])) ?></span>
          <?php if ($trx['confidentiality'] !== 'normal'): ?>
            <span class="badge danger">🔒 <?= e(labelConfidentiality($trx['confidentiality'])) ?></span>
          <?php endif; ?>
          <?php if ($trx['category_name']): ?>
            <span class="badge" style="background:<?= e($trx['category_color'] ?: '#f1f5f9') ?>22;color:<?= e($trx['category_color'] ?: '#64748b') ?>"><?= e($trx['category_name']) ?></span>
          <?php endif; ?>
          <?php foreach ($tags as $tg): ?><span class="chip">#<?= e($tg) ?></span><?php endforeach; ?>
        </div>

        <dl class="dl">
          <dt>رقم القيد</dt><dd><b dir="ltr"><?= e($trx['transaction_no']) ?></b></dd>
          <?php if ($trx['reference_no']): ?>
            <dt>رقم الجهة</dt><dd dir="ltr"><?= e($trx['reference_no']) ?></dd>
          <?php endif; ?>
          <dt>تاريخ المعاملة</dt><dd><?= e(gdate($trx['transaction_date'])) ?> م — <?= e($trx['hijri_date'] ?: Hijri::format($trx['transaction_date'])) ?></dd>
          <?php if ($trx['received_date']): ?>
            <dt>تاريخ الاستلام</dt><dd><?= e(gdate($trx['received_date'])) ?></dd>
          <?php endif; ?>
          <?php if ($trx['due_date']): ?>
            <dt>تاريخ الاستحقاق</dt>
            <dd>
              <?= e(gdate($trx['due_date'])) ?>
              <?php if ($trx['due_date'] < date('Y-m-d') && !in_array($trx['status'], ['completed','closed','archived'], true)): ?>
                <span class="badge danger">متأخرة <?= (int) ((strtotime('today') - strtotime($trx['due_date'])) / 86400) ?> يوم</span>
              <?php endif; ?>
            </dd>
          <?php endif; ?>
          <?php if ($trx['correspondent_name']): ?>
            <dt>الجهة</dt><dd><?= e($trx['correspondent_name']) ?> <span class="muted small">(<?= e(labelCorrespondentType($trx['correspondent_type'])) ?>)</span></dd>
          <?php endif; ?>
          <?php if ($trx['from_dept']): ?><dt>من إدارة</dt><dd><?= e($trx['from_dept']) ?></dd><?php endif; ?>
          <?php if ($trx['to_dept']): ?><dt>إلى إدارة</dt><dd><?= e($trx['to_dept']) ?></dd><?php endif; ?>
          <?php if ($trx['folder_name']): ?>
            <dt>موقع الأرشفة</dt>
            <dd><a href="<?= e(url('folders/index', ['id' => $trx['folder_id']])) ?>">🗂️ <?= e($trx['folder_name']) ?></a></dd>
          <?php endif; ?>
          <?php if ($trx['year_name']): ?><dt>العام الدراسي</dt><dd><?= e($trx['year_name']) ?></dd><?php endif; ?>
          <?php if ($trx['linked_no']): ?>
            <dt>مرتبطة بـ</dt>
            <dd><a href="<?= e(url('transactions/show', ['id' => $trx['linked_transaction_id']])) ?>">
              <?= e($trx['linked_no']) ?> — <?= e(excerpt($trx['linked_subject'], 40)) ?></a></dd>
          <?php endif; ?>
          <?php if ($trx['keywords']): ?><dt>كلمات مفتاحية</dt><dd class="small"><?= e($trx['keywords']) ?></dd><?php endif; ?>
          <dt>أُدخلت بواسطة</dt><dd><?= e($trx['creator_name'] ?? '-') ?> — <?= e(gdate($trx['created_at'], true)) ?></dd>
          <?php if ($trx['updater_name']): ?>
            <dt>آخر تعديل</dt><dd><?= e($trx['updater_name']) ?> — <?= e(gdate($trx['updated_at'], true)) ?></dd>
          <?php endif; ?>
        </dl>

        <?php if ($trx['body']): ?>
          <hr><h4>نص المعاملة</h4>
          <div style="white-space:pre-wrap;background:#f8fafc;padding:.8rem;border-radius:8px"><?= e($trx['body']) ?></div>
        <?php endif; ?>
        <?php if ($trx['notes']): ?>
          <hr><h4>ملاحظات</h4>
          <div class="small muted" style="white-space:pre-wrap"><?= e($trx['notes']) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ================================ المرفقات ================================ -->
    <div class="card">
      <div class="card-head">
        <h3>📎 المرفقات</h3>
        <span class="spacer"></span>
        <span class="chip"><?= count($attachments) ?> مرفق</span>
      </div>
      <div class="card-body">
        <?php View::partial('attachment_list', [
          'attachments' => $attachments,
          'ownerType'   => 'transaction',
          'ownerId'     => (int) $trx['id'],
        ]); ?>
      </div>
    </div>
  </div>

  <div>
    <!-- ============================ سجل الإحالات ============================ -->
    <div class="card">
      <div class="card-head"><h3>➡️ سجل الإحالات</h3></div>
      <div class="card-body">
        <?php if (!$routings): ?>
          <div class="empty"><span class="big">📭</span>لم تُحل المعاملة بعد</div>
        <?php else: ?>
        <ul class="timeline">
          <?php foreach ($routings as $r): ?>
            <li class="<?= e($r['status']) ?>">
              <div class="t-head">
                <b><?= e($r['to_name'] ?? $r['to_dept_name'] ?? '-') ?></b>
                <span class="badge info"><?= e(labelRoutingAction($r['action'])) ?></span>
                <span class="badge <?= $r['status'] === 'done' ? 'ok' : ($r['status'] === 'returned' ? 'danger' : 'warn') ?>">
                  <?= e(labelRoutingStatus($r['status'])) ?>
                </span>
              </div>
              <div class="t-time">
                من <?= e($r['from_name'] ?? 'النظام') ?> · <?= e(timeAgo($r['created_at'])) ?>
                <?php if ($r['due_date']): ?> · موعد: <?= e(gdate($r['due_date'])) ?><?php endif; ?>
              </div>
              <?php if ($r['instructions']): ?>
                <div class="t-body"><b>التأشيرة:</b> <?= e($r['instructions']) ?></div>
              <?php endif; ?>
              <?php if ($r['response']): ?>
                <div class="t-body" style="background:var(--brand-soft)"><b>الرد:</b> <?= e($r['response']) ?></div>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </div>

    <!-- ============================== الرد على الإحالة ============================== -->
    <?php if ($myRouting && in_array($myRouting['status'], ['pending','seen'], true)): ?>
    <div class="card">
      <div class="card-head"><h3>✍️ ردّي على الإحالة</h3></div>
      <div class="card-body">
        <form method="post" action="<?= e(url('transactions/respond')) ?>" data-once>
          <?= Csrf::field() ?>
          <input type="hidden" name="routing_id" value="<?= (int) $myRouting['id'] ?>">
          <div class="field mb">
            <label>الإجراء</label>
            <select name="routing_status">
              <option value="done">تم الإنجاز</option>
              <option value="seen">تم الاطلاع فقط</option>
              <option value="returned">إعادة للمُحيل</option>
            </select>
          </div>
          <div class="field mb">
            <label>ملاحظات الرد</label>
            <textarea name="response" rows="3" placeholder="اكتب ما تم بشأن المعاملة"></textarea>
          </div>
          <button class="btn block">إرسال الرد</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- ============================== تغيير الحالة ============================== -->
    <?php if (can('transactions.edit')): ?>
    <div class="card">
      <div class="card-head"><h3>🔄 تغيير الحالة</h3></div>
      <div class="card-body">
        <form method="post" action="<?= e(url('transactions/changeStatus')) ?>" class="flex">
          <?= Csrf::field() ?>
          <input type="hidden" name="id" value="<?= (int) $trx['id'] ?>">
          <select name="status" style="flex:1">
            <?php foreach (['draft','registered','in_progress','completed','archived','closed'] as $s): ?>
              <option value="<?= $s ?>" <?= $trx['status'] === $s ? 'selected' : '' ?>><?= e(labelStatus($s)) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn sm">حفظ</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- ============================== روابط المشاركة ============================== -->
    <?php if (can('shares.manage')): ?>
    <div class="card">
      <div class="card-head">
        <h3>🔗 المشاركة</h3>
        <span class="spacer"></span>
        <button class="btn outline sm" data-modal="modal-share">إنشاء رابط</button>
      </div>
      <div class="card-body">
        <?php if (!$shares): ?>
          <p class="muted small">لا توجد روابط مشاركة نشطة.</p>
        <?php else: foreach ($shares as $s):
          $link = (isset($_SERVER['HTTP_HOST']) ? (($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . $_SERVER['HTTP_HOST']
                   . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/') : '')
                   . 'index.php?r=share/view&token=' . $s['token']; ?>
          <div class="sep-y small">
            <div class="flex-between">
              <span class="truncate ltr xsmall" style="max-width:60%"><?= e($s['token']) ?></span>
              <span class="btn-group">
                <button class="btn outline xs" data-copy="<?= e($link) ?>">نسخ</button>
                <form method="post" action="<?= e(url('share/revoke')) ?>" style="display:inline" data-confirm="إلغاء رابط المشاركة؟">
                  <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                  <button class="btn danger xs">إلغاء</button>
                </form>
              </span>
            </div>
            <div class="muted xsmall">
              ينتهي: <?= e(gdate($s['expires_at'], true)) ?> ·
              <?= (int) $s['views'] ?> مشاهدة ·
              <?= $s['password_hash'] ? '🔒 محمي بكلمة مرور' : 'بدون كلمة مرور' ?>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ================================ نافذة الإحالة ================================ -->
<?php if (can('transactions.route')): ?>
<div class="modal" id="modal-route">
  <div class="modal-box">
    <form method="post" action="<?= e(url('transactions/route')) ?>" data-once>
      <?= Csrf::field() ?>
      <input type="hidden" name="transaction_id" value="<?= (int) $trx['id'] ?>">
      <div class="modal-head"><h3>➡️ إحالة المعاملة</h3>
        <button type="button" class="x" onclick="closeModal('modal-route')">&times;</button></div>
      <div class="modal-body">
        <div class="field mb">
          <label>إحالة إلى موظفين (يمكن اختيار أكثر من واحد)</label>
          <select name="to_user_id[]" multiple size="8">
            <?php foreach ($lists['users'] as $u): ?>
              <option value="<?= (int) $u['id'] ?>"><?= e($u['full_name']) ?><?= $u['dept'] ? ' — ' . e($u['dept']) : '' ?></option>
            <?php endforeach; ?>
          </select>
          <span class="hint">اضغط Ctrl مع النقر للاختيار المتعدد</span>
        </div>
        <div class="field mb">
          <label>أو إحالة لكل موظفي إدارة</label>
          <select name="to_department_id">
            <option value="">— لا شيء —</option>
            <?php foreach ($lists['departments'] as $d): ?>
              <option value="<?= (int) $d['id'] ?>"><?= e($d['_label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-grid two">
          <div class="field">
            <label>نوع الإحالة</label>
            <select name="action_type">
              <?php foreach (['for_action','for_info','for_review','for_approval','for_signature','for_archive'] as $a): ?>
                <option value="<?= $a ?>"><?= e(labelRoutingAction($a)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>موعد الإنجاز</label>
            <input type="date" name="due_date">
          </div>
        </div>
        <div class="field mt-sm">
          <label>التأشيرة / التعليمات</label>
          <textarea name="instructions" rows="3" placeholder="مثال : يُعمم على المعلمين ويُرفع بما تم"></textarea>
        </div>
      </div>
      <div class="modal-foot">
        <button type="submit" class="btn">إحالة</button>
        <button type="button" class="btn outline" onclick="closeModal('modal-route')">إلغاء</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- =============================== نافذة المشاركة =============================== -->
<?php if (can('shares.manage')): ?>
<div class="modal" id="modal-share">
  <div class="modal-box">
    <form method="post" action="<?= e(url('share/create')) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="shareable_type" value="transaction">
      <input type="hidden" name="shareable_id" value="<?= (int) $trx['id'] ?>">
      <div class="modal-head"><h3>🔗 إنشاء رابط مشاركة</h3>
        <button type="button" class="x" onclick="closeModal('modal-share')">&times;</button></div>
      <div class="modal-body">
        <div class="form-grid two">
          <div class="field">
            <label>مدة الصلاحية (أيام)</label>
            <input type="number" name="expires_days" value="7" min="1" max="365">
          </div>
          <div class="field">
            <label>كلمة مرور (اختياري)</label>
            <input type="text" name="share_password" placeholder="اتركه فارغاً لرابط مفتوح" dir="ltr">
          </div>
        </div>
        <label class="check mt-sm"><input type="checkbox" name="allow_download" value="1" checked> السماح بتحميل المرفقات</label>
        <p class="hint mt-sm">الرابط يتيح الاطلاع على المعاملة ومرفقاتها دون الحاجة لحساب في النظام.</p>
      </div>
      <div class="modal-foot">
        <button type="submit" class="btn">إنشاء الرابط</button>
        <button type="button" class="btn outline" onclick="closeModal('modal-share')">إلغاء</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
