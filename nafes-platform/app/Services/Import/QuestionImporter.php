<?php
declare(strict_types=1);

namespace App\Services\Import;

use App\Core\Auth;
use App\Core\DB;
use App\Services\QuestionBank;
use App\Services\Settings;

/** استيراد الأسئلة وخياراتها مع إنشاء التصنيفات الناقصة ومنع التكرار */
final class QuestionImporter extends Importer
{
    private const TYPE_ALIASES = [
        'single' => ['اختيار من متعدد', 'اختيار', 'mcq', 'single'],
        'true_false' => ['صح أو خطأ', 'صح او خطأ', 'صح وخطأ', 'tf', 'true_false', 'true/false'],
        'multiple' => ['اختيار متعدد الإجابات', 'متعدد الإجابات', 'multiple'],
        'matching' => ['توصيل', 'matching'],
        'ordering' => ['ترتيب', 'ordering'],
        'short_answer' => ['إجابة قصيرة', 'اجابة قصيرة', 'قصيرة', 'short', 'short_answer'],
    ];

    public function title(): string
    {
        return 'استيراد الأسئلة';
    }

    public function permission(): string
    {
        return 'questions.import';
    }

    public function kind(): string
    {
        return 'questions';
    }

    public function fields(): array
    {
        $f = [
            'stem' => ['نص السؤال', true, ['السؤال', 'question', 'stem'], ''],
            'type' => ['نوع السؤال', false, ['النوع', 'type'], 'اختيار من متعدد (افتراضي) / صح أو خطأ / اختيار متعدد الإجابات / توصيل / ترتيب / إجابة قصيرة'],
            'subject' => ['المادة', true, ['subject'], 'القراءة / الرياضيات / العلوم'],
            'grade' => ['الصف', true, ['grade'], 'مثل: الثالث المتوسط'],
            'domain' => ['المجال', false, ['domain'], ''],
            'skill_code' => ['رمز المهارة', false, ['skill code', 'الرمز'], ''],
            'skill' => ['المهارة', false, ['skill'], ''],
            'indicator' => ['مؤشر الأداء', false, ['المؤشر', 'indicator'], ''],
            'difficulty' => ['الصعوبة', false, ['مستوى الصعوبة', 'difficulty'], 'سهل / متوسط / صعب'],
            'cognitive' => ['المستوى المعرفي', false, ['cognitive'], 'معرفة / تطبيق / استدلال'],
            'points' => ['الدرجة', false, ['points'], 'افتراضياً 1'],
        ];
        for ($i = 1; $i <= 6; $i++) {
            $f['opt' . $i] = ['الخيار ' . $i, false, ['option ' . $i, 'خيار ' . $i, 'البديل ' . $i], $i === 1 ? 'للتوصيل: العنصر => المقابل' : ''];
        }
        return $f + [
            'correct' => ['الإجابة الصحيحة', false, ['الإجابة', 'answer', 'correct'], 'رقم الخيار (1–6)، أو أرقام مفصولة بفاصلة للمتعدد، أو صح/خطأ، أو الإجابات المقبولة مفصولة بـ | للقصيرة'],
            'explanation' => ['التفسير', false, ['تفسير الإجابة', 'explanation'], ''],
            'common_mistakes' => ['الأخطاء الشائعة', false, ['common mistakes'], ''],
            'source' => ['المصدر', false, ['مصدر السؤال', 'source'], ''],
            'usage_rights' => ['حقوق الاستخدام', false, ['الحقوق', 'rights'], ''],
        ];
    }

    public function example(): array
    {
        return ['ما قيمة س في المعادلة 2س + 3 = 11؟', 'اختيار من متعدد', 'الرياضيات', 'الثالث المتوسط', 'الجبر والدوال', 'ر9-4',
            'يحل المعادلات والمتباينات الخطية', '', 'متوسط', 'تطبيق', '1', '4', '7', '8', '3', '', '', '1',
            'نطرح 3 من الطرفين ثم نقسم على 2', 'الخلط بين الطرح والقسمة', 'من إعداد معلم المادة', 'داخلي للمدرسة'];
    }

