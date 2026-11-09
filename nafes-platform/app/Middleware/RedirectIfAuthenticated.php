<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;

final class RedirectIfAuthenticated
{
    public function handle(Request $request, array $args): void
    {
        if (Auth::check()) {
            redirect(Auth::home());
        }
    }
}
