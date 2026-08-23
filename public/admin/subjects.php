<?php
declare(strict_types=1);
define('APP_AREA', 'admin');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';

Auth::requireAdmin();

if (is_post()) {
    csrf_verify();
    $action = (string) input('action', '');
    $id     = input_int('id');
    $name   = (string) input('name', '');

    if ($action === 'save') {
        if ($name === '') {
            flash('error', 'اسم المادة مطلوب.');
        } elseif ($id) {
            Database::update('subjects', [
                'name'       => $name,
                'sort_order' => input_int('sort_order'),
                'is_active'  => isset($_POST['is_active']) ? 1 : 0,
            ], 'id = :sid', ['sid' => $id]);
            flash('success', 'تم تحديث المادة.');
        } else {
            $exists = Database::value('SELECT id FROM subjects WHERE name = ?', [$name]);
            if ($exists) {
                flash('error', 'المادة مضافة مسبقاً.');
            } else {
                Database::insert('subjects', [
                    'name'       => $name,
                    'sort_order' => input_int('sort_order') ?: (int) Database::value('SELECT COALESCE(MAX(sort_order),0)+1 FROM subjects', [], 1),
                    'is_active'  => 1,
                ]);
                flash('success', 'تمت إضافة المادة.');
            }
        }
    }

    if ($action === 'delete' && $id) {
        $used = (int) Database::value('SELECT COUNT(*) FROM assignments WHERE subject_id = ?', [$id], 0);
        if ($used > 0) {
            Database::update('subjects', ['is_active' => 0], 'id = :sid', ['sid' => $id]);
            flash('warning', 'المادة مستخدمة في الإسناد، تم إخفاؤها بدلاً من حذفها.');
        } else {
            Database::delete('subjects', 'id = ?', [$id]);
            flash('success', 'تم حذف المادة.');
        }
    }

    redirect('admin/subjects.php');
}

$editId   = input_int('edit');
$edit     = $editId ? Database::first('SELECT * FROM subjects WHERE id = ?', [$editId]) : null;
$subjects = Database::all('SELECT s.*, (SELECT COUNT(*) FROM assignments a WHERE a.subject_id = s.id) AS used FROM subjects s ORDER BY s.sort_order, s.name');

page_start('المواد الدراسية', 'admin', 'subjects');
?>
<div class="card">
  <div class="card-head"><h2><?= $edit ? 'تعديل مادة' : 'إضافة مادة' ?></h2></div>
  <div class="card-body">
    <form method="post" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <div class="field"><label>اسم المادة</label><input type="text" name="name" value="<?= h($edit['name'] ?? '') ?>" required></div>
      <div class="field"><label>الترتيب</label><input type="number" name="sort_order" value="<?= (int) ($edit['sort_order'] ?? 0) ?>"></div>
      <?php if ($edit): ?>
        <div class="field"><label>الحالة</label>
          <label class="flex"><input type="checkbox" name="is_active" style="width:auto" <?= (int) $edit['is_active'] === 1 ? 'checked' : '' ?>> مفعّلة</label>
        </div>
      <?php endif; ?>
      <button class="btn btn-primary" type="submit"><?= $edit ? 'حفظ' : 'إضافة' ?></button>
      <?php if ($edit): ?><a class="btn btn-outline" href="<?= h(url('admin/subjects.php')) ?>">إلغاء</a><?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2>المواد (<?= count($subjects) ?>)</h2></div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>#</th><th>المادة</th><th>الترتيب</th><th>مرات الإسناد</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($subjects as $i => $subject): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><strong><?= h($subject['name']) ?></strong></td>
            <td><?= (int) $subject['sort_order'] ?></td>
            <td><?= (int) $subject['used'] ?></td>
            <td><?= (int) $subject['is_active'] === 1 ? '<span class="badge badge-green">مفعّلة</span>' : '<span class="badge badge-gray">مخفية</span>' ?></td>
            <td class="actions">
              <a class="btn btn-sm btn-outline" href="<?= h(url('admin/subjects.php?edit=' . (int) $subject['id'])) ?>">تعديل</a>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $subject['id'] ?>">
                <button class="btn btn-sm btn-danger" name="action" value="delete" data-confirm="حذف المادة؟">حذف</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php page_end('admin'); ?>
