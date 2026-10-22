<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * التحليلات المبنية على النتائج الفعلية (test_results وresult_skill_scores).
 * نتائج التدريب الذاتي تدخل في الإتقان، ولا تدخل في متوسطات الاختبارات.
 */
final class Analytics
{
    /* ======================= الطالب ======================= */

    /** ملخص الطالب لكل مادة: متوسط الاختبارات، آخر نتيجة، عدد التدريب */
    public static function studentSubjects(int $studentId): array
    {
        return DB::all(
            "SELECT s.id, s.name, s.color, s.icon,
                    AVG(CASE WHEN t.type <> 'practice' THEN r.percent END) AS test_avg,
                    SUM(t.type <> 'practice') AS tests, SUM(t.type = 'practice') AS practices,
                    MAX(r.created_at) AS last_at
               FROM subjects s
          LEFT JOIN test_results r ON r.student_id = ? AND r.is_final = 1
          LEFT JOIN tests t ON t.id = r.test_id AND t.subject_id = s.id
              WHERE s.is_active = 1
           GROUP BY s.id, s.name, s.color, s.icon, s.sort_order ORDER BY s.sort_order",
            [$studentId]
        );
    }

    /** إتقان الطالب لكل مهارة من كل نتائجه: [skill_id => [score,max,percent,answers]] */
    public static function studentSkillMastery(int $studentId): array
    {
        $out = [];
        foreach (DB::all(
            'SELECT skill_id, SUM(score) AS score, SUM(max_score) AS max_score, COUNT(*) AS n
               FROM result_skill_scores WHERE student_id = ? AND skill_id IS NOT NULL GROUP BY skill_id',
            [$studentId]
        ) as $r) {
            $out[(int) $r['skill_id']] = ['score' => (float) $r['score'], 'max' => (float) $r['max_score'], 'n' => (int) $r['n'],
                'percent' => $r['max_score'] > 0 ? round($r['score'] / $r['max_score'] * 100, 1) : null];
        }
        return $out;
    }

    /** المهارات التي يحتاج الطالب لتحسينها (دون حد الإتقان وبعدد إجابات كافٍ) */
    public static function studentWeakSkills(int $studentId): array
    {
        $min = max(1, Settings::int('remedial_min_answers'));
        return DB::all(
            'SELECT x.skill_id, sk.name AS skill_name, sk.code, d.name AS domain_name, d.subject_id, sub.name AS subject_name,
                    x.score, x.max_score, ROUND(x.score / x.max_score * 100, 1) AS percent
               FROM (SELECT skill_id, SUM(score) score, SUM(max_score) max_score FROM result_skill_scores
                      WHERE student_id = ? AND skill_id IS NOT NULL GROUP BY skill_id HAVING SUM(max_score) >= ?) x
               JOIN skills sk ON sk.id = x.skill_id JOIN domains d ON d.id = sk.domain_id JOIN subjects sub ON sub.id = d.subject_id
              WHERE x.score / x.max_score * 100 < ?
           ORDER BY percent ASC',
            [$studentId, $min, Settings::int('mastery_threshold')]
        );
    }

    /** شجرة المادة ← المجال ← المهارة مع إتقان الطالب */
    public static function studentSkillTree(int $studentId, int $gradeId): array
    {
        $mastery = self::studentSkillMastery($studentId);
        $tree = [];
        foreach (DB::all(
            'SELECT sub.id AS subject_id, sub.name AS subject_name, d.id AS domain_id, d.name AS domain_name, sk.id AS skill_id, sk.code, sk.name AS skill_name
               FROM skills sk JOIN domains d ON d.id = sk.domain_id JOIN subjects sub ON sub.id = d.subject_id
              WHERE (d.grade_id = ? OR d.grade_id IS NULL) AND sub.is_active = 1 ORDER BY sub.sort_order, d.sort_order, sk.sort_order',
            [$gradeId]
        ) as $r) {
            $m = $mastery[(int) $r['skill_id']] ?? null;
            $tree[$r['subject_id']]['name'] = $r['subject_name'];
            $tree[$r['subject_id']]['domains'][$r['domain_id']]['name'] = $r['domain_name'];
            $tree[$r['subject_id']]['domains'][$r['domain_id']]['skills'][] = ['id' => (int) $r['skill_id'], 'code' => $r['code'], 'name' => $r['skill_name'],
                'percent' => $m['percent'] ?? null, 'max' => $m['max'] ?? 0];
        }
        foreach ($tree as &$s) {
            foreach ($s['domains'] as &$d) {
                $sc = $mx = 0;
                foreach ($d['skills'] as $sk) {
                    if ($sk['percent'] !== null) {
                        $sc += $sk['percent'] * $sk['max'];
                        $mx += $sk['max'];
                    }
                }
                $d['percent'] = $mx ? round($sc / $mx, 1) : null;
            }
        }
        return $tree;
    }

    /** تطور نتائج الطالب عبر الزمن (الاختبارات فقط) */
    public static function studentTrend(int $studentId): array
    {
        return DB::all(
            "SELECT r.created_at, r.percent, t.title, s.name AS subject_name FROM test_results r JOIN tests t ON t.id = r.test_id
          LEFT JOIN subjects s ON s.id = t.subject_id WHERE r.student_id = ? AND t.type <> 'practice' AND r.is_final = 1 ORDER BY r.created_at",
            [$studentId]
        );
    }

