<?php
declare(strict_types=1);

/**
 * كاتب ملفات إكسل (XLSX) بسيط ومكتفٍ ذاتياً — بدون مكتبات خارجية.
 * يدعم: اتجاه من اليمين لليسار، دمج الخلايا، عرض الأعمدة، وأنماط جاهزة.
 */
final class Xlsx
{
    /** أنماط جاهزة */
    public const STYLE_DEFAULT = 0;
    public const STYLE_TITLE   = 1;
    public const STYLE_HEADER  = 2;
    public const STYLE_BODY    = 3;
    public const STYLE_LABEL   = 4;
    public const STYLE_SUBJECT = 5;
    public const STYLE_NOTE    = 6;

    private array $rows   = [];
    private array $merges = [];
    private array $cols   = [];
    private string $sheetName;

    public function __construct(string $sheetName = 'الخطة')
    {
        $this->sheetName = $this->sanitizeSheetName($sheetName);
    }

    /** عرض الأعمدة بالترتيب (بالحروف) */
    public function setColumnWidths(array $widths): void
    {
        $this->cols = $widths;
    }

    /**
     * إضافة صف. كل خلية إما نص/رقم أو مصفوفة ['v' => القيمة, 's' => النمط]
     * تُعيد رقم الصف (يبدأ من 1).
     */
    public function addRow(array $cells, int $style = self::STYLE_BODY, ?float $height = null): int
    {
        $this->rows[] = ['cells' => $cells, 'style' => $style, 'height' => $height];
        return count($this->rows);
    }

    public function addBlankRow(?float $height = 8.0): int
    {
        return $this->addRow([], self::STYLE_DEFAULT, $height);
    }

    /** دمج خلايا: أرقام الصفوف والأعمدة تبدأ من 1 */
    public function merge(int $row1, int $col1, int $row2, int $col2): void
    {
        $this->merges[] = self::cellRef($row1, $col1) . ':' . self::cellRef($row2, $col2);
    }

    /** حرف العمود من رقمه (1 => A) */
    public static function columnLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $mod    = ($index - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $index  = intdiv($index - $mod - 1, 26);
        }
        return $letter;
    }

    public static function cellRef(int $row, int $col): string
    {
        return self::columnLetter($col) . $row;
    }

    /** حفظ الملف على القرص */
    public function save(string $path): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet());
        $zip->close();

        return true;
    }

    /** إرسال الملف للمتصفح مباشرة */
    public function download(string $filename): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($tmp === false || !$this->save($tmp)) {
            http_response_code(500);
            exit('تعذر إنشاء ملف الإكسل.');
        }
        download_headers($filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Length: ' . (string) filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    /* ---------------- أجزاء الملف ---------------- */

    private function sheet(): string
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>';
        $xml .= '<sheetViews><sheetView rightToLeft="1" tabSelected="1" workbookViewId="0"/></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="18"/>';

        if ($this->cols) {
            $xml .= '<cols>';
            foreach (array_values($this->cols) as $i => $width) {
                $xml .= sprintf('<col min="%1$d" max="%1$d" width="%2$s" customWidth="1"/>', $i + 1, number_format((float) $width, 2, '.', ''));
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        foreach ($this->rows as $rowIndex => $row) {
            $r = $rowIndex + 1;
            $xml .= '<row r="' . $r . '"';
            if ($row['height'] !== null) {
                $xml .= ' ht="' . number_format($row['height'], 2, '.', '') . '" customHeight="1"';
            }
            $xml .= '>';

            foreach (array_values($row['cells']) as $colIndex => $cell) {
                $style = $row['style'];
                $value = $cell;
                if (is_array($cell)) {
                    $value = $cell['v'] ?? '';
                    $style = (int) ($cell['s'] ?? $style);
                }
                $ref   = self::cellRef($r, $colIndex + 1);
                $value = (string) $value;

                if ($value === '') {
                    $xml .= sprintf('<c r="%s" s="%d"/>', $ref, $style);
                } elseif (is_numeric($value) && !preg_match('/^0\d/', $value) && abs((float) $value) < 1e15) {
                    $xml .= sprintf('<c r="%s" s="%d"><v>%s</v></c>', $ref, $style, $value);
                } else {
                    $xml .= sprintf(
                        '<c r="%s" s="%d" t="inlineStr"><is><t xml:space="preserve">%s</t></is></c>',
                        $ref,
                        $style,
                        self::escape($value)
                    );
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';

        if ($this->merges) {
            $xml .= '<mergeCells count="' . count($this->merges) . '">';
            foreach ($this->merges as $ref) {
                $xml .= '<mergeCell ref="' . $ref . '"/>';
            }
            $xml .= '</mergeCells>';
        }

        $xml .= '<pageMargins left="0.25" right="0.25" top="0.4" bottom="0.4" header="0.3" footer="0.3"/>';
        $xml .= '<pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/>';
        $xml .= '</worksheet>';

        return $xml;
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="4">'
            . '<font><sz val="11"/><name val="Arial"/></font>'
            . '<font><b/><sz val="11"/><name val="Arial"/></font>'
            . '<font><b/><sz val="16"/><color rgb="FF0F5F57"/><name val="Arial"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Arial"/></font>'
            . '</fonts>'
            . '<fills count="5">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF0F766E"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFDCEEEB"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FF94A3B8"/></left><right style="thin"><color rgb="FF94A3B8"/></right>'
            . '<top style="thin"><color rgb="FF94A3B8"/></top><bottom style="thin"><color rgb="FF94A3B8"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="7">'
            // 0 عادي
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="right" vertical="center" readingOrder="2"/></xf>'
            // 1 عنوان
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center" readingOrder="2"/></xf>'
            // 2 رأس الجدول
            . '<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1" readingOrder="2"/></xf>'
            // 3 خلية محتوى
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="top" wrapText="1" readingOrder="2"/></xf>'
            // 4 عنوان جانبي
            . '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1" readingOrder="2"/></xf>'
            // 5 خلية المادة
            . '<xf numFmtId="0" fontId="1" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1" readingOrder="2"/></xf>'
            // 6 ملاحظة
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center" wrapText="1" readingOrder="2"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::escape($this->sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private static function escape(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? $value;
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function sanitizeSheetName(string $name): string
    {
        $name = str_replace([':', '\\', '/', '?', '*', '[', ']'], '-', $name);
        return mb_substr(trim($name) ?: 'ورقة', 0, 31);
    }
}
