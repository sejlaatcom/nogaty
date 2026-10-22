<?php use App\Models\ClassRoom; ?>
<div class="page-header">
  <div><h1><?= e($teacher['full_name']) ?></h1><div class="sub"><?= e(implode('، ', $roles)) ?> — المعرف: <?= e($teacher['employee_number']) ?> <?= $teacher['specialization'] ? '— ' . e($teacher['specialization']) : '' ?></div></div>
  <?php if ($canManage): ?><a class="btn btn-light" href="<?= e(url('/teachers/' . $teacher['id'] . '/edit')) ?>"><i class="bi bi-pencil"></i> تعديل البيانات</a><?php endif; ?>
</div>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header"><h2>الإسناد في العام الحالي</h2></div>
      <div class="table-responsive"><table class="table">
        <thead><tr><th>الفصل</th><th>المادة</th><th>الطلاب</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($assignments as $a): ?>
          <tr><td><?= e($a['grade_name'] . ' / ' . $a['class_name']) ?></td>
            <td><?= $a['subject_name'] ? e($a['subject_name']) : '<span class="badge badge-soft-accent">إشراف على الفصل</span>' ?></td>
            <td><?= (int) $a['students_count'] ?></td>
            <td><?php if ($canManage): ?><form method="post" action="<?= e(url('/teachers/' . $teacher['id'] . '/assignments/' . $a['id'] . '/delete')) ?>"><?= csrf_field() ?>
              <button class="btn btn-sm btn-light text-danger" data-confirm="إلغاء هذا الإسناد؟"><i class="bi bi-x-lg"></i></button></form><?php endif; ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$assignments): ?><tr><td colspan="4"><div class="empty-state">لا يوجد إسناد — لن يرى المعلم أي فصل حتى يُسند إليه.</div></td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
    <?php if ($canManage): ?>
    <form class="card" method="post" action="<?= e(url('/teachers/' . $teacher['id'] . '/assignments')) ?>">
      <?= csrf_field() ?>
      <div class="card-header"><h2>إضافة إسناد</h2></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-7"><label class="form-label">الفصول</label>
            <div class="border rounded-3 p-2" style="max-height:220px;overflow:auto">
              <?php foreach ($classes as $c): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="class_ids[]" value="<?= (int) $c['id'] ?>" id="c<?= (int) $c['id'] ?>">
                <label class="form-check-label" for="c<?= (int) $c['id'] ?>"><?= e(ClassRoom::label($c)) ?> <small class="text-muted">(<?= (int) $c['students_count'] ?>)</small></label></div><?php endforeach; ?>
            </div></div>
          <div class="col-md-5"><label class="form-label">المواد</label>
            <?php foreach ($subjects as $s): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="subject_ids[]" value="<?= (int) $s['id'] ?>" id="s<?= (int) $s['id'] ?>">
              <label class="form-check-label" for="s<?= (int) $s['id'] ?>"><?= e($s['name']) ?></label></div><?php endforeach; ?>
            <hr><div class="form-check"><input class="form-check-input" type="checkbox" name="supervise" value="1" id="sup"><label class="form-check-label" for="sup">إشراف على الفصل (كل المواد) — للوكيل/المشرف</label></div>
          </div>
        </div>
        <button class="btn btn-primary mt-3"><i class="bi bi-plus-lg"></i> إضافة الإسناد</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header"><h2>الطلاب الذين يشرف عليهم (<?= count($students) ?>)</h2></div>
      <div class="table-tools" data-table-tools="ts"><input class="form-control form-control-sm" data-table-search placeholder="بحث…"></div>
      <div class="table-responsive" style="max-height:520px">
        <table class="table table-sm" id="ts" data-table><thead><tr><th>الطالب</th><th>الفصل</th></tr></thead><tbody>
          <?php foreach ($students as $s): ?><tr><td><a href="<?= e(url('/students/' . $s['id'])) ?>"><?= e($s['full_name']) ?></a></td><td class="small"><?= e($s['grade_name'] . ' / ' . $s['class_name']) ?></td></tr><?php endforeach; ?>
        </tbody></table>
      </div>
    </div>
  </div>
</div>
