<?php
/**
 * موجّه الطلبات : index.php?r=controller/action
 */
class Router
{
    /** المسارات المسموح بها دون تسجيل دخول */
    private const PUBLIC_ROUTES = [
        'auth/login', 'auth/authenticate', 'share/view', 'share/download', 'share/unlock',
    ];

    public static function dispatch(string $route): void
    {
        $route = trim($route, '/');
        if ($route === '') {
            $route = 'dashboard/index';
        }
        $parts      = explode('/', $route);
        $controller = preg_replace('/[^a-z0-9_]/i', '', $parts[0] ?? 'dashboard');
        $action     = preg_replace('/[^a-z0-9_]/i', '', $parts[1] ?? 'index');
        $normalized = strtolower($controller . '/' . $action);

        if (!in_array($normalized, self::PUBLIC_ROUTES, true)) {
            Auth::requireLogin();
        }

        $class = self::studly($controller) . 'Controller';
        $file  = APP_PATH . '/Controllers/' . $class . '.php';

        if (!is_file($file)) {
            self::notFound($route);
            return;
        }
        require_once $file;

        if (!class_exists($class)) {
            self::notFound($route);
            return;
        }
        $instance = new $class();
        $method   = self::camel($action);

        if (!method_exists($instance, $method)) {
            self::notFound($route);
            return;
        }
        $instance->$method();
    }

    private static function notFound(string $route): void
    {
        http_response_code(404);
        View::render('errors/404', ['route' => $route], Auth::check() ? 'app' : 'auth');
    }

    private static function studly(string $s): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $s)));
    }

    private static function camel(string $s): string
    {
        return lcfirst(self::studly($s));
    }
}
