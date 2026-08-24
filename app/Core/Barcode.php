<?php
/**
 * توليد باركود Code128 بصيغة SVG (بدون مكتبات خارجية)
 * يستخدم لطباعة أرقام المعاملات على الوثائق الورقية.
 */
class Barcode
{
    /** أنماط Code128 : 107 نمط، كل نمط 6 أرقام تمثل عرض الخطوط */
    private const PATTERNS = [
        '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213',
        '221312','231212','112232','122132','122231','113222','123122','123221','223211','221132',
        '221231','213212','223112','312131','311222','321122','321221','312212','322112','322211',
        '212123','212321','232121','111323','131123','131321','112313','132113','132311','211313',
        '231113','231311','112133','112331','132131','113123','113321','133121','313121','211331',
        '231131','213113','213311','213131','311123','311321','331121','312113','312311','332111',
        '314111','221411','431111','111224','111422','121124','121421','141122','141221','112214',
        '112412','122114','122411','142112','142211','241211','221114','413111','241112','134111',
        '111242','121142','121241','114212','124112','124211','411212','421112','421211','212141',
        '214121','412121','111143','111341','131141','114113','114311','411113','411311','113141',
        '114131','311141','411131','211412','211214','211232','2331112',
    ];

    /**
     * تحويل بادئات المعاملات العربية إلى مقابل لاتيني
     * لأن ترميز Code128 لا يدعم إلا محارف ASCII
     */
    private const TRANSLITERATION = [
        'و' => 'I',  // وارد
        'ص' => 'O',  // صادر
        'د' => 'N',  // داخلي
        'م' => 'T',  // معاملة
        'هـ' => 'H',
        'ه' => 'H',
    ];

    /** نص صالح للترميز بـ Code128 */
    public static function encodable(string $data): string
    {
        $data = strtr($data, self::TRANSLITERATION);
        // تحويل الأرقام العربية الهندية إلى أرقام لاتينية
        $data = strtr($data, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        return preg_replace('/[^\x20-\x7E]/', '', $data);
    }

    /**
     * توليد صورة SVG لباركود Code128-B
     * يُرمَّز النص بعد تحويله إلى ASCII، ويُعرض النص الأصلي أسفل الخطوط.
     */
    public static function svg(string $data, int $height = 60, float $moduleWidth = 1.6, bool $showText = true): string
    {
        $label = trim($data);
        $data  = self::encodable($label);
        if ($data === '') {
            return '';
        }
        $codes = [104]; // START B
        $sum   = 104;
        $pos   = 1;
        $len   = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $v = ord($data[$i]) - 32;
            if ($v < 0 || $v > 94) {
                $v = 0;
            }
            $codes[] = $v;
            $sum    += $v * $pos;
            $pos++;
        }
        $codes[] = $sum % 103;  // رمز التحقق
        $codes[] = 106;         // STOP

        $bars = '';
        foreach ($codes as $c) {
            $bars .= self::PATTERNS[$c] ?? self::PATTERNS[0];
        }

        $x       = 10.0;
        $rects   = '';
        $isBar   = true;
        $barsLen = strlen($bars);
        for ($i = 0; $i < $barsLen; $i++) {
            $w = ((int) $bars[$i]) * $moduleWidth;
            if ($isBar) {
                $rects .= sprintf('<rect x="%.2f" y="0" width="%.2f" height="%d" fill="#111"/>', $x, $w, $height);
            }
            $x     += $w;
            $isBar  = !$isBar;
        }

        $totalW = $x + 10;
        $totalH = $height + ($showText ? 20 : 4);
        $text   = $showText
            ? sprintf('<text x="%.2f" y="%d" font-family="monospace" font-size="14" text-anchor="middle" fill="#111" direction="ltr">%s</text>',
                $totalW / 2, $height + 16, htmlspecialchars($label, ENT_QUOTES, 'UTF-8'))
            : '';

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%.0f" height="%d" viewBox="0 0 %.2f %d" role="img" aria-label="باركود %s">'
            . '<rect width="100%%" height="100%%" fill="#fff"/>%s%s</svg>',
            $totalW, $totalH, $totalW, $totalH,
            htmlspecialchars($label, ENT_QUOTES, 'UTF-8'), $rects, $text
        );
    }

    /** صيغة data URI للاستخدام داخل وسم img */
    public static function dataUri(string $data, int $height = 60): string
    {
        $svg = self::svg($data, $height);
        return $svg ? 'data:image/svg+xml;base64,' . base64_encode($svg) : '';
    }
}
