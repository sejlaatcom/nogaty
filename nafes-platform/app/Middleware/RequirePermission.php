<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;

/** can:perm1|perm2 — يكفي امتلاك إحداها */
final class RequirePermission
{
    public function handle(Request $request, array $args): void
    {
        if (!Auth::can(...$args)) {
            throw new HttpException(403);
        }
    }
}
