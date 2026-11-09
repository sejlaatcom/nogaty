<?php
declare(strict_types=1);

namespace App\Services\Import;

/**
 * أساس معالج الاستيراد: تعريف الحقول، المطابقة التلقائية للأعمدة،
 * التحقق (معاينة بدون حفظ)، ثم الاستيراد الفعلي للسجلات الصحيحة فقط.
 */
abstract class Importer
{
    /** [key => [label, required, [aliases], help]] */
    abstract public function fields(): array;

    abstract public function title(): string;

    abstract public function permission(): string;

    /** صف مثال لقالب الاستيراد */
    abstract public function example(): array;

    /**
     * التحقق من صف واحد — يُعيد ['action' => create|update|reject, 'errors' => [], 'warnings' => [], 'data' => [...]]
     * $seen لاكتشاف التكرار داخل الملف نفسه.
     */
    abstract protected function check(array $row, array $options, array &$seen): array;

    /** حفظ صف صحيح — يُعيد ['action' => created|updated, 'credentials' => ?array] */
    abstract protected function persist(array $checked, array $options): array;

    /** خيارات إضافية لصفحة المطابقة: [key => [label, type, choices|default]] */
    public function options(): array
    {
        return [];
    }

    public function instructions(): array
    {
        return [];
    }

    /** مطابقة تلقائية بين رؤوس الملف والحقول */
    public function autoMap(array $headers): array
    {
        $norm = static fn ($s) => normalize_ar(str_replace(['_', '-', '*'], ' ', (string) $s));
        $map = [];
        foreach ($this->fields() as $key => [$label, , $aliases]) {
            $map[$key] = null;
            $candidates = array_map($norm, array_merge([$label, $key], $aliases));
            foreach ($headers as $index => $header) {
                if (in_array($norm($header), $candidates, true)) {
                    $map[$key] = $index;
                    break;
                }
            }
        }
        return $map;
    }

    /** تحويل صفوف الملف إلى سجلات بأسماء الحقول حسب المطابقة */
    public function mapRows(array $rows, array $mapping, bool $hasHeader = true): array
    {
        if ($hasHeader) {
            array_shift($rows);
        }
        $out = [];
        foreach ($rows as $i => $row) {
            $item = ['_line' => $i + ($hasHeader ? 2 : 1)];
            foreach ($mapping as $key => $col) {
                $item[$key] = ($col !== null && $col !== '' && isset($row[(int) $col])) ? trim((string) $row[(int) $col]) : '';
            }
            $out[] = $item;
        }
        return $out;
    }

    /** المعاينة: التحقق من كل السجلات بدون حفظ */
    public function validate(array $records, array $options): array
    {
        $seen = [];
        $results = [];
        foreach ($records as $record) {
            $errors = [];
            foreach ($this->fields() as $key => [$label, $required]) {
                if ($required && ($record[$key] ?? '') === '') {
                    $errors[] = "«{$label}» مطلوب";
                }
            }
            $checked = $errors ? ['action' => 'reject', 'errors' => $errors, 'warnings' => [], 'data' => []] : $this->check($record, $options, $seen);
            $checked['line']   = $record['_line'];
            $checked['record'] = $record;
            $results[] = $checked;
        }
        return $results;
    }

    /** الاستيراد الفعلي للسجلات الصحيحة */
    public function import(array $validated, array $options): array
    {
        $report = ['created' => 0, 'updated' => 0, 'rejected' => 0, 'rejected_rows' => [], 'credentials' => []];
        foreach ($validated as $row) {
            if ($row['action'] === 'reject') {
                $report['rejected']++;
                $report['rejected_rows'][] = ['line' => $row['line'], 'record' => $row['record'], 'errors' => $row['errors']];
                continue;
            }
            try {
                $result = $this->persist($row, $options);
                $report[$result['action']]++;
                if (!empty($result['credentials'])) {
                    $report['credentials'][] = $result['credentials'];
                }
            } catch (\Throwable $e) {
                $report['rejected']++;
                $report['rejected_rows'][] = ['line' => $row['line'], 'record' => $row['record'], 'errors' => ['تعذر الحفظ: ' . $e->getMessage()]];
            }
        }
        return $report;
    }

    /** نوع الدفعة في import_batches */
    public function kind(): string
    {
        return 'students';
    }

    /** حفظ سجل دفعة الاستيراد — يُعيد معرفها */
    public function saveBatch(array $report, string $filename, int $total): int
    {
        return \App\Core\DB::insert('import_batches', [
            'kind' => $this->kind(), 'user_id' => \App\Core\Auth::id(), 'filename' => mb_substr($filename, 0, 200),
            'total' => $total, 'created' => $report['created'], 'updated' => $report['updated'], 'rejected' => $report['rejected'],
            'report' => json_encode(['rejected_rows' => array_slice($report['rejected_rows'], 0, 2000)], JSON_UNESCAPED_UNICODE),
        ]);
    }

    public static function batch(int $id): ?array
    {
        $row = \App\Core\DB::first('SELECT b.*, u.full_name AS user_name FROM import_batches b LEFT JOIN users u ON u.id = b.user_id WHERE b.id = ?', [$id]);
        if ($row) {
            $row['report'] = json_decode((string) $row['report'], true) ?: [];
        }
        return $row;
    }

    /** صفوف قالب الاستيراد (رؤوس + مثال) */
    public function templateRows(): array
    {
        return [array_map(static fn ($f) => $f[0], $this->fields()), $this->example()];
    }
}
