<?php
declare(strict_types=1);
define('APP_AREA', 'admin');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';

Auth::requireAdmin();

$year = Plans::activeYear();
if (!$year) {
    flash('error', 'يجب إنشاء عام دراسي أولاً من صفحة التقويم والأسابيع.');
    redirect('admin/weeks.php');
}
$yearId = (int) $year['id'];

if (is_post()) {
    csrf_verify();
    $action = (string) input('action', '');

    if ($action === 'save_matrix') {
        $classroomId = input_int('classroom_id');
        $map = (array) ($_POST['teacher'] ?? []);
        $saved = 0;
        $removed = 0;

        foreach ($map as $subjectId => $teacherId) {
            $subjectId = (int) $subjectId;
            $teacherId = (int) $teacherId;

            if ($teacherId === 0) {
                $removed += Database::delete(
                    'assignments',
                    'classroom_id = ? AND subject_id = ? AND year_id = ?',
                    [$classroomId, $subjectId, $yearId]
                );
                continue;
            }
            Database::run(
                'INSERT INTO assignments (teacher_id, subject_id, classroom_id, year_id) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id)',
                [$teacherId, $subjectId, $classroomId, $yearId]
            );
            $saved++;
        }
        Activity::log('assignments_save', 'classrooms', $classroomId, "saved=$saved removed=$removed");
        flash('success', "تم حفظ الإسناد ($saved مادة).");
        redirect('admin/assignments.php?class=' . $classroomId);
    }

    if ($action === 'bulk') {
        $teacherId    = input_int('teacher_id');
        $subjectId    = input_int('subject_id');
        $classroomIds = array_map('intval', (array) ($_POST['classroom_ids'] ?? []));
        $count = 0;

        foreach (array_filter($classroomIds) as $classroomId) {
            Database::run(
                'INSERT INTO assignments (teacher_id, subject_id, classroom_id, year_id) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id)',
                [$teacherId, $subjectId, $classroomId, $yearId]
            );
            $count++;
        }
        flash('success', 'تم إسناد المادة في ' . $count . ' فصلاً.');
        redirect('admin/assignments.php');
    }

    if ($action === 'delete') {
        Database::delete('assignments', 'id = ?', [input_int('id')]);
        flash('success', 'تم حذف الإسناد.');
        redirect('admin/assignments.php');
    }
}

$classrooms = Plans::classrooms();
$classId    = input_int('class') ?: (int) ($classrooms[0]['id'] ?? 0);
$classroom  = $classId ? Plans::classroom($classId) : null;
$subjects   = Database::all('SELECT * FROM subjects WHERE is_active = 1 ORDER BY sort_order, name');
$teachers   = Database::all("SELECT id, name FROM users WHERE role IN ('teacher','admin') AND is_active = 1 ORDER BY name");

$current = [];
foreach (Database::all('SELECT subject_id, teacher_id FROM assignments WHERE classroom_id = ? AND year_id = ?', [$classId, $yearId]) as $row) {
    $current[(int) $row['subject_id']] = (int) $row['teacher_id'];
}

$allAssignments = Database::all(
    'SELECT a.id, u.name AS teacher_name, s.name AS subject_name, c.name AS class_name, g.name AS grade_name
       FROM assignments a
       JOIN users u ON u.id = a.teacher_id
       JOIN subjects s ON s.id = a.subject_id
       JOIN classrooms c ON c.id = a.classroom_id
       JOIN grades g ON g.id = c.grade_id
      WHERE a.year_id = ?
   ORDER BY u.name, g.sort_order, c.sort_order, s.sort_order',
    [$yearId]
);

page_start('إسناد المواد للمعلمين', 'admin', 'assignments');
?>
<div class="alert alert-info">
  الإسناد مرتبط بالعام الدراسي النشط: <b><?= h($year['name']) ?></b> — كل مادة في فصل معين يدرّسها معلم واحد.
</div>

<div class="card">
  <div class="card-head">
    <h2>إسناد مواد فصل كامل</h2>
    <form method="get" class="flex">
      <select name="class" data-auto-submit>
        <?php foreach ($classrooms as $item): ?>
          <option value="<?= (int) $item['id'] ?>" <?= (int) $item['id'] === $classId ? 'selected' : '' ?>>
            <?= h($item['grade_name'] . ' / ' . $item['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <div class="card-body">
    <?php if (!$classroom): ?>
      <div class="empty">لا توجد فصول. أضف الصفوف والفصول أولاً.</div>
    <?php else: ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_matrix">
        <input type="hidden" name="classroom_id" value="<?= $classId ?>">
        <div class="grid grid-3">
          <?php foreach ($subjects as $subject): ?>
            <div class="field">
              <label><?= h($subject['name']) ?></label>
              <select name="teacher[<?= (int) $subject['id'] ?>]">
                <option value="0">— بدون معلم —</option>
                <?php foreach ($teachers as $teacher): ?>
                  <option value="<?= (int) $teacher['id'] ?>"
                    <?= ($current[(int) $subject['id']] ?? 0) === (int) $teacher['id'] ? 'selected' : '' ?>>
                    <?= h($teacher['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endforeach; ?>
        </div>
        <button class="btn btn-primary" type="submit">حفظ إسناد <?= h(Plans::classLabel($classroom)) ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2>إسناد سريع: معلم لمادة في عدة فصول</h2></div>
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="bulk">
      <div class="grid grid-2">
        <div class="field">
          <label>المعلم</label>
          <select name="teacher_id" required>
            <?php foreach ($teachers as $teacher): ?>
              <option value="<?= (int) $teacher['id'] ?>"><?= h($teacher['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>المادة</label>
          <select name="subject_id" required>
            <?php foreach ($subjects as $subject): ?>
              <option value="<?= (int) $subject['id'] ?>"><?= h($subject['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="field">
        <label>الفصول</label>
        <div class="grid grid-4">
          <?php foreach ($classrooms as $item): ?>
            <label class="flex">
              <input type="checkbox" name="classroom_ids[]" value="<?= (int) $item['id'] ?>" style="width:auto">
              <?= h($item['grade_name'] . ' / ' . $item['name']) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <button class="btn btn-primary" type="submit">إسناد</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h2>جميع عمليات الإسناد (<?= count($allAssignments) ?>)</h2>
    <a class="btn btn-sm btn-outline" href="<?= h(url('admin/transfer.php?export=assignments&format=xlsx')) ?>">تصدير Excel</a>
  </div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>المعلم</th><th>المادة</th><th>الصف / الفصل</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($allAssignments as $item): ?>
          <tr>
            <td><?= h($item['teacher_name']) ?></td>
            <td><strong><?= h($item['subject_name']) ?></strong></td>
            <td><?= h($item['grade_name'] . ' / ' . $item['class_name']) ?></td>
            <td class="actions">
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                <button class="btn btn-sm btn-danger" name="action" value="delete" data-confirm="حذف الإسناد؟">حذف</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$allAssignments): ?><tr><td colspan="4" class="empty">لا يوجد إسناد بعد.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php page_end('admin'); ?>
