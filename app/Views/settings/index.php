<div class="page-head"><h1>⚙️ إعدادات النظام</h1></div>

<form method="post" action="<?= e(url('settings/update')) ?>" data-once>
  <?= Csrf::field() ?>

  <div class="card">
    <div class="card-head"><h3>🏫 بيانات المدرسة</h3></div>
    <div class="card-body">
      <div class="form-grid">
        <div class="field"><label>اسم المدرسة</label>
          <input type="text" name="school_name" value="<?= e(setting('school_name')) ?>"></div>
        <div class="field"><label>المرحلة</label>
          <input type="text" name="school_stage" value="<?= e(setting('school_stage')) ?>" placeholder="ابتدائي / متوسط / ثانوي"></div>
        <div class="field"><label>إدارة التعليم</label>
          <input type="text" name="education_admin" value="<?= e(setting('education_admin')) ?>"></div>
        <div class="field"><label>اسم النظام</label>
          <input type="text" name="app_name" value="<?= e(setting('app_name')) ?>"></div>
        <div class="field"><label>رابط شعار المدرسة</label>
          <input type="text" name="school_logo" value="<?= e(setting('school_logo')) ?>" dir="ltr" placeholder="assets/logo.png"></div>
        <div class="field"><label>لون النظام</label>
          <input type="color" name="theme_color" value="<?= e(setting('theme_color', '#0f766e')) ?>"></div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3>🔢 ترقيم المعاملات والتواريخ</h3></div>
    <div class="card-body">
      <div class="form-grid">
        <div class="field">
          <label>صيغة رقم المعاملة</label>
          <input type="text" name="transaction_no_format" dir="ltr" value="<?= e(setting('transaction_no_format')) ?>">
          <span class="hint">المتغيرات: <code>{TYPE}</code> نوع المعاملة، <code>{YEAR}</code> السنة الميلادية،
            <code>{HYEAR}</code> السنة الهجرية، <code>{SEQ}</code> التسلسل</span>
        </div>
        <div class="field">
          <label>عدد خانات التسلسل</label>
          <input type="number" name="seq_padding" min="1" max="10" value="<?= e(setting('seq_padding', 5)) ?>">
          <span class="hint">مثال بـ ٥ خانات : و-2026-00012</span>
        </div>
        <div class="field">
          <label>عرض التواريخ</label>
          <select name="date_type">
            <option value="both"      <?= setting('date_type') === 'both' ? 'selected' : '' ?>>ميلادي وهجري</option>
            <option value="gregorian" <?= setting('date_type') === 'gregorian' ? 'selected' : '' ?>>ميلادي فقط</option>
            <option value="hijri"     <?= setting('date_type') === 'hijri' ? 'selected' : '' ?>>هجري فقط</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3>📎 المرفقات والعرض</h3></div>
    <div class="card-body">
      <div class="form-grid">
        <div class="field">
          <label>الحد الأقصى لحجم الملف (ميجابايت)</label>
          <input type="number" name="max_upload_mb" min="1" max="2048" value="<?= e(setting('max_upload_mb', 50)) ?>">
          <span class="hint">حد الخادم الحالي : <?= e($stats['upload_max']) ?> — لن يتجاوزه النظام</span>
        </div>
        <div class="field">
          <label>عدد السجلات في الصفحة</label>
          <input type="number" name="items_per_page" min="5" max="200" value="<?= e(setting('items_per_page', 20)) ?>">
        </div>
        <div class="field full">
          <label>الامتدادات المسموح برفعها</label>
          <input type="text" name="allowed_extensions" dir="ltr" value="<?= e(setting('allowed_extensions')) ?>">
          <span class="hint">مفصولة بفاصلة. الملفات التنفيذية ممنوعة دائماً لأسباب أمنية.</span>
        </div>
      </div>
      <div class="alert info mt">
        <span>💡</span>
        <span>للملفات الكبيرة (الفيديو والصوت والأرشيفات) يُفضّل رفعها على <b>قوقل درايف</b> وإضافة رابطها في المرفقات،
          فيولّد النظام تلقائياً رابط العرض ورابط التحميل المباشر دون استهلاك مساحة الخادم.</span>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3>🔒 الأمان والتدقيق</h3></div>
    <div class="card-body">
      <div class="form-grid">
        <div class="field">
          <label>عدد محاولات الدخول الفاشلة المسموحة</label>
          <input type="number" name="login_max_attempts" min="0" max="50" value="<?= e(setting('login_max_attempts', 5)) ?>">
          <span class="hint">صفر = بلا حد</span>
        </div>
        <div class="field">
          <label>مدة الإيقاف بعد تجاوز المحاولات (دقيقة)</label>
          <input type="number" name="login_lockout_minutes" min="1" max="1440" value="<?= e(setting('login_lockout_minutes', 15)) ?>">
        </div>
        <div class="field">
          <label>سجل الأحداث</label>
          <label class="check">
            <input type="checkbox" name="enable_audit" value="1" <?= setting('enable_audit', '1') === '1' ? 'checked' : '' ?>>
            تفعيل تسجيل كل العمليات
          </label>
        </div>
      </div>
    </div>
    <div class="card-foot">
      <div class="form-actions"><button class="btn">💾 حفظ كل الإعدادات</button></div>
    </div>
  </div>
