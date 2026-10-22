<?php
declare(strict_types=1);

namespace App\Services;

/**
 * عرض السؤال من لقطته:
 *  preview — للمعلم مع الإجابات الصحيحة
 *  take    — للطالب أثناء الاختبار (بدون أي إشارة للإجابة الصحيحة)
 *  review  — بعد التسليم: إجابة الطالب والإجابة الصحيحة والتفسير (حسب الإعدادات)
 */
final class QuestionRenderer
{
    private const LETTERS = ['أ', 'ب', 'ج', 'د', 'هـ', 'و', 'ز', 'ح'];

    public static function letter(int $i): string
    {
        return self::LETTERS[$i] ?? (string) ($i + 1);
    }

    /** محتوى النص القرائي */
    public static function passage(?array $passage): string
    {
        if (!$passage) {
            return '';
        }
        $media = '';
        foreach ($passage['media'] ?? [] as $mid) {
            $media .= '<img class="q-media" src="' . e(url('/media/' . (int) $mid)) . '" alt="">';
        }
        return '<div class="passage-box mb-3"><div class="fw-bold text-primary mb-2"><i class="bi bi-book"></i> ' . e($passage['title'])
            . '</div>' . rich_text($passage['body']) . $media . '</div>';
    }

    public static function stem(array $q): string
    {
        $html = '<div class="q-stem">' . rich_text($q['stem']) . '</div>';
        foreach ($q['media'] ?? [] as $m) {
            $html .= '<img class="q-media" src="' . e(url('/media/' . (int) $m['id'])) . '" alt="' . e($m['alt'] ?? 'صورة السؤال') . '" loading="lazy">';
        }
        return $html;
    }

