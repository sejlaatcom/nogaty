<?php
declare(strict_types=1);

/** بنك أسئلة نافس: المجالات والمهارات والنصوص والأسئلة */
final class QuestionBank
{
    public const TYPES = [
        'mcq' => 'اختيار من متعدد',
        'tf'  => 'صح أو خطأ',
    ];

    public const DIFFICULTIES = [
        1 => 'سهل',
        2 => 'متوسط',
        3 => 'صعب',
    ];

    /* ---------------- المجالات والمهارات ---------------- */

    /** المجالات مع مهاراتها لمادة وصف (المجالات العامة بلا صف تظهر لكل الصفوف) */
    public static function domainsWithSkills(int $subjectId, ?int $gradeId = null): array
    {
        $params = [$subjectId];
        $gradeSql = '';
        if ($gradeId) {
            $gradeSql = ' AND (d.grade_id = ? OR d.grade_id IS NULL)';
            $params[] = $gradeId;
        }
        $domains = Database::all(
            "SELECT d.*, g.name AS grade_name
               FROM nafes_domains d
          LEFT JOIN grades g ON g.id = d.grade_id
              WHERE d.subject_id = ? $gradeSql
           ORDER BY d.sort_order, d.id",
            $params
        );
        if (!$domains) {
            return [];
        }

        $ids    = implode(',', array_map(static fn ($d) => (int) $d['id'], $domains));
        $skills = Database::all(
            "SELECT s.*, (SELECT COUNT(*) FROM nafes_questions q WHERE q.skill_id = s.id AND q.is_active = 1) AS questions_count
               FROM nafes_skills s WHERE s.domain_id IN ($ids) ORDER BY s.sort_order, s.id"
        );
        $byDomain = [];
        foreach ($skills as $skill) {
            $byDomain[(int) $skill['domain_id']][] = $skill;
        }
        foreach ($domains as &$domain) {
            $domain['skills'] = $byDomain[(int) $domain['id']] ?? [];
        }
        return $domains;
    }

    /** كل المهارات (للقوائم المنسدلة) مع المادة والصف */
    public static function allSkills(): array
    {
        return Database::all(
            'SELECT s.id, s.code, s.name, d.id AS domain_id, d.name AS domain_name, d.subject_id, d.grade_id
               FROM nafes_skills s
               JOIN nafes_domains d ON d.id = s.domain_id
           ORDER BY d.subject_id, d.sort_order, d.id, s.sort_order, s.id'
        );
    }

    public static function skill(int $id): ?array
    {
        return Database::first(
            'SELECT s.*, d.name AS domain_name, d.subject_id, d.grade_id
               FROM nafes_skills s JOIN nafes_domains d ON d.id = s.domain_id
              WHERE s.id = ?',
            [$id]
        );
    }

    public static function skillLabel(array $skill): string
    {
        return trim(($skill['code'] ? $skill['code'] . ' — ' : '') . $skill['name']);
    }

    /* ---------------- النصوص ---------------- */

    public static function passages(?int $subjectId = null, ?int $gradeId = null): array
    {
        $where  = ['1 = 1'];
        $params = [];
        if ($subjectId) {
            $where[]  = 'p.subject_id = ?';
            $params[] = $subjectId;
        }
        if ($gradeId) {
            $where[]  = 'p.grade_id = ?';
            $params[] = $gradeId;
        }
        return Database::all(
            'SELECT p.*, s.name AS subject_name, g.name AS grade_name, u.name AS author_name,
                    (SELECT COUNT(*) FROM nafes_questions q WHERE q.passage_id = p.id) AS questions_count
               FROM nafes_passages p
               JOIN subjects s ON s.id = p.subject_id
               JOIN grades g   ON g.id = p.grade_id
          LEFT JOIN users u    ON u.id = p.created_by
              WHERE ' . implode(' AND ', $where) . '
           ORDER BY p.id DESC',
            $params
        );
    }

    public static function passage(int $id): ?array
    {
        return Database::first('SELECT * FROM nafes_passages WHERE id = ?', [$id]);
    }

    /* ---------------- الأسئلة ---------------- */

