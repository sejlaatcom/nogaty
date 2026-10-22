<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;
use App\Services\Audit;

final class AuthController extends Controller
{
    public function showLogin(Request $request): string
    {
        return $this->view('auth/login', ['title' => 'تسجيل الدخول', 'bare' => true], 'layouts/guest');
    }

    public function login(Request $request): never
    {
        $username = $request->str('username');
        $password = (string) ($_POST['password'] ?? '');
        if ($username === '' || $password === '') {
            back_with_errors(['أدخل اسم المستخدم وكلمة المرور.'], '/login');
        }
        [$ok, $message] = Auth::attempt($username, $password, $request->ip());
        if (!$ok) {
            Session::keepInput(['username' => $username]);
            flash('danger', $message);
            redirect('/login');
        }
        $intended = Session::get('_intended');
        Session::forget('_intended');
        redirect(is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//') ? $intended : Auth::home());
    }

    public function logout(Request $request): never
    {
        Audit::log('logout', 'users', Auth::id());
        Auth::logout();
        redirect('/login');
    }
}
