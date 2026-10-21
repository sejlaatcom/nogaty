<?php
declare(strict_types=1);
define('APP_AREA', 'student');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';

$user      = Auth::requireStudent();
$classroom = $user['classroom_id'] ? Plans::classroom((int) $user['classroom_id']) : null;

page_start('الرئيسية', 'student', 'index');
?>
<div class="hero">
  <h1>أهلاً <?= h($user['name']) ?> 👋</h1>
  <p><?= h(Settings::get('nafes_title')) ?></p>
  <div class="hero-meta">
    <span><?= h($classroom ? Plans::classLabel($classroom) : 'لم يُحدَّد الفصل') ?></span>
    <span><?= h(Settings::get('school_name')) ?></span>
  </div>
</div>

<?php if (!$classroom): ?>
  <div class="alert alert-warning">لم يتم تحديد فصلك الدراسي بعد، يرجى مراجعة إدارة المدرسة.</div>
<?php endif; ?>

<div class="card">
  <div class="card-head"><h2>ما هي اختبارات نافس؟</h2></div>
  <div class="card-body">
    <p>اختبارات «نافس» الوطنية تقيس مدى تمكنك من المهارات الأساسية في <b>القراءة والرياضيات والعلوم</b>.
       في هذه المنصة تجد اختبارات تدريبية يعدّها معلموك، وتدريباً ذاتياً على كل مهارة، ونتائج توضّح مستواك ونقاط القوة والتحسين.</p>
  </div>
</div>
<?php page_end('student'); ?>
