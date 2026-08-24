<?php
/**
 * الوثائق والملفات داخل شجرة الأرشفة
 */
class DocumentsController
{
    public function index(): void
    {
        Auth::requirePermission('documents.view');

        $filters = [
            'q'      => Request::get('q'),
            'folder' => Request::int('folder'),
            'type'   => Request::get('type'),
            'conf'   => pick(Request::get('conf'), ['normal','confidential','top_secret'], null),
            'year'   => Request::int('year'),
            'from'   => cleanDate(Request::get('from')),
            'to'     => cleanDate(Request::get('to')),
            'media'  => pick(Request::get('media'), ['document','image','video','audio','archive','other'], null),
        ];

        $where  = ['d.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = likeAny(['d.title', 'd.description', 'd.doc_no', 'd.keywords'],
                (string) $filters['q'], $params);
        }
        if (!empty($filters['folder'])) {
            $ids = Tree::descendantIds('folders', (int) $filters['folder']);
            $where[] = 'd.folder_id IN (' . implode(',', array_map('intval', $ids)) . ')';
        }
        if (!empty($filters['type'])) {
            $where[] = 'd.doc_type = :dtype';
            $params['dtype'] = $filters['type'];
        }
        if (!empty($filters['conf'])) {
            $where[] = 'd.confidentiality = :conf';
            $params['conf'] = $filters['conf'];
        }
        if (!empty($filters['year'])) {
            $where[] = 'd.academic_year_id = :year';
            $params['year'] = $filters['year'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'd.doc_date >= :dfrom';
            $params['dfrom'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'd.doc_date <= :dto';
            $params['dto'] = $filters['to'];
        }
        if (!empty($filters['media'])) {
            $where[] = "EXISTS (SELECT 1 FROM attachments a
                        WHERE a.attachable_type='document' AND a.attachable_id = d.id AND a.media_group = :media)";
            $params['media'] = $filters['media'];
        }

        $sql = 'SELECT d.*, f.name AS folder_name, u.full_name AS creator, ay.name AS year_name
                  FROM documents d
                  JOIN folders f ON f.id = d.folder_id
                  LEFT JOIN users u ON u.id = d.created_by
                  LEFT JOIN academic_years ay ON ay.id = d.academic_year_id
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY d.id DESC';

        View::render('documents/index', [
            'title'   => 'الوثائق والملفات',
            'page'    => Paginator::make($sql, $params, Request::page()),
            'filters' => $filters,
            'lists'   => $this->lists(),
        ]);
    }

    public function create(): void
    {
        Auth::requirePermission('documents.create');
        View::render('documents/form', [
            'title'    => 'إضافة وثيقة جديدة',
            'document' => null,
            'folderId' => Request::int('folder'),
            'lists'    => $this->lists(),
            'tags'     => '',
        ]);
    }

    public function store(): void
    {
        Auth::requirePermission('documents.create');
        Csrf::check();

        $fields = $this->fields();
        if (!$fields['folder_id']) {
            keepOld($_POST);
            flash('error', 'يجب اختيار مجلد الأرشفة.');
            redirect('documents/create');
        }
        if (mb_strlen($fields['title']) < 3) {
            keepOld($_POST);
            flash('error', 'عنوان الوثيقة مطلوب (٣ أحرف على الأقل).');
            redirect('documents/create');
        }

        Database::begin();
        try {
            $fields['created_by'] = Auth::id();
            $fields['hijri_date'] = $fields['doc_date'] ? Hijri::format($fields['doc_date']) : null;

            $id  = Database::insert('documents', $fields);
            $this->syncTags($id, Request::post('tags'));
            $att = AttachmentService::saveFromRequest('document', $id, 'documents');
            Database::commit();

            Audit::log('create', 'document', $id, 'إضافة وثيقة : ' . $fields['title']);
            if ($att['errors']) {
                flash('warning', implode(' | ', $att['errors']));
            }
            clearOld();
            flash('success', 'تمت إضافة الوثيقة بنجاح مع ' . $att['saved'] . ' مرفق.');
            redirect('documents/show', ['id' => $id]);
        } catch (Throwable $ex) {
            Database::rollback();
            keepOld($_POST);
            flash('error', 'تعذر حفظ الوثيقة. ' . $ex->getMessage());
            redirect('documents/create');
        }
    }

