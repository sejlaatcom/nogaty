<?php
declare(strict_types=1);
define('APP_AREA', 'nafes');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';
require APP_PATH . '/views/nafes.php';

$user = Auth::requireStaff();

$subjects  = Nafes::subjects();
$grades    = Nafes::grades();
$subjectId = input_int('subject') ?: (int) ($subjects[0]['id'] ?? 0);
$gradeId   = input_int('grade') ?: (int) ($grades[0]['id'] ?? 0);
$back      = 'nafes/skills.php?subject=' . $subjectId . '&grade=' . $gradeId;

if (is_post()) {
    csrf_verify();
    $action = (string) input('action', '');
    $id     = input_int('id');
    $name   = (string) input('name', '');

    if ($action === 'save_domain') {
        if ($name === '') {
            flash('error', 'اسم المجال مطلوب.');
        } elseif ($id) {
            Database::update('nafes_domains', ['name' => $name, 'sort_order' => input_int('sort_order')], 'id = :did', ['did' => $id]);
            flash('success', 'تم تحديث المجال.');
        } else {
            Database::insert('nafes_domains', [
                'subject_id' => $subjectId,
                'grade_id'   => input('all_grades') === '1' ? null : $gradeId,
                'name'       => $name,
                'sort_order' => (int) Database::value('SELECT COALESCE(MAX(sort_order),0)+1 FROM nafes_domains WHERE subject_id = ?', [$subjectId], 1),
            ]);
            flash('success', 'تمت إضافة المجال.');
        }
    }

    if ($action === 'delete_domain' && $id) {
        $count = (int) Database::value(
            'SELECT COUNT(*) FROM nafes_questions q JOIN nafes_skills s ON s.id = q.skill_id WHERE s.domain_id = ?', [$id], 0
        );
        Database::delete('nafes_domains', 'id = ?', [$id]);
        flash('success', 'تم حذف المجال ومهاراته' . ($count ? ' — ' . $count . ' سؤال أصبح بدون مهارة.' : '.'));
    }

    if ($action === 'save_skill') {
        $domainId = input_int('domain_id');
        $data = ['name' => $name, 'code' => input('code') ?: null];
        if ($name === '' || (!$id && !$domainId)) {
            flash('error', 'اسم المهارة مطلوب.');
        } elseif ($id) {
            Database::update('nafes_skills', $data, 'id = :sid', ['sid' => $id]);
            flash('success', 'تم تحديث المهارة.');
        } else {
            Database::insert('nafes_skills', $data + [
                'domain_id'  => $domainId,
                'sort_order' => (int) Database::value('SELECT COALESCE(MAX(sort_order),0)+1 FROM nafes_skills WHERE domain_id = ?', [$domainId], 1),
            ]);
            flash('success', 'تمت إضافة المهارة.');
        }
    }

    if ($action === 'delete_skill' && $id) {
        Database::delete('nafes_skills', 'id = ?', [$id]);
        flash('success', 'تم حذف المهارة.');
    }

    redirect($back);
}

$domains   = $subjectId ? QuestionBank::domainsWithSkills($subjectId, $gradeId ?: null) : [];
$editSkill = input_int('edit_skill');

page_start('المجالات والمهارات', nafes_area(), 'nafes_skills');
?>

<div class="filters">
  <form method="get">
    <div class="field"><label>المادة</label><select name="subject" data-auto-submit><?= options_html($subjects, $subjectId) ?></select></div>
    <div class="field"><label>الصف</label><select name="grade" data-auto-submit><?= options_html($grades, $gradeId) ?></select></div>
  </form>
</div>

<div class="alert alert-info">
  نظّم نواتج التعلم لكل مادة في <b>مجالات</b> (مثل: الأعداد والعمليات، الهندسة والقياس) وتحت كل مجال <b>مهارات</b> يُربط بها كل سؤال،
  وعلى أساسها تُحسب نسبة إتقان الطالب والفصل لكل مهارة في التقارير.
</div>

