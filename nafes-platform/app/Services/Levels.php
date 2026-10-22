<?php
declare(strict_types=1);

namespace App\Services;

/** مستويات الأداء حسب الحدود المحددة في الإعدادات */
final class Levels
{
    public const LABELS = [
        'advanced'   => 'متقدم',
        'proficient' => 'متمكن',
        'basic'      => 'أساسي',
        'below'      => 'دون الأساسي',
    ];

    public const COLORS = [
        'advanced' => '#6d3fd1', 'proficient' => '#16803c', 'basic' => '#e8780c', 'below' => '#cf2e2e',
    ];

    public static function key(?float $percent): string
    {
        if ($percent === null) {
            return 'below';
        }
        return match (true) {
            $percent >= Settings::int('level_advanced')   => 'advanced',
            $percent >= Settings::int('level_proficient') => 'proficient',
            $percent >= Settings::int('level_basic')      => 'basic',
            default                                       => 'below',
        };
    }

    public static function label(?string $key): string
    {
        return self::LABELS[$key ?? ''] ?? '—';
    }

    /** شارة مستوى من مفتاح أو نسبة */
    public static function badge(string|float|null $levelOrPercent): string
    {
        if ($levelOrPercent === null || $levelOrPercent === '') {
            return '<span class="badge badge-soft-secondary">—</span>';
        }
        $key = is_string($levelOrPercent) && isset(self::LABELS[$levelOrPercent]) ? $levelOrPercent : self::key((float) $levelOrPercent);
        return '<span class="badge lv-' . $key . '">' . e(self::LABELS[$key]) . '</span>';
    }

    /** هل النسبة تمثل إتقاناً للمهارة؟ */
    public static function mastered(?float $percent): bool
    {
        return $percent !== null && $percent >= Settings::int('mastery_threshold');
    }

    public static function bar(?float $percent): string
    {
        $key = self::key($percent);
        return '<div class="progress" role="progressbar" aria-valuenow="' . (int) round($percent ?? 0) . '" aria-valuemin="0" aria-valuemax="100">'
            . '<div class="progress-bar bar-' . $key . '" style="width:' . max(2, (int) round($percent ?? 0)) . '%"></div></div>';
    }
}