    public function show(): void
    {
        Auth::requirePermission('documents.view');
        $id  = Request::int('id', 0);
        $doc = Database::first(
            'SELECT d.*, f.name AS folder_name, u.full_name AS creator, uu.full_name AS updater,
                    ay.name AS year_name
               FROM documents d
               JOIN folders f ON f.id = d.folder_id
               LEFT JOIN users u ON u.id = d.created_by
               LEFT JOIN users uu ON uu.id = d.updated_by
               LEFT JOIN academic_years ay ON ay.id = d.academic_year_id
              WHERE d.id = ? AND d.deleted_at IS NULL', [$id]);

        if (!$doc) {
            flash('error', 'الوثيقة غير موجودة.');
            redirect('documents/index');
        }
        Audit::log('view', 'document', $id, 'اطلاع على الوثيقة : ' . $doc['title']);

        View::render('documents/show', [
            'title'       => $doc['title'],
            'doc'         => $doc,
            'attachments' => AttachmentService::listFor('document', $id),
            'breadcrumb'  => Tree::breadcrumb('folders', (int) $doc['folder_id']),
            'tags'        => $this->getTags($id),
            'shares'      => Database::all(
                "SELECT * FROM shares WHERE shareable_type='document' AND shareable_id = ? ORDER BY id DESC", [$id]),
        ]);
    }

    public function edit(): void
    {
        Auth::requirePermission('documents.edit');
        $id  = Request::int('id', 0);
        $doc = Database::first('SELECT * FROM documents WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$doc) {
            flash('error', 'الوثيقة غير موجودة.');
            redirect('documents/index');
        }
        View::render('documents/form', [
            'title'       => 'تعديل الوثيقة',
            'document'    => $doc,
            'folderId'    => (int) $doc['folder_id'],
            'lists'       => $this->lists(),
            'attachments' => AttachmentService::listFor('document', $id),
            'tags'        => implode('، ', $this->getTags($id)),
        ]);
    }

    public function update(): void
    {
        Auth::requirePermission('documents.edit');
        Csrf::check();

        $id  = Request::int('id', 0);
        $doc = Database::first('SELECT * FROM documents WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$doc) {
            flash('error', 'الوثيقة غير موجودة.');
            redirect('documents/index');
        }

        $fields = $this->fields();
        $fields['updated_by'] = Auth::id();
        $fields['updated_at'] = date('Y-m-d H:i:s');
        $fields['hijri_date'] = $fields['doc_date'] ? Hijri::format($fields['doc_date']) : null;

        Database::begin();
        try {
            Database::update('documents', $fields, 'id = :id', ['id' => $id]);
            $this->syncTags($id, Request::post('tags'));
            $att = AttachmentService::saveFromRequest('document', $id, 'documents');
            Database::commit();

            Audit::log('update', 'document', $id, 'تعديل الوثيقة : ' . $doc['title']);
            if ($att['errors']) {
                flash('warning', implode(' | ', $att['errors']));
            }
            flash('success', 'تم تحديث الوثيقة.');
            redirect('documents/show', ['id' => $id]);
        } catch (Throwable $ex) {
            Database::rollback();
            flash('error', 'تعذر تحديث الوثيقة. ' . $ex->getMessage());
            redirect('documents/edit', ['id' => $id]);
        }
    }

    public function delete(): void
    {
        Auth::requirePermission('documents.delete');
        Csrf::check();
        $id  = Request::int('id', 0);
        $doc = Database::first('SELECT * FROM documents WHERE id = ?', [$id]);
        if ($doc) {
            Database::update('documents', ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
            Audit::log('delete', 'document', $id, 'حذف الوثيقة : ' . $doc['title']);
            flash('success', 'تم نقل الوثيقة إلى المحذوفات.');
        }
        redirect('documents/index');
    }

    public function trash(): void
    {
        Auth::requirePermission('documents.delete');
        $sql = 'SELECT d.*, f.name AS folder_name FROM documents d
                  JOIN folders f ON f.id = d.folder_id
                 WHERE d.deleted_at IS NOT NULL ORDER BY d.deleted_at DESC';
        View::render('documents/trash', [
            'title' => 'الوثائق المحذوفة',
            'page'  => Paginator::make($sql, [], Request::page()),
        ]);
    }

    public function restore(): void
    {
        Auth::requirePermission('documents.delete');
        Csrf::check();
        $id = Request::int('id', 0);
        Database::update('documents', ['deleted_at' => null], 'id = :id', ['id' => $id]);
        Audit::log('restore', 'document', $id, 'استرجاع وثيقة');
        flash('success', 'تم استرجاع الوثيقة.');
        redirect('documents/trash');
    }

    public function forceDelete(): void
    {
        Auth::requirePermission('documents.delete');
        Csrf::check();
        $id = Request::int('id', 0);
        AttachmentService::removeAllFor('document', $id);
        Database::delete('documents', 'id = ? AND deleted_at IS NOT NULL', [$id]);
        Audit::log('delete', 'document', $id, 'حذف نهائي للوثيقة');
        flash('success', 'تم الحذف النهائي للوثيقة ومرفقاتها.');
        redirect('documents/trash');
    }

    // ------------------------------ داخلي ------------------------------
    private function fields(): array
    {
        return [
            'folder_id'        => Request::int('folder_id') ?: null,
            'title'            => mb_substr((string) Request::post('title', ''), 0, 500),
            'doc_no'           => mb_substr((string) Request::post('doc_no', ''), 0, 80) ?: null,
            'doc_date'         => cleanDate(Request::post('doc_date')),
            'doc_type'         => mb_substr((string) Request::post('doc_type', ''), 0, 80) ?: null,
            'description'      => Request::post('description') ?: null,
            'confidentiality'  => pick(Request::post('confidentiality'), ['normal','confidential','top_secret'], 'normal'),
            'keywords'         => mb_substr((string) Request::post('keywords', ''), 0, 500) ?: null,
            'academic_year_id' => Request::int('academic_year_id') ?: null,
        ];
    }

    private function syncTags(int $id, ?string $raw): void
    {
        Database::delete('taggables', "taggable_type = 'document' AND taggable_id = ?", [$id]);
        foreach (parseTags($raw) as $name) {
            Database::run('INSERT IGNORE INTO tags (name) VALUES (?)', [$name]);
            $tagId = (int) Database::value('SELECT id FROM tags WHERE name = ?', [$name]);
            if ($tagId) {
                Database::run('INSERT IGNORE INTO taggables (tag_id, taggable_type, taggable_id) VALUES (?, ?, ?)',
                    [$tagId, 'document', $id]);
            }
        }
    }

    private function getTags(int $id): array
    {
        $rows = Database::all(
            "SELECT tg.name FROM tags tg JOIN taggables t ON t.tag_id = tg.id
              WHERE t.taggable_type = 'document' AND t.taggable_id = ?", [$id]);
        return array_column($rows, 'name');
    }

    private function lists(): array
    {
        return [
            'folders' => Tree::flatten(Tree::build(Database::all(
                'SELECT id, parent_id, name FROM folders WHERE is_active = 1 ORDER BY sort_order, name'))),
            'years'   => Database::all('SELECT id, name, is_current FROM academic_years ORDER BY id DESC'),
            'types'   => array_column(Database::all(
                "SELECT DISTINCT doc_type FROM documents WHERE doc_type IS NOT NULL AND doc_type <> '' ORDER BY doc_type"), 'doc_type'),
        ];
    }
}