</form>

<div class="grid grid-2">
  <div class="card">
    <div class="card-head"><h3>📊 معلومات النظام</h3></div>
    <div class="card-body">
      <dl class="dl">
        <dt>إصدار النظام</dt><dd><?= e($stats['app_version']) ?></dd>
        <dt>إصدار PHP</dt><dd dir="ltr"><?= e($stats['php_version']) ?></dd>
        <dt>إصدار MySQL</dt><dd dir="ltr"><?= e($stats['mysql_version']) ?></dd>
        <dt>حجم قاعدة البيانات</dt><dd><?= e($stats['db_size']) ?> ميجابايت</dd>
        <dt>الملفات المخزّنة</dt><dd><?= num($stats['files_count']) ?> ملف — <?= e(Uploader::humanSize($stats['files_size'])) ?></dd>
        <dt>روابط قوقل درايف</dt><dd><?= num($stats['drive_count']) ?> رابط</dd>
        <dt>حد رفع الملفات بالخادم</dt><dd dir="ltr"><?= e($stats['upload_max']) ?> / POST <?= e($stats['post_max']) ?></dd>
      </dl>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3>🔢 عدادات ترقيم المعاملات</h3></div>
    <div class="card-body tight">
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>النوع</th><th>السنة</th><th>آخر رقم</th><th>تصفير</th></tr></thead>
          <tbody>
          <?php if (!$sequences): ?>
            <tr><td colspan="4" class="center muted">لم تُقيّد معاملات بعد</td></tr>
          <?php else: foreach ($sequences as $s): ?>
            <tr>
              <td><?= e(labelDirection($s['name'])) ?></td>
              <td><?= (int) $s['year'] ?></td>
              <td><b><?= num($s['current_no']) ?></b></td>
              <td>
                <form method="post" action="<?= e(url('settings/resetSequence')) ?>" style="display:inline"
                      data-confirm="تصفير العداد قد يسبب تكرار الأرقام. متابعة؟">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="name" value="<?= e($s['name']) ?>">
                  <input type="hidden" name="year" value="<?= (int) $s['year'] ?>">
                  <button class="btn danger xs">تصفير</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <p class="hint mt-sm">يبدأ الترقيم تلقائياً من ١ مع كل سنة ميلادية جديدة.</p>
    </div>
  </div>
</div>

<?php if (can('audit.view')): ?>
<div class="card">
  <div class="card-head"><h3>🧹 صيانة سجل الأحداث</h3></div>
  <div class="card-body">
    <form method="post" action="<?= e(url('audit/purge')) ?>" class="flex"
          data-confirm="سيتم حذف السجلات الأقدم من المدة المحددة نهائياً. متابعة؟">
      <?= Csrf::field() ?>
      <label class="small">حذف السجلات الأقدم من</label>
      <input type="number" name="days" value="365" min="30" max="3650" style="width:120px">
      <span class="small">يوم</span>
      <button class="btn danger sm">تنفيذ</button>
    </form>
  </div>
</div>
<?php endif; ?>
