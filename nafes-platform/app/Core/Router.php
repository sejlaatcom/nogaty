<?php
declare(strict_types=1);

namespace App\Core;

/** موجّه بسيط بمسارات ومعاملات {id} وطبقات وسيطة */
final class Router
{
    private array $routes = [];
    private array $groupMiddleware = [];
    private string $groupPrefix = '';

    public function get(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add(['GET'], $path, $handler, $middleware);
    }

    public function post(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add(['POST'], $path, $handler, $middleware);
    }

    public function match(array $methods, string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add($methods, $path, $handler, $middleware);
    }

    public function group(array $options, callable $routes): void
    {
        [$prevMw, $prevPrefix] = [$this->groupMiddleware, $this->groupPrefix];
        $this->groupMiddleware = array_merge($this->groupMiddleware, $options['middleware'] ?? []);
        $this->groupPrefix    .= $options['prefix'] ?? '';
        $routes($this);
        [$this->groupMiddleware, $this->groupPrefix] = [$prevMw, $prevPrefix];
    }

    private function add(array $methods, string $path, array|callable $handler, array $middleware): void
    {
        $full    = '/' . trim($this->groupPrefix . $path, '/');
        // {id} وما ينتهي بـ id أرقام فقط، وغيرها أي مقطع
        $pattern = preg_replace_callback('#\{(\w+)\}#', static fn ($m) => '(?P<' . $m[1] . '>' . (str_ends_with($m[1], 'id') ? '\d+' : '[^/]+') . ')', $full);
        $this->routes[] = [
            'methods'    => $methods,
            'pattern'    => '#^' . $pattern . '$#u',
            'handler'    => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
    }

    public function dispatch(Request $request): mixed
    {
        $path   = $request->path();
        $method = $request->method();
        $allowed = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $path, $m)) {
                continue;
            }
            $allowed = true;
            if (!in_array($method, $route['methods'], true)) {
                continue;
            }
            $request->params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);

            foreach ($route['middleware'] as $mw) {
                Middleware::run($mw, $request);
            }

            $handler = $route['handler'];
            if (is_array($handler)) {
                [$class, $action] = $handler;
                return (new $class())->$action($request);
            }
            return $handler($request);
        }

        throw new HttpException($allowed ? 405 : 404, $allowed ? 'طريقة الطلب غير مسموحة.' : '');
    }
}