<?php foreach ($domains as $domain): ?>
<div class="card">
  <div class="card-head">
    <form method="post" class="inline-form" style="flex:1">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_domain">
      <input type="hidden" name="id" value="<?= (int) $domain['id'] ?>">
      <div class="field" style="flex:3"><input type="text" name="name" value="<?= h($domain['name']) ?>" aria-label="اسم المجال"></div>
      <div class="field" style="max-width:90px;min-width:70px"><input type="number" name="sort_order" value="<?= (int) $domain['sort_order'] ?>" aria-label="الترتيب"></div>
      <button class="btn btn-sm btn-outline">حفظ</button>
      <?php if ($domain['grade_id'] === null): ?><span class="badge badge-gray">لكل الصفوف</span><?php endif; ?>
    </form>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $domain['id'] ?>">
      <button class="btn btn-sm btn-danger" name="action" value="delete_domain" data-confirm="حذف المجال وكل مهاراته؟">حذف المجال</button>
    </form>
  </div>
  <div class="card-body tight">
    <table class="data">
      <thead><tr><th style="width:110px">الرمز</th><th>المهارة</th><th style="width:90px">الأسئلة</th><th style="width:200px"></th></tr></thead>
      <tbody>
      <?php foreach ($domain['skills'] as $skill): ?>
        <?php if ($editSkill === (int) $skill['id']): ?>
          <tr>
            <td colspan="4">
              <form method="post" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_skill">
                <input type="hidden" name="id" value="<?= (int) $skill['id'] ?>">
                <div class="field" style="max-width:120px"><input type="text" name="code" value="<?= h($skill['code']) ?>" placeholder="الرمز"></div>
                <div class="field" style="flex:4"><input type="text" name="name" value="<?= h($skill['name']) ?>" required></div>
                <button class="btn btn-sm btn-primary">حفظ</button>
                <a class="btn btn-sm btn-outline" href="<?= h(url($back)) ?>">إلغاء</a>
              </form>
            </td>
          </tr>
        <?php else: ?>
          <tr>
            <td class="text-muted"><?= h($skill['code'] ?: '—') ?></td>
            <td><?= h($skill['name']) ?></td>
            <td><a href="<?= h(url('nafes/questions.php?subject=' . $subjectId . '&grade=' . $gradeId . '&skill=' . (int) $skill['id'])) ?>"><?= (int) $skill['questions_count'] ?></a></td>
            <td class="actions">
              <a class="btn btn-sm btn-outline" href="<?= h(url($back . '&edit_skill=' . (int) $skill['id'])) ?>">تعديل</a>
              <a class="btn btn-sm btn-outline" href="<?= h(url('nafes/question.php?subject=' . $subjectId . '&grade=' . $gradeId . '&skill=' . (int) $skill['id'])) ?>">+ سؤال</a>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $skill['id'] ?>">
                <button class="btn btn-sm btn-danger" name="action" value="delete_skill" data-confirm="حذف المهارة؟ الأسئلة المرتبطة بها تبقى بدون مهارة.">حذف</button>
              </form>
            </td>
          </tr>
        <?php endif; ?>
      <?php endforeach; ?>
        <tr>
          <td colspan="4">
            <form method="post" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="save_skill">
              <input type="hidden" name="domain_id" value="<?= (int) $domain['id'] ?>">
              <div class="field" style="max-width:120px"><input type="text" name="code" placeholder="الرمز"></div>
              <div class="field" style="flex:4"><input type="text" name="name" placeholder="مهارة جديدة: مثل يقارن بين الكسور ويرتبها" required></div>
              <button class="btn btn-sm btn-primary">إضافة مهارة</button>
            </form>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>

<?php if ($subjectId): ?>
<div class="card">
  <div class="card-head"><h2>إضافة مجال</h2></div>
  <div class="card-body">
    <form method="post" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_domain">
      <div class="field" style="flex:3"><label>اسم المجال</label><input type="text" name="name" placeholder="مثل: الهندسة والقياس" required></div>
      <label class="flex"><input type="checkbox" name="all_grades" value="1" style="width:auto"> لكل الصفوف</label>
      <button class="btn btn-primary">إضافة المجال</button>
    </form>
  </div>
</div>
<?php else: ?>
  <div class="alert alert-warning">لا توجد مواد. أضف المواد أولاً من صفحة المواد.</div>
<?php endif; ?>

<?php page_end(nafes_area()); ?>
