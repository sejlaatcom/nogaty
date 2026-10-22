<?php
declare(strict_types=1);

namespace App\Helpers;

use App\Core\View;

/** بناء رسوم Chart.js من بيانات حقيقية */
final class Chart
{
    private static bool $loaded = false;

    public const PALETTE = ['#0b5394', '#0ea5b7', '#16803c', '#e8780c', '#6d3fd1', '#cf2e2e', '#64748b', '#d4a017'];

    /** عنصر canvas مع إعداده — $percent يجعل المحور 0–100٪ */
    public static function render(string $type, array $labels, array $datasets, int $height = 280, bool $percent = false, array $options = []): string
    {
        if (!self::$loaded) {
            View::push('scripts', '<script src="' . asset('vendor/chartjs/chart.umd.min.js') . '"></script><script src="' . asset('js/charts.js') . '"></script>');
            self::$loaded = true;
        }
        foreach ($datasets as $i => &$ds) {
            $color = $ds['color'] ?? self::PALETTE[$i % count(self::PALETTE)];
            unset($ds['color']);
            if (in_array($type, ['doughnut', 'pie'], true)) {
                $ds['backgroundColor'] ??= array_slice(array_merge($ds['colors'] ?? [], self::PALETTE), 0, count($labels));
                unset($ds['colors']);
            } else {
                $ds['backgroundColor'] ??= $type === 'line' ? $color . '22' : $color;
                $ds['borderColor'] ??= $color;
                if ($type === 'line') {
                    $ds += ['tension' => 0.3, 'fill' => true, 'pointRadius' => 4];
                } else {
                    $ds += ['borderRadius' => 6, 'maxBarThickness' => 42];
                }
            }
        }
        $config = ['type' => $type, 'data' => ['labels' => array_values($labels), 'datasets' => $datasets], 'options' => $options, 'percent' => $percent];
        return '<div style="position:relative;height:' . $height . 'px"><canvas data-chart="' . e(json_encode($config, JSON_UNESCAPED_UNICODE)) . '" role="img"></canvas></div>';
    }

    /** لا توجد بيانات */
    public static function empty(string $text = 'لا توجد بيانات كافية بعد.'): string
    {
        return '<div class="empty-state"><i class="bi bi-bar-chart"></i>' . e($text) . '</div>';
    }
}