    /**
     * البحث في الأسئلة.
     * @param array $filters subject, grade, skill, difficulty, type, q, mine (user id), active
     */
    public static function search(array $filters, int $page = 1, int $perPage = 25): array
    {
        [$whereSql, $params] = self::filterSql($filters);

        $total = (int) Database::value("SELECT COUNT(*) FROM nafes_questions q WHERE $whereSql", $params, 0);
        $offset = max(0, ($page - 1) * $perPage);

        $rows = Database::all(
            "SELECT q.*, s.name AS subject_name, g.name AS grade_name,
                    sk.name AS skill_name, sk.code AS skill_code, u.name AS author_name, p.title AS passage_title,
                    (SELECT COUNT(*) FROM nafes_test_questions tq WHERE tq.question_id = q.id) AS used_count
               FROM nafes_questions q
               JOIN subjects s       ON s.id = q.subject_id
               JOIN grades g         ON g.id = q.grade_id
          LEFT JOIN nafes_skills sk  ON sk.id = q.skill_id
          LEFT JOIN users u          ON u.id = q.created_by
          LEFT JOIN nafes_passages p ON p.id = q.passage_id
              WHERE $whereSql
           ORDER BY q.id DESC
              LIMIT " . (int) $perPage . ' OFFSET ' . (int) $offset,
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'pages' => max(1, (int) ceil($total / $perPage)), 'page' => $page];
    }

