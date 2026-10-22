<?php
declare(strict_types=1);
define('APP_AREA', 'nafes');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';
require APP_PATH . '/views/nafes.php';

$user = Auth::requireStaff();

$subjects  = Nafes::subjects();
$grades    = Nafes::grades();
$subjectId = input_int('subject');
$gradeId   = input_int('grade');

if (is_post()) {
    csrf_verify();
    $action = (string) input('action', '');
    $id     = input_int('id');
    $own    = $id ? QuestionBank::passage($id) : null;

    if ($id && (!$own || ($user['role'] !== 'admin' && (int) $own['created_by'] !== (int) $user['id']))) {
        flash('error', 'لا يمكنك تعديل نص أضافه معلم آخر.');
        redirect('nafes/passages.php');
    }

    if ($action === 'save') {
        $data = [
            'subject_id' => input_int('subject_id'),
            'grade_id'   => input_int('grade_id'),
            'title'      => (string) input('title', ''),
            'body'       => trim((string) ($_POST['body'] ?? '')),
        ];
        if (!$data['subject_id'] || !$data['grade_id'] || $data['title'] === '' || $data['body'] === '') {
            flash('error', 'جميع الحقول مطلوبة.');
            redirect('nafes/passages.php' . ($id ? '?edit=' . $id : ''));
        }
        if ($id) {
            Database::update('nafes_passages', $data, 'id = :pid', ['pid' => $id]);
            flash('success', 'تم تحديث النص.');
        } else {
            $id = Database::insert('nafes_passages', $data + ['created_by' => $user['id']]);
            flash('success', 'تمت إضافة النص. يمكنك الآن إضافة أسئلة عليه.');
        }
        redirect('nafes/passages.php?edit=' . $id);
    }

    if ($action === 'delete' && $id) {
        Database::delete('nafes_passages', 'id = ?', [$id]);
        flash('success', 'تم حذف النص (الأسئلة المرتبطة به بقيت في البنك بدون نص).');
        redirect('nafes/passages.php');
    }
}

$editId   = input_int('edit');
$edit     = $editId ? QuestionBank::passage($editId) : null;
$passages = QuestionBank::passages($subjectId ?: null, $gradeId ?: null);
$readingId = (int) Database::value("SELECT id FROM subjects WHERE name = 'لغتي'", [], 0);

page_start('نصوص القراءة', nafes_area(), 'nafes_passages');
?>

<div class="card">
  <div class="card-head"><h2><?= $edit ? 'تعديل نص: ' . h($edit['title']) : 'إضافة نص قراءة' ?></h2>
    <?php if ($edit): ?>
      <div class="flex">
        <a class="btn btn-sm btn-primary" href="<?= h(url('nafes/question.php?passage=' . (int) $edit['id'] . '&subject=' . (int) $edit['subject_id'] . '&grade=' . (int) $edit['grade_id'])) ?>">+ سؤال على هذا النص</a>
        <a class="btn btn-sm btn-outline" href="<?= h(url('nafes/passages.php')) ?>">نص جديد</a>
      </div>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <div class="grid grid-3">
        <div class="field"><label>العنوان *</label><input type="text" name="title" value="<?= h($edit['title'] ?? '') ?>" required></div>
        <div class="field"><label>المادة *</label><select name="subject_id" required><?= options_html($subjects, (int) ($edit['subject_id'] ?? ($subjectId ?: $readingId))) ?></select></div>
        <div class="field"><label>الصف *</label><select name="grade_id" required><?= options_html($grades, (int) ($edit['grade_id'] ?? $gradeId)) ?></select></div>
      </div>
      <div class="field"><label>النص *</label><textarea name="body" rows="10" data-grow required><?= h($edit['body'] ?? '') ?></textarea></div>
      <button class="btn btn-primary" type="submit"><?= $edit ? 'حفظ التعديلات' : 'إضافة النص' ?></button>
    </form>
  </div>
</div>

<div class="filters">
  <form method="get">
    <div class="field"><label>المادة</label><select name="subject" data-auto-submit><?= options_html($subjects, $subjectId, 'كل المواد') ?></select></div>
    <div class="field"><label>الصف</label><select name="grade" data-auto-submit><?= options_html($grades, $gradeId, 'كل الصفوف') ?></select></div>
  </form>
</div>

<div class="card">
  <div class="card-head"><h2>النصوص (<?= count($passages) ?>)</h2></div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>العنوان</th><th>المادة / الصف</th><th>مقتطف</th><th>الأسئلة</th><th>أضافه</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($passages as $passage): ?>
          <tr>
            <td><strong><?= h($passage['title']) ?></strong></td>
            <td><?= h($passage['subject_name'] . ' — ' . $passage['grade_name']) ?></td>
            <td class="text-muted"><?= h(str_short($passage['body'], 70)) ?></td>
            <td><a href="<?= h(url('nafes/questions.php?passage=' . (int) $passage['id'])) ?>"><?= (int) $passage['questions_count'] ?></a></td>
            <td class="text-muted"><?= h($passage['author_name'] ?? '—') ?></td>
            <td class="actions">
              <a class="btn btn-sm btn-outline" href="<?= h(url('nafes/passages.php?edit=' . (int) $passage['id'])) ?>">فتح</a>
              <?php if ($user['role'] === 'admin' || (int) $passage['created_by'] === (int) $user['id']): ?>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $passage['id'] ?>">
                <button class="btn btn-sm btn-danger" name="action" value="delete" data-confirm="حذف النص؟">حذف</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$passages): ?>
          <tr><td colspan="6" class="empty">لا توجد نصوص بعد. نصوص القراءة تُستخدم لأسئلة الفهم القرائي في مادة لغتي.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php page_end(nafes_area()); ?>
