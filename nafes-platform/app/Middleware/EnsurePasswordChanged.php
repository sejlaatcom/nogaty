<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;

/** يُلزم المستخدم بتغيير كلمة المرور الأولية قبل استخدام المنصة */
final class EnsurePasswordChanged
{
    public function handle(Request $request, array $args): void
    {
        $user = Auth::user();
        if ($user && (int) $user['must_change_password'] === 1 && !$request->wantsJson()) {
            redirect('/account/password');
        }
    }
}
