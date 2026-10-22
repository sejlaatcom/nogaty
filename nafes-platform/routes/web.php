<?php
/** @var App\Core\Router $router */

use App\Controllers\AccountController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\MediaController;
use App\Controllers\NotificationController;
use App\Controllers\UserController;

/* ---------------- عام ---------------- */
$router->get('/', static fn () => redirect(App\Core\Auth::check() ? App\Core\Auth::home() : '/login'));
$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);
$router->get('/media/logo', [MediaController::class, 'logo']);

/* ---------------- الحساب (بدون اشتراط تغيير كلمة المرور) ---------------- */
$router->group(['middleware' => ['auth']], static function ($r) {
    $r->get('/account/password', [AccountController::class, 'passwordForm']);
    $r->post('/account/password', [AccountController::class, 'updatePassword']);
    $r->get('/notifications/feed', [NotificationController::class, 'feed']);
});

/* ---------------- المستخدمون المسجلون ---------------- */
$router->group(['middleware' => ['auth', 'password']], static function ($r) {
    $r->get('/account', [AccountController::class, 'show']);
    $r->post('/account', [AccountController::class, 'update']);
    $r->get('/media/{id}', [MediaController::class, 'show']);

    $r->get('/notifications', [NotificationController::class, 'index']);
    $r->get('/notifications/{id}/open', [NotificationController::class, 'open']);
    $r->post('/notifications/read-all', [NotificationController::class, 'readAll']);

    $r->get('/dashboard', [DashboardController::class, 'index'], ['can:dashboard.view']);

    // المستخدمون والأدوار
    $r->group(['middleware' => ['can:users.manage']], static function ($r) {
        $r->get('/users', [UserController::class, 'index']);
        $r->get('/users/create', [UserController::class, 'create']);
        $r->post('/users', [UserController::class, 'store']);
        $r->get('/users/roles', [UserController::class, 'roles']);
        $r->post('/users/roles', [UserController::class, 'saveRoles']);
        $r->get('/users/{id}/edit', [UserController::class, 'edit']);
        $r->post('/users/{id}', [UserController::class, 'update']);
        $r->post('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
        $r->post('/users/{id}/toggle', [UserController::class, 'toggle']);
    });
});

/* ======================= المرحلة 2: الهيكل والطلاب والمعلمون والاستيراد ======================= */
use App\Controllers\ImportController;
use App\Controllers\StructureController;
use App\Controllers\StudentController;
use App\Controllers\TeacherController;

$router->group(['middleware' => ['auth', 'password']], static function ($r) {
    $r->group(['middleware' => ['can:structure.manage']], static function ($r) {
        $r->get('/structure', [StructureController::class, 'index']);
        $r->post('/structure/years', [StructureController::class, 'saveYear']);
        $r->post('/structure/years/{id}/current', [StructureController::class, 'setCurrentYear']);
        $r->post('/structure/years/{id}/archive', [StructureController::class, 'archiveYear']);
        $r->post('/structure/grades', [StructureController::class, 'saveGrade']);
        $r->post('/structure/classes', [StructureController::class, 'saveClass']);
        $r->post('/structure/classes/{id}/toggle', [StructureController::class, 'toggleClass']);
        $r->post('/structure/classes/{id}/delete', [StructureController::class, 'deleteClass']);
        $r->post('/structure/subjects', [StructureController::class, 'saveSubject']);
    });

    $r->get('/students', [StudentController::class, 'index'], ['can:students.view']);
    $r->get('/students/export', [StudentController::class, 'export'], ['can:students.view']);
    $r->get('/students/cards', [StudentController::class, 'cards'], ['can:students.manage']);
    $r->get('/students/create', [StudentController::class, 'create'], ['can:students.manage']);
    $r->post('/students', [StudentController::class, 'store'], ['can:students.manage']);
    $r->post('/students/bulk', [StudentController::class, 'bulk'], ['can:students.manage']);
    $r->get('/students/{id}', [StudentController::class, 'show'], ['can:students.view']);
    $r->get('/students/{id}/edit', [StudentController::class, 'edit'], ['can:students.manage']);
    $r->post('/students/{id}', [StudentController::class, 'update'], ['can:students.manage']);
    $r->post('/students/{id}/transfer', [StudentController::class, 'transfer'], ['can:students.manage']);
    $r->post('/students/{id}/delete', [StudentController::class, 'destroy'], ['can:students.manage']);

    $r->get('/teachers', [TeacherController::class, 'index'], ['can:teachers.view']);
    $r->get('/teachers/export', [TeacherController::class, 'export'], ['can:teachers.view']);
    $r->get('/teachers/create', [TeacherController::class, 'create'], ['can:teachers.manage']);
    $r->post('/teachers', [TeacherController::class, 'store'], ['can:teachers.manage']);
    $r->post('/teachers/copy-assignments', [TeacherController::class, 'copyAssignments'], ['can:teachers.manage']);
    $r->get('/teachers/{id}', [TeacherController::class, 'show'], ['can:teachers.view']);
    $r->get('/teachers/{id}/edit', [TeacherController::class, 'edit'], ['can:teachers.manage']);
    $r->post('/teachers/{id}', [TeacherController::class, 'update'], ['can:teachers.manage']);
    $r->post('/teachers/{id}/assignments', [TeacherController::class, 'assign'], ['can:teachers.manage']);
    $r->post('/teachers/{id}/assignments/{aid}/delete', [TeacherController::class, 'unassign'], ['can:teachers.manage']);
    $r->post('/teachers/{id}/toggle', [TeacherController::class, 'toggle'], ['can:teachers.manage']);
    $r->post('/teachers/{id}/reset-password', [TeacherController::class, 'resetPassword'], ['can:teachers.manage']);

    // معالج الاستيراد (الصلاحية يتحقق منها المستورد نفسه)
    $r->get('/import/{kind}', [ImportController::class, 'upload']);
    $r->post('/import/{kind}', [ImportController::class, 'store']);
    $r->get('/import/{kind}/template', [ImportController::class, 'template']);
    $r->get('/import/{kind}/map', [ImportController::class, 'map']);
    $r->post('/import/{kind}/map', [ImportController::class, 'saveMap']);
    $r->get('/import/{kind}/preview', [ImportController::class, 'preview']);
    $r->post('/import/{kind}/run', [ImportController::class, 'run']);
    $r->get('/import/{kind}/report/{id}', [ImportController::class, 'report']);
});

/* ======================= المرحلة 3: بنك الأسئلة والتصنيفات ======================= */
use App\Controllers\PassageController;
use App\Controllers\QuestionController;
use App\Controllers\TaxonomyController;

$router->group(['middleware' => ['auth', 'password']], static function ($r) {
    $r->get('/taxonomy', [TaxonomyController::class, 'index'], ['can:questions.view']);
    $r->get('/taxonomy/tree', [TaxonomyController::class, 'tree'], ['can:questions.view|tests.view']);
    $r->post('/taxonomy', [TaxonomyController::class, 'save'], ['can:taxonomy.manage']);

    $r->get('/passages', [PassageController::class, 'index'], ['can:questions.view']);
    $r->get('/passages/create', [PassageController::class, 'form'], ['can:questions.create']);
    $r->post('/passages', [PassageController::class, 'save'], ['can:questions.create']);
    $r->get('/passages/{id}/edit', [PassageController::class, 'form'], ['can:questions.view']);
    $r->post('/passages/{id}', [PassageController::class, 'save'], ['can:questions.create']);
    $r->post('/passages/{id}/delete', [PassageController::class, 'destroy'], ['can:questions.edit_any']);

    $r->group(['middleware' => ['can:questions.view']], static function ($r) {
        $r->get('/questions', [QuestionController::class, 'index']);
        $r->get('/questions/export', [QuestionController::class, 'export']);
        $r->get('/questions/create', [QuestionController::class, 'create'], ['can:questions.create']);
        $r->post('/questions', [QuestionController::class, 'store'], ['can:questions.create']);
        $r->post('/questions/preview', [QuestionController::class, 'previewRender'], ['can:questions.create']);
        $r->post('/questions/status', [QuestionController::class, 'status']);
        $r->get('/questions/{id}', [QuestionController::class, 'show']);
        $r->get('/questions/{id}/edit', [QuestionController::class, 'edit'], ['can:questions.create']);
        $r->post('/questions/{id}', [QuestionController::class, 'update'], ['can:questions.create']);
        $r->post('/questions/{id}/duplicate', [QuestionController::class, 'duplicate'], ['can:questions.create']);
        $r->post('/questions/{id}/status', [QuestionController::class, 'status']);
        $r->post('/questions/{id}/delete', [QuestionController::class, 'destroy'], ['can:questions.create']);
        $r->post('/questions/{id}/revisions/{rid}/restore', [QuestionController::class, 'restore'], ['can:questions.create']);
    });
});

/* ======================= المرحلة 4: الاختبارات وبوابة الطالب ======================= */
use App\Controllers\StudentPortalController;
use App\Controllers\TestController;

$router->group(['middleware' => ['auth', 'password', 'can:student.portal']], static function ($r) {
    $r->get('/student', [StudentPortalController::class, 'home']);
    $r->get('/student/tests', [StudentPortalController::class, 'tests']);
    $r->get('/student/tests/{id}', [StudentPortalController::class, 'intro']);
    $r->post('/student/tests/{id}/start', [StudentPortalController::class, 'start']);
    $r->get('/student/attempts/{id}', [StudentPortalController::class, 'exam']);
    $r->post('/student/attempts/{id}/save', [StudentPortalController::class, 'save']);
    $r->post('/student/attempts/{id}/submit', [StudentPortalController::class, 'submit']);
    $r->get('/student/attempts/{id}/done', [StudentPortalController::class, 'done']);
    $r->get('/student/results', [StudentPortalController::class, 'results']);
    $r->get('/student/results/{id}', [StudentPortalController::class, 'result']);
    $r->get('/student/practice', [StudentPortalController::class, 'practice']);
    $r->post('/student/practice/start', [StudentPortalController::class, 'startPractice']);
    $r->get('/student/progress', [StudentPortalController::class, 'progress']);
});

$router->group(['middleware' => ['auth', 'password', 'can:tests.view']], static function ($r) {
    $r->get('/tests', [TestController::class, 'index']);
    $r->get('/tests/create', [TestController::class, 'create'], ['can:tests.create']);
    $r->post('/tests', [TestController::class, 'store'], ['can:tests.create']);
    $r->get('/tests/{id}', [TestController::class, 'show']);
    $r->get('/tests/{id}/edit', [TestController::class, 'edit'], ['can:tests.create']);
    $r->post('/tests/{id}', [TestController::class, 'update'], ['can:tests.create']);
    $r->post('/tests/{id}/questions', [TestController::class, 'questions'], ['can:tests.create']);
    $r->post('/tests/{id}/assign', [TestController::class, 'assign'], ['can:tests.create']);
    $r->post('/tests/{id}/status', [TestController::class, 'status'], ['can:tests.create']);
    $r->post('/tests/{id}/release', [TestController::class, 'release'], ['can:tests.create']);
    $r->post('/tests/{id}/duplicate', [TestController::class, 'duplicate'], ['can:tests.create']);
    $r->post('/tests/{id}/delete', [TestController::class, 'destroy'], ['can:tests.create']);
    $r->get('/tests/{id}/results', [TestController::class, 'results'], ['can:results.view']);
    $r->post('/tests/{id}/reset-attempt', [TestController::class, 'resetAttempt'], ['can:results.grade']);
    $r->get('/tests/{id}/paper', [TestController::class, 'paper']);
    $r->get('/attempts/{id}', [TestController::class, 'attempt'], ['can:results.view']);
    $r->get('/grading', [TestController::class, 'gradingQueue'], ['can:results.grade']);
    $r->post('/grading/{id}', [TestController::class, 'grade'], ['can:results.grade']);
});
