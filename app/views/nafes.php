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
function pagination_html(int $page, int $pages, array $query, string $key = 'page'): string
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
        $link  = '?' . http_build_query([$key => $i] + $query);
        $html .= $i === $page ? '<span class="current">' . $i . '</span>' : '<a href="' . h($link) . '">' . $i . '</a>';
    }
    return $html . '</div>';
}

/** حلقة النتيجة مع لون المستوى */
function score_ring(float $percent, string $caption = ''): string
{
    $color = match (Nafes::levelKey($percent)) {
        'advanced' => '#7c3aed', 'proficient' => 'var(--green)', 'basic' => '#d97706', default => 'var(--red)',
    };
    return '<div class="score-ring" style="--p:' . round($percent) . ';--ring-color:' . $color . '"><div><b>'
        . Nafes::pct($percent) . '</b><small>' . h($caption) . '</small></div></div>';
}

/** شريط إتقان مهارة */
function skill_bar(string $label, ?float $percent, string $extra = ''): string
{
    $key = Nafes::levelKey($percent);
    return '<div class="skill-bar"><span>' . h($label) . ($extra !== '' ? ' <small>' . h($extra) . '</small>' : '') . '</span>'
        . '<div class="progress"><span class="lv-' . $key . '" style="width:' . max(2, (int) round($percent ?? 0)) . '%"></span></div>'
        . '<b>' . Nafes::pct($percent) . '</b></div>';
}

/** ملخص نتيجة محاولة ومراجعة إجاباتها (للطالب والمعلم) */
function render_attempt_result(array $attempt, bool $showReview): void
{
    $percent   = (float) $attempt['percent'];
    $breakdown = Exams::skillBreakdown((int) $attempt['id']);
    ?>
    <div class="grid grid-2">
      <div class="card">
        <div class="card-body text-center">
          <?= score_ring($percent, (int) $attempt['score'] . ' من ' . (int) $attempt['total']) ?>
          <p class="mb0">المستوى: <?= Nafes::levelBadge($percent) ?></p>
          <p class="text-muted mb0">التسليم: <?= h((string) $attempt['submitted_at']) ?> · المدة: <?= h(Exams::durationLabel($attempt)) ?></p>
        </div>
      </div>
      <div class="card">
        <div class="card-head"><h2>الأداء في المهارات</h2></div>
        <div class="card-body">
          <?php foreach ($breakdown as $row): ?>
            <?= skill_bar(($row['skill_code'] ? $row['skill_code'] . ' — ' : '') . $row['skill_name'], (float) $row['percent'], (int) $row['correct'] . '/' . (int) $row['total']) ?>
          <?php endforeach; ?>
          <?php $weak = array_filter($breakdown, static fn ($r) => Nafes::levelKey((float) $r['percent']) === 'below' && $r['skill_id']); ?>
          <?php if ($weak): ?>
            <p class="help mt16 mb0">مهارات تحتاج إلى مزيد من التدريب: <?= h(implode('، ', array_map(static fn ($r) => $r['skill_name'], $weak))) ?></p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if (!$showReview) { return; } ?>
    <div class="card">
      <div class="card-head"><h2>مراجعة الإجابات</h2></div>
      <div class="card-body">
        <?php foreach (Exams::attemptQuestions((int) $attempt['id']) as $i => $q): ?>
          <div class="review-q <?= (int) $q['is_correct'] === 1 ? 'ok' : 'no' ?>">
            <div class="flex mb16">
              <strong>السؤال <?= $i + 1 ?></strong>
              <?= (int) $q['is_correct'] === 1 ? '<span class="badge badge-green">✓ صحيحة</span>' : ($q['option_id'] === null ? '<span class="badge badge-gray">لم تتم الإجابة</span>' : '<span class="badge badge-red">✗ خاطئة</span>') ?>
              <?php if ($q['skill_name']): ?><small class="text-muted"><?= h($q['skill_name']) ?></small><?php endif; ?>
            </div>
            <?php question_body($q, $q['passage'], false); ?>
            <ol class="q-options">
              <?php foreach ($q['options'] as $j => $option): ?>
                <?php
                $class = (int) $option['is_correct'] === 1 ? 'correct' : ((int) $q['option_id'] === (int) $option['id'] ? 'wrong' : '');
                ?>
                <li class="<?= $class ?>"><span class="opt-letter"><?= option_letter($j) ?></span><?= h($option['body']) ?>
                  <?= (int) $q['option_id'] === (int) $option['id'] ? ' <small>(إجابتك)</small>' : '' ?></li>
              <?php endforeach; ?>
            </ol>
            <?php if ($q['explanation']): ?><div class="q-explain"><b>التفسير:</b> <?= nl2br_e($q['explanation']) ?></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php
}
