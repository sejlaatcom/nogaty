<?php
/**
 * تسجيل الدخول والخروج
 */
class AuthController
{
    public function login(): void
    {
        if (Auth::check()) {
            redirect('dashboard/index');
        }
        View::render('auth/login', [
            'title' => 'تسجيل الدخول',
        ], 'auth');
    }

    public function authenticate(): void
    {
        if (!Request::isPost()) {
            redirect('auth/login');
        }
        Csrf::check();

        $username = (string) Request::post('username', '');
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            flash('error', 'يرجى إدخال اسم المستخدم وكلمة المرور.');
            redirect('auth/login');
        }

        $result = Auth::attempt($username, $password);
        if (!$result['ok']) {
            flash('error', $result['message']);
            redirect('auth/login');
        }

        flash('success', 'مرحباً بك ' . $result['user']['full_name']);

        $intended = $_SESSION['intended'] ?? null;
        unset($_SESSION['intended']);

        if ($result['user']['must_change_password']) {
            flash('warning', 'يجب عليك تغيير كلمة المرور قبل المتابعة.');
            redirect('profile/password');
        }
        if ($intended && str_contains($intended, 'index.php')) {
            header('Location: ' . $intended);
            exit;
        }
        redirect('dashboard/index');
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('auth/login');
    }
}
