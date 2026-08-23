<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

if (Auth::check()) {
    Activity::log('logout', 'users', Auth::id());
}
Auth::logout();
redirect('login.php');
