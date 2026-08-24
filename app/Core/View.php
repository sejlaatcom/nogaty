<?php
/**
 * محرك عرض بسيط يعتمد على ملفات PHP
 */
class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'app'): void
    {
        echo self::capture($view, $data, $layout);
    }

    public static function capture(string $view, array $data = [], ?string $layout = 'app'): string
    {
        $file = APP_PATH . '/Views/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('ملف العرض غير موجود: ' . $view);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        $content = ob_get_clean();

        if ($layout) {
            $layoutFile = APP_PATH . '/Views/layouts/' . $layout . '.php';
            if (!is_file($layoutFile)) {
                throw new RuntimeException('ملف القالب غير موجود: ' . $layout);
            }
            ob_start();
            include $layoutFile;
            $content = ob_get_clean();
        }
        return $content;
    }

    /** تضمين جزء صغير */
    public static function partial(string $name, array $data = []): void
    {
        $file = APP_PATH . '/Views/partials/' . $name . '.php';
        if (is_file($file)) {
            extract($data, EXTR_SKIP);
            include $file;
        }
    }
}