    /** شروط التصفية المشتركة بين البحث والتصدير والتوليد العشوائي */
    public static function filterSql(array $filters, string $alias = 'q'): array
    {
        $where  = ['1 = 1'];
        $params = [];
        $map = ['subject' => 'subject_id', 'grade' => 'grade_id', 'skill' => 'skill_id', 'difficulty' => 'difficulty', 'passage' => 'passage_id'];
        foreach ($map as $key => $column) {
            if (!empty($filters[$key])) {
                $where[]  = "$alias.$column = ?";
                $params[] = (int) $filters[$key];
            }
        }
        if (!empty($filters['type']) && isset(self::TYPES[$filters['type']])) {
            $where[]  = "$alias.type = ?";
            $params[] = $filters['type'];
        }
        if (!empty($filters['mine'])) {
            $where[]  = "$alias.created_by = ?";
            $params[] = (int) $filters['mine'];
        }
        if (isset($filters['active']) && $filters['active'] !== '' && $filters['active'] !== null) {
            $where[]  = "$alias.is_active = ?";
            $params[] = (int) $filters['active'];
        }
        if (!empty($filters['q'])) {
            $where[]  = "$alias.stem LIKE ?";
            $params[] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['exclude'])) {
            $ids = implode(',', array_map('intval', (array) $filters['exclude']));
            $where[] = "$alias.id NOT IN ($ids)";
        }
        return [implode(' AND ', $where), $params];
    }

    public static function find(int $id): ?array
    {
        $question = Database::first(
            'SELECT q.*, s.name AS subject_name, g.name AS grade_name, sk.name AS skill_name, sk.code AS skill_code
               FROM nafes_questions q
               JOIN subjects s      ON s.id = q.subject_id
               JOIN grades g        ON g.id = q.grade_id
          LEFT JOIN nafes_skills sk ON sk.id = q.skill_id
              WHERE q.id = ?',
            [$id]
        );
        if (!$question) {
            return null;
        }
        $question['options'] = self::options($id);
        $question['passage'] = $question['passage_id'] ? self::passage((int) $question['passage_id']) : null;
        return $question;
    }

    public static function options(int $questionId): array
    {
        return Database::all('SELECT * FROM nafes_options WHERE question_id = ? ORDER BY sort_order, id', [$questionId]);
    }

    /** خيارات عدة أسئلة دفعة واحدة: [question_id => [options]] */
    public static function optionsFor(array $questionIds): array
    {
        $ids = array_filter(array_map('intval', $questionIds));
        if (!$ids) {
            return [];
        }
        $out = [];
        foreach (Database::all('SELECT * FROM nafes_options WHERE question_id IN (' . implode(',', $ids) . ') ORDER BY sort_order, id') as $option) {
            $out[(int) $option['question_id']][] = $option;
        }
        return $out;
    }

    /** هل يستطيع المستخدم تعديل السؤال؟ الإدارة دائماً، والمعلم لأسئلته فقط */
    public static function canEdit(array $user, array $question): bool
    {
        return $user['role'] === 'admin' || (int) $question['created_by'] === (int) $user['id'];
    }

    /**
     * التحقق من بيانات السؤال وحفظها.
     * $data: subject_id, grade_id, skill_id, passage_id, type, stem, difficulty, explanation, is_active
     * $options: [['body' => '', 'is_correct' => bool], ...]
     * تُعيد [id, errors]
     */
    public static function save(?int $id, array $data, array $options, int $userId): array
    {
        $errors = [];
        $data['type'] = isset(self::TYPES[$data['type'] ?? '']) ? $data['type'] : 'mcq';
        $data['difficulty'] = isset(self::DIFFICULTIES[(int) ($data['difficulty'] ?? 2)]) ? (int) $data['difficulty'] : 2;

        if (trim((string) ($data['stem'] ?? '')) === '') {
            $errors[] = 'نص السؤال مطلوب.';
        }
        if (empty($data['subject_id']) || empty($data['grade_id'])) {
            $errors[] = 'المادة والصف مطلوبان.';
        }

        if ($data['type'] === 'tf') {
            $correctTrue = !empty($options[0]['is_correct']);
            $options = [
                ['body' => 'صح', 'is_correct' => $correctTrue],
                ['body' => 'خطأ', 'is_correct' => !$correctTrue],
            ];
        } else {
            $options = array_values(array_filter($options, static fn ($o) => trim((string) ($o['body'] ?? '')) !== ''));
            if (count($options) < 2) {
                $errors[] = 'أضف خيارين على الأقل.';
            }
            if (count(array_filter($options, static fn ($o) => !empty($o['is_correct']))) !== 1) {
                $errors[] = 'حدد إجابة صحيحة واحدة.';
            }
        }

        if (!empty($data['skill_id'])) {
            $skill = self::skill((int) $data['skill_id']);
            if (!$skill || (int) $skill['subject_id'] !== (int) $data['subject_id']) {
                $errors[] = 'المهارة المختارة لا تتبع المادة نفسها.';
            }
        }

        if ($errors) {
            return [null, $errors];
        }

        $row = [
            'subject_id'  => (int) $data['subject_id'],
            'grade_id'    => (int) $data['grade_id'],
            'skill_id'    => !empty($data['skill_id']) ? (int) $data['skill_id'] : null,
            'passage_id'  => !empty($data['passage_id']) ? (int) $data['passage_id'] : null,
            'type'        => $data['type'],
            'stem'        => trim((string) $data['stem']),
            'difficulty'  => $data['difficulty'],
            'explanation' => trim((string) ($data['explanation'] ?? '')) ?: null,
            'is_active'   => !empty($data['is_active']) ? 1 : 0,
        ];
        if (array_key_exists('image', $data)) {
            $row['image'] = $data['image'];
        }

        $id = Database::transaction(static function () use ($id, $row, $options, $userId) {
            if ($id) {
                Database::update('nafes_questions', $row, 'id = :qid', ['qid' => $id]);
            } else {
                $id = Database::insert('nafes_questions', $row + ['created_by' => $userId ?: null]);
            }
            self::saveOptions($id, $options);
            return $id;
        });

        return [$id, []];
    }

    /**
     * حفظ الخيارات مع الإبقاء على معرّفاتها قدر الإمكان، حتى لا تفقد إجابات الطلاب السابقة ارتباطها.
     */
    private static function saveOptions(int $questionId, array $options): void
    {
        $existing = self::options($questionId);
        foreach (array_values($options) as $index => $option) {
            $values = [
                'body'       => trim((string) $option['body']),
                'is_correct' => !empty($option['is_correct']) ? 1 : 0,
                'sort_order' => $index + 1,
            ];
            if (isset($existing[$index])) {
                Database::update('nafes_options', $values, 'id = :oid', ['oid' => $existing[$index]['id']]);
            } else {
                Database::insert('nafes_options', $values + ['question_id' => $questionId]);
            }
        }
        foreach (array_slice($existing, count($options)) as $extra) {
            Database::delete('nafes_options', 'id = ?', [$extra['id']]);
        }
    }

    /** حذف سؤال: إن كان مستخدماً في اختبار أو أجاب عنه طلاب يُعطَّل فقط حفاظاً على النتائج */
    public static function delete(int $id): string
    {
        $used = (int) Database::value('SELECT COUNT(*) FROM nafes_test_questions WHERE question_id = ?', [$id], 0)
              + (int) Database::value('SELECT COUNT(*) FROM nafes_attempt_answers WHERE question_id = ?', [$id], 0);
        if ($used > 0) {
            Database::update('nafes_questions', ['is_active' => 0], 'id = :qid', ['qid' => $id]);
            return 'disabled';
        }
        $image = (string) Database::value('SELECT image FROM nafes_questions WHERE id = ?', [$id], '');
        Database::delete('nafes_questions', 'id = ?', [$id]);
        self::removeImage($image);
        return 'deleted';
    }

    /* ---------------- الصور ---------------- */

    /** رفع صورة السؤال — تُعيد [اسم الملف، رسالة الخطأ] */
    public static function uploadImage(array $file): array
    {
        if (empty($file['name']) || !is_uploaded_file($file['tmp_name'] ?? '')) {
            return [null, ''];
        }
        $info    = @getimagesize($file['tmp_name']);
        $allowed = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
        if ($info === false || !isset($allowed[$info[2]])) {
            return [null, 'الصورة يجب أن تكون بصيغة PNG أو JPG أو GIF أو WEBP.'];
        }
        if ($file['size'] > 3 * 1024 * 1024) {
            return [null, 'حجم الصورة يجب ألا يتجاوز 3 ميجابايت.'];
        }
        $dir = PUBLIC_PATH . '/uploads/questions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $name = 'q-' . bin2hex(random_bytes(6)) . '.' . $allowed[$info[2]];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            return [null, 'تعذر حفظ الصورة على الخادم.'];
        }
        return ['questions/' . $name, ''];
    }

    public static function removeImage(?string $image): void
    {
        if ($image && preg_match('#^questions/[a-z0-9.-]+$#', $image)) {
            $path = PUBLIC_PATH . '/uploads/' . $image;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /* ---------------- الاستيراد والتصدير ---------------- */

    public const CSV_HEADERS = ['المادة', 'الصف', 'رمز المهارة', 'المهارة', 'النوع', 'الصعوبة', 'نص السؤال',
                                'الخيار 1', 'الخيار 2', 'الخيار 3', 'الخيار 4', 'الإجابة الصحيحة', 'التفسير'];

    /** صفوف التصدير بنفس صيغة الاستيراد */
    public static function exportRows(array $filters): array
    {
        [$whereSql, $params] = self::filterSql($filters);
        $questions = Database::all(
            "SELECT q.*, s.name AS subject_name, g.name AS grade_name, sk.name AS skill_name, sk.code AS skill_code
               FROM nafes_questions q
               JOIN subjects s      ON s.id = q.subject_id
               JOIN grades g        ON g.id = q.grade_id
          LEFT JOIN nafes_skills sk ON sk.id = q.skill_id
              WHERE $whereSql ORDER BY q.subject_id, q.grade_id, q.id",
            $params
        );
        $options = self::optionsFor(array_column($questions, 'id'));

        $rows = [self::CSV_HEADERS];
        foreach ($questions as $q) {
            $opts    = $options[(int) $q['id']] ?? [];
            $correct = '';
            foreach ($opts as $i => $option) {
                if ((int) $option['is_correct'] === 1) {
                    $correct = $q['type'] === 'tf' ? $option['body'] : (string) ($i + 1);
                }
            }
            $rows[] = [
                $q['subject_name'], $q['grade_name'], $q['skill_code'] ?? '', $q['skill_name'] ?? '',
                self::TYPES[$q['type']], self::DIFFICULTIES[(int) $q['difficulty']] ?? 'متوسط', $q['stem'],
                $q['type'] === 'mcq' ? ($opts[0]['body'] ?? '') : '', $q['type'] === 'mcq' ? ($opts[1]['body'] ?? '') : '',
                $q['type'] === 'mcq' ? ($opts[2]['body'] ?? '') : '', $q['type'] === 'mcq' ? ($opts[3]['body'] ?? '') : '',
                $correct, $q['explanation'] ?? '',
            ];
        }
        return $rows;
    }

    /** استيراد الأسئلة من صفوف CSV (الصف الأول رؤوس الأعمدة) */
    public static function import(array $rows, int $userId, bool $createSkills = true): array
    {
        $result = ['created' => 0, 'errors' => []];
        $items  = Csv::toAssoc($rows);

        foreach ($items as $line => $item) {
            $row  = $line + 2;
            $item = array_map(static fn ($v) => trim((string) $v), $item);

            $subjectId = (int) Database::value('SELECT id FROM subjects WHERE name = ?', [$item['المادة'] ?? ''], 0);
            $gradeId   = (int) Database::value('SELECT id FROM grades WHERE name = ?', [$item['الصف'] ?? ''], 0);
            if (!$subjectId || !$gradeId) {
                $result['errors'][] = "السطر $row: المادة أو الصف غير موجود.";
                continue;
            }

            $skillId = self::resolveSkill($subjectId, $gradeId, $item['رمز المهارة'] ?? '', $item['المهارة'] ?? '', $createSkills);

            $typeText = $item['النوع'] ?? '';
            $correct  = $item['الإجابة الصحيحة'] ?? '';
            $isTf     = in_array($typeText, ['صح أو خطأ', 'صح وخطأ', 'tf'], true)
                     || ($typeText === '' && ($item['الخيار 1'] ?? '') === '' && in_array($correct, ['صح', 'خطأ'], true));

            $options = [];
            if ($isTf) {
                $options[] = ['body' => 'صح', 'is_correct' => in_array($correct, ['صح', 'صحيح', '1', 'true'], true)];
            } else {
                // الإجابة الصحيحة رقم الخيار (1–6)، وإلا فهي نص الخيار نفسه
                $byNumber = preg_match('/^[1-6]$/', $correct) === 1 && ($item['الخيار ' . $correct] ?? '') !== '';
                for ($i = 1; $i <= 6; $i++) {
                    $body = $item['الخيار ' . $i] ?? '';
                    if ($body !== '') {
                        $options[] = ['body' => $body, 'is_correct' => $byNumber ? $correct === (string) $i : $correct === $body];
                    }
                }
            }

            $difficulty = (int) (array_search($item['الصعوبة'] ?? '', self::DIFFICULTIES, true) ?: 2);

            [$id, $errors] = self::save(null, [
                'subject_id'  => $subjectId,
                'grade_id'    => $gradeId,
                'skill_id'    => $skillId ?: null,
                'type'        => $isTf ? 'tf' : 'mcq',
                'stem'        => $item['نص السؤال'] ?? '',
                'difficulty'  => $difficulty,
                'explanation' => $item['التفسير'] ?? '',
                'is_active'   => 1,
            ], $options, $userId);

            if ($errors) {
                $result['errors'][] = "السطر $row: " . implode(' ', $errors);
                continue;
            }
            $result['created']++;
        }

        Activity::log('import_questions', 'nafes_questions', null, 'created=' . $result['created']);
        return $result;
    }

    /** إيجاد المهارة بالرمز أو الاسم، وإنشاؤها تحت مجال «عام» إن لم توجد */
    private static function resolveSkill(int $subjectId, int $gradeId, string $code, string $name, bool $create): int
    {
        if ($code === '' && $name === '') {
            return 0;
        }
        $id = (int) Database::value(
            'SELECT s.id FROM nafes_skills s JOIN nafes_domains d ON d.id = s.domain_id
              WHERE d.subject_id = ? AND (d.grade_id = ? OR d.grade_id IS NULL)
                AND ((? <> \'\' AND s.code = ?) OR (? <> \'\' AND s.name = ?))
              ORDER BY s.id LIMIT 1',
            [$subjectId, $gradeId, $code, $code, $name, $name],
            0
        );
        if ($id || !$create || $name === '') {
            return $id;
        }
        $domainId = (int) Database::value(
            "SELECT id FROM nafes_domains WHERE subject_id = ? AND grade_id = ? AND name = 'مهارات مستوردة'",
            [$subjectId, $gradeId],
            0
        );
        if (!$domainId) {
            $domainId = Database::insert('nafes_domains', ['subject_id' => $subjectId, 'grade_id' => $gradeId, 'name' => 'مهارات مستوردة', 'sort_order' => 99]);
        }
        return Database::insert('nafes_skills', ['domain_id' => $domainId, 'code' => $code ?: null, 'name' => $name]);
    }
}
