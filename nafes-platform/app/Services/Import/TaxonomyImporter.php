<?php
declare(strict_types=1);

namespace App\Services\Import;

use App\Core\DB;

/** استيراد التصنيفات: الصف، المادة، المجال، المهارة، مؤشر الأداء */
final class TaxonomyImporter extends Importer
{
    public function title(): string
    {
        return 'استيراد المجالات والمهارات';
    }

    public function permission(): string
    {
        return 'taxonomy.manage';
    }

    public function kind(): string
    {
        return 'taxonomy';
    }

    public function fields(): array
    {
        return [
            'grade' => ['الصف', true, ['grade'], 'مثل: الثالث المتوسط'],
            'subject' => ['المادة', true, ['subject'], 'القراءة / الرياضيات / العلوم'],
            'domain' => ['المجال', true, ['domain'], ''],
            'skill_code' => ['رمز المهارة', false, ['الرمز', 'code'], ''],
            'skill' => ['المهارة', false, ['skill'], ''],
            'indicator' => ['مؤشر الأداء', false, ['المؤشر', 'indicator'], 'صف لكل مؤشر، مع تكرار المجال والمهارة'],
        ];
    }

    public function example(): array
    {
        return ['الثالث المتوسط', 'الرياضيات', 'الهندسة والقياس', 'ر9-12', 'يحسب المساحة الجانبية والكلية للمجسمات', 'يحسب المساحة الكلية للمكعب'];
    }

    public function instructions(): array
    {
        return ['صف لكل مؤشر أداء (أو لكل مهارة بدون مؤشر). العناصر الموجودة لا تُكرر، وتُضاف الناقصة فقط.'];
    }

    protected function check(array $row, array $options, array &$seen): array
    {
        $errors = [];
        $subjectId = (int) DB::value('SELECT id FROM subjects WHERE name = ? OR code = ?', [$row['subject'], mb_strtolower($row['subject'])], 0);
        $gradeId = (int) DB::value('SELECT id FROM grades WHERE name = ?', [$row['grade']], 0);
        if (!$subjectId) {
            $errors[] = 'المادة غير موجودة';
        }
        if (!$gradeId) {
            $errors[] = 'الصف غير موجود';
        }
        $key = implode('|', [$row['grade'], $row['subject'], $row['domain'], $row['skill'], $row['indicator']]);
        if (isset($seen['k'][$key])) {
            $errors[] = 'مكرر في الملف (السطر ' . $seen['k'][$key] . ')';
        }
        $seen['k'][$key] = $row['_line'];
        $exists = !$errors && DB::value(
            'SELECT 1 FROM domains d LEFT JOIN skills s ON s.domain_id = d.id AND s.name = ? LEFT JOIN learning_indicators i ON i.skill_id = s.id AND i.description = ?
              WHERE d.subject_id = ? AND d.grade_id = ? AND d.name = ? AND (? = \'\' OR s.id IS NOT NULL) AND (? = \'\' OR i.id IS NOT NULL)',
            [$row['skill'], $row['indicator'], $subjectId, $gradeId, $row['domain'], $row['skill'], $row['indicator']]
        );
        return ['action' => $errors ? 'reject' : ($exists ? 'update' : 'create'), 'errors' => $errors,
                'warnings' => $exists ? ['موجود مسبقاً — لا تغيير'] : [], 'data' => $row + ['subject_id' => $subjectId, 'grade_id' => $gradeId]];
    }

    protected function persist(array $checked, array $options): array
    {
        $d = $checked['data'];
        $domainId = (int) DB::value('SELECT id FROM domains WHERE subject_id = ? AND grade_id = ? AND name = ?', [$d['subject_id'], $d['grade_id'], $d['domain']], 0)
            ?: DB::insert('domains', ['subject_id' => $d['subject_id'], 'grade_id' => $d['grade_id'], 'name' => $d['domain'], 'sort_order' => 99]);
        if ($d['skill'] !== '') {
            $skillId = (int) DB::value('SELECT id FROM skills WHERE domain_id = ? AND name = ?', [$domainId, $d['skill']], 0)
                ?: DB::insert('skills', ['domain_id' => $domainId, 'code' => $d['skill_code'] ?: null, 'name' => $d['skill'], 'sort_order' => 99]);
            if ($d['indicator'] !== '') {
                DB::run('INSERT IGNORE INTO learning_indicators (skill_id, description, sort_order) VALUES (?, ?, 99)', [$skillId, $d['indicator']]);
            }
        }
        return ['action' => $checked['action'] === 'update' ? 'updated' : 'created'];
    }
}
