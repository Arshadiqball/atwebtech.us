<?php

declare(strict_types=1);

final class View
{
    private static ?array $formFlash = null;
    private static bool $flashLoaded = false;

    public static function render(string $page, array $vars = [], array $meta = [], int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, private');

        $meta += [
            'title' => 'atwebtech',
            'description' => '',
            'path' => current_path(),
            'og_image' => asset('img/og-default.png'),
            'type' => 'website',
            'schema' => [],
            'noindex' => !config('app.is_production'),
        ];

        $layout = ($meta['layout'] ?? 'site') === 'app' ? 'layout-app' : 'layout';
        $content = self::capture(BASE_PATH . "/views/pages/{$page}.php", $vars + ['meta' => $meta]);
        echo self::capture(BASE_PATH . "/views/{$layout}.php", ['content' => $content, 'meta' => $meta]);
        exit;
    }

    public static function partial(string $name, array $vars = []): void
    {
        $file = BASE_PATH . '/views/' . str_replace('..', '', $name) . '.php';
        (static function (string $__file, array $__vars): void {
            extract($__vars, EXTR_SKIP);
            require $__file;
        })($file, $vars);
    }

    /** Flash data for the form that was just submitted without JavaScript. */
    public static function formState(string $formId): ?array
    {
        if (!self::$flashLoaded) {
            self::$formFlash = flash('form');
            self::$flashLoaded = true;
        }
        return (self::$formFlash['form'] ?? null) === $formId ? self::$formFlash : null;
    }

    private static function capture(string $__file, array $__vars): string
    {
        ob_start();
        (static function (string $__file, array $__vars): void {
            extract($__vars, EXTR_SKIP);
            require $__file;
        })($__file, $__vars);
        return (string) ob_get_clean();
    }
}
