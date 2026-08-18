<?php

declare(strict_types=1);

namespace WPP\Foundation\Helpers;

use InvalidArgumentException;

/**
 * Renders PHP templates from each module's resources/views directory.
 *
 * Dot-notation: View::load('Admin.metabox') resolves to
 * src/Admin/resources/views/metabox.php. $args are extracted into template scope.
 */
final class View
{
    public static function load(string $path, array $args = []): string
    {
        return self::renderFile(self::resolve($path), $args);
    }

    public static function render(string $path, array $args = []): void
    {
        echo self::load($path, $args);
    }

    /** Load a view relative to a caller-supplied base directory. */
    public static function loadRelative(string $baseDir, string $path, array $args = []): string
    {
        $rel = str_replace('.', '/', $path);
        $template = rtrim($baseDir, '/\\') . '/' . $rel . '.php';
        return self::renderFile($template, $args);
    }

    private static function renderFile(string $template, array $args): string
    {
        if (! file_exists($template)) {
            throw new InvalidArgumentException("WP Performance view template not found: {$template}");
        }

        ob_start();

        if (! empty($args)) {
            extract($args, EXTR_SKIP);
        }

        include $template;

        return (string) ob_get_clean();
    }

    private static function resolve(string $path): string
    {
        if (str_contains($path, '.')) {
            [$domain, $rest] = explode('.', $path, 2);
            $rest = str_replace('.', '/', $rest);
            return WPP_DIR . "src/{$domain}/resources/views/{$rest}.php";
        }

        return WPP_DIR . "src/resources/views/{$path}.php";
    }
}
