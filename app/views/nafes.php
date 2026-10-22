<?php
declare(strict_types=1);

/** أدوات عرض مشتركة لصفحات منصة نافس */

/** خيارات قائمة منسدلة من صفوف قاعدة البيانات */
function options_html(array $rows, int $selected = 0, string $placeholder = '', string $labelKey = 'name'): string
{
    $html = $placeholder !== '' ? '<option value="0">' . h($placeholder) . '</option>' : '';
    foreach ($rows as $row) {
        $id    = (int) $row['id'];
        $html .= '<option value="' . $id . '"' . ($id === $selected ? ' selected' : '') . '>' . h((string) $row[$labelKey]) . '</option>';
    }
    return $html;
}

/** قائمة المهارات مجمعة بالمجال — كل خيار يحمل المادة والصف لتصفيته بالجافاسكربت */
function skill_options_html(array $skills, int $selected = 0, string $placeholder = '— بدون مهارة —'): string
{
    $html  = '<option value="0">' . h($placeholder) . '</option>';
    $group = null;
    foreach ($skills as $skill) {
        if ($group !== $skill['domain_id']) {
            $html .= ($group !== null ? '</optgroup>' : '')
                   . '<optgroup label="' . h($skill['domain_name']) . '" data-subject="' . (int) $skill['subject_id']
                   . '" data-grade="' . (int) ($skill['grade_id'] ?? 0) . '">';
            $group = $skill['domain_id'];
        }
        $id    = (int) $skill['id'];
        $html .= '<option value="' . $id . '"' . ($id === $selected ? ' selected' : '') . '>' . h(QuestionBank::skillLabel($skill)) . '</option>';
    }
    return $html . ($group !== null ? '</optgroup>' : '');
}

/** شارة صعوبة السؤال */
function difficulty_badge(int $difficulty): string
{
    $class = match ($difficulty) { 1 => 'badge-green', 3 => 'badge-red', default => 'badge-amber' };
    return '<span class="badge ' . $class . '">' . h(QuestionBank::DIFFICULTIES[$difficulty] ?? '—') . '</span>';
}

/** نص السؤال مع الصورة (ونص القراءة إن وُجد) */
function question_body(array $question, ?array $passage = null, bool $showPassage = true): void
{
    if ($showPassage && $passage): ?>
      <details class="passage" open>
        <summary>النص: <?= h($passage['title']) ?></summary>
        <div class="passage-body"><?= nl2br_e($passage['body']) ?></div>
      </details>
    <?php endif; ?>
    <div class="q-stem"><?= nl2br_e($question['stem']) ?></div>
    <?php if (!empty($question['image'])): ?>
      <img class="q-image" src="<?= h(url('uploads/' . $question['image'])) ?>" alt="صورة السؤال">
    <?php endif;
}

/** حروف الخيارات العربية */
function option_letter(int $index): string
{
    return ['أ', 'ب', 'ج', 'د', 'هـ', 'و'][$index] ?? (string) ($index + 1);
}

/** ترقيم الصفحات */
function pagination_html(int $page, int $pages, array $query): string
{
    if ($pages <= 1) {
        return '';
    }
    $html = '<div class="pagination">';
    for ($i = 1; $i <= $pages; $i++) {
        if ($pages > 12 && $i > 2 && $i < $pages - 1 && abs($i - $page) > 2) {
            if (!str_ends_with($html, '…</span>')) {
                $html .= '<span>…</span>';
            }
            continue;
        }
        $link  = '?' . http_build_query(['page' => $i] + $query);
        $html .= $i === $page ? '<span class="current">' . $i . '</span>' : '<a href="' . h($link) . '">' . $i . '</a>';
    }
    return $html . '</div>';
}
