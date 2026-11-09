<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

/**
 * بنك الأسئلة: التحقق والحفظ، اكتشاف التكرار، تتبع النسخ، سير المراجعة والنشر، واللقطات المجمدة.
 */
final class QuestionBank
{
    public const TYPES = [
        'single'       => 'اختيار من متعدد',
        'true_false'   => 'صح أو خطأ',
        'multiple'     => 'اختيار متعدد الإجابات',
        'matching'     => 'توصيل',
        'ordering'     => 'ترتيب',
        'short_answer' => 'إجابة قصيرة',
    ];

    public const DIFFICULTIES = [1 => 'سهل', 2 => 'متوسط', 3 => 'صعب'];

    public const COGNITIVE = ['knowing' => 'معرفة', 'applying' => 'تطبيق', 'reasoning' => 'استدلال'];

    public const STATUSES = [
        'draft'     => ['مسودة', 'secondary'],
        'review'    => ['بانتظار المراجعة', 'warning'],
        'published' => ['منشور', 'success'],
        'archived'  => ['مؤرشف', 'secondary'],
    ];

    /* ======================= القراءة ======================= */

    public static function find(int $id): ?array
    {
        $q = DB::first(
            'SELECT q.*, s.name AS subject_name, g.name AS grade_name, d.name AS domain_name, sk.name AS skill_name, sk.code AS skill_code,
                    li.description AS indicator_name, p.title AS passage_title, u.full_name AS author_name, rv.full_name AS reviewer_name
               FROM questions q
               JOIN subjects s ON s.id = q.subject_id JOIN grades g ON g.id = q.grade_id
          LEFT JOIN domains d ON d.id = q.domain_id LEFT JOIN skills sk ON sk.id = q.skill_id
          LEFT JOIN learning_indicators li ON li.id = q.indicator_id LEFT JOIN passages p ON p.id = q.passage_id
          LEFT JOIN users u ON u.id = q.created_by LEFT JOIN users rv ON rv.id = q.reviewed_by
              WHERE q.id = ?',
            [$id]
        );
        if ($q) {
            $q['options'] = self::options($id);
            $q['media']   = DB::all('SELECT id, alt_text, mime FROM question_media WHERE question_id = ? ORDER BY id', [$id]);
        }
        return $q;
    }

    public static function options(int $questionId): array
    {
        return DB::all('SELECT * FROM question_options WHERE question_id = ? ORDER BY sort_order, id', [$questionId]);
    }

