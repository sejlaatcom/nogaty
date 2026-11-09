<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use ZipArchive;

/**
 * قراءة وكتابة Excel (xlsx) وCSV.
 * تستخدم PhpSpreadsheet عند تثبيتها عبر Composer، وإلا قارئ/كاتب XLSX مدمج يكفي لملفات الاستيراد والتقارير.
 */
final class Spreadsheet
{
    public static function hasLibrary(): bool
    {
        return class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class);
    }

    /* ======================= القراءة ======================= */

    /** أسماء أوراق ملف Excel (CSV ورقة واحدة) */
    public static function sheetNames(string $path): array
    {
        if (!str_ends_with(strtolower($path), '.xlsx')) {
            return ['CSV'];
        }
        if (self::hasLibrary()) {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            return $reader->listWorksheetNames($path);
        }
        return array_keys(self::nativeSheets($path));
    }

    /** صفوف ورقة: مصفوفة مصفوفات نصية (الصف الأول عادةً رؤوس الأعمدة) */
    public static function read(string $path, ?string $sheet = null, int $maxRows = 5000): array
    {
        $rows = str_ends_with(strtolower($path), '.xlsx') ? self::readXlsx($path, $sheet, $maxRows) : self::readCsv($path, $maxRows);
        // إزالة الصفوف الفارغة وتوحيد الطول
        $rows = array_values(array_filter($rows, static fn ($r) => implode('', array_map('strval', $r)) !== ''));
        $width = $rows ? max(array_map('count', $rows)) : 0;
        return array_map(static fn ($r) => array_pad(array_map(static fn ($v) => trim((string) $v), $r), $width, ''), $rows);
    }

    private static function readCsv(string $path, int $maxRows): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1256'); // ملفات Excel العربية القديمة
        }
        $first = strtok($content, "\n") ?: '';
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : (substr_count($first, "\t") > substr_count($first, ',') ? "\t" : ',');
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false && count($rows) < $maxRows) {
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }

    private static function readXlsx(string $path, ?string $sheet, int $maxRows): array
    {
        if (self::hasLibrary()) {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            if ($sheet !== null) {
                $reader->setLoadSheetsOnly([$sheet]);
            }
            $book = $reader->load($path);
            $ws   = $sheet !== null && $book->sheetNameExists($sheet) ? $book->getSheetByName($sheet) : $book->getActiveSheet();
            $rows = [];
            foreach ($ws->toArray('', true, false, false) as $row) {
                $rows[] = $row;
                if (count($rows) >= $maxRows) {
                    break;
                }
            }
            return $rows;
        }

        $sheets = self::nativeSheets($path);
        $target = $sheet !== null && isset($sheets[$sheet]) ? $sheets[$sheet] : reset($sheets);
        if (!$target) {
            return [];
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('تعذر فتح ملف Excel.');
        }
        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $doc = simplexml_load_string($xml);
            foreach ($doc->si as $si) {
                $text = '';
                if (isset($si->t)) {
                    $text = (string) $si->t;
                } else {
                    foreach ($si->r as $run) {
                        $text .= (string) $run->t;
                    }
                }
                $shared[] = $text;
            }
        }
        $xml = $zip->getFromName($target);
        $zip->close();
        if ($xml === false) {
            return [];
        }
        $doc  = simplexml_load_string($xml);
        $rows = [];
        foreach ($doc->sheetData->row as $row) {
            $line = [];
            foreach ($row->c as $cell) {
                $ref   = (string) $cell['r'];
                $col   = self::columnIndex(preg_replace('/\d+/', '', $ref) ?: 'A');
                $type  = (string) $cell['t'];
                $value = match ($type) {
                    's'         => $shared[(int) $cell->v] ?? '',
                    'inlineStr' => (string) ($cell->is->t ?? ''),
                    'b'         => ((string) $cell->v) === '1' ? 'TRUE' : 'FALSE',
                    default     => (string) $cell->v,
                };
                // أعداد صحيحة مخزنة كعشرية (مثل الرقم المدرسي)
                if ($type === '' && preg_match('/^\d+\.0+$/', $value)) {
                    $value = (string) (int) $value;
                }
                $line[$col] = $value;
            }
            if ($line) {
                $max = max(array_keys($line));
                $full = array_fill(0, $max + 1, '');
                foreach ($line as $i => $v) {
                    $full[$i] = $v;
                }
                $rows[] = $full;
            }
            if (count($rows) >= $maxRows) {
                break;
            }
        }
        return $rows;
    }

    /** خريطة اسم الورقة => مسار ملفها داخل xlsx */
    private static function nativeSheets(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('تعذر فتح ملف Excel — تأكد أنه بصيغة .xlsx');
        }
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels     = $zip->getFromName('xl/_rels/workbook.xml.rels');
        $zip->close();
        if ($workbook === false || $rels === false) {
            throw new RuntimeException('ملف Excel غير صالح.');
        }
        $targets = [];
        $relDoc = simplexml_load_string($rels);
        foreach ($relDoc->Relationship as $rel) {
            $targets[(string) $rel['Id']] = 'xl/' . ltrim(str_replace('/xl/', '', (string) $rel['Target']), '/');
        }
        $out = [];
        $wb = simplexml_load_string($workbook);
        foreach ($wb->sheets->sheet as $sheet) {
            $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $out[(string) $sheet['name']] = $targets[$rid] ?? '';
        }
        return $out;
    }

    private static function columnIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n - 1;
    }

    private static function columnLetter(int $index): string
    {
        $s = '';
        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $s = chr(65 + ($index - 1) % 26) . $s;
        }
        return $s;
    }

    /* ======================= الكتابة ======================= */

    public static function downloadCsv(string $filename, array $rows): never
    {
        download_headers($filename, 'text/csv; charset=UTF-8');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($out, array_map(static fn ($v) => self::safeCell($v), $row), ',', '"', '\\');
        }
        fclose($out);
        exit;
    }

    /**
     * تنزيل ملف Excel: $sheets = ['اسم الورقة' => [صف الرؤوس، صفوف...]]
     * $title سطر عنوان اختياري أعلى كل ورقة.
     */
    public static function downloadXlsx(string $filename, array $sheets, ?string $title = null): never
    {
        $path = self::writeXlsx($sheets, $title);
        download_headers($filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        @unlink($path);
        exit;
    }

    public static function writeXlsx(array $sheets, ?string $title = null): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        if (self::hasLibrary()) {
            $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $book->removeSheetByIndex(0);
            foreach ($sheets as $name => $rows) {
                $ws = $book->createSheet();
                $ws->setTitle(self::sheetTitle((string) $name));
                $ws->setRightToLeft(true);
                $r = 1;
                if ($title !== null) {
                    $ws->setCellValue('A1', $title);
                    $ws->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                    $r = 3;
                }
                $headerRow = $r;
                foreach ($rows as $row) {
                    $c = 1;
                    foreach ($row as $value) {
                        $ws->setCellValue([$c++, $r], is_string($value) ? self::safeCell($value) : $value);
                    }
                    $r++;
                }
                $cols = $rows ? count($rows[0]) : 1;
                $last = self::columnLetter($cols - 1);
                $ws->getStyle("A{$headerRow}:{$last}{$headerRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0B5394']],
                ]);
                for ($i = 0; $i < $cols; $i++) {
                    $ws->getColumnDimension(self::columnLetter($i))->setAutoSize(true);
                }
                $ws->freezePane('A' . ($headerRow + 1));
            }
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);
            return $path;
        }
        self::nativeWrite($path, $sheets, $title);
        return $path;
    }

    /** منع حقن الصيغ في Excel (=, +, -, @) */
    private static function safeCell(mixed $value): string
    {
        $value = (string) $value;
        return preg_match('/^[=+\-@\t\r]/', $value) && !is_numeric($value) ? "'" . $value : $value;
    }

    private static function sheetTitle(string $name): string
    {
        $name = preg_replace('/[\[\]\*\?\/\\\\:]/u', ' ', $name) ?? 'Sheet';
        return mb_substr(trim($name) ?: 'Sheet', 0, 31);
    }

    private static function nativeWrite(string $path, array $sheets, ?string $title): void
    {
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $x = static fn ($v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $sheetXml = [];
        $i = 0;
        foreach ($sheets as $name => $rows) {
            $i++;
            $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                 . '<sheetViews><sheetView workbookViewId="0" rightToLeft="1"/></sheetViews>';
            $cols = $rows ? count($rows[0]) : 1;
            $xml .= '<cols>';
            for ($c = 0; $c < $cols; $c++) {
                $xml .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="22" customWidth="1"/>';
            }
            $xml .= '</cols><sheetData>';
            $r = 1;
            if ($title !== null) {
                $xml .= '<row r="1"><c r="A1" t="inlineStr" s="1"><is><t>' . $x($title) . '</t></is></c></row>';
                $r = 3;
            }
            foreach (array_values($rows) as $index => $row) {
                $xml .= '<row r="' . $r . '">';
                foreach (array_values($row) as $c => $value) {
                    $ref = self::columnLetter($c) . $r;
                    $style = $index === 0 ? ' s="2"' : '';
                    if (is_int($value) || is_float($value)) {
                        $xml .= '<c r="' . $ref . '"' . $style . '><v>' . $value . '</v></c>';
                    } else {
                        $xml .= '<c r="' . $ref . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . $x(self::safeCell($value)) . '</t></is></c>';
                    }
                }
                $xml .= '</row>';
                $r++;
            }
            $sheetXml[$i] = [$name, $xml . '</sheetData></worksheet>'];
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . implode('', array_map(static fn ($n) => '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>', array_keys($sheetXml)))
            . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'
            . implode('', array_map(static fn ($n, $s) => '<sheet name="' . $x(self::sheetTitle((string) $s[0])) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>', array_keys($sheetXml), $sheetXml))
            . '</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . implode('', array_map(static fn ($n) => '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>', array_keys($sheetXml)))
            . '<Relationship Id="rId999" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="3"><font><sz val="11"/><name val="Arial"/></font><font><b/><sz val="13"/><name val="Arial"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Arial"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0B5394"/></patternFill></fill></fills>'
            . '<borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="3"><xf/><xf fontId="1" applyFont="1"/><xf fontId="2" fillId="2" applyFont="1" applyFill="1"/></cellXfs></styleSheet>');
        foreach ($sheetXml as $n => [, $xml]) {
            $zip->addFromString('xl/worksheets/sheet' . $n . '.xml', $xml);
        }
        $zip->close();
    }
}
