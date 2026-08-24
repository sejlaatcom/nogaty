<div class="page-head"><h1>👤 ملفي الشخصي</h1></div>

<div class="grid" style="grid-template-columns:1fr 1fr">
  <div>
    <div class="card">
      <div class="card-head"><h3>البيانات الشخصية</h3></div>
      <div class="card-body">
        <form method="post" action="<?= e(url('profile/update')) ?>">
          <?= Csrf::field() ?>
          <div class="form-grid two">
            <div class="field"><label>الاسم الكامل</label>
              <input type="text" name="full_name" value="<?= e($user['full_name']) ?>" required maxlength="150"></div>
            <div class="field"><label>اسم الدخول</label>
              <input type="text" value="<?= e($user['username']) ?>" readonly dir="ltr"></div>
            <div class="field"><label>البريد الإلكتروني</label>
              <input type="email" name="email" value="<?= e($user['email']) ?>" dir="ltr"></div>
            <div class="field"><label>الجوال</label>
              <input type="tel" name="phone" value="<?= e($user['phone']) ?>" dir="ltr"></div>
            <div class="field"><label>المسمى الوظيفي</label>
              <input type="text" value="<?= e($user['job_title'] ?: '-') ?>" readonly></div>
            <div class="field"><label>الإدارة</label>
              <input type="text" value="<?= e($user['department_name'] ?: '-') ?>" readonly></div>
            <div class="field"><label>الدور</label>
              <input type="text" value="<?= e($user['role_name']) ?>" readonly></div>
            <div class="field"><label>آخر دخول</label>
              <input type="text" value="<?= e(gdate($user['last_login_at'], true)) ?>" readonly></div>
          </div>
          <div class="form-actions mt">
            <button class="btn">💾 حفظ البيانات</button>
            <a class="btn outline" href="<?= e(url('profile/password')) ?>">🔑 تغيير كلمة المرور</a>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h3>📊 نشاطي</h3></div>
      <div class="card-body">
        <div class="grid grid-2">
          <div class="stat"><span class="ico bg-brand">🗃️</span>
            <span><span class="num"><?= num($stats['created']) ?></span><span class="lbl">معاملة أدخلتها</span></span></div>
          <div class="stat"><span class="ico bg-info">📄</span>
            <span><span class="num"><?= num($stats['documents']) ?></span><span class="lbl">وثيقة أضفتها</span></span></div>
          <div class="stat"><span class="ico bg-warn">⏳</span>
            <span><span class="num"><?= num($stats['pending']) ?></span><span class="lbl">بانتظار إجرائي</span></span></div>
          <div class="stat"><span class="ico bg-ok">✅</span>
            <span><span class="num"><?= num($stats['done']) ?></span><span class="lbl">إحالة أنجزتها</span></span></div>
        </div>
      </div>
    </div>
  </div>

  <div>
    <div class="card">
      <div class="card-head"><h3>🔐 صلاحياتي</h3></div>
      <div class="card-body">
        <?php if (Auth::isAdmin()): ?>
          <div class="alert success"><span>👑</span><span>لديك صلاحيات مدير النظام الكاملة على جميع أجزاء النظام.</span></div>
        <?php endif; ?>
        <?php
        $grouped = [];
        foreach ($permissions as $p) { $grouped[$p['group_name']][] = $p['name']; }
        foreach ($grouped as $g => $items): ?>
          <b class="small"><?= e($g) ?></b>
          <div class="flex mb"><?php foreach ($items as $i): ?><span class="chip">✓ <?= e($i) ?></span><?php endforeach; ?></div>
        <?php endforeach; ?>
        <?php if (!$permissions && !Auth::isAdmin()): ?>
          <p class="muted">لم تُمنح صلاحيات بعد.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h3>🕒 آخر عمليات الدخول</h3></div>
      <div class="card-body tight">
        <div class="table-wrap">
          <table class="data">
            <thead><tr><th>التاريخ والوقت</th><th>عنوان IP</th></tr></thead>
            <tbody>
            <?php foreach ($lastLogins as $l): ?>
              <tr><td class="small"><?= e(gdate($l['created_at'], true)) ?></td>
                  <td class="small ltr"><?= e($l['ip_address']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$lastLogins): ?><tr><td colspan="2" class="center muted">لا يوجد سجل</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
