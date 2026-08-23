<?php
declare(strict_types=1);

/** قراءة وكتابة ملفات CSV بترميز UTF-8 (متوافق مع إكسل العربي) */
final class Csv
{
    /** إرسال ملف CSV للمتصفح */
    public static function download(string $filename, array $rows): never
    {
        download_headers($filename, 'text/csv; charset=UTF-8');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM حتى يفتح إكسل الملف بالعربية
        foreach ($rows as $row) {
            fputcsv($out, array_map(static fn ($v) => (string) $v, $row), ',', '"', '\\');
        }
        fclose($out);
        exit;
    }

    /** قراءة ملف CSV إلى مصفوفة صفوف */
    public static function read(string $path, int $maxRows = 5000): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $first = fgets($handle);
        if ($first === false) {
            fclose($handle);
            return [];
        }
        $first     = preg_replace('/^\xEF\xBB\xBF/', '', $first) ?? $first;
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';

        rewind($handle);
        $rows  = [];
        $index = 0;
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if ($index === 0) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($row[0] ?? '')) ?? '';
            }
            $clean = array_map(static fn ($v) => trim((string) $v), $row);
            if (implode('', $clean) !== '') {
                $rows[] = $clean;
            }
            if (++$index >= $maxRows) {
                break;
            }
        }
        fclose($handle);

        return $rows;
    }

    /** تحويل صفوف إلى مصفوفات مفهرسة بأسماء الأعمدة (الصف الأول رؤوس) */
    public static function toAssoc(array $rows): array
    {
        if (!$rows) {
            return [];
        }
        $headers = array_map(static fn ($h) => trim((string) $h), array_shift($rows));
        $out = [];
        foreach ($rows as $row) {
            $item = [];
            foreach ($headers as $i => $header) {
                $item[$header] = $row[$i] ?? '';
            }
            $out[] = $item;
        }
        return $out;
    }
}
