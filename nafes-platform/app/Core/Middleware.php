<?php
declare(strict_types=1);

namespace App\Core;

use App\Middleware\Authenticate;
use App\Middleware\EnsurePasswordChanged;
use App\Middleware\RedirectIfAuthenticated;
use App\Middleware\RequirePermission;
use App\Middleware\RequireRole;

/** تشغيل الطبقات الوسيطة بأسمائها: auth, guest, password, can:perm|perm, role:slug */
final class Middleware
{
    public static function run(string $name, Request $request): void
    {
        [$key, $arg] = array_pad(explode(':', $name, 2), 2, '');
        $instance = match ($key) {
            'auth'     => new Authenticate(),
            'guest'    => new RedirectIfAuthenticated(),
            'password' => new EnsurePasswordChanged(),
            'can'      => new RequirePermission(),
            'role'     => new RequireRole(),
            default    => throw new \LogicException('Unknown middleware: ' . $name),
        };
        $instance->handle($request, $arg === '' ? [] : explode('|', $arg));
    }
}
