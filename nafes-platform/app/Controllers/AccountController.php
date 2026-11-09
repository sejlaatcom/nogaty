<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Models\User;
use App\Services\Audit;
use App\Validators\Validator;

final class AccountController extends Controller
{
    public function show(Request $request): string
    {
        $user = Auth::user();
        $profile = DB::first(
            'SELECT s.student_number, t.employee_number FROM users u
          LEFT JOIN students s ON s.user_id = u.id LEFT JOIN teachers t ON t.user_id = u.id WHERE u.id = ?',
            [$user['id']]
        );
        return $this->view('auth/account', ['title' => 'حسابي', 'user' => $user, 'profile' => $profile]);
    }

    public function update(Request $request): never
    {
        $errors = Validator::make($_POST, ['email' => 'nullable|email|max:150'], ['email' => 'البريد الإلكتروني']);
        if ($errors) {
            back_with_errors($errors, '/account');
        }
        DB::update('users', ['email' => $request->str('email') ?: null], 'id = :id', ['id' => Auth::id()]);
        flash('success', 'تم حفظ بيانات الحساب.');
        redirect('/account');
    }

    public function passwordForm(Request $request): string
    {
        return $this->view('auth/password', ['title' => 'تغيير كلمة المرور', 'forced' => (int) Auth::user()['must_change_password'] === 1]);
    }

    public function updatePassword(Request $request): never
    {
        $user    = Auth::user();
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['password'] ?? '');

        $errors = [];
        if (!password_verify($current, $user['password_hash'])) {
            $errors[] = 'كلمة المرور الحالية غير صحيحة.';
        }
        $errors = array_merge($errors, User::passwordErrors($new));
        if ($new !== ($_POST['password_confirmation'] ?? '')) {
            $errors[] = 'كلمتا المرور غير متطابقتين.';
        }
        if ($new === $current) {
            $errors[] = 'اختر كلمة مرور مختلفة عن الحالية.';
        }
        if ($errors) {
            back_with_errors($errors, '/account/password');
        }
        User::setPassword((int) $user['id'], $new, false);
        \App\Core\Session::regenerate();
        Audit::log('password_change', 'users', (int) $user['id']);
        flash('success', 'تم تغيير كلمة المرور بنجاح.');
        redirect(Auth::home());
    }
}
