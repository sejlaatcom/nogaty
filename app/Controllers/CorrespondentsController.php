<?php
/**
 * الجهات المراسلة والتصنيفات والأعوام الدراسية
 */
class CorrespondentsController
{
    public function index(): void
    {
        Auth::requirePermission('correspondents.manage');
        $q    = Request::get('q');
        $type = pick(Request::get('type'),
            ['government','education','company','school','individual','internal','other'], null);

        $where  = ['1=1'];
        $params = [];
        if ($q) {
            $where[] = likeAny(['name', 'contact_person', 'phone'], (string) $q, $params);
        }
        if ($type) {
            $where[] = 'type = :type';
            $params['type'] = $type;
        }

        $sql = 'SELECT c.*, (SELECT COUNT(*) FROM transactions t
                              WHERE t.correspondent_id = c.id AND t.deleted_at IS NULL) AS trx_count
                  FROM correspondents c
                 WHERE ' . implode(' AND ', $where) . ' ORDER BY c.name';

        View::render('correspondents/index', [
            'title' => 'الجهات المراسلة',
            'page'  => Paginator::make($sql, $params, Request::page()),
            'q'     => $q,
            'type'  => $type,
        ]);
    }

    public function store(): void
    {
        Auth::requirePermission('correspondents.manage');
        Csrf::check();
        $name = mb_substr((string) Request::post('name', ''), 0, 200);
        if (mb_strlen($name) < 2) {
            flash('error', 'اسم الجهة مطلوب.');
            redirect('correspondents/index');
        }
        $id = Database::insert('correspondents', $this->fields($name));
        Audit::log('create', 'correspondent', $id, 'إضافة جهة : ' . $name);
        flash('success', 'تمت إضافة الجهة.');
        redirect('correspondents/index');
    }

    public function update(): void
    {
        Auth::requirePermission('correspondents.manage');
        Csrf::check();
        $id = Request::int('id', 0);
        $c  = Database::first('SELECT * FROM correspondents WHERE id = ?', [$id]);
        if (!$c) {
            redirect('correspondents/index');
        }
        $fields = $this->fields(mb_substr((string) Request::post('name', $c['name']), 0, 200));
        $fields['is_active'] = Request::bool('is_active');
        Database::update('correspondents', $fields, 'id = :id', ['id' => $id]);
        Audit::log('update', 'correspondent', $id, 'تعديل جهة : ' . $fields['name']);
        flash('success', 'تم تحديث بيانات الجهة.');
        redirect('correspondents/index');
    }

    public function delete(): void
    {
        Auth::requirePermission('correspondents.manage');
        Csrf::check();
        $id = Request::int('id', 0);
        if (Database::value('SELECT 1 FROM transactions WHERE correspondent_id = ? LIMIT 1', [$id])) {
            Database::update('correspondents', ['is_active' => 0], 'id = :id', ['id' => $id]);
            flash('success', 'تم إخفاء الجهة (مرتبطة بمعاملات فلا تُحذف نهائياً).');
        } else {
            Database::delete('correspondents', 'id = ?', [$id]);
            flash('success', 'تم حذف الجهة.');
        }
        Audit::log('delete', 'correspondent', $id, 'حذف / إخفاء جهة');
        redirect('correspondents/index');
    }

    // ------------------------- التصنيفات والأعوام -------------------------
    public function categories(): void
    {
        Auth::requirePermission('categories.manage');
        View::render('correspondents/categories', [
            'title'      => 'التصنيفات والأعوام الدراسية',
            'categories' => Database::all(
                'SELECT c.*, (SELECT COUNT(*) FROM transactions t WHERE t.category_id = c.id AND t.deleted_at IS NULL) AS trx_count
                   FROM categories c ORDER BY c.name'),
            'years'      => Database::all('SELECT * FROM academic_years ORDER BY id DESC'),
        ]);
    }

    public function saveCategory(): void
    {
        Auth::requirePermission('categories.manage');
        Csrf::check();
        $id   = Request::int('id');
        $name = mb_substr((string) Request::post('name', ''), 0, 150);
        if (mb_strlen($name) < 2) {
            flash('error', 'اسم التصنيف مطلوب.');
            redirect('correspondents/categories');
        }
        $data = [
            'name'      => $name,
            'color'     => mb_substr((string) Request::post('color', '#64748b'), 0, 20),
            'is_active' => Request::bool('is_active'),
        ];
        if ($id) {
            Database::update('categories', $data, 'id = :id', ['id' => $id]);
        } else {
            $data['is_active'] = 1;
            Database::insert('categories', $data);
        }
        flash('success', 'تم حفظ التصنيف.');
        redirect('correspondents/categories');
    }

    public function deleteCategory(): void
    {
        Auth::requirePermission('categories.manage');
        Csrf::check();
        $id = Request::int('id', 0);
        Database::run('UPDATE transactions SET category_id = NULL WHERE category_id = ?', [$id]);
        Database::delete('categories', 'id = ?', [$id]);
        flash('success', 'تم حذف التصنيف.');
        redirect('correspondents/categories');
    }

    public function saveYear(): void
    {
        Auth::requirePermission('categories.manage');
        Csrf::check();
        $id   = Request::int('id');
        $name = mb_substr((string) Request::post('name', ''), 0, 60);
        if (mb_strlen($name) < 2) {
            flash('error', 'اسم العام الدراسي مطلوب.');
            redirect('correspondents/categories');
        }
        $data = [
            'name'       => $name,
            'start_date' => cleanDate(Request::post('start_date')),
            'end_date'   => cleanDate(Request::post('end_date')),
            'is_current' => Request::bool('is_current'),
        ];
        if ($data['is_current']) {
            Database::run('UPDATE academic_years SET is_current = 0');
        }
        if ($id) {
            Database::update('academic_years', $data, 'id = :id', ['id' => $id]);
        } else {
            Database::insert('academic_years', $data);
        }
        flash('success', 'تم حفظ العام الدراسي.');
        redirect('correspondents/categories');
    }

    // ------------------------------- داخلي -------------------------------
    private function fields(string $name): array
    {
        return [
            'name'           => $name,
            'type'           => pick(Request::post('type'),
                                  ['government','education','company','school','individual','internal','other'], 'government'),
            'contact_person' => mb_substr((string) Request::post('contact_person', ''), 0, 150) ?: null,
            'phone'          => mb_substr((string) Request::post('phone', ''), 0, 40) ?: null,
            'email'          => filter_var(Request::post('email'), FILTER_VALIDATE_EMAIL) ?: null,
            'address'        => mb_substr((string) Request::post('address', ''), 0, 300) ?: null,
            'notes'          => mb_substr((string) Request::post('notes', ''), 0, 500) ?: null,
        ];
    }
}
