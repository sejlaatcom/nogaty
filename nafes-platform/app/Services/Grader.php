<?php
declare(strict_types=1);

namespace App\Services;

/** التصحيح الآلي لكل أنواع الأسئلة من لقطة السؤال */
final class Grader
{
    /**
     * @return array{score: float|null, correct: int|null, manual: bool, blank: bool}
     *  score null و manual=true عندما يحتاج السؤال تصحيحاً يدوياً.
     */
    public static function grade(array $q, array $answer, float $points): array
    {
        $blank = self::isBlank($q['type'], $answer);
        if ($q['type'] === 'short_answer' && !empty($q['manual']) && !$blank) {
            return ['score' => null, 'correct' => null, 'manual' => true, 'blank' => false];
        }
        if ($blank) {
            return ['score' => 0.0, 'correct' => 0, 'manual' => false, 'blank' => true];
        }

        $fraction = 0.0;
        switch ($q['type']) {
            case 'single':
            case 'true_false':
                foreach ($q['options'] as $o) {
                    if ($o['is_correct'] && (int) $o['id'] === (int) ($answer['option'] ?? 0)) {
                        $fraction = 1.0;
                    }
                }
                break;

            case 'multiple':
                // درجة جزئية: (الصحيح المختار − الخاطئ المختار) ÷ عدد الصحيح، والإجابة صحيحة فقط بالتطابق التام
                $correctIds = array_map(static fn ($o) => (int) $o['id'], array_filter($q['options'], static fn ($o) => $o['is_correct']));
                $picked = array_unique(array_map('intval', (array) ($answer['options'] ?? [])));
                $hits = count(array_intersect($picked, $correctIds));
                $wrong = count(array_diff($picked, $correctIds));
                $fraction = $correctIds ? max(0, ($hits - $wrong) / count($correctIds)) : 0;
                break;

            case 'matching':
                $pairs = (array) ($answer['pairs'] ?? []);
                $ok = 0;
                foreach ($q['options'] as $o) {
                    if ((int) ($pairs[(int) $o['id']] ?? $pairs[(string) $o['id']] ?? 0) === (int) $o['id']) {
                        $ok++;
                    }
                }
                $fraction = $q['options'] ? $ok / count($q['options']) : 0;
                break;

            case 'ordering':
                $order = array_map('intval', (array) ($answer['order'] ?? []));
                $positions = [];
                foreach ($q['options'] as $o) {
                    $positions[(int) $o['id']] = (int) $o['position'];
                }
                $ok = 0;
                foreach ($order as $i => $id) {
                    if (($positions[$id] ?? 0) === $i + 1) {
                        $ok++;
                    }
                }
                $fraction = $positions ? $ok / count($positions) : 0;
                break;

            case 'short_answer':
                $given = normalize_ar((string) ($answer['text'] ?? ''));
                foreach ($q['options'] as $o) {
                    $acc = normalize_ar((string) $o['body']);
                    if ($given === $acc || (is_numeric(str_replace(' ', '.', $given)) && is_numeric(str_replace(' ', '.', $acc))
                        && abs((float) str_replace(' ', '.', $given) - (float) str_replace(' ', '.', $acc)) < 1e-9)) {
                        $fraction = 1.0;
                    }
                }
                break;
        }

        return [
            'score'   => round($points * $fraction, 2),
            'correct' => $fraction >= 0.9999 ? 1 : 0,
            'manual'  => false,
            'blank'   => false,
        ];
    }

    public static function isBlank(string $type, array $answer): bool
    {
        return match ($type) {
            'single', 'true_false' => empty($answer['option']),
            'multiple'             => empty($answer['options']),
            'matching'             => !array_filter((array) ($answer['pairs'] ?? [])),
            'ordering'             => empty($answer['order']) || empty($answer['touched']),
            'short_answer'         => trim((string) ($answer['text'] ?? '')) === '',
            default                => true,
        };
    }

    /** تحويل مدخلات النموذج إلى صيغة الإجابة المخزنة حسب النوع */
    public static function normalizeInput(string $type, mixed $raw, array $q): array
    {
        $validIds = array_map(static fn ($o) => (int) $o['id'], $q['options']);
        return match ($type) {
            'single', 'true_false' => in_array((int) $raw, $validIds, true) ? ['option' => (int) $raw] : [],
            'multiple'  => ['options' => array_values(array_intersect(array_map('intval', (array) $raw), $validIds))],
            'matching'  => ['pairs' => array_filter(
                array_map('intval', array_intersect_key((array) $raw, array_flip($validIds))),
                static fn ($v) => in_array($v, $validIds, true)
            )],
            'ordering'  => (static function () use ($raw, $validIds) {
                $order = array_values(array_intersect(array_map('intval', (array) $raw), $validIds));
                return count($order) === count($validIds) ? ['order' => $order, 'touched' => 1] : [];
            })(),
            'short_answer' => ['text' => mb_substr(trim((string) (is_array($raw) ? '' : $raw)), 0, 300)],
            default => [],
        };
    }
}
