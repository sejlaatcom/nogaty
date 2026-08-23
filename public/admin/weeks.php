<?php
declare(strict_types=1);
define('APP_AREA', 'admin');
require __DIR__ . '/../../app/bootstrap.php';
require APP_PATH . '/views/layout.php';

Auth::requireAdmin();

if (is_post()) {
    csrf_verify();
    $action = (string) input('action', '');

    switch ($action) {
        case 'save_year':
            $name = (string) input('name', '');
            if ($name === '') {
                flash('error', 'اسم العام الدراسي مطلوب.');
                break;
            }
            $id = input_int('id');
            if ($id) {
                Database::update('academic_years', ['name' => $name], 'id = :yid', ['yid' => $id]);
            } else {
                $id = Database::insert('academic_years', ['name' => $name, 'is_active' => 0]);
                foreach ([1 => 'الفصل الدراسي الأول', 2 => 'الفصل الدراسي الثاني', 3 => 'الفصل الدراسي الثالث'] as $no => $termName) {
                    Database::insert('terms', ['year_id' => $id, 'name' => $termName, 'term_no' => $no, 'is_active' => $no === 1 ? 1 : 0]);
                }
            }
            flash('success', 'تم حفظ العام الدراسي وفصوله الثلاثة.');
            break;

        case 'activate_year':
            Database::run('UPDATE academic_years SET is_active = 0');
            Database::update('academic_years', ['is_active' => 1], 'id = :yid', ['yid' => input_int('id')]);
            flash('success', 'تم تفعيل العام الدراسي.');
            break;

        case 'activate_term':
            $termId = input_int('id');
            $term   = Database::first('SELECT * FROM terms WHERE id = ?', [$termId]);
            if ($term) {
                Database::run('UPDATE terms SET is_active = 0 WHERE year_id = ?', [$term['year_id']]);
                Database::update('terms', ['is_active' => 1], 'id = :tid', ['tid' => $termId]);
                flash('success', 'تم تفعيل الفصل الدراسي.');
            }
            break;

        case 'generate':
            $termId = input_int('term_id');
            $start  = (string) input('start_date', '');
            $count  = max(1, min(30, input_int('count', 13)));
            if (!$termId || $start === '') {
                flash('error', 'يرجى اختيار الفصل الدراسي وتاريخ بداية الأسبوع الأول.');
                break;
            }
            $created = Plans::generateWeeks($termId, $start, $count, input('replace') === '1');
            Database::run(
                'UPDATE terms SET start_date = (SELECT MIN(start_date) FROM weeks WHERE term_id = ?),
                                  end_date   = (SELECT MAX(end_date)   FROM weeks WHERE term_id = ?)
                  WHERE id = ?',
                [$termId, $termId, $termId]
            );
            Activity::log('weeks_generate', 'terms', $termId, "count=$created");
            flash('success', 'تم توليد ' . $created . ' أسبوعاً مع التواريخ الهجرية.');
            break;

        case 'save_week':
            $id = input_int('id');
            if ($id) {
                $start = (string) input('start_date', '');
                $end   = (string) input('end_date', '');
                Database::update('weeks', [
                    'title'       => (string) input('title', ''),
                    'start_date'  => $start,
                    'end_date'    => $end,
                    'hijri_start' => (string) (input('hijri_start') ?: Hijri::long($start)),
                    'hijri_end'   => (string) (input('hijri_end') ?: Hijri::long($end)),
                ], 'id = :wid', ['wid' => $id]);
                flash('success', 'تم تحديث بيانات الأسبوع.');
            }
            break;

        case 'toggle_week':
            Database::run("UPDATE weeks SET status = IF(status = 'open', 'closed', 'open') WHERE id = ?", [input_int('id')]);
            flash('success', 'تم تغيير حالة الإدخال للأسبوع.');
            break;

        case 'delete_week':
            Database::delete('weeks', 'id = ?', [input_int('id')]);
            flash('success', 'تم حذف الأسبوع وخططه.');
            break;
    }

    redirect('admin/weeks.php?term=' . input_int('term_id'));
}

$years = Database::all('SELECT * FROM academic_years ORDER BY name DESC');
$terms = Database::all('SELECT t.*, y.name AS year_name FROM terms t JOIN academic_years y ON y.id = t.year_id ORDER BY y.name DESC, t.term_no');

$termId = input_int('term') ?: (int) (Plans::activeTerm()['id'] ?? 0);
$weeks  = $termId ? Database::all('SELECT * FROM weeks WHERE term_id = ? ORDER BY week_no', [$termId]) : [];

$editWeek = input_int('edit_week') ? Database::first('SELECT * FROM weeks WHERE id = ?', [input_int('edit_week')]) : null;