    public function options(): array
    {
        $statuses = ['draft' => 'مسودة', 'review' => 'بانتظار المراجعة'];
        if (QuestionBank::canPublish()) {
            $statuses['published'] = 'منشور مباشرة';
        }
        return [
            'status' => ['حالة الأسئلة المستوردة', 'select', $statuses],
            'create_taxonomy' => ['إنشاء المجالات والمهارات غير الموجودة', 'bool', Settings::get('import_create_taxonomy')],
        ];
    }

    public function instructions(): array
    {
        return [
            'كل صف سؤال واحد. الإلزامي: نص السؤال والمادة والصف.',
            'الإجابة الصحيحة: رقم الخيار للاختيار من متعدد، وأرقام مفصولة بفاصلة لمتعدد الإجابات (1,3)، وصح/خطأ، والإجابات المقبولة مفصولة بـ | للإجابة القصيرة.',
            'التوصيل: اكتب كل خيار بصيغة «العنصر => المقابل». الترتيب: اكتب الخيارات بترتيبها الصحيح.',
            'الأسئلة المطابقة لأسئلة موجودة في البنك أو مكررة في الملف لا تُستورد.',
        ];
    }

    private function typeOf(string $value): ?string
    {
        if ($value === '') {
            return 'single';
        }
        foreach (self::TYPE_ALIASES as $key => $aliases) {
            if (in_array(mb_strtolower(trim($value)), $aliases, true)) {
                return $key;
            }
        }
        return null;
    }

