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

    switch ($action) {
        case 'save_grade':
            $name = (string) input('name', '');
            if ($name === '') {
                flash('error', 'اسم الصف مطلوب.');
                break;
            }
            $data = ['name' => $name, 'stage' => (string) input('stage', 'ابتدائي'), 'sort_order' => input_int('sort_order')];
            if ($id) {
                Database::update('grades', $data, 'id = :gid', ['gid' => $id]);
                flash('success', 'تم تحديث الصف.');
            } elseif (Database::value('SELECT id FROM grades WHERE name = ?', [$name])) {
                flash('error', 'الصف مضاف مسبقاً.');
            } else {
                Database::insert('grades', $data);
                flash('success', 'تمت إضافة الصف.');
            }
            break;

        case 'delete_grade':
            if ($id) {
                Database::delete('grades', 'id = ?', [$id]);
                flash('success', 'تم حذف الصف وفصوله.');
            }
            break;

        case 'save_class':
            $gradeId = input_int('grade_id');
            $name    = (string) input('class_name', '');
            if (!$gradeId || $name === '') {
                flash('error', 'يرجى اختيار الصف وكتابة اسم الفصل.');
                break;
            }
            $exists = Database::value('SELECT id FROM classrooms WHERE grade_id = ? AND name = ?', [$gradeId, $name]);
            if ($exists) {
                flash('error', 'هذا الفصل موجود مسبقاً في الصف نفسه.');
            } else {
                Database::insert('classrooms', [
                    'grade_id'   => $gradeId,
                    'name'       => $name,
                    'sort_order' => input_int('class_order'),
                    'is_active'  => 1,
                ]);
                flash('success', 'تمت إضافة الفصل.');
            }
            break;

        case 'delete_class':
            if ($id) {
                $used = (int) Database::value('SELECT COUNT(*) FROM plans WHERE classroom_id = ?', [$id], 0);
                if ($used > 0) {
                    Database::update('classrooms', ['is_active' => 0], 'id = :cid', ['cid' => $id]);
                    flash('warning', 'الفصل يحتوي على خطط، تم إخفاؤه بدلاً من حذفه.');
                } else {
                    Database::delete('classrooms', 'id = ?', [$id]);
                    flash('success', 'تم حذف الفصل.');
                }
            }
            break;

        case 'bulk_classes':
            // إضافة عدة فصول دفعة واحدة لكل الصفوف: أ ب ج ...
            $names = array_filter(array_map('trim', explode(',', (string) input('names', ''))));
            $added = 0;
            foreach (Database::all('SELECT id FROM grades') as $grade) {
                foreach ($names as $order => $name) {
                    $exists = Database::value('SELECT id FROM classrooms WHERE grade_id = ? AND name = ?', [$grade['id'], $name]);
                    if (!$exists) {
                        Database::insert('classrooms', ['grade_id' => $grade['id'], 'name' => $name, 'sort_order' => $order + 1, 'is_active' => 1]);
                        $added++;
                    }
                }
            }
            flash('success', 'تمت إضافة ' . $added . ' فصلاً.');
            break;
    }

    redirect('admin/grades.php');
}

$editId = input_int('edit');
$edit   = $editId ? Database::first('SELECT * FROM grades WHERE id = ?', [$editId]) : null;
$grades = Database::all('SELECT * FROM grades ORDER BY sort_order, name');
$classes = Database::all(
    'SELECT c.*, g.name AS grade_name,
            (SELECT COUNT(*) FROM assignments a WHERE a.classroom_id = c.id) AS subjects_count
       FROM classrooms c JOIN grades g ON g.id = c.grade_id
   ORDER BY g.sort_order, c.sort_order, c.name'
);

page_start('الصفوف والفصول', 'admin', 'grades');
?>
<div class="grid grid-2">
  <div class="card">
    <div class="card-head"><h2><?= $edit ? 'تعديل صف' : 'إضافة صف دراسي' ?></h2></div>
    <div class="card-body">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_grade">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <div class="field"><label>اسم الصف</label>
          <input type="text" name="name" value="<?= h($edit['name'] ?? '') ?>" placeholder="مثال: الخامس الابتدائي" required></div>
        <div class="field"><label>المرحلة</label>
          <select name="stage">
            <?php foreach (['ابتدائي', 'متوسط', 'ثانوي', 'رياض أطفال'] as $stage): ?>
              <option value="<?= h($stage) ?>" <?= ($edit['stage'] ?? '') === $stage ? 'selected' : '' ?>><?= h($stage) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>الترتيب</label><input type="number" name="sort_order" value="<?= (int) ($edit['sort_order'] ?? 0) ?>"></div>
        <button class="btn btn-primary" type="submit"><?= $edit ? 'حفظ' : 'إضافة الصف' ?></button>
        <?php if ($edit): ?><a class="btn btn-outline" href="<?= h(url('admin/grades.php')) ?>">إلغاء</a><?php endif; ?>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2>إضافة فصل</h2></div>
    <div class="card-body">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_class">
        <div class="field"><label>الصف</label>
          <select name="grade_id" required>
            <option value="">— اختر الصف —</option>
            <?php foreach ($grades as $grade): ?>
              <option value="<?= (int) $grade['id'] ?>"><?= h($grade['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>اسم الفصل</label><input type="text" name="class_name" placeholder="مثال: أ أو 1" required></div>
        <div class="field"><label>الترتيب</label><input type="number" name="class_order" value="0"></div>
        <button class="btn btn-primary" type="submit">إضافة الفصل</button>
      </form>
      <hr style="margin:16px 0;border:none;border-top:1px solid var(--line)">
      <form method="post" class="inline-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="bulk_classes">
        <div class="field"><label>إضافة فصول لكل الصفوف دفعة واحدة</label>
          <input type="text" name="names" placeholder="أ, ب, ج" required></div>
        <button class="btn btn-outline" type="submit">إضافة للجميع</button>
      </form>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2>الصفوف (<?= count($grades) ?>)</h2></div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>الصف</th><th>المرحلة</th><th>الترتيب</th><th>عدد الفصول</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($grades as $grade): ?>
          <tr>
            <td><strong><?= h($grade['name']) ?></strong></td>
            <td><?= h($grade['stage']) ?></td>
            <td><?= (int) $grade['sort_order'] ?></td>
            <td><?= (int) Database::value('SELECT COUNT(*) FROM classrooms WHERE grade_id = ?', [$grade['id']], 0) ?></td>
            <td class="actions">
              <a class="btn btn-sm btn-outline" href="<?= h(url('admin/grades.php?edit=' . (int) $grade['id'])) ?>">تعديل</a>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $grade['id'] ?>">
                <button class="btn btn-sm btn-danger" name="action" value="delete_grade"
                        data-confirm="سيتم حذف الصف وجميع فصوله وخططها. متابعة؟">حذف</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2>الفصول (<?= count($classes) ?>)</h2></div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>الصف</th><th>الفصل</th><th>المواد المسندة</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($classes as $class): ?>
          <tr>
            <td><?= h($class['grade_name']) ?></td>
            <td><strong><?= h($class['name']) ?></strong></td>
            <td><?= (int) $class['subjects_count'] ?></td>
            <td><?= (int) $class['is_active'] === 1 ? '<span class="badge badge-green">نشط</span>' : '<span class="badge badge-gray">مخفي</span>' ?></td>
            <td class="actions">
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $class['id'] ?>">
                <button class="btn btn-sm btn-danger" name="action" value="delete_class" data-confirm="حذف الفصل؟">حذف</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$classes): ?><tr><td colspan="5" class="empty">لا توجد فصول.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php page_end('admin'); ?>
