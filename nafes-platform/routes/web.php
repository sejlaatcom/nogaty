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
