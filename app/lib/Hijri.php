<?php
declare(strict_types=1);

/**
 * تحويل التاريخ الميلادي إلى هجري (تقويم أم القرى).
 * يستخدم إضافة intl إن كانت متوفرة، وإلا يستخدم الحساب الجدولي.
 */
final class Hijri
{
    public const MONTHS = [
        1 => 'محرم', 2 => 'صفر', 3 => 'ربيع الأول', 4 => 'ربيع الآخر',
        5 => 'جمادى الأولى', 6 => 'جمادى الآخرة', 7 => 'رجب', 8 => 'شعبان',
        9 => 'رمضان', 10 => 'شوال', 11 => 'ذو القعدة', 12 => 'ذو الحجة',
    ];

    /** [سنة, شهر, يوم] هجرياً من تاريخ ميلادي (Y-m-d) */
    public static function fromDate(string $gregorian): array
    {
        $date = self::parse($gregorian);

        if (class_exists('IntlCalendar')) {
            $cal = IntlCalendar::createInstance(new DateTimeZone('UTC'), 'ar_SA@calendar=islamic-umalqura');
            if ($cal instanceof IntlCalendar) {
                $cal->clear();
                $cal->setTime($date->getTimestamp() * 1000.0);
                return [
                    (int) $cal->get(IntlCalendar::FIELD_YEAR),
                    (int) $cal->get(IntlCalendar::FIELD_MONTH) + 1,
                    (int) $cal->get(IntlCalendar::FIELD_DAY_OF_MONTH),
                ];
            }
        }

        return self::tabularFromJd(self::gregorianToJd($date));
    }

    /** تاريخ ميلادي (Y-m-d) من تاريخ هجري */
    public static function toDate(int $year, int $month, int $day): string
    {
        if (class_exists('IntlCalendar')) {
            $cal = IntlCalendar::createInstance(new DateTimeZone('UTC'), 'ar_SA@calendar=islamic-umalqura');
            if ($cal instanceof IntlCalendar) {
                $cal->clear();
                $cal->set($year, $month - 1, $day, 12, 0, 0);
                return (new DateTimeImmutable('@' . (int) ($cal->getTime() / 1000)))
                    ->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
            }
        }

        $jd = self::tabularToJd($year, $month, $day);
        return self::jdToGregorian($jd);
    }

    /** صيغة مختصرة: 1448/03/10هـ */
    public static function short(string $gregorian, bool $arabicDigits = false): string
    {
        [$y, $m, $d] = self::fromDate($gregorian);
        $text = sprintf('%d/%d/%dهـ', $y, $m, $d);
        return $arabicDigits ? ar_digits($text) : $text;
    }

    /** صيغة كاملة: 10 ربيع الأول 1448هـ */
    public static function long(string $gregorian, bool $arabicDigits = false): string
    {
        [$y, $m, $d] = self::fromDate($gregorian);
        $text = sprintf('%d %s %dهـ', $d, self::MONTHS[$m] ?? (string) $m, $y);
        return $arabicDigits ? ar_digits($text) : $text;
    }

    /** اسم اليوم بالعربية من تاريخ ميلادي */
    public static function dayName(string $gregorian): string
    {
        $names = ['Sun' => 'الأحد', 'Mon' => 'الاثنين', 'Tue' => 'الثلاثاء', 'Wed' => 'الأربعاء',
                  'Thu' => 'الخميس', 'Fri' => 'الجمعة', 'Sat' => 'السبت'];
        return $names[self::parse($gregorian)->format('D')] ?? '';
    }

    /** السنة الهجرية الحالية */
    public static function currentYear(): int
    {
        return self::fromDate(date('Y-m-d'))[0];
    }

    private static function parse(string $gregorian): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($gregorian . ' 12:00:00', new DateTimeZone('UTC'));
        } catch (Throwable) {
            return new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }
    }

    /* ---------------- الحساب الجدولي (احتياطي) ---------------- */

    private static function gregorianToJd(DateTimeImmutable $date): int
    {
        $y = (int) $date->format('Y');
        $m = (int) $date->format('n');
        $d = (int) $date->format('j');

        $a = intdiv(14 - $m, 12);
        $y2 = $y + 4800 - $a;
        $m2 = $m + 12 * $a - 3;

        return $d + intdiv(153 * $m2 + 2, 5) + 365 * $y2 + intdiv($y2, 4) - intdiv($y2, 100) + intdiv($y2, 400) - 32045;
    }

    private static function jdToGregorian(int $jd): string
    {
        $a = $jd + 32044;
        $b = intdiv(4 * $a + 3, 146097);
        $c = $a - intdiv(146097 * $b, 4);
        $d = intdiv(4 * $c + 3, 1461);
        $e = $c - intdiv(1461 * $d, 4);
        $m = intdiv(5 * $e + 2, 153);

        $day   = $e - intdiv(153 * $m + 2, 5) + 1;
        $month = $m + 3 - 12 * intdiv($m, 10);
        $year  = 100 * $b + $d - 4800 + intdiv($m, 10);

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private static function tabularFromJd(int $jd): array
    {
        $jd = $jd - 1948440 + 10632;
        $n  = intdiv($jd - 1, 10631);
        $jd -= 10631 * $n;
        $j  = intdiv(10985 - $jd, 5316) * intdiv(50 * $jd, 17719) + intdiv($jd, 5670) * intdiv(43 * $jd, 15238);
        $jd -= intdiv(30 - $j, 15) * intdiv(17719 * $j, 50) + intdiv($j, 16) * intdiv(15238 * $j, 43) - 29;

        $month = intdiv(24 * $jd, 709);
        $day   = $jd - intdiv($month * 709, 24);
        $year  = 30 * $n + $j - 30;

        return [$year, $month, $day];
    }

    private static function tabularToJd(int $year, int $month, int $day): int
    {
        return $day + intdiv($month * 709, 24) + intdiv(($year - 1) % 30 * 10631, 30)
             + intdiv($year - 1, 30) * 10631 + 1948086;
    }
}