    /* ======================= تقرير المحاولة ======================= */

    /**
     * تقرير محاولة: الدرجة، التوزيع، النتيجة حسب المادة/المجال/المهارة، الأخطاء، المقارنة، التوصيات.
     * $forStudent يطبق إعدادات المراجعة وإظهار التفسير.
     */
    public static function attemptReport(int $attemptId, bool $forStudent): array
    {
        $attempt = TestEngine::attempt($attemptId);
        $result = DB::first('SELECT * FROM test_results WHERE attempt_id = ?', [$attemptId]);
        $items = TestEngine::attemptItems($attemptId);

        $bySkill = DB::all(
            'SELECT rs.*, sk.name AS skill_name, sk.code, d.name AS domain_name, sub.name AS subject_name
               FROM result_skill_scores rs LEFT JOIN skills sk ON sk.id = rs.skill_id LEFT JOIN domains d ON d.id = rs.domain_id
               JOIN subjects sub ON sub.id = rs.subject_id WHERE rs.result_id = ? ORDER BY rs.percent',
            [$result['id'] ?? 0]
        );
        $byDomain = [];
        $bySubject = [];
        foreach ($bySkill as $s) {
            foreach ([['byDomain', $s['domain_name'] ?? 'غير مصنف'], ['bySubject', $s['subject_name']]] as [$var, $key]) {
                ${$var}[$key] ??= ['score' => 0, 'max' => 0];
                ${$var}[$key]['score'] += (float) $s['score'];
                ${$var}[$key]['max'] += (float) $s['max_score'];
            }
        }

        $previous = DB::all(
            "SELECT a.id, a.attempt_no, a.submitted_at, r.percent, r.level FROM test_attempts a JOIN test_results r ON r.attempt_id = a.id
              WHERE a.test_id = ? AND a.student_id = ? AND a.id <> ? AND a.submitted_at < ? ORDER BY a.submitted_at",
            [$attempt['test_id'], $attempt['student_id'], $attemptId, $attempt['submitted_at'] ?? date('Y-m-d H:i:s')]
        );
        $subjectAvg = $attempt['subject_id'] ? DB::value(
            "SELECT AVG(r.percent) FROM test_results r JOIN tests t ON t.id = r.test_id
              WHERE r.student_id = ? AND t.subject_id = ? AND r.attempt_id <> ? AND t.type <> 'practice' AND r.is_final = 1",
            [$attempt['student_id'], $attempt['subject_id'], $attemptId]
        ) : null;

        $mastery = Settings::int('mastery_threshold');
        $weak = array_values(array_filter($bySkill, static fn ($s) => $s['skill_id'] && (float) $s['percent'] < $mastery));
        $review = !$forStudent || (int) $attempt['allow_review'] === 1;
        $explain = !$forStudent || (int) $attempt['show_explanations'] === 1;

        return [
            'attempt' => $attempt, 'result' => $result, 'items' => $items, 'bySkill' => $bySkill,
            'byDomain' => $byDomain, 'bySubject' => $bySubject, 'previous' => $previous,
            'subjectAvg' => $subjectAvg !== null ? (float) $subjectAvg : null, 'weak' => $weak,
            'recommendations' => self::recommendations($result, $weak, $attempt), 'review' => $review, 'explain' => $explain,
            'forStudent' => $forStudent,
        ];
    }

    /** توصيات قائمة على قواعد واضحة (يمكن استبدالها بمحرك ذكاء اصطناعي مستقبلاً) */
    public static function recommendations(?array $result, array $weak, array $attempt): array
    {
        if (!$result) {
            return [];
        }
        $out = [];
        foreach (array_slice($weak, 0, 3) as $s) {
            $out[] = ['icon' => 'lightning-charge', 'text' => 'تدرّب على مهارة «' . $s['skill_name'] . '» (' . pct((float) $s['percent']) . ')',
                      'action' => ['/student/practice/start', ['subject' => $s['subject_id'], 'skill' => $s['skill_id']]]];
        }
        $total = max(1, (int) $result['correct_count'] + (int) $result['wrong_count'] + (int) $result['blank_count']);
        if ((int) $result['blank_count'] / $total >= 0.2) {
            $out[] = ['icon' => 'clock-history', 'text' => 'تركت ' . (int) $result['blank_count'] . ' سؤالاً دون إجابة — نظّم وقتك وأجب عن كل الأسئلة، فلا عقوبة على المحاولة.', 'action' => null];
        }
        if (in_array($result['level'], ['advanced', 'proficient'], true) && !$weak) {
            $out[] = ['icon' => 'stars', 'text' => 'أداء متميز! جرّب أسئلة أصعب أو اختباراً محاكياً شاملاً للحفاظ على مستواك.', 'action' => null];
        }
        if ($result['level'] === 'below') {
            $out[] = ['icon' => 'person-raised-hand', 'text' => 'راجع معلمك لوضع خطة علاجية تناسبك، وابدأ بأضعف مهارة.', 'action' => null];
        }
        return $out;
    }
}