    /** بحث مع التصفية والترقيم */
    public static function search(array $f, int $page = 1, int $perPage = 25): array
    {
        [$where, $params] = self::filterSql($f);
        $total = (int) DB::value("SELECT COUNT(*) FROM questions q WHERE $where", $params, 0);
        $rows = DB::all(
            "SELECT q.id, q.type, q.stem, q.difficulty, q.cognitive_level, q.status, q.points, q.created_by, q.updated_at, q.version,
                    s.name AS subject_name, g.name AS grade_name, sk.name AS skill_name, sk.code AS skill_code, d.name AS domain_name,
                    u.full_name AS author_name, p.title AS passage_title,
                    (SELECT COUNT(*) FROM test_questions tq WHERE tq.question_id = q.id) AS used_count
               FROM questions q JOIN subjects s ON s.id = q.subject_id JOIN grades g ON g.id = q.grade_id
          LEFT JOIN skills sk ON sk.id = q.skill_id LEFT JOIN domains d ON d.id = q.domain_id
          LEFT JOIN users u ON u.id = q.created_by LEFT JOIN passages p ON p.id = q.passage_id
              WHERE $where ORDER BY q.id DESC LIMIT " . (int) $perPage . ' OFFSET ' . (int) (max(0, $page - 1) * $perPage),
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    /** شروط التصفية المشتركة (البحث، التوليد التلقائي، التصدير) */
    public static function filterSql(array $f, string $a = 'q'): array
    {
        $where = ['1 = 1'];
        $params = [];
        foreach (['subject' => 'subject_id', 'grade' => 'grade_id', 'domain' => 'domain_id', 'skill' => 'skill_id',
                  'indicator' => 'indicator_id', 'difficulty' => 'difficulty', 'passage' => 'passage_id'] as $key => $col) {
            if (!empty($f[$key])) {
                $where[]  = "$a.$col = ?";
                $params[] = (int) $f[$key];
            }
        }
        if (!empty($f['type']) && isset(self::TYPES[$f['type']])) {
            $where[]  = "$a.type = ?";
            $params[] = $f['type'];
        }
        if (!empty($f['types']) && is_array($f['types'])) {
            [$in, $p] = DB::in(array_values(array_intersect($f['types'], array_keys(self::TYPES))));
            $where[] = "$a.type IN ($in)";
            $params = array_merge($params, $p);
        }
        if (!empty($f['cognitive']) && isset(self::COGNITIVE[$f['cognitive']])) {
            $where[]  = "$a.cognitive_level = ?";
            $params[] = $f['cognitive'];
        }
        if (!empty($f['status']) && isset(self::STATUSES[$f['status']])) {
            $where[]  = "$a.status = ?";
            $params[] = $f['status'];
        } elseif (empty($f['include_archived'])) {
            $where[] = "$a.status <> 'archived'";
        }
        if (!empty($f['mine'])) {
            $where[]  = "$a.created_by = ?";
            $params[] = (int) $f['mine'];
        }
        if (!empty($f['q'])) {
            $where[]  = "($a.stem LIKE ? OR $a.id = ?)";
            $params[] = '%' . $f['q'] . '%';
            $params[] = (int) $f['q'];
        }
        if (!empty($f['exclude'])) {
            [$in, $p] = DB::in(array_map('intval', (array) $f['exclude']));
            $where[] = "$a.id NOT IN ($in)";
            $params = array_merge($params, $p);
        }
        // المعلم بدون صلاحية الاطلاع على كامل البنك يرى المنشور وأسئلته فقط
        if (!empty($f['visibility_user'])) {
            $where[]  = "($a.status = 'published' OR $a.created_by = ?)";
            $params[] = (int) $f['visibility_user'];
        }
        return [implode(' AND ', $where), $params];
    }

    /* ======================= الصلاحيات ======================= */

    public static function canEdit(array $question): bool
    {
        return Auth::can('questions.edit_any') || (Auth::can('questions.create') && (int) $question['created_by'] === Auth::id());
    }

    public static function canPublish(): bool
    {
        return Auth::can('questions.review') || (Auth::can('questions.create') && Settings::bool('teacher_can_publish_questions'));
    }

    /* ======================= التحقق والحفظ ======================= */

    /**
     * تطبيع وتحقق من بيانات السؤال وخياراته حسب النوع.
     * $options: [['body','match_text','is_correct','feedback'], ...] — ordering بترتيبها الصحيح
     * $accepted: إجابات مقبولة للإجابة القصيرة
     */
    public static function validate(array $data, array $options, array $accepted = []): array
    {
        $errors = [];
        $type = $data['type'] ?? '';
        if (!isset(self::TYPES[$type])) {
            $errors[] = 'نوع السؤال غير صالح.';
        }
        if (trim((string) ($data['stem'] ?? '')) === '') {
            $errors[] = 'نص السؤال مطلوب.';
        }
        if (empty($data['subject_id']) || empty($data['grade_id'])) {
            $errors[] = 'المادة والصف مطلوبان.';
        }
        if (!isset(self::DIFFICULTIES[(int) ($data['difficulty'] ?? 0)])) {
            $errors[] = 'مستوى الصعوبة غير صالح.';
        }
        if (!isset(self::COGNITIVE[$data['cognitive_level'] ?? ''])) {
            $errors[] = 'المستوى المعرفي غير صالح.';
        }
        if ((float) ($data['points'] ?? 1) <= 0 || (float) ($data['points'] ?? 1) > 100) {
            $errors[] = 'درجة السؤال يجب أن تكون بين 0.25 و100.';
        }
        // التحقق من اتساق التصنيف
        if (!empty($data['skill_id'])) {
            $skill = DB::first('SELECT s.id, d.id AS domain_id, d.subject_id FROM skills s JOIN domains d ON d.id = s.domain_id WHERE s.id = ?', [(int) $data['skill_id']]);
            if (!$skill || (int) $skill['subject_id'] !== (int) $data['subject_id']) {
                $errors[] = 'المهارة المختارة لا تتبع المادة نفسها.';
            }
        }
        if (!empty($data['indicator_id']) && !empty($data['skill_id'])
            && !DB::value('SELECT 1 FROM learning_indicators WHERE id = ? AND skill_id = ?', [(int) $data['indicator_id'], (int) $data['skill_id']])) {
            $errors[] = 'مؤشر الأداء لا يتبع المهارة المختارة.';
        }

        $options = array_values(array_filter($options, static fn ($o) => trim((string) ($o['body'] ?? '')) !== ''));
        $correct = count(array_filter($options, static fn ($o) => !empty($o['is_correct'])));
        switch ($type) {
            case 'single':
                if (count($options) < 2) {
                    $errors[] = 'أضف خيارين على الأقل.';
                }
                if ($correct !== 1) {
                    $errors[] = 'حدد إجابة صحيحة واحدة بالضبط.';
                }
                break;
            case 'true_false':
                if ($correct !== 1) {
                    $errors[] = 'حدد هل العبارة صحيحة أم خاطئة.';
                }
                break;
            case 'multiple':
                if (count($options) < 3) {
                    $errors[] = 'أضف ثلاثة خيارات على الأقل.';
                }
                if ($correct < 2) {
                    $errors[] = 'حدد إجابتين صحيحتين على الأقل (وإلا استخدم الاختيار من متعدد).';
                }
                if ($correct === count($options)) {
                    $errors[] = 'لا يصح أن تكون كل الخيارات صحيحة.';
                }
                break;
            case 'matching':
                if (count($options) < 2) {
                    $errors[] = 'أضف زوجين على الأقل للتوصيل.';
                }
                foreach ($options as $o) {
                    if (trim((string) ($o['match_text'] ?? '')) === '') {
                        $errors[] = 'لكل عنصر في التوصيل عنصر مقابل.';
                        break;
                    }
                }
                break;
            case 'ordering':
                if (count($options) < 3) {
                    $errors[] = 'أضف ثلاثة عناصر على الأقل للترتيب.';
                }
                break;
            case 'short_answer':
                $accepted = array_values(array_filter(array_map('trim', $accepted), static fn ($v) => $v !== ''));
                if (!$accepted && empty($data['manual_grading'])) {
                    $errors[] = 'أضف إجابة مقبولة واحدة على الأقل، أو فعّل التصحيح اليدوي.';
                }
                break;
        }
        // خيارات مكررة
        $bodies = array_map(static fn ($o) => normalize_ar((string) $o['body']), $options);
        if (in_array($type, ['single', 'multiple', 'ordering'], true) && count($bodies) !== count(array_unique($bodies))) {
            $errors[] = 'يوجد خياران متطابقان.';
        }
        return $errors;
    }

    /** بصمة المحتوى لاكتشاف الأسئلة المكررة */
    public static function hash(array $data, array $options, array $accepted = []): string
    {
        $parts = array_map(static fn ($o) => normalize_ar((string) ($o['body'] ?? '')) . '>' . normalize_ar((string) ($o['match_text'] ?? '')), $options);
        if ($data['type'] !== 'ordering') {
            sort($parts);
        }
        return hash('sha256', implode('|', [
            (int) $data['subject_id'], (int) $data['grade_id'], $data['type'], normalize_ar((string) $data['stem']),
            implode('#', $parts), implode('#', array_map('normalize_ar', $accepted)), (int) ($data['passage_id'] ?? 0),
        ]));
    }

    /** سؤال مطابق موجود؟ يُعيد معرفه */
    public static function duplicateOf(string $hash, int $exceptId = 0): ?int
    {
        $id = DB::value("SELECT id FROM questions WHERE content_hash = ? AND id <> ? AND status <> 'archived' LIMIT 1", [$hash, $exceptId]);
        return $id ? (int) $id : null;
    }

    /**
     * حفظ سؤال (إنشاء أو تعديل) — يُعيد [id, errors]
     * التعديل يحفظ نسخة سابقة في question_revisions ويرفع رقم النسخة.
     */
    public static function save(?int $id, array $data, array $options, array $accepted = [], bool $allowDuplicate = false, ?string $note = null): array
    {
        $data += ['domain_id' => null, 'skill_id' => null, 'indicator_id' => null, 'passage_id' => null, 'explanation' => null,
                  'common_mistakes' => null, 'difficulty' => 2, 'cognitive_level' => 'applying', 'points' => 1, 'manual_grading' => 0,
                  'source' => null, 'usage_rights' => null, 'status' => 'draft'];
        if ($data['type'] === 'true_false') {
            $isTrue = !empty($options[0]['is_correct']);
            $options = [['body' => 'صح', 'is_correct' => $isTrue], ['body' => 'خطأ', 'is_correct' => !$isTrue]];
        }
        if ($data['type'] === 'short_answer') {
            $options = array_map(static fn ($a) => ['body' => $a, 'is_correct' => 1], array_values(array_filter(array_map('trim', $accepted), static fn ($v) => $v !== '')));
        }
        $errors = self::validate($data, $options, $accepted);
        if ($errors) {
            return [null, $errors];
        }
        $options = array_values(array_filter($options, static fn ($o) => trim((string) ($o['body'] ?? '')) !== ''));

        // المجال يُستنتج من المهارة
        if (!empty($data['skill_id'])) {
            $data['domain_id'] = (int) DB::value('SELECT domain_id FROM skills WHERE id = ?', [(int) $data['skill_id']]);
        }
        $hash = self::hash($data, $options, $accepted);
        if (!$allowDuplicate && ($dup = self::duplicateOf($hash, (int) $id))) {
            return [null, ["السؤال مكرر — يطابق السؤال رقم #$dup في البنك."]];
        }

        $row = [
            'subject_id' => (int) $data['subject_id'], 'grade_id' => (int) $data['grade_id'],
            'domain_id' => $data['domain_id'] ?: null, 'skill_id' => $data['skill_id'] ?: null, 'indicator_id' => $data['indicator_id'] ?: null,
            'passage_id' => $data['passage_id'] ?: null, 'type' => $data['type'], 'stem' => trim((string) $data['stem']),
            'explanation' => trim((string) $data['explanation']) ?: null, 'common_mistakes' => trim((string) $data['common_mistakes']) ?: null,
            'difficulty' => (int) $data['difficulty'], 'cognitive_level' => $data['cognitive_level'], 'points' => round((float) $data['points'], 2),
            'manual_grading' => $data['type'] === 'short_answer' && !empty($data['manual_grading']) ? 1 : 0,
            'source' => trim((string) $data['source']) ?: null, 'usage_rights' => trim((string) $data['usage_rights']) ?: null,
            'content_hash' => $hash, 'updated_by' => Auth::id(),
        ];

        $id = DB::transaction(static function () use ($id, $row, $data, $options, $note) {
            if ($id) {
                $current = self::find($id);
                DB::insert('question_revisions', [
                    'question_id' => $id, 'version' => (int) $current['version'],
                    'snapshot' => json_encode(self::revisionPayload($current), JSON_UNESCAPED_UNICODE),
                    'changed_by' => Auth::id(), 'change_note' => $note,
                ]);
                // تعديل سؤال منشور ممن لا يملك صلاحية النشر يعيده للمراجعة
                $status = $data['status'];
                if ($status === 'published' && !self::canPublish()) {
                    $status = 'review';
                }
                DB::update('questions', $row + ['version' => (int) $current['version'] + 1, 'status' => $status], 'id = :id', ['id' => $id]);
                if ($status !== $current['status']) {
                    self::markReviewed($id, $status);
                }
            } else {
                $status = $data['status'] === 'published' && !self::canPublish() ? 'review' : $data['status'];
                $id = DB::insert('questions', $row + ['status' => $status, 'created_by' => Auth::id()]);
                if ($status === 'published') {
                    self::markReviewed($id, $status);
                }
            }
            self::saveOptions($id, $options);
            return $id;
        });
        return [$id, []];
    }

    /** الإبقاء على معرفات الخيارات قدر الإمكان */
    private static function saveOptions(int $questionId, array $options): void
    {
        $existing = self::options($questionId);
        foreach ($options as $i => $o) {
            $values = [
                'body' => trim((string) $o['body']), 'match_text' => isset($o['match_text']) && trim((string) $o['match_text']) !== '' ? trim((string) $o['match_text']) : null,
                'is_correct' => !empty($o['is_correct']) ? 1 : 0, 'correct_position' => $i + 1,
                'feedback' => isset($o['feedback']) && trim((string) $o['feedback']) !== '' ? mb_substr(trim((string) $o['feedback']), 0, 500) : null,
                'sort_order' => $i + 1,
            ];
            if (isset($existing[$i])) {
                DB::update('question_options', $values, 'id = :id', ['id' => $existing[$i]['id']]);
            } else {
                DB::insert('question_options', $values + ['question_id' => $questionId]);
            }
        }
        foreach (array_slice($existing, count($options)) as $extra) {
            DB::delete('question_options', 'id = ?', [$extra['id']]);
        }
    }

    private static function revisionPayload(array $q): array
    {
        $keep = ['type', 'stem', 'explanation', 'common_mistakes', 'difficulty', 'cognitive_level', 'points', 'subject_id', 'grade_id',
                 'domain_id', 'skill_id', 'indicator_id', 'passage_id', 'status', 'source', 'usage_rights', 'manual_grading'];
        return array_intersect_key($q, array_flip($keep)) + [
            'options' => array_map(static fn ($o) => array_intersect_key($o, array_flip(['body', 'match_text', 'is_correct', 'feedback'])), $q['options']),
        ];
    }

    public static function setStatus(int $id, string $status, ?string $note = null): bool
    {
        if (!isset(self::STATUSES[$status])) {
            return false;
        }
        if (in_array($status, ['published'], true) && !self::canPublish()) {
            return false;
        }
        DB::update('questions', ['status' => $status, 'review_note' => $note], 'id = :id', ['id' => $id]);
        self::markReviewed($id, $status);
        return true;
    }

    private static function markReviewed(int $id, string $status): void
    {
        if ($status === 'published') {
            DB::update('questions', ['reviewed_by' => Auth::id(), 'reviewed_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
        }
    }

    /** نسخ سؤال كمسودة جديدة */
    public static function duplicate(int $id): int
    {
        $q = self::find($id);
        $data = $q;
        $data['status'] = 'draft';
        $data['stem'] = $q['stem'];
        $accepted = $q['type'] === 'short_answer' ? array_column($q['options'], 'body') : [];
        [$newId] = self::save(null, $data, $q['options'], $accepted, true);
        // نسخ الوسائط (مرجع للملف نفسه)
        DB::run('INSERT INTO question_media (question_id, file_path, mime, size, alt_text, uploaded_by) SELECT ?, file_path, mime, size, alt_text, ? FROM question_media WHERE question_id = ?', [$newId, Auth::id(), $id]);
        // البصمة نفسها؛ نميّز النسخة حتى لا تُعد مكررة عند تعديلها
        DB::update('questions', ['content_hash' => hash('sha256', $q['content_hash'] . '-copy-' . $newId)], 'id = :id', ['id' => $newId]);
        return (int) $newId;
    }

    /** حذف: إن كان مستخدماً في اختبار يُؤرشف فقط */
    public static function delete(int $id): string
    {
        if (DB::value('SELECT 1 FROM test_questions WHERE question_id = ? LIMIT 1', [$id])) {
            DB::update('questions', ['status' => 'archived'], 'id = :id', ['id' => $id]);
            return 'archived';
        }
        $media = DB::column('SELECT file_path FROM question_media WHERE question_id = ?', [$id]);
        DB::delete('questions', 'id = ?', [$id]);
        foreach ($media as $path) {
            if (!DB::value('SELECT 1 FROM question_media WHERE file_path = ?', [$path])) {
                Uploads::delete($path);
            }
        }
        return 'deleted';
    }

    /* ======================= اللقطة المجمدة ======================= */

    /** لقطة كاملة للسؤال تُحفظ مع الاختبار (تشمل الإجابات — لا تُرسل للطالب) */
    public static function snapshot(int $id): ?array
    {
        $q = self::find($id);
        if (!$q) {
            return null;
        }
        $passage = $q['passage_id'] ? DB::first('SELECT id, title, body FROM passages WHERE id = ?', [$q['passage_id']]) : null;
        if ($passage) {
            $passage['media'] = array_map('intval', DB::column('SELECT id FROM question_media WHERE passage_id = ?', [$passage['id']]));
        }
        return [
            'id' => (int) $q['id'], 'version' => (int) $q['version'], 'type' => $q['type'], 'stem' => $q['stem'],
            'explanation' => $q['explanation'], 'common_mistakes' => $q['common_mistakes'], 'points' => (float) $q['points'],
            'difficulty' => (int) $q['difficulty'], 'cognitive_level' => $q['cognitive_level'], 'manual' => (bool) $q['manual_grading'],
            'subject_id' => (int) $q['subject_id'], 'domain_id' => $q['domain_id'] ? (int) $q['domain_id'] : null,
            'skill_id' => $q['skill_id'] ? (int) $q['skill_id'] : null, 'skill_name' => $q['skill_name'], 'domain_name' => $q['domain_name'],
            'passage' => $passage, 'media' => array_map(static fn ($m) => ['id' => (int) $m['id'], 'alt' => $m['alt_text']], $q['media']),
            'options' => array_map(static fn ($o) => [
                'id' => (int) $o['id'], 'body' => $o['body'], 'match_text' => $o['match_text'], 'is_correct' => (bool) $o['is_correct'],
                'position' => (int) $o['correct_position'], 'feedback' => $o['feedback'],
            ], $q['options']),
        ];
    }

    /* ======================= تصدير ======================= */

    public const EXPORT_HEADERS = ['نص السؤال', 'نوع السؤال', 'المادة', 'الصف', 'المجال', 'رمز المهارة', 'المهارة', 'مؤشر الأداء',
        'الصعوبة', 'المستوى المعرفي', 'الدرجة', 'الخيار 1', 'الخيار 2', 'الخيار 3', 'الخيار 4', 'الخيار 5', 'الخيار 6',
        'الإجابة الصحيحة', 'التفسير', 'الأخطاء الشائعة', 'المصدر', 'حقوق الاستخدام', 'الحالة'];

    public static function exportRows(array $filters): array
    {
        [$where, $params] = self::filterSql($filters);
        $rows = [self::EXPORT_HEADERS];
        $questions = DB::all(
            "SELECT q.*, s.name AS subject_name, g.name AS grade_name, d.name AS domain_name, sk.name AS skill_name, sk.code AS skill_code, li.description AS indicator
               FROM questions q JOIN subjects s ON s.id = q.subject_id JOIN grades g ON g.id = q.grade_id
          LEFT JOIN domains d ON d.id = q.domain_id LEFT JOIN skills sk ON sk.id = q.skill_id LEFT JOIN learning_indicators li ON li.id = q.indicator_id
              WHERE $where ORDER BY q.subject_id, q.id LIMIT 5000",
            $params
        );
        foreach ($questions as $q) {
            $opts = self::options((int) $q['id']);
            $cells = array_fill(0, 6, '');
            $correct = '';
            if ($q['type'] === 'short_answer') {
                $correct = implode(' | ', array_column($opts, 'body'));
            } elseif ($q['type'] === 'true_false') {
                foreach ($opts as $o) {
                    if ((int) $o['is_correct']) {
                        $correct = $o['body'];
                    }
                }
            } else {
                $marks = [];
                foreach (array_slice($opts, 0, 6) as $i => $o) {
                    $cells[$i] = $q['type'] === 'matching' ? $o['body'] . ' => ' . $o['match_text'] : $o['body'];
                    if ((int) $o['is_correct']) {
                        $marks[] = $i + 1;
                    }
                }
                $correct = in_array($q['type'], ['single', 'multiple'], true) ? implode(',', $marks) : '';
            }
            $rows[] = array_merge([
                $q['stem'], self::TYPES[$q['type']], $q['subject_name'], $q['grade_name'], $q['domain_name'] ?? '', $q['skill_code'] ?? '',
                $q['skill_name'] ?? '', $q['indicator'] ?? '', self::DIFFICULTIES[(int) $q['difficulty']], self::COGNITIVE[$q['cognitive_level']],
                (float) $q['points'],
            ], $cells, [$correct, $q['explanation'] ?? '', $q['common_mistakes'] ?? '', $q['source'] ?? '', $q['usage_rights'] ?? '', self::STATUSES[$q['status']][0]]);
        }
        return $rows;
    }
}
