<?php
declare(strict_types=1);
define('APP_AREA', 'admin');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';

Auth::requireAdmin();

$labels = [
    'login' => 'تسجيل دخول', 'logout' => 'خروج', 'plan_submit' => 'إرسال خطة',
    'plan_approve' => 'اعتماد خطة', 'plan_reject' => 'إرجاع خطة', 'plan_reopen' => 'فتح خطة',
    'user_create' => 'إضافة مستخدم', 'user_update' => 'تعديل مستخدم', 'user_delete' => 'حذف مستخدم',
    'password_reset' => 'إعادة تعيين كلمة مرور', 'password_change' => 'تغيير كلمة المرور',
    'settings_update' => 'تعديل الإعدادات', 'weeks_generate' => 'توليد أسابيع',
    'assignments_save' => 'حفظ إسناد', 'import_teachers' => 'استيراد معلمين',
    'import_assignments' => 'استيراد إسناد', 'import_plans' => 'استيراد خطط',
];

$logs = Activity::recent(200);

page_start('سجل العمليات', 'admin', 'logs');
?>
<div class="card">
  <div class="card-head"><h2>آخر 200 عملية</h2></div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>التاريخ</th><th>المستخدم</th><th>العملية</th><th>التفاصيل</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
          <tr>
            <td class="text-muted nowrap"><?= h($log['created_at']) ?></td>
            <td><?= h($log['user_name'] ?: '—') ?></td>
            <td><span class="badge badge-teal"><?= h($labels[$log['action']] ?? $log['action']) ?></span></td>
            <td class="text-muted"><?= h(str_short($log['details'], 70)) ?></td>
            <td class="text-muted"><?= h($log['ip'] ?: '—') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?><tr><td colspan="5" class="empty">لا توجد عمليات مسجلة.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php page_end('admin'); ?>
