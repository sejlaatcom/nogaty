<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

/** تشغيل الطلب: الجلسة، CSRF، التوجيه، ومعالجة الأخطاء */
final class App
{
    public static function installed(): bool
    {
        return is_file(BASE_PATH . '/.env') && is_file(STORAGE_PATH . '/installed.lock');
    }

    public static function run(): void
    {
        if (!self::installed()) {
            header('Location: ' . base_path_url() . '/install.php');
            exit;
        }

        self::securityHeaders();
        Session::start();
        $request = new Request();

        try {
            if ($request->isPost()) {
                self::verifyCsrf($request);
            }
            $router = new Router();
            require BASE_PATH . '/routes/web.php';
            $result = $router->dispatch($request);
            if (is_string($result)) {
                echo $result;
            }
        } catch (HttpException $e) {
            self::renderError($request, $e->status, $e->getMessage());
        } catch (Throwable $e) {
            error_log((string) $e);
            if (config('app.debug')) {
                http_response_code(500);
                echo '<pre style="direction:ltr;text-align:left">' . e((string) $e) . '</pre>';
                return;
            }
            self::renderError($request, 500, 'حدث خطأ غير متوقع. تم تسجيله وسيُعالج، يرجى المحاولة لاحقاً.');
        }
    }

    private static function verifyCsrf(Request $request): void
    {
        $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($token) || !hash_equals(Session::csrfToken(), $token)) {
            throw new HttpException(419);
        }
    }

    private static function securityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: same-origin');
        header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    }

    public static function renderError(Request $request, int $status, string $message): void
    {
        http_response_code($status);
        if ($request->wantsJson()) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => false, 'error' => $message, 'status' => $status], JSON_UNESCAPED_UNICODE);
            return;
        }
        echo View::render('errors/error', ['status' => $status, 'message' => $message, 'title' => 'خطأ ' . $status],
            Auth::check() ? 'layouts/app' : 'layouts/guest');
    }
}