    /**
     * @param array  $q      لقطة السؤال
     * @param string $mode   preview|take|review
     * @param string $name   اسم الحقل للإجابة (take)
     * @param array  $answer إجابة الطالب المفكوكة
     * @param array  $order  ترتيب العرض {"main":[ids], "right":[ids]}
     * @param array  $review ['show_correct' => bool, 'show_explanation' => bool]
     */
    public static function body(array $q, string $mode, string $name = 'a', array $answer = [], array $order = [], array $review = []): string
    {
        $options = self::ordered($q['options'], $order['main'] ?? []);
        $showCorrect = $mode === 'preview' || ($mode === 'review' && !empty($review['show_correct']));
        $html = '';

        switch ($q['type']) {
            case 'single':
            case 'true_false':
            case 'multiple':
                $multi = $q['type'] === 'multiple';
                $chosen = $multi ? array_map('intval', $answer['options'] ?? []) : [(int) ($answer['option'] ?? 0)];
                if ($multi && $mode === 'take') {
                    $html .= '<div class="small text-muted mb-2"><i class="bi bi-info-circle"></i> اختر كل الإجابات الصحيحة.</div>';
                }
                $html .= '<div class="option-list">';
                foreach ($options as $i => $o) {
                    $id = (int) $o['id'];
                    $picked = in_array($id, $chosen, true);
                    $cls = '';
                    if ($mode !== 'take' && $showCorrect) {
                        $cls = $o['is_correct'] ? 'correct' : ($picked ? 'wrong' : '');
                    } elseif ($mode === 'review' && $picked) {
                        $cls = 'border-primary';
                    }
                    $input = $mode === 'take'
                        ? '<input class="form-check-input" type="' . ($multi ? 'checkbox' : 'radio') . '" name="' . e($name) . ($multi ? '[]' : '') . '" value="' . $id . '"' . ($picked ? ' checked' : '') . '>'
                        : '<i class="bi bi-' . ($picked ? ($multi ? 'check-square-fill' : 'record-circle-fill') : ($multi ? 'square' : 'circle')) . ' text-primary"></i>';
                    $html .= '<label class="option-item ' . $cls . '">' . $input . '<span class="opt-letter">' . self::letter($i) . '</span><span class="flex-grow-1">'
                        . rich_inline($o['body']) . '</span>';
                    if ($mode !== 'take' && $showCorrect && $o['is_correct']) {
                        $html .= '<span class="badge badge-soft-success">الإجابة الصحيحة</span>';
                    }
                    if ($mode === 'review' && $picked) {
                        $html .= '<small class="text-muted">إجابتك</small>';
                    }
                    $html .= '</label>';
                    if (($mode === 'preview' || ($mode === 'review' && $picked && !empty($review['show_explanation']))) && !$o['is_correct'] && !empty($o['feedback'])) {
                        $html .= '<div class="small text-warning-emphasis ms-5 mb-1"><i class="bi bi-lightbulb"></i> ' . e($o['feedback']) . '</div>';
                    }
                }
                $html .= '</div>';
                break;

            case 'matching':
                $right = self::ordered($q['options'], $order['right'] ?? []);
                $pairs = array_map('intval', (array) ($answer['pairs'] ?? []));
                $html .= '<div class="small text-muted mb-2"><i class="bi bi-arrow-left-right"></i> صِل كل عنصر بما يناسبه من القائمة.</div>';
                foreach ($options as $o) {
                    $id = (int) $o['id'];
                    $html .= '<div class="match-row"><div class="left">' . rich_inline($o['body']) . '</div><i class="bi bi-arrow-left text-muted"></i>';
                    if ($mode === 'take') {
                        $html .= '<select class="form-select" name="' . e($name) . '[' . $id . ']"><option value="">— اختر —</option>';
                        foreach ($right as $r) {
                            $html .= '<option value="' . (int) $r['id'] . '"' . (($pairs[$id] ?? 0) === (int) $r['id'] ? ' selected' : '') . '>' . e($r['match_text']) . '</option>';
                        }
                        $html .= '</select>';
                    } else {
                        $pickedId = $pairs[$id] ?? 0;
                        $pickedText = '—';
                        foreach ($q['options'] as $r) {
                            if ((int) $r['id'] === $pickedId) {
                                $pickedText = $r['match_text'];
                            }
                        }
                        $ok = $pickedId === $id;
                        $html .= '<div class="left ' . ($mode === 'review' && $showCorrect ? ($ok ? 'border-success' : 'border-danger') : '') . '">'
                            . ($mode === 'preview' ? e($o['match_text']) : e($pickedText) . ($showCorrect && !$ok ? '<br><small class="text-success">الصحيح: ' . e($o['match_text']) . '</small>' : ''))
                            . '</div>';
                    }
                    $html .= '</div>';
                }
                break;

            case 'ordering':
                $current = array_map('intval', (array) ($answer['order'] ?? []));
                $items = $current ? self::ordered($q['options'], $current) : $options;
                if ($mode === 'take') {
                    $html .= '<div class="small text-muted mb-2"><i class="bi bi-arrow-down-up"></i> رتّب العناصر باستخدام الأسهم من الأول إلى الأخير.</div><div class="sortable-list" data-sortable>';
                    foreach ($items as $o) {
                        $html .= '<div class="sort-item"><span class="handle"><i class="bi bi-grip-vertical"></i></span><span class="flex-grow-1">' . rich_inline($o['body'])
                            . '</span><input type="hidden" name="' . e($name) . '[]" value="' . (int) $o['id'] . '">'
                            . '<button type="button" class="btn btn-sm btn-light" data-move="-1" aria-label="أعلى"><i class="bi bi-arrow-up"></i></button>'
                            . '<button type="button" class="btn btn-sm btn-light" data-move="1" aria-label="أسفل"><i class="bi bi-arrow-down"></i></button></div>';
                    }
                    $html .= '</div>';
                } else {
                    $list = $mode === 'preview' ? self::byPosition($q['options']) : $items;
                    $html .= '<ol class="list-group list-group-numbered">';
                    foreach ($list as $i => $o) {
                        $ok = (int) $o['position'] === $i + 1;
                        $html .= '<li class="list-group-item ' . ($mode === 'review' && $showCorrect ? ($ok ? 'list-group-item-success' : 'list-group-item-danger') : '') . '">' . rich_inline($o['body']) . '</li>';
                    }
                    $html .= '</ol>';
                    if ($mode === 'review' && $showCorrect) {
                        $html .= '<div class="small mt-2 text-success">الترتيب الصحيح: ' . e(implode(' ← ', array_map(static fn ($o) => strip_tags($o['body']), self::byPosition($q['options'])))) . '</div>';
                    }
                }
                break;

            case 'short_answer':
                $text = (string) ($answer['text'] ?? '');
                if ($mode === 'take') {
                    $html .= '<input type="text" class="form-control form-control-lg" name="' . e($name) . '" value="' . e($text) . '" maxlength="300" placeholder="اكتب إجابتك هنا" autocomplete="off">';
                } else {
                    if ($mode === 'review') {
                        $html .= '<div class="border rounded-3 p-2 mb-2">إجابتك: <b>' . e($text !== '' ? $text : '—') . '</b></div>';
                    }
                    if ($showCorrect && $q['options']) {
                        $html .= '<div class="small text-success">الإجابات المقبولة: ' . e(implode(' / ', array_column($q['options'], 'body'))) . '</div>';
                    }
                    if ($mode === 'preview' && !empty($q['manual'])) {
                        $html .= '<div class="small text-warning-emphasis"><i class="bi bi-pencil-square"></i> يُصحّح يدوياً</div>';
                    }
                }
                break;
        }

        if (($mode === 'preview' || ($mode === 'review' && !empty($review['show_explanation']))) && !empty($q['explanation'])) {
            $html .= '<div class="alert alert-info mt-3 mb-0 small"><b><i class="bi bi-lightbulb"></i> التفسير:</b> ' . rich_text($q['explanation']) . '</div>';
        }
        if ($mode === 'preview' && !empty($q['common_mistakes'])) {
            $html .= '<div class="alert alert-warning mt-2 mb-0 small"><b>أخطاء شائعة:</b> ' . rich_text($q['common_mistakes']) . '</div>';
        }
        return $html;
    }

    /** ترتيب الخيارات حسب قائمة معرفات (الباقي بعدها) */
    public static function ordered(array $options, array $ids): array
    {
        if (!$ids) {
            return array_values($options);
        }
        $byId = [];
        foreach ($options as $o) {
            $byId[(int) $o['id']] = $o;
        }
        $out = [];
        foreach ($ids as $id) {
            if (isset($byId[(int) $id])) {
                $out[] = $byId[(int) $id];
                unset($byId[(int) $id]);
            }
        }
        return array_merge($out, array_values($byId));
    }

    private static function byPosition(array $options): array
    {
        usort($options, static fn ($a, $b) => (int) $a['position'] <=> (int) $b['position']);
        return $options;
    }

    /** نسخة من اللقطة بدون أي معلومة عن الإجابة الصحيحة — لما يُرسل للمتصفح أثناء الاختبار */
    public static function stripAnswers(array $q): array
    {
        unset($q['explanation'], $q['common_mistakes']);
        $q['options'] = array_map(static fn ($o) => ['id' => $o['id'], 'body' => $o['body'], 'match_text' => $o['match_text'] ?? null], $q['options']);
        if ($q['type'] === 'short_answer') {
            $q['options'] = [];
        }
        return $q;
    }
}
