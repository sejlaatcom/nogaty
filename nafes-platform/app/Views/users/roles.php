<div class="page-header"><div><h1>الأدوار والصلاحيات</h1><div class="sub">تُطبَّق الصلاحيات على الخادم في كل عملية. دور «مدير النظام» يملك جميع الصلاحيات دائماً.</div></div>
  <a class="btn btn-light" href="<?= e(url('/users')) ?>">رجوع</a></div>
<form method="post" action="<?= e(url('/users/roles')) ?>" class="card">
  <?= csrf_field() ?>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>الصلاحية</th><?php foreach ($roles as $r): ?><th class="text-center"><?= e($r['name']) ?><br><small class="text-muted"><?= (int) $r['users_count'] ?> مستخدم</small></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php $group = null; foreach ($perms as $p): ?>
        <?php if ($group !== $p['group_name']): $group = $p['group_name']; ?>
          <tr><td colspan="<?= count($roles) + 1 ?>" class="bg-light fw-bold small"><?= e($group) ?></td></tr>
        <?php endif; ?>
        <tr><td><?= e($p['name']) ?> <small class="text-muted ltr"><?= e($p['slug']) ?></small></td>
          <?php foreach ($roles as $r): ?>
            <td class="text-center"><input class="form-check-input" type="checkbox" name="perm[<?= (int) $r['id'] ?>][<?= (int) $p['id'] ?>]" value="1"
              <?= checked(!empty($map[(int) $r['id']][(int) $p['id']])) ?> <?= $r['slug'] === 'admin' ? 'disabled checked' : '' ?>></td>
          <?php endforeach; ?></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-transparent"><button class="btn btn-primary" data-confirm="حفظ تعديلات الصلاحيات؟ ستُطبق فوراً على كل المستخدمين."><i class="bi bi-check2"></i> حفظ الصلاحيات</button></div>
</form>
