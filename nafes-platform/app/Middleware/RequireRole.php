<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;

final class RequireRole
{
    public function handle(Request $request, array $args): void
    {
        if (!Auth::hasRole(...$args)) {
            throw new HttpException(403);
        }
    }
}