    protected function check(array $row, array $options, array &$seen): array
    {
        $errors = [];
        $warnings = [];
        $type = $this->typeOf($row['type']);
        if (!$type) {
            return ['action' => 'reject', 'errors' => ['نوع السؤال غير معروف: ' . $row['type']], 'warnings' => [], 'data' => []];
        }
        $subjectId = (int) DB::value('SELECT id FROM subjects WHERE name = ? OR code = ?', [$row['subject'], mb_strtolower($row['subject'])], 0);
        $gradeId = (int) DB::value('SELECT id FROM grades WHERE name = ?', [$row['grade']], 0);
        if (!$subjectId) {
            $errors[] = 'المادة غير موجودة: ' . $row['subject'];
        }
        if (!$gradeId) {
            $errors[] = 'الصف غير موجود: ' . $row['grade'];
        }
        $difficulty = ['' => 2, 'سهل' => 1, 'متوسط' => 2, 'صعب' => 3, '1' => 1, '2' => 2, '3' => 3][$row['difficulty']] ?? null;
        if ($difficulty === null) {
            $errors[] = 'الصعوبة غير معروفة';
            $difficulty = 2;
        }
        $cognitive = ['' => 'applying', 'معرفة' => 'knowing', 'تطبيق' => 'applying', 'استدلال' => 'reasoning'][$row['cognitive']] ?? null;
        if ($cognitive === null) {
            $errors[] = 'المستوى المعرفي غير معروف';
            $cognitive = 'applying';
        }
        $points = $row['points'] === '' ? 1.0 : (float) str_replace(',', '.', $row['points']);

        // الخيارات
        $opts = [];
        for ($i = 1; $i <= 6; $i++) {
            if ($row['opt' . $i] !== '') {
                $opts[] = $row['opt' . $i];
            }
        }
        $correct = trim($row['correct']);
        $optionRows = [];
        $accepted = [];
        switch ($type) {
            case 'single':
            case 'multiple':
                $marks = array_map('intval', preg_split('/[،,\s]+/u', $correct) ?: []);
                foreach ($opts as $i => $body) {
                    $optionRows[] = ['body' => $body, 'is_correct' => in_array($i + 1, $marks, true) || ($type === 'single' && $correct === $body)];
                }
                break;
            case 'true_false':
                if (!in_array($correct, ['صح', 'خطأ', 'صحيح', 'خاطئ', '1', '0'], true)) {
                    $errors[] = 'الإجابة الصحيحة لسؤال صح/خطأ يجب أن تكون «صح» أو «خطأ»';
                }
                $optionRows = [['body' => 'صح', 'is_correct' => in_array($correct, ['صح', 'صحيح', '1'], true)]];
                break;
            case 'matching':
                foreach ($opts as $body) {
                    [$left, $right] = array_pad(array_map('trim', preg_split('/=>|←|->/u', $body, 2) ?: []), 2, '');
                    if ($right === '') {
                        $errors[] = 'خيار التوصيل يجب أن يكون بصيغة «العنصر => المقابل»';
                    }
                    $optionRows[] = ['body' => $left, 'match_text' => $right];
                }
                break;
            case 'ordering':
                $optionRows = array_map(static fn ($b) => ['body' => $b], $opts);
                break;
            case 'short_answer':
                $accepted = array_values(array_filter(array_map('trim', explode('|', $correct))));
                break;
        }

        $data = [
            'type' => $type, 'subject_id' => $subjectId, 'grade_id' => $gradeId, 'stem' => $row['stem'],
            'explanation' => $row['explanation'], 'common_mistakes' => $row['common_mistakes'], 'difficulty' => $difficulty,
            'cognitive_level' => $cognitive, 'points' => $points, 'source' => $row['source'], 'usage_rights' => $row['usage_rights'],
            'status' => $options['status'] ?? 'draft',
        ];
        if (!$errors) {
            $validation = QuestionBank::validate($data, $type === 'true_false'
                ? [['body' => 'صح', 'is_correct' => $optionRows[0]['is_correct']], ['body' => 'خطأ', 'is_correct' => !$optionRows[0]['is_correct']]]
                : $optionRows, $accepted);
            $errors = array_merge($errors, $validation);
        }

        // التصنيف
        $taxonomy = ['domain_id' => null, 'skill_id' => null, 'indicator_id' => null, 'create' => []];
        if (!$errors && ($row['domain'] !== '' || $row['skill'] !== '' || $row['skill_code'] !== '')) {
            $taxonomy = $this->resolveTaxonomy($subjectId, $gradeId, $row, !empty($options['create_taxonomy']));
            if ($taxonomy['error']) {
                $errors[] = $taxonomy['error'];
            } elseif ($taxonomy['create']) {
                $warnings[] = 'سيُنشأ: ' . implode('، ', $taxonomy['create']);
            }
        }

        // التكرار
        $duplicate = false;
        if (!$errors) {
            $hashOptions = $type === 'true_false' ? [['body' => 'صح', 'is_correct' => $optionRows[0]['is_correct']], ['body' => 'خطأ', 'is_correct' => !$optionRows[0]['is_correct']]]
                : ($type === 'short_answer' ? array_map(static fn ($a) => ['body' => $a], $accepted) : $optionRows);
            $hash = QuestionBank::hash($data, $hashOptions, $accepted);
            if ($dup = QuestionBank::duplicateOf($hash)) {
                $errors[] = "مكرر: يطابق السؤال #$dup في البنك";
                $duplicate = true;
            } elseif (isset($seen['h'][$hash])) {
                $errors[] = 'مكرر في الملف (السطر ' . $seen['h'][$hash] . ')';
                $duplicate = true;
            } else {
                $seen['h'][$hash] = $row['_line'];
            }
        }

        return [
            'action' => $errors ? 'reject' : 'create', 'errors' => $errors, 'warnings' => $warnings, 'duplicate' => $duplicate,
            'data' => ['question' => $data, 'options' => $optionRows, 'accepted' => $accepted, 'taxonomy' => $taxonomy, 'row' => $row],
        ];
    }

