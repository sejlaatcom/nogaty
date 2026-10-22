<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;

final class Authenticate
{
    public function handle(Request $request, array $args): void
    {
        if (Auth::check()) {
            return;
        }
        if ($request->wantsJson()) {
            throw new HttpException(401, 'انتهت الجلسة، يرجى تسجيل الدخول مجدداً.');
        }
        if ($request->method() === 'GET') {
            Session::put('_intended', $request->path() . (empty($_GET) ? '' : '?' . http_build_query($_GET)));
        }
        redirect('/login');
    }
}