page_start('التقويم والأسابيع الدراسية', 'admin', 'weeks');
?>
<div class="grid grid-2">
  <div class="card">
    <div class="card-head"><h2>الأعوام الدراسية</h2></div>
    <div class="card-body">
      <form method="post" class="inline-form mb16">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_year">
        <div class="field"><label>إضافة عام دراسي</label>
          <input type="text" name="name" placeholder="مثال: <?= h(Hijri::currentYear() . 'هـ') ?>" required></div>
        <button class="btn btn-primary" type="submit">إضافة</button>
      </form>
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>العام</th><th>الحالة</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($years as $year): ?>
            <tr>
              <td><strong><?= h($year['name']) ?></strong></td>
              <td><?= (int) $year['is_active'] === 1 ? '<span class="badge badge-green">نشط</span>' : '<span class="badge badge-gray">—</span>' ?></td>
              <td class="actions">
                <?php if ((int) $year['is_active'] !== 1): ?>
                  <form method="post" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $year['id'] ?>">
                    <button class="btn btn-sm btn-outline" name="action" value="activate_year">تفعيل</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2>الفصول الدراسية</h2></div>
    <div class="card-body tight">
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>العام</th><th>الفصل</th><th>من</th><th>إلى</th><th>الأسابيع</th><th>الحالة</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($terms as $term): ?>
            <tr>
              <td><?= h($term['year_name']) ?></td>
              <td><strong><?= h($term['name']) ?></strong></td>
              <td class="text-muted"><?= h($term['start_date'] ?: '—') ?></td>
              <td class="text-muted"><?= h($term['end_date'] ?: '—') ?></td>
              <td><?= (int) Database::value('SELECT COUNT(*) FROM weeks WHERE term_id = ?', [$term['id']], 0) ?></td>
              <td><?= (int) $term['is_active'] === 1 ? '<span class="badge badge-green">نشط</span>' : '—' ?></td>
              <td class="actions">
                <a class="btn btn-sm btn-outline" href="<?= h(url('admin/weeks.php?term=' . (int) $term['id'])) ?>">الأسابيع</a>
                <?php if ((int) $term['is_active'] !== 1): ?>
                  <form method="post" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $term['id'] ?>">
                    <button class="btn btn-sm btn-outline" name="action" value="activate_term">تفعيل</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2>توليد أسابيع الفصل الدراسي</h2></div>
  <div class="card-body">
    <form method="post" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="generate">
      <div class="field">
        <label>الفصل الدراسي</label>
        <select name="term_id" required>
          <?php foreach ($terms as $term): ?>
            <option value="<?= (int) $term['id'] ?>" <?= (int) $term['id'] === $termId ? 'selected' : '' ?>>
              <?= h($term['year_name'] . ' — ' . $term['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>تاريخ أول يوم أحد (ميلادي)</label><input type="date" name="start_date" required></div>
      <div class="field"><label>عدد الأسابيع</label><input type="number" name="count" value="13" min="1" max="30"></div>
      <div class="field"><label>&nbsp;</label>
        <label class="flex"><input type="checkbox" name="replace" value="1" style="width:auto"> حذف الأسابيع السابقة</label>
      </div>
      <button class="btn btn-primary" type="submit" data-confirm="سيتم توليد الأسابيع مع التواريخ الهجرية. متابعة؟">توليد الأسابيع</button>
    </form>
    <p class="help mt16">يتم احتساب كل أسبوع من الأحد إلى الخميس، وتُضاف التواريخ الهجرية تلقائياً وفق تقويم أم القرى.</p>
  </div>
</div>

<?php if ($editWeek): ?>
<div class="card">
  <div class="card-head"><h2>تعديل الأسبوع <?= (int) $editWeek['week_no'] ?></h2>
    <a class="btn btn-sm btn-outline" href="<?= h(url('admin/weeks.php?term=' . $termId)) ?>">إلغاء</a></div>
  <div class="card-body">
    <form method="post" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_week">
      <input type="hidden" name="id" value="<?= (int) $editWeek['id'] ?>">
      <input type="hidden" name="term_id" value="<?= $termId ?>">
      <div class="field"><label>اسم الأسبوع</label><input type="text" name="title" value="<?= h($editWeek['title']) ?>"></div>
      <div class="field"><label>من (ميلادي)</label><input type="date" name="start_date" value="<?= h($editWeek['start_date']) ?>" required></div>
      <div class="field"><label>إلى (ميلادي)</label><input type="date" name="end_date" value="<?= h($editWeek['end_date']) ?>" required></div>
      <div class="field"><label>من (هجري)</label><input type="text" name="hijri_start" value="<?= h($editWeek['hijri_start']) ?>"></div>
      <div class="field"><label>إلى (هجري)</label><input type="text" name="hijri_end" value="<?= h($editWeek['hijri_end']) ?>"></div>
      <button class="btn btn-primary" type="submit">حفظ</button>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head"><h2>أسابيع الفصل المحدد (<?= count($weeks) ?>)</h2></div>
  <div class="card-body tight">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>#</th><th>الأسبوع</th><th>من (ميلادي)</th><th>إلى</th><th>من (هجري)</th><th>إلى (هجري)</th><th>الخطط</th><th>الإدخال</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($weeks as $week): ?>
          <tr>
            <td><?= (int) $week['week_no'] ?></td>
            <td><strong><?= h($week['title']) ?></strong></td>
            <td class="text-muted nowrap"><?= h($week['start_date']) ?></td>
            <td class="text-muted nowrap"><?= h($week['end_date']) ?></td>
            <td class="nowrap"><?= h($week['hijri_start']) ?></td>
            <td class="nowrap"><?= h($week['hijri_end']) ?></td>
            <td><?= (int) Database::value('SELECT COUNT(*) FROM plans WHERE week_id = ?', [$week['id']], 0) ?></td>
            <td><?= $week['status'] === 'open' ? '<span class="badge badge-green">مفتوح</span>' : '<span class="badge badge-red">مغلق</span>' ?></td>
            <td class="actions">
              <a class="btn btn-sm btn-outline" href="<?= h(url('admin/weeks.php?term=' . $termId . '&edit_week=' . (int) $week['id'])) ?>">تعديل</a>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $week['id'] ?>">
                <input type="hidden" name="term_id" value="<?= $termId ?>">
                <button class="btn btn-sm btn-outline" name="action" value="toggle_week">
                  <?= $week['status'] === 'open' ? 'إغلاق' : 'فتح' ?>
                </button>
                <button class="btn btn-sm btn-danger" name="action" value="delete_week"
                        data-confirm="سيتم حذف الأسبوع وكل خططه. متابعة؟">حذف</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$weeks): ?><tr><td colspan="9" class="empty">لا توجد أسابيع لهذا الفصل. استخدم أداة التوليد أعلاه.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php page_end('admin'); ?>