    /** إيجاد المجال والمهارة والمؤشر (أو تحديد ما سيُنشأ) */
    private function resolveTaxonomy(int $subjectId, int $gradeId, array $row, bool $create, bool $commit = false): array
    {
        $out = ['domain_id' => null, 'skill_id' => null, 'indicator_id' => null, 'create' => [], 'error' => null];
        $skill = null;
        if ($row['skill_code'] !== '' || $row['skill'] !== '') {
            $skill = DB::first(
                'SELECT s.id, s.domain_id FROM skills s JOIN domains d ON d.id = s.domain_id
                  WHERE d.subject_id = ? AND (d.grade_id = ? OR d.grade_id IS NULL) AND ((? <> \'\' AND s.code = ?) OR (? <> \'\' AND s.name = ?)) LIMIT 1',
                [$subjectId, $gradeId, $row['skill_code'], $row['skill_code'], $row['skill'], $row['skill']]
            );
        }
        if ($skill) {
            $out['skill_id'] = (int) $skill['id'];
            $out['domain_id'] = (int) $skill['domain_id'];
        } else {
            if ($row['domain'] !== '') {
                $out['domain_id'] = (int) DB::value('SELECT id FROM domains WHERE subject_id = ? AND (grade_id = ? OR grade_id IS NULL) AND name = ?', [$subjectId, $gradeId, $row['domain']], 0) ?: null;
            }
            if (!$out['domain_id'] && $row['domain'] !== '') {
                if (!$create) {
                    $out['error'] = 'المجال غير موجود: ' . $row['domain'];
                    return $out;
                }
                $out['create'][] = 'المجال «' . $row['domain'] . '»';
                if ($commit) {
                    $out['domain_id'] = DB::insert('domains', ['subject_id' => $subjectId, 'grade_id' => $gradeId, 'name' => $row['domain'], 'sort_order' => 99]);
                }
            }
            if ($row['skill'] !== '') {
                if (!$create) {
                    $out['error'] = 'المهارة غير موجودة: ' . $row['skill'];
                    return $out;
                }
                if (!$out['domain_id'] && !$commit && $row['domain'] === '') {
                    $out['error'] = 'حدد المجال لإنشاء المهارة الجديدة';
                    return $out;
                }
                $out['create'][] = 'المهارة «' . $row['skill'] . '»';
                if ($commit) {
                    $out['skill_id'] = (int) DB::value('SELECT id FROM skills WHERE domain_id = ? AND name = ?', [$out['domain_id'], $row['skill']], 0)
                        ?: DB::insert('skills', ['domain_id' => $out['domain_id'], 'code' => $row['skill_code'] ?: null, 'name' => $row['skill'], 'sort_order' => 99]);
                }
            } elseif ($row['skill_code'] !== '') {
                $out['error'] = 'رمز المهارة غير موجود: ' . $row['skill_code'];
                return $out;
            }
        }
        if ($row['indicator'] !== '' && ($out['skill_id'] || !$commit)) {
            $ind = $out['skill_id'] ? DB::value('SELECT id FROM learning_indicators WHERE skill_id = ? AND description = ?', [$out['skill_id'], $row['indicator']]) : null;
            if ($ind) {
                $out['indicator_id'] = (int) $ind;
            } elseif ($create) {
                $out['create'][] = 'المؤشر «' . $row['indicator'] . '»';
                if ($commit && $out['skill_id']) {
                    $out['indicator_id'] = DB::insert('learning_indicators', ['skill_id' => $out['skill_id'], 'description' => $row['indicator'], 'sort_order' => 99]);
                }
            }
        }
        return $out;
    }

    protected function persist(array $checked, array $options): array
    {
        $d = $checked['data'];
        $q = $d['question'];
        $tax = $this->resolveTaxonomy($q['subject_id'], $q['grade_id'], $d['row'], !empty($options['create_taxonomy']), true);
        $q['domain_id'] = $tax['domain_id'];
        $q['skill_id'] = $tax['skill_id'];
        $q['indicator_id'] = $tax['indicator_id'];
        [$id, $errors] = QuestionBank::save(null, $q, $d['options'], $d['accepted']);
        if ($errors) {
            throw new \RuntimeException(implode(' ', $errors));
        }
        return ['action' => 'created'];
    }

    public function import(array $validated, array $options): array
    {
        $report = parent::import($validated, $options);
        $report['duplicates'] = count(array_filter($validated, static fn ($r) => !empty($r['duplicate'])));
        return $report;
    }

    public function saveBatch(array $report, string $filename, int $total): int
    {
        return DB::insert('question_import_batches', [
            'user_id' => Auth::id(), 'filename' => mb_substr($filename, 0, 200), 'total' => $total,
            'imported' => $report['created'], 'duplicates' => $report['duplicates'] ?? 0, 'rejected' => $report['rejected'],
            'report' => json_encode(['rejected_rows' => array_slice($report['rejected_rows'], 0, 2000)], JSON_UNESCAPED_UNICODE),
        ]);
    }
}
