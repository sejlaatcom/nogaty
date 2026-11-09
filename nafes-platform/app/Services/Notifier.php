<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use Throwable;

/** الإشعارات داخل المنصة (يمكن إضافة البريد أو واتساب لاحقاً كقنوات إضافية) */
final class Notifier
{
    public const TYPES = [
        'test_assigned'   => ['اختبار جديد', 'journal-plus', 'primary'],
        'test_deadline'   => ['اقتراب موعد الإغلاق', 'alarm', 'warning'],
        'test_incomplete' => ['اختبار غير مكتمل', 'hourglass-split', 'warning'],
        'result_ready'    => ['نتيجة جديدة', 'bar-chart', 'success'],
        'plan_assigned'   => ['خطة علاجية/إثرائية', 'heart-pulse', 'accent'],
        'student_support' => ['طالب يحتاج متابعة', 'person-exclamation', 'danger'],
        'report_ready'    => ['تقرير جديد', 'file-earmark-bar-graph', 'primary'],
        'grading_needed'  => ['تصحيح يدوي مطلوب', 'pencil-square', 'warning'],
        'badge_awarded'   => ['شارة جديدة', 'trophy', 'success'],
        'system'          => ['تنبيه', 'info-circle', 'secondary'],
    ];

    /** إرسال إشعار لعدة مستخدمين؛ dedupe يمنع التكرار لنفس المستخدم */
    public static function send(array $userIds, string $type, string $title, ?string $body = null, ?string $link = null, ?string $dedupe = null): int
    {
        $sent = 0;
        foreach (array_unique(array_filter(array_map('intval', $userIds))) as $userId) {
            try {
                $sent += DB::run(
                    'INSERT IGNORE INTO notifications (user_id, type, title, body, link, dedupe_key) VALUES (?, ?, ?, ?, ?, ?)',
                    [$userId, $type, mb_substr($title, 0, 200), $body !== null ? mb_substr($body, 0, 500) : null, $link, $dedupe]
                )->rowCount();
            } catch (Throwable) {
            }
        }
        return $sent;
    }

    /** مستخدمو الطلاب المسجلين حالياً في فصول */
    public static function studentUsersInClasses(array $classIds): array
    {
        if (!$classIds) {
            return [];
        }
        [$in, $params] = DB::in($classIds);
        return array_map('intval', DB::column(
            "SELECT s.user_id FROM student_class_enrollments e JOIN students s ON s.id = e.student_id
              WHERE e.status = 'active' AND e.class_id IN ($in)",
            $params
        ));
    }

    /** معلمو فصل (ومادة) لإشعارهم بالمتابعة */
    public static function teacherUsersForClass(int $classId, ?int $subjectId = null): array
    {
        return array_map('intval', DB::column(
            'SELECT DISTINCT t.user_id FROM teacher_assignments ta JOIN teachers t ON t.id = ta.teacher_id
              WHERE ta.class_id = ? AND (ta.subject_id IS NULL OR ? IS NULL OR ta.subject_id = ?)',
            [$classId, $subjectId, $subjectId]
        ));
    }

    public static function usersWithRole(string ...$slugs): array
    {
        [$in, $params] = DB::in($slugs);
        return array_map('intval', DB::column(
            "SELECT DISTINCT ur.user_id FROM user_roles ur JOIN roles r ON r.id = ur.role_id JOIN users u ON u.id = ur.user_id
              WHERE u.is_active = 1 AND r.slug IN ($in)",
            $params
        ));
    }

    /** تنبيهات اقتراب الإغلاق (خلال 24 ساعة) والمحاولات غير المكتملة — تُستدعى دورياً */
    public static function deadlineReminders(): void
    {
        $tests = DB::all(
            "SELECT id, title, available_until FROM tests
              WHERE status = 'published' AND type <> 'practice' AND available_until BETWEEN NOW() AND NOW() + INTERVAL 1 DAY"
        );
        foreach ($tests as $test) {
            $users = DB::column(
                "SELECT DISTINCT s.user_id FROM test_assignments ta
                   JOIN student_class_enrollments e ON (e.class_id = ta.class_id AND e.status = 'active') OR e.student_id = ta.student_id
                   JOIN students s ON s.id = e.student_id
                  WHERE ta.test_id = ?
                    AND NOT EXISTS (SELECT 1 FROM test_attempts a WHERE a.test_id = ta.test_id AND a.student_id = s.id AND a.status <> 'in_progress')",
                [$test['id']]
            );
            self::send($users, 'test_deadline', 'يقترب إغلاق اختبار: ' . $test['title'],
                'يُغلق الاختبار في ' . dt($test['available_until']) . '، بادر بأدائه.', '/student/tests', 'deadline-' . $test['id']);
        }
        $open = DB::all(
            "SELECT a.id, a.test_id, s.user_id, t.title FROM test_attempts a
               JOIN students s ON s.id = a.student_id JOIN tests t ON t.id = a.test_id
              WHERE a.status = 'in_progress' AND t.type <> 'practice' AND a.last_activity_at < NOW() - INTERVAL 30 MINUTE
                AND (a.deadline_at IS NULL OR a.deadline_at > NOW())"
        );
        foreach ($open as $row) {
            self::send([(int) $row['user_id']], 'test_incomplete', 'لديك اختبار لم تكمله: ' . $row['title'],
                'أكمل إجاباتك وسلّم الاختبار قبل إغلاقه.', '/student/tests', 'incomplete-' . $row['id']);
        }
    }
}
