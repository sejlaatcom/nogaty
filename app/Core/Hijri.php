<?php
/**
 * تحويل التاريخ الميلادي إلى الهجري والعكس (الخوارزمية الكويتية / أم القرى التقريبية)
 */
class Hijri
{
    private const MONTHS = [
        1 => 'محرم', 2 => 'صفر', 3 => 'ربيع الأول', 4 => 'ربيع الآخر',
        5 => 'جمادى الأولى', 6 => 'جمادى الآخرة', 7 => 'رجب', 8 => 'شعبان',
        9 => 'رمضان', 10 => 'شوال', 11 => 'ذو القعدة', 12 => 'ذو الحجة',
    ];

    /** ميلادي -> هجري : يعيد [سنة, شهر, يوم] */
    public static function fromGregorian(int $y, int $m, int $d): array
    {
        $jd = self::gregorianToJd($y, $m, $d);
        return self::jdToHijri($jd);
    }

    /** هجري -> ميلادي : يعيد [سنة, شهر, يوم] */
    public static function toGregorian(int $hy, int $hm, int $hd): array
    {
        $jd = self::hijriToJd($hy, $hm, $hd);
        return self::jdToGregorian($jd);
    }

    /** تنسيق نصي : 1447/02/15 */
    public static function format(?string $gregorianDate, bool $withMonthName = false): string
    {
        if (!$gregorianDate) {
            return '';
        }
        $ts = strtotime($gregorianDate);
        if ($ts === false) {
            return '';
        }
        [$y, $m, $d] = self::fromGregorian((int)date('Y', $ts), (int)date('n', $ts), (int)date('j', $ts));
        if ($withMonthName) {
            return sprintf('%d %s %d هـ', $d, self::MONTHS[$m] ?? $m, $y);
        }
        return sprintf('%04d/%02d/%02d هـ', $y, $m, $d);
    }

    public static function monthName(int $m): string
    {
        return self::MONTHS[$m] ?? '';
    }

    /** السنة الهجرية الحالية */
    public static function currentYear(): int
    {
        [$y] = self::fromGregorian((int)date('Y'), (int)date('n'), (int)date('j'));
        return $y;
    }

    // ------------------------- الحسابات الداخلية -------------------------

    private static function gregorianToJd(int $y, int $m, int $d): int
    {
        if ($m < 3) { $y--; $m += 12; }
        $a = intdiv($y, 100);
        $b = 2 - $a + intdiv($a, 4);
        if ($y < 1583 || ($y === 1582 && ($m < 10 || ($m === 10 && $d < 15)))) {
            $b = 0;
        }
        return (int) floor(365.25 * ($y + 4716)) + (int) floor(30.6001 * ($m + 1)) + $d + $b - 1524;
    }

    private static function jdToGregorian(int $jd): array
    {
        $a = $jd + 32044;
        $b = intdiv(4 * $a + 3, 146097);
        $c = $a - intdiv(146097 * $b, 4);
        $dd = intdiv(4 * $c + 3, 1461);
        $e = $c - intdiv(1461 * $dd, 4);
        $mm = intdiv(5 * $e + 2, 153);
        $day   = $e - intdiv(153 * $mm + 2, 5) + 1;
        $month = $mm + 3 - 12 * intdiv($mm, 10);
        $year  = 100 * $b + $dd - 4800 + intdiv($mm, 10);
        return [$year, $month, $day];
    }

    private static function hijriToJd(int $y, int $m, int $d): int
    {
        return (int) (floor((11 * $y + 3) / 30) + 354 * $y + 30 * $m
             - floor(($m - 1) / 2) + $d + 1948440 - 386);
    }

    private static function jdToHijri(int $jd): array
    {
        $jd = $jd - 1948440 + 10632;
        $n  = (int) floor(($jd - 1) / 10631);
        $jd = $jd - 10631 * $n + 354;
        $j  = ((int) floor((10985 - $jd) / 5316)) * ((int) floor((50 * $jd) / 17719))
            + ((int) floor($jd / 5670)) * ((int) floor((43 * $jd) / 15238));
        $jd = $jd - ((int) floor((30 - $j) / 15)) * ((int) floor((17719 * $j) / 50))
            - ((int) floor($j / 16)) * ((int) floor((15238 * $j) / 43)) + 29;
        $month = (int) floor((24 * $jd) / 709);
        $day   = $jd - (int) floor((709 * $month) / 24);
        $year  = 30 * $n + $j - 30;
        return [$year, $month, $day];
    }
}
