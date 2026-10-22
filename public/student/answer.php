<?php
/** حفظ إجابة سؤال أثناء الاختبار (طلب AJAX) */
declare(strict_types=1);
define('APP_AREA', 'student');
require __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

$user = Auth::user();
if (!$user || $user['role'] !== 'student' || !is_post()) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'error' => 'انتهت الجلسة، يرجى تسجيل الدخول مجدداً.'], JSON_UNESCAPED_UNICODE));
}
$token = $_POST['_token'] ?? '';
if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
    http_response_code(419);
    exit(json_encode(['ok' => false, 'error' => 'انتهت صلاحية الصفحة، حدّثها.'], JSON_UNESCAPED_UNICODE));
}

$attempt = Exams::attempt(input_int('attempt'));
if (!$attempt || (int) $attempt['student_id'] !== (int) $user['id']) {
    http_response_code(404);
    exit(json_encode(['ok' => false, 'error' => 'المحاولة غير موجودة.'], JSON_UNESCAPED_UNICODE));
}

$option = isset($_POST['option']) ? input_int('option') : null;
$flag   = isset($_POST['flag']) ? input('flag') === '1' : null;
$error  = Exams::saveAnswer($attempt, input_int('question'), $option, $flag);

echo json_encode([
    'ok'       => $error === '',
    'error'    => $error,
    'closed'   => $error !== '' && Exams::attempt((int) $attempt['id'])['status'] === 'submitted',
    'seconds'  => Exams::secondsLeft($attempt),
], JSON_UNESCAPED_UNICODE);
