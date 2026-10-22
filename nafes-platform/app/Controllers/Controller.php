<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\View;

abstract class Controller
{
    protected function view(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        return View::render($template, $data, $layout);
    }

    protected function authorize(string ...$permissions): void
    {
        if (!Auth::can(...$permissions)) {
            throw new HttpException(403);
        }
    }

    protected function notFound(?array $row): array
    {
        if (!$row) {
            throw new HttpException(404);
        }
        return $row;
    }
}
